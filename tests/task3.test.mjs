import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { searchMatches } from '../resources/js/search-match.js';

const app = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '');
const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
const prototypeApp = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');

test('prototype product links resolve storefront slugs', () => {
    assert.match(prototypeApp, /item\.slug === requestedProductId \|\| item\.id === numericId/);
});
function node(dataset = {}) {
    const classes = new Set();
    const handlers = {};
    return {
        dataset, handlers, children: [], style: {}, hidden: false, value: '', textContent: '',
        classList: { contains: (c) => classes.has(c), add: (c) => classes.add(c), remove: (c) => classes.delete(c), toggle(c, on = !classes.has(c)) { on ? classes.add(c) : classes.delete(c); return on; } },
        addEventListener(type, fn) { (handlers[type] ??= []).push(fn); },
        fire(type, event = {}) { for (const fn of handlers[type] ?? []) fn(event); },
        setAttribute(key, value) { this[key] = value; },
        querySelector() { return null; }, querySelectorAll() { return []; },
        appendChild(child) { this.children.push(child); }, replaceChildren(...next) { this.children = next; },
        focus() {}, scrollIntoView() {},
        set innerHTML(value) { this.children = []; this.html = value; }, get innerHTML() { return this.html ?? ''; },
    };
}
const CATALOG = { 11: { product_title: 'Robe Sutra', unit_price: 100 }, 22: { product_title: 'Tas Kulit', unit_price: 200 } };
// Deterministic fake of the session cart API: POST /cart/items SETs quantity, DELETE removes, GET reads.
function fakeServer(initial = []) {
    const lines = new Map(initial.map((line) => [line.product_id, { ...line }]));
    const calls = [];
    const payload = () => {
        const items = [...lines.values()].map((line) => ({ available: true, ...CATALOG[line.product_id], ...line }));
        return { items, subtotal: items.reduce((sum, i) => sum + i.unit_price * i.quantity, 0) };
    };
    const fetch = async (url, init = {}) => {
        const method = init.method || 'GET';
        const body = init.body ? JSON.parse(init.body) : null;
        calls.push({ url, method, body });
        if (method === 'POST' && url === '/cart/items') lines.set(body.product_id, { product_id: body.product_id, quantity: body.quantity });
        const adjusted = method === 'POST' && url.match(/^\/cart\/items\/(\d+)\/delta$/);
        if (adjusted) {
            const product_id = Number(adjusted[1]);
            const quantity = Math.max(0, (lines.get(product_id)?.quantity || 0) + body.delta);
            if (quantity) lines.set(product_id, { product_id, quantity });
            else lines.delete(product_id);
        }
        const removed = method === 'DELETE' && url.match(/^\/cart\/items\/(\d+)$/);
        if (removed) lines.delete(Number(removed[1]));
        return { ok: true, json: async () => payload() };
    };
    return { fetch, calls, snapshot: () => [...lines.values()].map((line) => ({ ...line })), writes: () => calls.filter((c) => c.method !== 'GET') };
}
function storefront({ server = fakeServer(), legacy = {}, search = '' } = {}) {
    const elements = new Map();
    const drawerInput = node();
    const drawerResults = node();
    elements.set('[data-search-input]', [drawerInput]);
    elements.set('[data-search-results]', [drawerResults]);
    elements.set('[data-search-status]', [node()]);
    const historyWrites = [];
    const history = { replaceState(_state, _title, url) {
        historyWrites.push(String(url));
        window.location.href = String(url);
        window.location.search = new URL(url).search;
    } };
    for (const selector of ['.storefront', '[data-overlay]', '.cart-drawer', '.search-panel', '.quick-view-modal', '.concierge-modal', '#welcome-profile-drawer', '[data-cart-items]', '[data-cart-empty]', '[data-cart-footer]', '[data-product-count]', '#live-catalog-search-input', '#welcome-feedback-strip', '[data-legacy-cart]', '[data-legacy-lines]']) elements.set(selector, [node()]);
    const emptyClearBtn = node({ clearCatalogSearch: '' });
    emptyClearBtn.closest = (s) => (s === '[data-clear-catalog-search]' ? emptyClearBtn : null);
    const emptyProducts = node();
    emptyProducts.children = [emptyClearBtn];
    emptyProducts.querySelector = (s) => (s === '[data-clear-catalog-search]' ? emptyClearBtn : null);
    elements.set('.empty-products', [emptyProducts]);
    elements.set('[data-clear-catalog-search]', [emptyClearBtn]);
    const cards = [node({ id: 'robe', productId: '11', name: 'Robe Sutra', category: 'Busana', material: 'Sutra', price: '100' }), node({ id: 'bag', productId: '22', name: 'Tas Kulit', category: 'Tas', material: 'Kulit', price: '200' })];
    elements.set('[data-product-card]', cards);
    const filters = ['Semua', 'Busana', 'Tas'].map((filter) => node({ filter }));
    elements.set('[data-filter]', filters);
    const addButtons = cards.map((card) => { const button = node(); button.closest = () => card; return button; });
    elements.set('[data-card-add]', addButtons);
    for (const selector of ['[data-open-cart]', '[data-open-profile]', '[data-close-profile]', '[data-open-concierge]']) elements.set(selector, [node()]);
    const document = node();
    document.body = node(); document.activeElement = node();
    document.querySelector = (s) => elements.get(s)?.[0] ?? null;
    document.querySelectorAll = (s) => elements.get(s) ?? [];
    document.createElement = () => node();
    const values = new Map(Object.entries(legacy));
    const storageWrites = [];
    const window = node();
    window.location = { href: `https://zafira.test/${search}`, search };
    // Only deep-link tests opt in: run idle work synchronously so the real URL-restore path executes.
    if (search) window.requestIdleCallback = (cb) => cb();
    class IntersectionObserver { observe() {} unobserve() {} }
    const localStorage = {
        getItem: (k) => values.get(k) ?? null,
        setItem: (k, v) => { storageWrites.push(['set', k]); values.set(k, v); },
        removeItem: (k) => { storageWrites.push(['remove', k]); values.delete(k); },
    };
    // test-only stub: app.js imports initAdminTables and searchMatches, stripped by the import regex above
    const context = { document, window, navigator: {}, localStorage, fetch: server.fetch, initAdminTables() {}, searchMatches, IntersectionObserver, URL, URLSearchParams, history, setTimeout() {}, clearTimeout() {} };
    vm.runInNewContext(app, context);
    // The fake fetch only resolves through microtasks, so one macrotask turn drains the whole queued cart chain.
    const settle = () => new Promise((resolve) => setImmediate(resolve));
    return { document, window, cards, filters, addButtons, drawerInput, drawerResults, server, values, storageWrites, settle, historyWrites, get: (s) => elements.get(s)[0], rows: () => elements.get('[data-cart-items]')[0].children };
}
function cartClick(h, index, action) {
    const row = h.get('[data-cart-items]').children[index];
    const button = action === 'remove' ? null : { dataset: { quantity: action } };
    h.get('[data-cart-items]').fire('click', { target: { closest: (s) => s === '[data-cart-id]' ? row : s === '[data-remove-cart]' ? action === 'remove' : button } });
}

test('legacy cart keys stay untouched, are never auto-posted, and never merge into the server cart', async () => {
    const v1 = JSON.stringify([{ id: 'robe', name: 'Robe Sutra', size: 'M', monogram: 'AB', quantity: 2 }]);
    const unversioned = JSON.stringify([{ id: 'bag', quantity: 1 }, { id: 'scarf', name: 'Selendang', quantity: 3 }]);
    const h = storefront({ legacy: { 'zafira-cart:v1': v1, 'zafira-cart': unversioned } });
    await h.settle();
    // Both keys survive byte-for-byte: no migration, rewrite, or deletion.
    assert.equal(h.values.get('zafira-cart:v1'), v1);
    assert.equal(h.values.get('zafira-cart'), unversioned);
    assert.deepEqual(h.storageWrites, []);
    // Only the initial server read happened: no automatic POST/DELETE of legacy lines.
    assert.deepEqual(h.server.calls, [{ url: '/cart', method: 'GET', body: null }]);
    assert.deepEqual(h.server.snapshot(), []);
    assert.equal(h.rows().length, 0);
    // Legacy lines are shown read-only for the customer instead.
    assert.equal(h.get('[data-legacy-cart]').hidden, false);
    assert.deepEqual(h.get('[data-legacy-lines]').children.map((li) => li.textContent), [
        'Robe Sutra × 2 · Ukuran: M · Monogram: AB',
        'bag × 1',
        'Selendang × 3',
    ]);
});

test('server cart renders by product_id, quantity edits send relative deltas, and a fresh harness restores from server state', async () => {
    const h = storefront({ server: fakeServer([{ product_id: 11, quantity: 2 }, { product_id: 22, quantity: 1 }]) });
    await h.settle();
    assert.deepEqual(h.rows().map((row) => row.dataset.cartId), ['11', '22']);
    cartClick(h, 0, 'plus');
    await h.settle();
    assert.deepEqual(h.server.writes(), [{ url: '/cart/items/11/delta', method: 'POST', body: { delta: 1 } }]);
    cartClick(h, 1, 'minus');
    await h.settle();
    assert.deepEqual(h.server.writes().at(-1), { url: '/cart/items/22/delta', method: 'POST', body: { delta: -1 } });
    assert.deepEqual(h.server.snapshot(), [{ product_id: 11, quantity: 3 }]);

    const reload = storefront({ server: fakeServer(h.server.snapshot()) });
    await reload.settle();
    assert.deepEqual(reload.rows().map((row) => row.dataset.cartId), ['11']);
    assert.match(reload.rows()[0].innerHTML, /<span>3<\/span>/);
    cartClick(reload, 0, 'remove');
    await reload.settle();
    assert.deepEqual(reload.server.writes(), [{ url: '/cart/items/11', method: 'DELETE', body: null }]);
    assert.equal(reload.rows().length, 0);
    assert.deepEqual(h.values.size, 0); // the cart never touches localStorage
});

test('catalog search and category intersect through one hidden-based filter', () => {
    const h = storefront();
    const input = h.get('#live-catalog-search-input');
    input.value = 'sutra'; input.fire('input');
    assert.deepEqual(h.cards.map((c) => c.hidden), [false, true]);
    h.filters[2].fire('click');
    assert.deepEqual(h.cards.map((c) => c.hidden), [true, true]);
    assert.equal(String(h.get('[data-product-count]').textContent), '0');
    input.value = ''; input.fire('input');
    assert.deepEqual(h.cards.map((c) => c.hidden), [true, false]);
    assert.ok(!welcome.includes('card.style.display'));
});

test('profile shares overlay and scroll lifecycle with cart and concierge', () => {
    const h = storefront();
    h.get('[data-open-profile]').fire('click');
    assert.ok(h.get('#welcome-profile-drawer').classList.contains('open'));
    assert.ok(h.document.body.classList.contains('no-scroll'));
    h.get('[data-open-cart]').fire('click');
    h.get('[data-close-profile]').fire('click');
    assert.ok(h.get('[data-overlay]').classList.contains('open'));
    assert.ok(h.document.body.classList.contains('no-scroll'));
    h.get('[data-open-concierge]').fire('click');
    h.get('[data-overlay]').fire('click');
    assert.ok(!h.document.body.classList.contains('no-scroll'));
    h.get('[data-open-profile]').fire('click');
    h.document.fire('keydown', { key: 'Escape' });
    assert.equal(h.get('#welcome-profile-drawer')['aria-hidden'], 'true');
    assert.ok(!h.get('[data-overlay]').classList.contains('open'));
    assert.ok(!h.document.body.classList.contains('no-scroll'));
    assert.ok(!welcome.includes('document.body.style.overflow'));
    assert.ok(!welcome.includes("e.key === 'Escape'"));
});

test('standard card add POSTs a +1 delta for the server product_id and survives a fresh harness', async () => {
    const h = storefront();
    await h.settle();
    h.addButtons[1].fire('click');
    await h.settle();
    h.addButtons[1].fire('click');
    await h.settle();
    assert.deepEqual(h.server.writes(), [
        { url: '/cart/items/22/delta', method: 'POST', body: { delta: 1 } },
        { url: '/cart/items/22/delta', method: 'POST', body: { delta: 1 } },
    ]);
    assert.deepEqual(h.rows().map((row) => row.dataset.cartId), ['22']);

    // Fresh page load with the server state as supplied: next add continues from the server quantity.
    const reload = storefront({ server: fakeServer(h.server.snapshot()) });
    await reload.settle();
    assert.deepEqual(reload.rows().map((row) => row.dataset.cartId), ['22']);
    reload.addButtons[1].fire('click');
    await reload.settle();
    assert.deepEqual(reload.server.writes(), [{ url: '/cart/items/22/delta', method: 'POST', body: { delta: 1 } }]);
    assert.deepEqual(reload.server.snapshot(), [{ product_id: 22, quantity: 3 }], 'delta continues from the server quantity');
});

test('apparel card add still goes to the PDP and never writes to the server cart', async () => {
    const h = storefront();
    await h.settle();
    h.addButtons[0].fire('click');
    await h.settle();
    assert.equal(h.window.location.href, '/produk/robe');
    assert.deepEqual(h.server.writes(), []);
});

test('runtime translations cannot restore removed claims in any language', () => {
    const source = readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8');
    const start = source.indexOf('const I18N =');
    const end = source.indexOf('// --- 2.', start);
    const dict = vm.runInNewContext(`${source.slice(start, end)}; I18N`);
    for (const [lang, translations] of Object.entries(dict)) {
        assert.ok(!/certificat|certificato|certificate|sertifikat/i.test(translations.service_4_title), lang);
        assert.ok(!/3.?5|3 à 5|3 a 5/.test(translations.atelier_step_3), lang);
        assert.match(translations.toast_rendezvous_registered, /simula/i);
        for (const key of Object.keys(translations)) assert.ok(!/^(telemetry_|account_cert|account_step_|profile_(advisor|silhouette|registered_size|chest_waist|vic_badge))/.test(key), `${lang}:${key}`);
    }
    assert.ok(!welcome.includes('Madame Aurelia'));
    assert.ok(!welcome.includes('(3–5 hari)'));
});

test('both runtime dictionaries omit unsupported claims and keep four languages', () => {
    for (const path of ['../public/js/zafira-core.js', '../prototype/zafira-core.js']) {
        const source = readFileSync(new URL(path, import.meta.url), 'utf8');
        const dictionary = vm.runInNewContext(`${source.slice(source.indexOf('const I18N ='), source.indexOf('// --- 2.'))}; I18N`);
        assert.deepEqual(Object.keys(dictionary).sort(), ['en', 'fr', 'id', 'it'], path);
        const productBlock = source.slice(source.indexOf('const MULTI_PRODUCTS'), source.indexOf('// Cross-reference aliases'));
        assert.doesNotMatch(productBlock, /\b(?:60 hours|60 jam|soixante heures|sessanta ore|3 minggu|12 jam|twelve hours|douze heures|dodici ore|24k|serial|nomor seri|numéro de série|numero di serie|measurements:)\b/i, path);
        for (const translations of Object.values(dictionary)) {
            assert.doesNotMatch(JSON.stringify(translations), /\b(?:MSI|telemetry|18k|18 karat|18 carats|18 carati|24k|certificate|certificat|certificato|sertifikat|serial|advisor|takt time|throughput|ledger)\b/i, path);
        }
    }
});

test('searchMatches gives substring priority over typo matches', () => {
    const items = [
        { id: '1', haystack: 'tas kulit' },
        { id: '2', haystack: 'tas sutra' },
    ];
    const results = searchMatches(items, 'sutr');
    assert.deepEqual(results.map((i) => i.id), ['2']);
    assert.deepEqual(searchMatches([{ id: 'fuzzy', haystack: 'stura' }, { id: 'literal', haystack: 'sutra halus' }], 'sutra', 1).map((i) => i.id), ['literal']);
});

test('searchMatches fallback works for single-word typo with edit distance 1 or adjacent transposition', () => {
    const items = [
        { id: '1', haystack: 'sutra sutera halus' },
        { id: '2', haystack: 'gaun malam sutra' },
        { id: '3', haystack: 'jaket kulit' },
    ];
    // adjacent transposition: 'stura' -> 'sutra'
    assert.deepEqual(searchMatches(items, 'stura').map((i) => i.id), ['1', '2']);
    // single character replacement: 'sutrx' ~ 'sutra'
    assert.deepEqual(searchMatches(items, 'sutrx').map((i) => i.id), ['1', '2']);
    // short word (< 4 chars) must not trigger typo fallback
    assert.deepEqual(searchMatches([{ id: 't', haystack: 'tas' }], 'taz').map((i) => i.id), []);
    // Unicode: one non-ASCII substitution counts as one edit
    assert.deepEqual(searchMatches([{ id: 'u', haystack: 'éclat' }], 'eclat').map((i) => i.id), ['u']);
    // multi-word query must not trigger typo fallback
    assert.deepEqual(searchMatches(items, 'sutrx halus').map((i) => i.id), []);
});

test('app live search uses shared search-match typo fallback and guided empty state resets without losing filters', () => {
    const h = storefront();
    const input = h.get('#live-catalog-search-input');
    // 'stura' is an adjacent transposition of 'sutra' (>= 4 chars)
    input.value = 'stura';
    input.fire('input');
    assert.deepEqual(h.cards.map((c) => c.hidden), [false, true]);
    assert.match(h.get('#welcome-feedback-strip').textContent, /saran mirip/i);
    // `hidden` cannot reveal an element styled display:none inline.
    assert.doesNotMatch(welcome.match(/<div id="welcome-feedback-strip"[^>]*>/)[0], /display:\s*none/);
    assert.match(welcome.match(/<div id="welcome-feedback-strip"[^>]*>/)[0], /\shidden[\s>]/);

    // typo fallback is scoped to the active category, and clearing keeps that category
    h.filters[2].fire('click');
    assert.deepEqual(h.cards.map((c) => c.hidden), [true, true]);
    input.value = 'kulti';
    input.fire('input');
    assert.deepEqual(h.cards.map((c) => c.hidden), [true, false]);
    h.get('[data-clear-catalog-search]').fire('click');
    assert.deepEqual(h.cards.map((c) => c.hidden), [true, false]);
    h.filters[0].fire('click');

    // guided empty state: no raw user HTML, clear resets search preserving category/favorites
    input.value = '<script>alert(1)</script>';
    input.fire('input');
    const empty = h.get('.empty-products');
    assert.equal(empty.hidden, false);
    assert.ok(!empty.innerHTML.includes('<script>'));
    const clearBtn = empty.querySelector('[data-clear-catalog-search]');
    assert.ok(clearBtn);
    clearBtn.fire('click');
    assert.equal(input.value, '');
    assert.equal(empty.hidden, true);
});

test('deep link ?cari= runs the real restore path: catalog is filtered and the drawer is not left stale', () => {
    // The restore block sits in try/catch, so the old undeclared `searchInput` failed silently: cards stayed visible.
    const h = storefront({ search: '?cari=stura' });
    assert.equal(h.get('#live-catalog-search-input').value, 'stura');
    assert.equal(h.drawerInput.value, 'stura');
    assert.deepEqual(h.cards.map((c) => c.hidden), [false, true]);
});

test('catalog edits and guided clear sync cari while preserving category, favorites and unrelated URL params', () => {
    for (const scope of ['kategori=Tas', 'tampilkan=favorit']) {
        const h = storefront({ search: `?${scope}&cari=lama&campaign=maison` });
        const input = h.get('#live-catalog-search-input');
        input.value = 'kulit';
        input.fire('input');
        let params = new URL(h.window.location.href).searchParams;
        assert.equal(params.get('cari'), 'kulit');
        assert.equal(params.get('campaign'), 'maison');
        const [key, value] = scope.split('=');
        assert.equal(params.get(key), value);
        h.get('[data-clear-catalog-search]').fire('click');
        params = new URL(h.window.location.href).searchParams;
        assert.equal(params.has('cari'), false);
        assert.equal(params.get(key), value);
        assert.equal(params.get('campaign'), 'maison');
    }
});

test('deep-link renders drawer through existing search without any URL writes', () => {
    for (const query of ['stura', 'zzzzzz', 's']) {
        const h = storefront({ search: `?kategori=Busana&cari=${query}&campaign=maison` });
        assert.deepEqual(h.historyWrites, []);
        if (query === 'stura') assert.match(h.drawerResults.innerHTML, /data-search-product="robe"/);
        if (query === 'zzzzzz') assert.match(h.drawerResults.innerHTML, /Tidak ada kreasi/);
        if (query === 's') assert.equal(h.drawerResults.innerHTML, '');
    }
});

test('drawer announces count, fuzzy and empty feedback separately from interactive results', () => {
    assert.match(welcome, /<p\b[^>]*role="status"[^>]*aria-live="polite"[^>]*data-search-status[^>]*><\/p>/);
    assert.doesNotMatch(welcome.match(/<div\b[^>]*data-search-results[^>]*>/)[0], /role="status"|aria-live/);
    const h = storefront();
    const status = h.get('[data-search-status]');
    h.drawerInput.value = 'sutra'; h.drawerInput.fire('input');
    assert.match(status.textContent, /1 kreasi/);
    assert.doesNotMatch(status.textContent, /saran mirip/);
    h.drawerInput.value = 'stura'; h.drawerInput.fire('input');
    assert.match(status.textContent, /1 kreasi.*saran mirip/);
    h.drawerInput.value = '<img src=x>'; h.drawerInput.fire('input');
    assert.match(status.textContent, /Tidak ada kreasi/);
    assert.doesNotMatch(status.textContent, /<img/);
    h.drawerInput.value = ''; h.drawerInput.fire('input');
    assert.equal(status.textContent, '');
});

test('drawer search shares the matcher: typo fallback, escaped output, no results hint', () => {
    const h = storefront();
    h.drawerInput.value = 'stura';
    h.drawerInput.fire('input');
    assert.match(h.drawerResults.innerHTML, /data-search-product="robe"/);
    assert.ok(!h.drawerResults.innerHTML.includes('data-search-product="bag"'));
    h.drawerInput.value = '<img src=x onerror=alert(1)>';
    h.drawerInput.fire('input');
    assert.ok(!h.drawerResults.innerHTML.includes('<img'));
    assert.match(h.drawerResults.innerHTML, /Tidak ada kreasi/);
});
