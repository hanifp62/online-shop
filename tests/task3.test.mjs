import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const app = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '');
const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
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
        appendChild(child) { this.children.push(child); }, focus() {}, scrollIntoView() {},
        set innerHTML(value) { this.children = []; this.html = value; }, get innerHTML() { return this.html ?? ''; },
    };
}
function storefront(initial = []) {
    const elements = new Map();
    for (const selector of ['.storefront', '[data-overlay]', '.cart-drawer', '.search-panel', '.quick-view-modal', '.concierge-modal', '#welcome-profile-drawer', '[data-cart-items]', '[data-cart-empty]', '[data-cart-footer]', '[data-product-count]', '.empty-products', '#live-catalog-search-input', '#welcome-feedback-strip']) elements.set(selector, [node()]);
    const cards = [node({ id: 'robe', name: 'Robe Sutra', category: 'Busana', material: 'Sutra', price: '100' }), node({ id: 'bag', name: 'Tas Kulit', category: 'Tas', material: 'Kulit', price: '200' })];
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
    const values = new Map([['zafira-cart', JSON.stringify(initial)]]);
    const window = node();
    window.location = { href: 'https://zafira.test/', search: '' };
    const context = { document, window, navigator: {}, localStorage: { getItem: (k) => values.get(k) ?? null, setItem: (k, v) => values.set(k, v), removeItem: (k) => values.delete(k) }, URL, URLSearchParams, history: { replaceState() {} }, setTimeout() {}, clearTimeout() {} };
    vm.runInNewContext(app, context);
    return { document, window, cards, filters, addButtons, get: (s) => elements.get(s)[0], cart: () => JSON.parse(values.get('zafira-cart:v1')) };
}
function cartClick(h, index, action) {
    const row = h.get('[data-cart-items]').children[index];
    const button = action === 'remove' ? null : { dataset: { quantity: action } };
    h.get('[data-cart-items]').fire('click', { target: { closest: (s) => s === '[data-cart-id]' ? row : s === '[data-remove-cart]' ? action === 'remove' : button } });
}

test('legacy cart normalizes variants, collision-safe update/remove survives reload', () => {
    const items = [
        { id: 'robe-long', size: 'M', monogram: 'A', quantity: 1, price: 100 },
        { id: 'robe', size: 'long-M', monogram: 'A', quantity: 2, price: 100 },
        { id: 'bag', quantity: 1, price: 200 },
    ];
    const h = storefront(items);
    const rows = h.get('[data-cart-items]').children;
    assert.notEqual(rows[0].dataset.cartId, rows[1].dataset.cartId);
    assert.equal(h.cart()[2].size, '');
    assert.equal(h.cart()[2].monogram, '');
    cartClick(h, 1, 'plus');
    assert.deepEqual(h.cart().map((i) => i.quantity), [1, 3, 1]);
    cartClick(h, 0, 'remove');
    const reload = storefront(h.cart());
    assert.deepEqual(reload.cart().map((i) => [i.id, i.size, i.monogram, i.quantity]), [['robe', 'long-M', 'A', 3], ['bag', '', '', 1]]);
    cartClick(reload, 0, 'minus');
    assert.equal(reload.cart()[0].quantity, 2);
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

test('card add routes apparel to PDP instead of adding without size', () => {
    const h = storefront();
    h.addButtons[0].fire('click');
    assert.equal(h.window.location.href, '/produk/robe');
    assert.equal(h.cart().length, 0);
    h.addButtons[1].fire('click');
    assert.equal(h.cart()[0].id, 'bag');
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
