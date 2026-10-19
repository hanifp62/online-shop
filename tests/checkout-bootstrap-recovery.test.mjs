import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Runs the inline script of resources/views/shop/index.blade.php against a fake DOM.
// Guard: when the first cart GET fails, the account sections (addresses, orders, favorites)
// must still recover so checkout stays usable after the cart loads on retry.
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
        replaceChildren(...nodes) { this.children = [...nodes]; this.options = []; this.value = ''; },
        setAttribute(k, v) { this.attrs[k] = v; }, removeAttribute(k) { delete this.attrs[k]; },
        addEventListener(type, fn) { (listeners[type] ??= []).push(fn); },
        querySelectorAll() { return []; },
        // A real <select> adopts the value of its selected option.
        add(option) { this.options.push(option); if (option.selected || !this.value) this.value = String(option.value); },
        reset() {},
        get text() { return [this.textContent, ...this.children.map((c) => c.text)].join(' '); },
        find(pred) { if (pred(this)) return this; for (const c of this.children) { const hit = c.find?.(pred); if (hit) return hit; } return null; },
    };
}

const flush = async () => { for (let i = 0; i < 20; i++) await new Promise((r) => setImmediate(r)); };
const item = { product_id: 7, product_title: 'Tas Kulit', unit_price: 200, quantity: 1, subtotal: 200, available: true };
const address = { id: 3, label: 'Rumah', recipient_name: 'Vivienne', phone: '+62 812', line1: 'Jl. Melati 1', line2: '', city: 'Jakarta', province: 'DKI', postal_code: '10110', is_default: true };

// Routes answers by URL so a later retry is not tied to a fixed queue order.
function shop({ cartFailures = 0 } = {}) {
    const ids = new Map();
    const $ = (id) => { if (!ids.has(id)) ids.set(id, el()); return ids.get(id); };
    const main = el('main');
    const calls = [];
    let cartAttempts = 0;
    const api = async (url, method = 'GET', body) => {
        calls.push({ url, method, body });
        if (url === '/r/cart.show') {
            cartAttempts += 1;
            if (cartAttempts <= cartFailures) throw new Error('Jaringan terputus.');
            return { items: [item], subtotal: 200 };
        }
        if (url === '/r/addresses.index') return { addresses: [address] };
        if (url.startsWith('/r/orders.index')) return { data: [], current_page: 1, last_page: 1, total: 0 };
        if (url === '/r/favorites.index') return { favorites: [] };
        if (url === '/r/checkout.store') return { order: { id: 9, total: 200, status: 'pending', items: [] } };
        return {};
    };
    const document = {
        getElementById: $,
        createElement: (tag) => el(tag),
        querySelector: (s) => (s === 'main' ? main : el()),
        querySelectorAll: () => [],
    };
    const context = {
        window: { shopRequest: api }, document, crypto: { randomUUID: () => 'uuid' }, confirm: () => true,
        Option: function Option(label, value) { return { label, value, selected: false }; },
        FormData: function FormData() { return { get: () => '', [Symbol.iterator]: function* () {} }; },
        Object, Promise, Number, String,
    };
    vm.runInNewContext(source(true), context);
    return { $, calls };
}

const submitCheckout = (h) => h.$('checkout-form').onsubmit({
    preventDefault() {},
    target: { address_id: { value: h.$('checkout-address').value }, voucher_code: { value: '' }, reset() {} },
});

test('account: a failed first cart GET still loads addresses so checkout works after retry', async () => {
    const h = shop({ cartFailures: 1 });
    await flush();

    assert.match(h.$('cart-list').text, /Tas belum dapat dimuat/, 'failed cart shows retry state');
    assert.match(h.$('feedback').text, /Jaringan terputus/, 'bootstrap preserves the cart error');
    assert.match(h.$('address-list').text, /Rumah/, 'addresses load before retry');

    const retry = h.$('cart-list').find((n) => n.tag === 'button' && /Coba lagi/.test(n.textContent));
    assert.ok(retry, 'retry button rendered');
    retry.onclick();
    await flush();

    assert.match(h.$('cart-list').text, /Tas Kulit/, 'retry reloads the cart');
    assert.match(h.$('address-list').text, /Rumah/, 'stored addresses recovered after the cart failure');
    assert.ok(h.$('checkout-address').options.length > 0, 'checkout address select populated');
    assert.equal(h.$('checkout-button').disabled, false, 'checkout enabled once cart and addresses are loaded');

    submitCheckout(h);
    await flush();
    assert.equal(h.calls.filter((c) => c.url === '/r/checkout.store').length, 1, 'checkout POST reaches the server');
});

test('account: a failed first cart GET still loads orders and favorites', async () => {
    const h = shop({ cartFailures: 1 });
    await flush();
    assert.equal(h.calls.filter((c) => c.url === '/r/addresses.index').length, 1);
    assert.equal(h.calls.filter((c) => c.url.startsWith('/r/orders.index')).length, 1);
    assert.equal(h.calls.filter((c) => c.url === '/r/favorites.index').length, 1);
    const retry = h.$('cart-list').find((n) => n.tag === 'button' && /Coba lagi/.test(n.textContent));
    retry.onclick();
    await flush();

    assert.ok(h.calls.some((c) => c.url.startsWith('/r/orders.index')), 'orders fetched');
    assert.ok(h.calls.some((c) => c.url === '/r/favorites.index'), 'favorites fetched');
    assert.match(h.$('order-list').text, /Belum ada pesanan/, 'orders section rendered');
    assert.match(h.$('favorite-list').text, /Belum ada favorit/, 'favorites section rendered');
});

test('account: a healthy first load keeps cart, addresses, and checkout together (control)', async () => {
    const h = shop({ cartFailures: 0 });
    await flush();
    assert.match(h.$('cart-list').text, /Tas Kulit/);
    assert.match(h.$('address-list').text, /Rumah/);
    assert.equal(h.$('checkout-button').disabled, false);
});
