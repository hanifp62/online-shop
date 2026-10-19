import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Same-tab mutations serialize. +/-, add and undo send relative deltas so the server
// applies them under its cart lock; another tab's write between GET and POST is not lost.
const app = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '');

function node(dataset = {}) {
    const classes = new Set();
    const handlers = {};
    return {
        dataset, handlers, children: [], offsetParent: {}, style: {}, hidden: false, textContent: '',
        classList: { contains: (c) => classes.has(c), add: (c) => classes.add(c), remove: (c) => classes.delete(c), toggle(c, on = !classes.has(c)) { on ? classes.add(c) : classes.delete(c); return on; } },
        addEventListener(type, fn) { (handlers[type] ??= []).push(fn); },
        fire(type, event = {}) { for (const fn of handlers[type] ?? []) fn(event); },
        setAttribute(key, value) { this[key] = value; },
        querySelector(selector) {
            const rowMatch = selector.match(/^\[data-cart-id="(\d+)"\] (.+)$/);
            if (rowMatch) return this.children.find(child => child.dataset.cartId === rowMatch[1])?.querySelector(rowMatch[2]) ?? null;
            return this.children.find(child => selector === '[data-toast-action]' ? child.dataset.toastAction !== undefined : selector === '[data-remove-cart]' ? child.dataset.removeCart !== undefined : child.dataset.quantity === selector.match(/data-quantity="(.+)"/)?.[1]) ?? null;
        }, querySelectorAll() { return this.children.flatMap(child => [child, ...child.querySelectorAll()]).filter(child => child.type === 'button' || child.dataset.quantity || child.dataset.removeCart !== undefined || child.dataset.closeCart !== undefined).filter(child => !child.hidden); },
        appendChild(child) { child.parent?.removeChild(child); child.parent = this; child.parentNode = this; this.children.push(child); },
        removeChild(child) { this.children = this.children.filter(entry => entry !== child); child.parent = child.parentNode = null; },
        contains(child) { return child === this || this.children.some(node => node.contains(child)); },
        closest(selector) { return selector === '[data-cart-id]' && this.dataset.cartId ? this : this.parent?.closest(selector); },
        replaceChildren() { if (this.contains(this.owner?.activeElement)) this.owner.activeElement = this.owner.body; this.children = []; },
        set innerHTML(value) { for (const dataset of [{ quantity: 'minus' }, { quantity: 'plus' }, { removeCart: '' }]) { const child = node(dataset); child.owner = this.owner; this.appendChild(child); } },
        focus() { if (this.owner) this.owner.activeElement = this; },
    };
}

function storefront({ cartDrawer = true } = {}) {
    const elements = new Map();
    for (const s of ['.storefront', '.cart-drawer', '[data-cart-items]', '[data-cart-empty]', '[data-cart-footer]', '[data-cart-status]', '.toast', '[data-open-cart]', '[data-close-cart]', '.cart-count', '.cart-count-text']) elements.set(s, [node()]);
    elements.get('.cart-drawer')[0].appendChild(elements.get('[data-close-cart]')[0]);
    elements.get('.cart-drawer')[0].appendChild(elements.get('[data-cart-items]')[0]);
    elements.get('[data-close-cart]')[0].dataset.closeCart = '';
    if (!cartDrawer) elements.delete('.cart-drawer');
    const card = node({ id: 'bag', productId: '7', name: 'Tas Kulit', category: 'Tas', price: '200' });
    const addButton = node();
    addButton.closest = () => card;
    elements.set('[data-product-card]', [card]);
    elements.set('[data-card-add]', [addButton]);
    const document = node();
    document.body = node();
    document.activeElement = node();
    document.visibilityState = 'visible';
    document.querySelector = (s) => elements.get(s)?.[0] ?? null;
    document.querySelectorAll = (s) => elements.get(s) ?? [];
    document.createElement = () => { const element = node(); element.owner = document; return element; }; 
    for (const nodes of elements.values()) for (const element of nodes) element.owner = document;
    document.body.appendChild(elements.get('.toast')[0]);
    const message = node(); message.dataset.toastMessage = '';
    elements.get('.toast')[0].appendChild(message);
    const timers = new Map(); let timerId = 0;
    const requests = [];
    const fetch = (url, options) => new Promise((resolve) => {
        requests.push({ url, method: options.method || 'GET', body: options.body ? JSON.parse(options.body) : null, respond: (data, ok = true) => resolve({ ok, json: async () => data }) });
    });
    const window = node();
    window.location = { href: 'https://zafira.test/', search: '' };
    const context = { document, window, navigator: {}, fetch, initAdminTables() {}, // test-only stub: app.js imports it from ./admin-table
    localStorage: { getItem: () => null, setItem() {}, removeItem() {} }, URL, URLSearchParams, history: { replaceState() {} }, setTimeout(fn, ms) { timers.set(++timerId, { fn, ms }); return timerId; }, clearTimeout(id) { timers.delete(id); } };
    vm.runInNewContext(app, context);
    const items = elements.get('[data-cart-items]')[0];
    const toast = elements.get('.toast')[0];
    return {
        requests,
        document,
        drawer: elements.get('.cart-drawer')?.[0],
        expireToast() { for (const [id, timer] of [...timers]) if (timer.ms >= 3500) { timers.delete(id); timer.fn(); } },
        tab(shiftKey = false) { const event = { key: 'Tab', shiftKey, preventDefault() {} }; document.fire('keydown', event); },
        closeCart() { elements.get('[data-close-cart]')[0].fire('click'); },
        items,
        status: elements.get('[data-cart-status]')[0],
        badge: elements.get('.cart-count')[0],
        close: elements.get('[data-close-cart]')[0],
        toast,
        addButton,
        openCart: elements.get('[data-open-cart]')[0],
        last: () => requests[requests.length - 1],
        undo: () => toast.querySelector('[data-toast-action]').onclick(),
        click(action) {
            const row = items.children[0];
            items.fire('click', { target: { closest: (s) => (s === '[data-cart-id]' ? row : s === '[data-remove-cart]' ? action === 'remove' : action === 'remove' ? null : { dataset: { quantity: action } }) } });
        },
    };
}

const flush = () => new Promise((resolve) => setImmediate(resolve));
const cartOf = (quantity) => ({ items: quantity ? [{ product_id: 7, product_title: 'Tas Kulit', unit_price: 200, quantity, available: true }] : [], subtotal: quantity * 200 });

async function boot(quantity) {
    const h = storefront();
    await flush();
    h.requests[0].respond(cartOf(quantity));
    await flush();
    return h;
}

// Respond to the latest request and let the queue advance.
async function reply(h, data, ok = true) {
    h.last().respond(data, ok);
    await flush();
}

test('quantity plus sends a relative delta, so another tab\'s write is not overwritten', async () => {
    const h = await boot(2);
    h.click('plus');
    await flush();
    assert.equal(h.requests.length, 2);
    assert.equal(h.last().method, 'GET', 'fresh GET /cart happens before mutating');
    assert.equal(h.last().url, '/cart');
    await reply(h, cartOf(5)); // another tab raised it to 5
    assert.equal(h.last().method, 'POST');
    assert.equal(h.last().url, '/cart/items/7/delta');
    assert.deepEqual(h.last().body, { delta: 1 }, 'server applies +1 to its own state, not an absolute from the client');
});

test('add to cart sends +1 delta', async () => {
    const h = await boot(0);
    h.addButton.fire('click');
    await flush();
    assert.equal(h.last().method, 'GET');
    await reply(h, cartOf(4));
    assert.equal(h.last().url, '/cart/items/7/delta');
    assert.deepEqual(h.last().body, { delta: 1 });
});

test('rapid adds during a slow request are serialized with fresh quantities, not dropped', async () => {
    const h = await boot(0);
    h.addButton.fire('click');
    h.addButton.fire('click');
    await flush();
    assert.equal(h.requests.length, 2, 'second add waits instead of firing in parallel');
    await reply(h, cartOf(0));
    assert.equal(h.requests.length, 3);
    assert.deepEqual(h.last().body, { delta: 1 });
    await reply(h, cartOf(1));
    assert.equal(h.requests.length, 4, 'second add was not silently dropped');
    assert.equal(h.last().method, 'GET');
    await reply(h, cartOf(1));
    assert.deepEqual(h.last().body, { delta: 1 }, 'second add also sent as its own delta');
});

test('rapid quantity clicks apply each step and stop once the line is gone', async () => {
    const h = await boot(2);
    h.click('plus');
    h.click('plus');
    await flush();
    await reply(h, cartOf(2));
    assert.deepEqual(h.last().body, { delta: 1 });
    await reply(h, cartOf(3));
    await reply(h, cartOf(3));
    assert.deepEqual(h.last().body, { delta: 1 });
    await reply(h, cartOf(4));

    const h2 = await boot(1);
    h2.click('minus');
    h2.click('minus');
    await flush();
    await reply(h2, cartOf(1));
    assert.equal(h2.last().url, '/cart/items/7/delta');
    assert.deepEqual(h2.last().body, { delta: -1 }, 'minus is a delta; server removes the line at zero');
    await reply(h2, cartOf(0));
    await reply(h2, cartOf(0)); // fresh GET for the second minus
    assert.equal(h2.requests.length, 4, 'second minus on a removed line sends nothing');
    assert.notEqual(h2.status.textContent, 'Memproses…', 'status not left stuck on Memproses after null-intent');
});

test('no-op intent after fresh GET renders latest snapshot and badge', async () => {
    const h = await boot(2);
    assert.equal(h.items.children.length, 1);
    assert.equal(String(h.badge.textContent), '2');
    h.click('plus');
    await flush();
    await reply(h, cartOf(0)); // another tab removed the line: intent is null
    assert.equal(h.requests.length, 2, 'no mutation sent');
    assert.equal(h.items.children.length, 0, 'stale row disappears');
    assert.equal(String(h.badge.textContent), '0', 'badge reflects fresh snapshot');
});

test('a failed request does not block later actions; undo still restores removed quantity', async () => {
    const h = await boot(2);
    h.click('plus');
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, { message: 'Stok habis.' }, false);
    assert.equal(h.last().method, 'GET', 'queue continues after failure');
    await reply(h, cartOf(2));
    assert.equal(h.last().method, 'DELETE');
    await reply(h, { ...cartOf(0), removed_quantity: 3 });

    h.undo();
    await flush();
    await reply(h, cartOf(0));
    assert.equal(h.last().method, 'POST');
    assert.equal(h.last().url, '/cart/items/7/delta');
    assert.deepEqual(h.last().body, { delta: 3 }, 'undo re-adds the quantity the server actually removed');
});

test('remove queued behind a successful plus undoes to the updated quantity, added to fresh state', async () => {
    const h = await boot(2);
    h.click('plus');
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, cartOf(3));
    await reply(h, cartOf(3));
    await reply(h, { ...cartOf(0), removed_quantity: 3 });
    h.undo();
    await flush();
    await reply(h, cartOf(1)); // another tab re-added 1 meanwhile
    assert.deepEqual(h.last().body, { delta: 3 }, 'undo is relative, so the other tab\'s 1 is kept server-side');
});

test('status announces resulting item count and subtotal after a change', async () => {
    const h = await boot(2);
    h.click('plus');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, cartOf(3));
    assert.match(h.status.textContent, /3 barang/);
    assert.match(h.status.textContent, /600/);
});

test('zero-quantity removal does not offer an undo action', async () => {
    const h = await boot(2);
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, { ...cartOf(0), removed_quantity: 0 });
    // A delta of 0 is always rejected by the server, so no undo may be offered.
    assert.equal(h.toast.querySelector('[data-toast-action]'), null);
});

test('failed undo does not claim absence when another tab restored the item', async () => {
    const h = await boot(2);
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, { ...cartOf(0), removed_quantity: 2 });
    h.undo();
    await flush();
    await reply(h, cartOf(99));
    await reply(h, { message: 'Stok tidak mencukupi.' }, false);
    assert.doesNotMatch(h.status.textContent, /tetap terhapus/);
    assert.match(h.status.textContent, /belum berhasil diurungkan/);
    assert.equal(String(h.badge.textContent), '99');
});

test('failed fresh GET aborts the mutation and keeps the queue alive', async () => {
    const h = await boot(2);
    h.click('plus');
    h.click('plus');
    await flush();
    await reply(h, { message: 'Jaringan terputus.' }, false);
    assert.equal(h.last().method, 'GET', 'no POST from stale state; next action proceeds');
    await reply(h, cartOf(2));
    assert.deepEqual(h.last().body, { delta: 1 });
});

test('opening the drawer refreshes the cart', async () => {
    const h = await boot(1);
    h.openCart.fire('click');
    await flush();
    assert.equal(h.requests.length, 2);
    assert.equal(h.last().method, 'GET');
    assert.equal(h.last().url, '/cart');
});

test('returning to the tab refreshes the cart; hiding does not', async () => {
    const h = await boot(1);
    h.document.visibilityState = 'hidden';
    h.document.fire('visibilitychange');
    await flush();
    assert.equal(h.requests.length, 1);
    h.document.visibilityState = 'visible';
    h.document.fire('visibilitychange');
    await flush();
    assert.equal(h.requests.length, 2);
    assert.equal(h.last().url, '/cart');
});

test('rerender restores focus to the same row action', async () => {
    const h = await boot(2);
    const row = h.items.children[0];
    const plus = row.querySelector('[data-quantity="plus"]');
    plus.focus();
    assert.equal(h.document.activeElement, plus);
    h.click('plus');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, cartOf(3));
    assert.equal(h.document.activeElement?.dataset?.quantity, 'plus');
    assert.equal(h.document.activeElement?.closest('[data-cart-id]')?.dataset?.cartId, '7');
});

test('deleting a row falls back to a safe focus target instead of document.body', async () => {
    const h = await boot(1);
    const row = h.items.children[0];
    const remove = row.querySelector('[data-remove-cart]');
    remove.focus();
    assert.equal(h.document.activeElement, remove);
    h.click('remove');
    await flush();
    await reply(h, cartOf(1));
    await reply(h, cartOf(0));
    assert.notEqual(h.document.activeElement, h.document.body, 'focus does not reset to body');
    assert.equal(h.document.activeElement, h.close, 'focus moves to close button when row disappears');
});

// R-11 / FR-201: undo lifecycle stays inside the modal and never strands focus.
async function removedInOpenCart() {
    const h = await boot(2);
    h.openCart.fire('click');
    await flush();
    await reply(h, cartOf(2));
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    await reply(h, { ...cartOf(0), removed_quantity: 2 });
    return h;
}

test('undo belongs to the open dialog and participates in its keyboard trap', async () => {
    const h = await removedInOpenCart();
    const action = h.toast.querySelector('[data-toast-action]');
    assert.ok(h.drawer.contains(action));
    h.close.focus();
    h.tab(true);
    assert.equal(h.document.activeElement, action);
    h.tab();
    assert.equal(h.document.activeElement, h.close);
});

test('late delete response cannot offer undo after drawer closes and reopens', async () => {
    const h = await boot(2);
    h.openCart.fire('click');
    await flush();
    await reply(h, cartOf(2));
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    h.closeCart();
    h.openCart.fire('click');
    await reply(h, { ...cartOf(0), removed_quantity: 2 });
    assert.equal(h.toast.querySelector('[data-toast-action]'), null);
});

test('late delete response while drawer stays closed cannot offer undo', async () => {
    const h = await boot(2);
    h.openCart.fire('click');
    await flush();
    await reply(h, cartOf(2));
    h.click('remove');
    await flush();
    await reply(h, cartOf(2));
    h.closeCart();
    await reply(h, { ...cartOf(0), removed_quantity: 2 });
    assert.equal(h.toast.querySelector('[data-toast-action]'), null);
    assert.equal(h.toast.classList.contains('show'), false);
    assert.equal(h.toast.parentNode, h.document.body);
});

test('focused undo survives timeout; activation clears action and restores safe focus', async () => {
    const h = await removedInOpenCart();
    const action = h.toast.querySelector('[data-toast-action]');
    action.focus();
    h.expireToast();
    assert.ok(h.toast.classList.contains('show'));
    h.undo();
    assert.equal(action.hidden, true);
    assert.equal(action.onclick, null);
    assert.equal(h.document.activeElement, h.close);
    assert.equal(h.toast.parentNode, h.document.body);
    await flush();
    await reply(h, cartOf(0));
    assert.deepEqual(h.last().body, { delta: 2 });
});

test('unfocused timeout and close/reopen discard stale undo and restore toast home', async () => {
    for (const cleanup of ['timeout', 'close']) {
        const h = await removedInOpenCart();
        const action = h.toast.querySelector('[data-toast-action]');
        if (cleanup === 'timeout') h.expireToast();
        else { action.focus(); h.closeCart(); }
        assert.equal(action.hidden, true);
        assert.equal(action.onclick, null);
        assert.equal(h.toast.parentNode, h.document.body);
        assert.equal(h.toast.classList.contains('show'), false);
        if (cleanup === 'close') assert.equal(h.document.activeElement, h.openCart);
        h.openCart.fire('click');
        assert.equal(action.hidden, true);
    }
});

test('later non-action toast clears focused undo without losing focus', async () => {
    const h = await removedInOpenCart();
    const action = h.toast.querySelector('[data-toast-action]');
    action.focus();
    h.addButton.fire('click');
    await flush();
    await reply(h, cartOf(0));
    await reply(h, cartOf(1));
    assert.equal(action.hidden, true);
    assert.equal(action.onclick, null);
    assert.equal(h.document.activeElement, h.close);
    assert.equal(h.toast.parentNode, h.document.body);
    assert.ok(h.toast.classList.contains('show'));
    h.expireToast();
    assert.equal(h.toast.classList.contains('show'), false);
});

test('ordinary toast still works on a storefront without a cart drawer', async () => {
    const h = storefront({ cartDrawer: false });
    await flush();
    await reply(h, cartOf(0));
    h.addButton.fire('click');
    await flush();
    await reply(h, cartOf(0));
    await reply(h, cartOf(1));
    assert.ok(h.toast.classList.contains('show'));
    assert.equal(h.toast.parentNode, h.document.body);
    h.expireToast();
    assert.equal(h.toast.classList.contains('show'), false);
});
