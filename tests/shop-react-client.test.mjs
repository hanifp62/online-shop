import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkoutPayload, createCheckoutClient, createShopRequest, validCart, validOrder } from '../resources/js/lib/shop-client.js';

const cart = { items: [{ product_id: 7, product_title: 'Sutra', quantity: 1, unit_price: 100, subtotal: 100, available: true }], subtotal: 100 };
const order = { order: { id: 8, total: 100, status: 'pending', items: [] }, tracking_token: null, tracking_recovery: 'unavailable' };
const fields = () => new Map([['address_id', '3'], ['voucher_code', ' CODE '], ['customer_name', 'Élodie'], ['customer_email', 'private@example.test'], ['status', 'paid'], ['total', '1'], ['user_id', '1']]);
for (const loggedIn of [false, true]) {
    for (const failure of [new Error('network'), Object.assign(new Error('timeout'), { timeout: true }), Object.assign(new Error('server'), { status: 503 }), {}]) {
        test(`${loggedIn ? 'account' : 'guest'} uncertain ${failure.message ?? 'malformed success'} retains exact payload and key`, async () => {
            let reads = 0;
            const calls = [];
            const client = createCheckoutClient({ loggedIn, urls: { checkout: '/checkout' }, text: key => key, uuid: () => 'same-key', loadCart: async () => { reads++; return cart; }, api: async (url, method, body) => { calls.push(body); if (calls.length === 1) { if (failure instanceof Error) throw failure; return failure; } return order; } });
            await assert.rejects(client.submit(fields()));
            const frozen = client.pending;
            assert.ok(Object.isFrozen(frozen));
            await client.submit(new Map());
            assert.equal(reads, 1);
            assert.equal(calls[0], frozen);
            assert.equal(calls[1], frozen);
            assert.equal(client.pending, null);
        });
    }
    for (const status of [409, 422]) test(`${loggedIn}: definitive ${status} clears pending`, async () => {
        const client = createCheckoutClient({ loggedIn, urls: {}, text: key => key, uuid: () => 'key', loadCart: async () => cart, api: async () => { throw Object.assign(new Error('rejected'), { status }); } });
        await assert.rejects(client.submit(fields()));
        assert.equal(client.pending, null);
    });
    test(`${loggedIn}: fresh empty and unavailable cart abort before POST`, async () => {
        for (const next of [{ items: [], subtotal: 0 }, { ...cart, items: [{ ...cart.items[0], available: false }] }]) {
            const client = createCheckoutClient({ loggedIn, urls: {}, text: key => key, loadCart: async () => next, api: async () => assert.fail('no POST') });
            await assert.rejects(client.submit(fields()), /check_cart/);
            assert.equal(client.pending, null);
        }
    });
}
for (const tokenShape of [{}, { tracking_token: undefined }, { tracking_token: '' }, { tracking_token: 'a'.repeat(63) }, { tracking_token: 'A'.repeat(64) }, { tracking_token: 'a'.repeat(64) + '\n' }, { tracking_token: 123 }, { tracking_token: null }, { tracking_token: null, tracking_recovery: 'available' }]) {
    test(`guest malformed tracking shape ${JSON.stringify(tokenShape)} retains original payload/key for replay`, async () => {
        const calls = [];
        let reads = 0;
        const client = createCheckoutClient({ loggedIn: false, urls: { checkout: '/checkout' }, text: key => key, uuid: () => 'frozen-key', loadCart: async () => { reads++; return cart; }, api: async (url, method, body) => { calls.push(body); return calls.length === 1 ? { order: order.order, ...tokenShape } : order; } });
        await assert.rejects(client.submit(fields()), /checkout_pending/);
        const frozen = client.pending;
        assert.equal(frozen.idempotency_key, 'frozen-key');
        await client.submit(new Map());
        assert.equal(calls[1], frozen);
        assert.equal(reads, 1);
        assert.equal(client.pending, null);
    });
}
for (const tracking of [{ tracking_token: 'a'.repeat(64) }, { tracking_token: null, tracking_recovery: 'unavailable' }]) {
    test(`guest accepts explicit server tracking contract ${JSON.stringify(tracking)}`, async () => {
        const client = createCheckoutClient({ loggedIn: false, urls: {}, text: key => key, uuid: () => 'key', loadCart: async () => cart, api: async () => ({ order: order.order, ...tracking }) });
        await client.submit(fields());
        assert.equal(client.pending, null);
    });
}
test('order validation rejects malformed line elements without throwing', () => {
    for (const item of [null, undefined, 'item', {}, { product_title: 'Sutra', quantity: 1 }, { product_title: 'Sutra', quantity: 0, subtotal: 0 }]) {
        assert.equal(validOrder({ order: { ...order.order, items: [item] } }), false);
    }
});
test('payloads allowlist guest contact/shipping and account address/voucher only', () => {
    const guest = checkoutPayload(fields(), false, 'key');
    assert.deepEqual(Object.keys(guest), ['customer_name', 'customer_email', 'customer_phone', 'label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code', 'idempotency_key']);
    assert.deepEqual(checkoutPayload(fields(), true, 'key'), { address_id: 3, voucher_code: 'CODE', idempotency_key: 'key' });
    assert.ok(validCart({ items: [], subtotal: 0 }), 'DELETE minimum snapshot remains valid');
});
test('transport covers stalled body with deadline and does not invent HTTP rejection status', async () => {
    const api = createShopRequest({ timeout: 'timeout' }, { csrf: 'test', timeoutMs: 5, fetch: async () => ({ ok: true, status: 200, json: () => new Promise(() => {}) }) });
    await assert.rejects(api('/checkout'), error => error.timeout === true && error.status === undefined);
});
test('unreadable successful body is uncertain; unreadable failure carries HTTP status', async () => {
    for (const status of [200, 201, 409, 422, 503]) {
        const api = createShopRequest({ generic: 'failed' }, { csrf: 'test', fetch: async () => ({ ok: status < 300, status, json: async () => { throw new Error('invalid'); } }) });
        await assert.rejects(api('/checkout'), error => error.status === (status < 300 ? undefined : status));
    }
});
test('request sends POST token in JSON with same-origin CSRF, never URL', async () => {
    let sent;
    const api = createShopRequest({}, { csrf: 'test', fetch: async (...args) => { sent = args; return { ok: true, json: async () => order }; } });
    await api('/orders/track', 'POST', { token: 'a'.repeat(64) });
    assert.equal(sent[0], '/orders/track');
    assert.equal(sent[1].credentials, 'same-origin');
    assert.equal(sent[1].headers['X-CSRF-TOKEN'], 'test');
    assert.deepEqual(JSON.parse(sent[1].body), { token: 'a'.repeat(64) });
});
