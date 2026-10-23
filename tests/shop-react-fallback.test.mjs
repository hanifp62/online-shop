import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('resources/views/shop/index.blade.php', 'utf8');
const validation = source.slice(source.indexOf('    function validateCheckoutResult('), source.indexOf('    function requireSettledCheckout('));
const guestHandler = source.slice(source.indexOf("        $('guest-checkout-form').onsubmit ="), source.indexOf("        $('guest-clear-token').onclick ="))
    .replace(/@json\(route\('checkout.store'\)\)/g, "'/checkout'");

for (const item of [null, {}, 'item', { product_title: 'Sutra', quantity: 1 }, { product_title: 'Sutra', quantity: 0, subtotal: 0 }]) {
    test(`Blade rejects malformed order item ${JSON.stringify(item)} before clearing pending; retry reuses payload/key`, async () => {
        const form = { reset() {} };
        const nodes = new Map([['guest-checkout-form', form]]);
        const calls = [];
        let reads = 0;
        let operation;
        const order = { id: 9, total: 100, status: 'pending', items: [] };
        const context = {
            pendingCheckout: null, checkoutKey: null, cart: { items: [{ available: true }] },
            $: id => { if (!nodes.has(id)) nodes.set(id, { replaceChildren() {}, append() {} }); return nodes.get(id); },
            FormData: function () { return new Map([['customer_name', 'Original']]); },
            crypto: { randomUUID: () => 'same-key' },
            action: fn => { operation = fn().catch(error => error); },
            loadCart: async () => { reads++; },
            api: async (url, method, payload) => { calls.push(payload); return { order: calls.length === 1 ? { ...order, items: [item] } : order }; },
            pendingCheckoutMessage: () => 'Uncertain', shopText: (key, fallback) => fallback,
            text: () => ({}), statusLabel: value => value, money: value => value, message() {},
        };
        vm.createContext(context);
        vm.runInContext(validation + guestHandler, context);
        form.onsubmit({ preventDefault() {}, target: form });
        const failure = await operation;
        assert.match(failure.message, /Uncertain/);
        const pending = context.pendingCheckout;
        assert.equal(pending.idempotency_key, 'same-key');
        assert.equal(reads, 1);
        form.onsubmit({ preventDefault() {}, target: form });
        await operation;
        assert.equal(calls.length, 2);
        assert.equal(calls[1], pending);
        assert.equal(context.pendingCheckout, null);
        assert.equal(reads, 2, 'only success refresh adds a cart read');
    });
}
