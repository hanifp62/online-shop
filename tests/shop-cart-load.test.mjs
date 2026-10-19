import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Runs the inline script of resources/views/shop/index.blade.php against a fake DOM.
// Guards: a failed cart load must not leave stale rows, and checkout must re-read the cart first.
const blade = readFileSync(new URL('../resources/views/shop/index.blade.php', import.meta.url), 'utf8');
const inline = blade.match(/<script>\n([\s\S]*?)<\/script>/)[1];

function source(loggedIn) {
    return inline
        .replace(/@json\(auth\(\)->check\(\)\)/g, String(loggedIn))
        .replace(/@json\(route\('([^']+)'\)\)/g, (_, name) => JSON.stringify(`/r/${name}`))
        .replace(/@json\(url\('([^']+)'\)\)/g, (_, path) => JSON.stringify(path));
}

function el(tag = 'div') {
    const listeners = {};
    return {
        tag, children: [], textContent: '', disabled: false, value: '', style: {}, attrs: {}, options: [],
        append(...nodes) { this.children.push(...nodes); },
        replaceChildren(...nodes) { this.children = [...nodes]; },
        setAttribute(k, v) { this.attrs[k] = v; }, removeAttribute(k) { delete this.attrs[k]; },
        addEventListener(type, fn) { (listeners[type] ??= []).push(fn); },
        querySelectorAll() { return []; },
        add(option) { this.options.push(option); },
        reset() {},
        get text() { return [this.textContent, ...this.children.map((c) => c.text)].join(' '); },
        find(pred) { if (pred(this)) return this; for (const c of this.children) { const hit = c.find?.(pred); if (hit) return hit; } return null; },
    };
}

const flush = async () => { for (let i = 0; i < 10; i++) await new Promise((r) => setImmediate(r)); };
const item = { product_id: 7, product_title: 'Tas Kulit', unit_price: 200, quantity: 1, subtotal: 200, available: true };

function shop({ loggedIn, responses }) {
    const ids = new Map();
    const $ = (id) => { if (!ids.has(id)) ids.set(id, el()); return ids.get(id); };
    $('checkout-summary').append($('checkout-estimate'), $('checkout-summary-content'));
    const main = el('main');
    const calls = [];
    const queue = [...responses];
    const api = async (url, method = 'GET', body) => {
        calls.push({ url, method, body });
        const next = queue.length ? queue.shift() : { items: [], subtotal: 0, addresses: [], data: [], current_page: 1, last_page: 1, total: 0, favorites: [] };
        if (next instanceof Error) throw next;
        return typeof next === 'function' ? next(url, method) : next;
    };
    const document = {
        getElementById: $,
        createElement: (tag) => el(tag),
        querySelector: (s) => (s === 'main' ? main : el()),
        querySelectorAll: () => [],
    };
    const checkoutAddress = $('checkout-address');
    checkoutAddress.value = '3';
    const context = {
        window: { shopRequest: api }, document, crypto: { randomUUID: () => 'uuid' }, confirm: () => true,
        Option: function Option(label, value) { return { label, value }; },
        FormData: function FormData() { return { get: () => '', [Symbol.iterator]: function* () {} }; },
        Object, Promise, Number, String,
    };
    vm.runInNewContext(source(loggedIn), context);
    return { $, calls, queue };
}

const submit = (h, id) => h.$(id).onsubmit({ preventDefault() {}, target: { address_id: { value: '3' }, voucher_code: { value: '' }, reset() {} } });
const isCart = (c) => c.url === '/r/cart.show';
const isCheckout = (c) => c.url === '/r/checkout.store';

for (const loggedIn of [true, false]) {
    const who = loggedIn ? 'account' : 'guest';
    const form = loggedIn ? 'checkout-form' : 'guest-checkout-form';
    const button = loggedIn ? 'checkout-button' : 'guest-checkout-button';
    const boot = loggedIn
        ? [{ items: [item], subtotal: 200 }, { addresses: [] }, { data: [], current_page: 1, last_page: 1, total: 0 }, { favorites: [] }]
        : [{ items: [item], subtotal: 200 }];

    test(`${who}: failed reload clears stale rows, shows retry, keeps checkout disabled, no checkout POST`, async () => {
        const h = shop({ loggedIn, responses: [...boot, new Error('Jaringan terputus.')] });
        await flush();
        assert.match(h.$('cart-list').text, /Tas Kulit/);
        submit(h, form);
        await flush();
        assert.equal(h.calls.filter(isCheckout).length, 0, 'checkout aborted when cart reload fails');
        assert.doesNotMatch(h.$('cart-list').text, /Tas Kulit/, 'stale rows cleared');
        assert.match(h.$('cart-list').text, /Tas belum dapat dimuat/);
        const retry = h.$('cart-list').find((n) => n.tag === 'button' && /Coba lagi/.test(n.textContent));
        assert.ok(retry, 'retry button rendered');
        assert.equal(h.$(button).disabled, true);

        h.queue.push({ items: [item], subtotal: 200 });
        retry.onclick();
        await flush();
        assert.match(h.$('cart-list').text, /Tas Kulit/, 'retry reloads the cart');
        assert.equal(h.$('feedback').text, 'Tas belanja berhasil dimuat.', 'successful retry replaces processing feedback');
        assert.equal(h.$(button).disabled, false);
    });

    test(`${who}: checkout re-reads the cart and aborts when it is now empty`, async () => {
        const h = shop({ loggedIn, responses: [...boot, { items: [], subtotal: 0 }] });
        await flush();
        submit(h, form);
        await flush();
        const afterBoot = h.calls.slice(boot.length);
        assert.ok(isCart(afterBoot[0]), 'fresh GET cart before checkout');
        assert.equal(h.calls.filter(isCheckout).length, 0);
        assert.equal(h.$(button).disabled, true);
    });

    test(`${who}: a lost checkout response can be replayed with the same key even when the cart reads empty`, async () => {
        const order = { order: { id: 9, total: 200, status: 'pending', items: [] }, tracking_token: 'trk-1' };
        const h = shop({
            loggedIn,
            // Server committed the order and cleared the cart, but the response was lost.
            responses: [...boot, { items: [item], subtotal: 200 }, new Error('Jaringan terputus.'), order, { items: [], subtotal: 0 }],
        });
        await flush();
        submit(h, form);
        await flush();
        const first = h.calls.filter(isCheckout);
        assert.equal(first.length, 1, 'first attempt reached the server');
        assert.equal(h.$(button).disabled, false, 'retry stays available while a replay is pending');

        submit(h, form);
        await flush();
        const attempts = h.calls.filter(isCheckout);
        assert.equal(attempts.length, 2, 'retry replays despite the now-empty cart');
        assert.equal(attempts[1].body.idempotency_key, attempts[0].body.idempotency_key, 'same idempotency key');
        assert.match(h.$('feedback').text, /Pesanan #9/);
        if (!loggedIn) assert.match(h.$('guest-order-confirmation').text, /trk-1/, 'guest tracking token still surfaced');
    });

    test(`${who}: a rejected checkout drops the pending replay and aborts on an empty cart`, async () => {
        const rejected = Object.assign(new Error('Stok habis.'), { status: 422 });
        const h = shop({
            loggedIn,
            responses: [...boot, { items: [item], subtotal: 200 }, rejected, { items: [], subtotal: 0 }],
        });
        await flush();
        submit(h, form);
        await flush();
        submit(h, form);
        await flush();
        assert.equal(h.calls.filter(isCheckout).length, 1, 'no replay after a definitive rejection');
        assert.equal(h.$(button).disabled, true);
    });

    test(`${who}: checkout proceeds after a fresh non-empty cart read`, async () => {
        const order = { order: { id: 9, total: 200, status: 'pending', items: [] } };
        const h = shop({ loggedIn, responses: [...boot, { items: [item], subtotal: 200 }, order] });
        await flush();
        submit(h, form);
        await flush();
        const afterBoot = h.calls.slice(boot.length);
        assert.ok(isCart(afterBoot[0]));
        assert.ok(isCheckout(afterBoot[1]), 'checkout POST follows the fresh read');
    });
}

test('initial load failure shows retry state instead of an empty-looking bag', async () => {
    const h = shop({ loggedIn: true, responses: [new Error('Server sibuk.')] });
    await flush();
    assert.match(h.$('cart-list').text, /Tas belum dapat dimuat/);
    assert.equal(h.$('checkout-button').disabled, true);
});

for (const loggedIn of [true, false]) {
    const who = loggedIn ? 'account' : 'guest';
    const boot = loggedIn
        ? [{ items: [item], subtotal: 200 }, { addresses: [] }, { data: [], current_page: 1, last_page: 1, total: 0 }, { favorites: [] }]
        : [{ items: [item], subtotal: 200 }];

    test(`${who}: shared checkout summary shows product, subtotal, and pending status`, async () => {
        const h = shop({ loggedIn, responses: boot });
        await flush();
        assert.match(h.$('checkout-summary').text, /Tas Kulit/);
        assert.match(h.$('checkout-summary').text, /Subtotal: Rp200/);
        assert.doesNotMatch(h.$('checkout-summary').text, /\bTotal:|Diskon: Rp0/);
        assert.match(h.$('checkout-summary').text, /pending \/ belum dibayar/);
    });

    test(`${who}: checkout summary updates after cart reload`, async () => {
        const h = shop({ loggedIn, responses: [...boot, {}, { items: [{ ...item, quantity: 2, subtotal: 400 }], subtotal: 400 }] });
        await flush();
        await h.$('cart-list').find((n) => n.tag === 'button' && /Ubah jumlah/.test(n.textContent)).onclick();
        await flush();
        assert.match(h.$('checkout-summary').text, /× 2/);
        assert.match(h.$('checkout-summary').text, /Subtotal: Rp400/);
        assert.doesNotMatch(h.$('checkout-summary').text, /\bTotal:|Diskon: Rp0/);
    });

    test(`${who}: checkout summary shows empty state`, async () => {
        const h = shop({ loggedIn, responses: loggedIn
            ? [{ items: [], subtotal: 0 }, { addresses: [] }, { data: [], current_page: 1, last_page: 1, total: 0 }, { favorites: [] }]
            : [{ items: [], subtotal: 0 }] });
        await flush();
        assert.match(h.$('checkout-summary').text, /Tas kosong/);
    });

    test(`${who}: failed reload shows unavailable shared summary`, async () => {
        const h = shop({ loggedIn, responses: [...boot, new Error('Server sibuk.')] });
        await flush();
        submit(h, loggedIn ? 'checkout-form' : 'guest-checkout-form');
        await flush();
        assert.match(h.$('checkout-summary').text, /Ringkasan tidak tersedia/);
        assert.doesNotMatch(h.$('checkout-summary').text, /Tas Kulit/);
    });
}
