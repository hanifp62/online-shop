import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function loadCartRequest() {
    const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
    const start = source.indexOf('    function cartRequest(');
    const end = source.indexOf('\n    // Relative change:', start);
    return source.slice(start, end);
}

function harness(fetchImpl, timers = { setTimeout, clearTimeout }) {
    const functionSource = loadCartRequest();
    const status = { textContent: '' };
    const toasts = [];
    const context = {
        cartRequests: Promise.resolve(),
        cart: [],
        cartSubtotal: 0,
        select: () => status,
        fetch: fetchImpl,
        renderCart() {},
        updateCounts() {},
        rupiah: (n) => `Rp${n}`,
        showToast(message) { toasts.push(message); },
        AbortController,
        ...timers,
    };
    vm.createContext(context);
    vm.runInContext(`${functionSource}\nthis.cartRequest = cartRequest;`, context);
    return { context, status, toasts };
}

const response = (payload, ok = true, status = ok ? 200 : 400) => ({
    ok,
    status,
    json: async () => payload,
});

const settle = () => new Promise((resolve) => setImmediate(resolve));

test('cartRequest returns successful JSON for direct request', async () => {
    const h = harness(async () => response({ items: [{ quantity: 1 }], subtotal: 10 }));
    const data = await h.context.cartRequest('/cart', 'POST', { item: 1 });
    assert.deepEqual(data, { items: [{ quantity: 1 }], subtotal: 10 });
    assert.equal(h.status.textContent, 'Tas belanja diperbarui: 1 barang, subtotal Rp10.');
});

test('cartRequest maps malformed JSON to a friendly message', async () => {
    const h = harness(async () => ({ ok: true, status: 200, json: async () => { throw new SyntaxError('raw parser detail'); } }));
    assert.equal(await h.context.cartRequest('/cart'), null);
    assert.equal(h.status.textContent, 'Respons server tidak valid. Coba lagi.');
    assert.deepEqual(h.toasts, ['Respons server tidak valid. Coba lagi.']);
});

test('cartRequest maps HTTP 419 and 5xx without exposing server text', async () => {
    for (const [status, message] of [[419, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'], [503, 'Layanan sedang bermasalah. Coba lagi sebentar lagi.']]) {
        const h = harness(async () => response({ message: 'internal database detail' }, false, status));
        assert.equal(await h.context.cartRequest('/cart'), null);
        assert.equal(h.status.textContent, message);
        assert.doesNotMatch(h.status.textContent, /internal database/);
    }
});

test('cartRequest maps network failure and timeout to friendly messages', async () => {
    const network = harness(async () => { throw new TypeError('socket secret detail'); });
    assert.equal(await network.context.cartRequest('/cart'), null);
    assert.equal(network.status.textContent, 'Jaringan bermasalah. Periksa koneksi lalu coba lagi.');

    const timeout = harness((_url, options) => new Promise((_, reject) => {
        if (!options?.signal) return;
        options.signal.addEventListener('abort', () => {
            const error = new Error('The operation was aborted');
            error.name = 'AbortError';
            reject(error);
        });
    }), { setTimeout: (fn) => setImmediate(fn), clearTimeout() {} });
    assert.equal(await timeout.context.cartRequest('/cart'), null);
    assert.equal(timeout.status.textContent, 'Permintaan terlalu lama. Coba lagi.');
    assert.doesNotMatch(timeout.toasts.join(' '), /aborted/);
});

test('cartRequest safely parses fresh GET before checking response status', async () => {
    let calls = 0;
    const h = harness(async () => {
        calls += 1;
        return calls === 1 ? response({ items: [], subtotal: 0 }) : response({ items: [], subtotal: 0 });
    });
    const data = await h.context.cartRequest(() => ['/cart/items/1', 'POST', { quantity: 1 }]);
    assert.deepEqual(data, { items: [], subtotal: 0 });
    assert.equal(calls, 2);
});
