// REM-20261021 / FR-REM-005 (audit UI-01, 03, 04, 05, 07, 08, 09, 10) — TESTS FIRST.
//
// Runtime tests execute REAL shipped JS inside node:vm with hand-built DOM stubs.
// Blade/copy and the limited root-CSS model are static contract checks, NOT browser/layout evidence.
// No browser, server, database or PHP is used. Tests marked "[baseline]" characterise behavior that already
// works and must stay green; every other test is expected to be RED until the storefront lane implements it.
//
// Contract choices that are NOT dictated by the audit are labelled "CONTRACT:" so the implementer can
// challenge them with a written reason instead of silently bending a test.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { searchMatches } from '../resources/js/search-match.js';

const root = new URL('../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

// ---------------------------------------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------------------------------------

function langSection(locale, section) {
    const source = read(`lang/${locale}/shop_ui.php`);
    const match = source.match(new RegExp(`\\n    '${section}' => \\[([\\s\\S]*?)\\n    \\],`));
    assert.ok(match, `lang/${locale}/shop_ui.php must contain the '${section}' section`);
    return match[1];
}

function assertLangKeys(section, keys) {
    for (const locale of LOCALES) {
        const body = langSection(locale, section);
        for (const key of keys) {
            assert.match(body, new RegExp(`'${key}' =>\\s*['"]`), `lang/${locale}/shop_ui.php '${section}.${key}' is missing`);
        }
    }
}

/** Flat, registry-style node (same idea as tests/task3 + cart-concurrency) for running resources/js/app.js. */
function flatNode(dataset = {}) {
    const classes = new Set();
    const handlers = {};
    const attrs = {};
    const node = {
        dataset, handlers, attrs, children: [], style: {}, hidden: false, disabled: false, value: '', textContent: '', innerHTML: '',
        classList: {
            contains: (c) => classes.has(c), add: (c) => classes.add(c), remove: (c) => classes.delete(c),
            toggle(c, on = !classes.has(c)) { if (on) classes.add(c); else classes.delete(c); return on; },
        },
        addEventListener(type, fn) { (handlers[type] ??= []).push(fn); },
        removeEventListener() {},
        fire(type, event = {}) { for (const fn of handlers[type] ?? []) fn(event); },
        setAttribute(key, value) { attrs[key] = String(value); },
        getAttribute: (key) => attrs[key] ?? null,
        hasAttribute: (key) => key in attrs,
        removeAttribute(key) { delete attrs[key]; },
        querySelector: () => null,
        querySelectorAll: () => [],
        appendChild(child) { node.children.push(child); child.parentNode = node; return child; },
        replaceChildren(...next) { node.children = next; },
        closest: () => null,
        contains: (other) => other === node,
        focus() {}, scrollIntoView() {},
    };
    return node;
}

const appSource = () => read('resources/js/app.js').replace(/^import .*;\n/gm, '');

/**
 * Boots the real resources/js/app.js against a registry DOM.
 * `cards` become [data-product-card] nodes each with a wishlist toggle and an add-to-bag button.
 */
function appHarness({ fetchImpl, cards = [], dataset = {}, localValues = {}, copy = {}, extra = {}, search = '' } = {}) {
    const elements = new Map();
    const set = (selector, nodes) => elements.set(selector, Array.isArray(nodes) ? nodes : [nodes]);

    const storefront = flatNode(dataset);
    set('.storefront', storefront);
    for (const selector of ['[data-overlay]', '.cart-drawer', '.search-panel', '.quick-view-modal', '.concierge-modal', '#welcome-profile-drawer', '[data-cart-items]', '[data-cart-empty]', '[data-cart-footer]', '[data-cart-status]', '[data-close-cart]', '[data-open-cart]', '[data-open-profile]', '[data-close-profile]', '[data-open-concierge]', '.cart-count', '.cart-count-text', '[data-product-count]', '#live-catalog-search-input', '#welcome-feedback-strip']) set(selector, flatNode());
    const wishlistCount = flatNode();
    set('.wishlist-count', wishlistCount);
    set('meta[name="csrf-token"]', Object.assign(flatNode(), { content: 'csrf-token' }));

    const toast = flatNode();
    const toastMessage = flatNode();
    toast.querySelector = (selector) => (selector === '[data-toast-message]' ? toastMessage : null);
    set('.toast', toast);

    const cardNodes = cards.map((card) => flatNode({ price: '100', stock: '5', category: 'Tas', priceLabel: 'Rp100', image: '/x.jpg', alt: 'x', description: 'd', color: 'c', ...card }));
    set('[data-product-card]', cardNodes);
    const toggles = cardNodes.map((card) => { const button = flatNode(); button.closest = (s) => (s === '[data-product-card]' ? card : null); return button; });
    const adds = cardNodes.map((card) => { const button = flatNode(); button.closest = (s) => (s === '[data-product-card]' ? card : null); return button; });
    set('[data-toggle-wishlist]', toggles);
    set('[data-card-add]', adds);
    for (const [selector, nodes] of Object.entries(extra)) set(selector, nodes);

    const document = flatNode();
    document.body = flatNode();
    document.activeElement = flatNode();
    document.visibilityState = 'visible';
    document.documentElement = flatNode();
    document.querySelector = (s) => elements.get(s)?.[0] ?? null;
    document.querySelectorAll = (s) => elements.get(s) ?? [];
    document.createElement = () => flatNode();
    document.getElementById = (id) => (id === 'zafira-app-copy' ? { textContent: JSON.stringify(copy) } : elements.get(`#${id}`)?.[0] ?? null);
    document.body.appendChild(toast);

    const window = flatNode();
    window.location = { href: `https://zafira.test/${search}`, search, origin: 'https://zafira.test' };
        if (search) window.requestIdleCallback = (callback) => callback();
    window.matchMedia = () => ({ matches: false });

    const values = new Map(Object.entries(localValues));
    const storageWrites = [];
    const localStorage = {
        getItem: (key) => values.get(key) ?? null,
        setItem(key, value) { storageWrites.push(key); values.set(key, String(value)); },
        removeItem(key) { storageWrites.push(`remove:${key}`); values.delete(key); },
    };
    class IntersectionObserver { observe() {} unobserve() {} }
    const timers = new Map();
    let timerId = 0;
    const context = {
        document, window, navigator: {}, localStorage, fetch: fetchImpl, initAdminTables() {}, searchMatches, IntersectionObserver,
        URL, URLSearchParams, history: { replaceState() {} }, AbortController,
        setTimeout(fn, ms) { timers.set(++timerId, { fn, ms }); return timerId; },
        clearTimeout(id) { timers.delete(id); },
    };
    vm.runInNewContext(appSource(), context);
    return {
        document, window, storefront, toast, toastMessage, wishlistCount, toggles, adds, cardNodes, values, storageWrites,
        status: elements.get('[data-cart-status]')[0],
        get: (s) => elements.get(s)?.[0],
        pressed: () => toggles.map((button) => button.attrs['aria-pressed']),
    };
}

/** Auto-answering fake of the account/cart API for favorites tests. */
function fakeShopServer({ favorites = [], failFavorites = 0, slugs = {} } = {}) {
    const calls = [];
    const favorited = new Set(favorites);
    const answer = (status, data) => ({ ok: status < 400, status, json: async () => data });
    const fetch = async (url, init = {}) => {
        const method = init.method || 'GET';
        const body = init.body ? JSON.parse(init.body) : null;
        calls.push({ url: String(url), method, body, headers: init.headers || {} });
        if (url === '/cart') return answer(200, { items: [], subtotal: 0 });
        if (url === '/favorites' && method === 'GET') {
            return answer(200, { favorites: [...favorited].map((id) => ({ product_id: id, product: { product_id: id, product_slug: slugs[id] } })) });
        }
        if (url === '/favorites' && method === 'POST') {
            if (failFavorites) return answer(failFavorites, { message: 'SQLSTATE internal boom' });
            favorited.add(body.product_id);
            return answer(200, { favorite: { product_id: body.product_id } });
        }
        const removal = String(url).match(/^\/favorites\/(\d+)$/);
        if (removal && method === 'DELETE') {
            if (failFavorites) return answer(failFavorites, { message: 'SQLSTATE internal boom' });
            favorited.delete(Number(removal[1]));
            return answer(200, { ok: true });
        }
        return answer(404, {});
    };
    return { fetch, calls, favorited, favoriteCalls: () => calls.filter((c) => c.url.startsWith('/favorites')) };
}

/** Manually-answered fetch: each request waits until the test calls respond(). */
function manualFetch() {
    const requests = [];
    const fetch = (url, init = {}) => new Promise((resolve, reject) => {
        const request = {
            url: String(url), method: init.method || 'GET', body: init.body ? JSON.parse(init.body) : null, done: false,
            respond(status, data) { request.done = true; resolve({ ok: status < 400, status, json: async () => data }); },
            fail(error) { request.done = true; reject(error); },
        };
        requests.push(request);
    });
    return { fetch, requests, pending: () => requests.filter((r) => !r.done) };
}

const TWO_CARDS = [
    { id: 'robe', productId: '11', name: 'Robe Sutra', category: 'Busana' },
    { id: 'bag', productId: '22', name: 'Tas Kulit', category: 'Tas' },
];

// ---------------------------------------------------------------------------------------------------------
// UI-01 — canonical storefront must not inherit the prototype's docked viewport
// ---------------------------------------------------------------------------------------------------------

function parseCssRules(css) {
    const source = css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/@(import|charset|namespace)[^;]*;/g, '');
    const rules = [];
    let i = 0;
    while (i < source.length) {
        const open = source.indexOf('{', i);
        if (open < 0) break;
        const head = source.slice(i, open).trim();
        let depth = 1;
        let j = open + 1;
        while (j < source.length && depth) {
            if (source[j] === '{') depth += 1;
            else if (source[j] === '}') depth -= 1;
            j += 1;
        }
        const body = source.slice(open + 1, j - 1);
        if (!head.startsWith('@')) rules.push({ selector: head, body });
        else if (/^@(media|layer|supports|container)\b/.test(head)) rules.push(...parseCssRules(body));
        i = j;
    }
    return rules;
}

/** Minimal cascade for the two root elements only: enough to answer "what does html/body compute to?". */
function rootCascade(viewPath, bodyClasses) {
    const view = read(viewPath);
    const sheets = [];
    const token = /<link[^>]+rel="stylesheet"[^>]*href="\{\{\s*asset\('([^']+)'\)\s*\}\}[^"]*"[^>]*>|@vite\(\[([^\]]*)\]\)|<style[^>]*>([\s\S]*?)<\/style>/g;
    for (const match of view.matchAll(token)) {
        if (match[1]) sheets.push(read(`public/${match[1]}`));
        else if (match[2]) for (const entry of match[2].matchAll(/'([^']+\.css)'/g)) sheets.push(read(entry[1]));
        else if (match[3]) sheets.push(match[3].replace(/\{\{[\s\S]*?\}\}/g, ''));
    }
    const computed = { html: {}, body: {} };
    let order = 0;
    for (const sheet of sheets) {
        for (const rule of parseCssRules(sheet)) {
            for (const raw of rule.selector.split(',')) {
                const selector = raw.trim();
                let target = null;
                let specificity = 0;
                if (/^(html|:root)$/.test(selector)) { target = 'html'; specificity = selector === ':root' ? 10 : 1; }
                const bodyMatch = selector.match(/^(?:html\s+)?body((?:\.[\w-]+)*)$/);
                if (bodyMatch) {
                    const classes = [...bodyMatch[1].matchAll(/\.([\w-]+)/g)].map((m) => m[1]);
                    if (classes.every((c) => bodyClasses.includes(c))) { target = 'body'; specificity = 1 + classes.length * 10 + (selector.startsWith('html') ? 1 : 0); }
                }
                if (!target) continue;
                for (const declaration of rule.body.split(';')) {
                    const split = declaration.indexOf(':');
                    if (split < 0) continue;
                    const property = declaration.slice(0, split).trim();
                    let value = declaration.slice(split + 1).trim();
                    const important = /!important\s*$/.test(value);
                    value = value.replace(/!important\s*$/, '').trim();
                    order += 1;
                    const previous = computed[target][property];
                    const weight = [important ? 1 : 0, specificity, order];
                    const wins = !previous || weight[0] > previous.weight[0] || (weight[0] === previous.weight[0] && (weight[1] > previous.weight[1] || (weight[1] === previous.weight[1] && weight[2] > previous.weight[2])));
                    if (wins) computed[target][property] = { value, weight };
                }
            }
        }
    }
    const flat = (bucket) => Object.fromEntries(Object.entries(bucket).map(([k, v]) => [k, v.value]));
    return { html: flat(computed.html), body: flat(computed.body) };
}

for (const [label, view, bodyClasses] of [
    ['home (welcome)', 'resources/views/welcome.blade.php', ['storefront']],
    ['catalog (/koleksi)', 'resources/views/catalog/index.blade.php', ['catalog-page']],
]) {
    test(`UI-01: ${label} computes a full-viewport root, not the prototype dock band`, () => {
        const { html, body } = rootCascade(view, bodyClasses);
        for (const property of ['overflow', 'overflow-y']) {
            assert.doesNotMatch(html[property] ?? 'visible', /\b(hidden|clip)\b/, `html ${property} must not clip the canonical page (window must stay the scroller for the header's window.scrollY logic)`);
        }
        assert.match(html['--dock-band'] ?? '0px', /^0(px)?$/, 'canonical pages reserve no dock band');
        assert.doesNotMatch(body.height ?? 'auto', /dock-band|calc\(\s*100d?v?h\s*-/, 'body height must not be viewport minus a dock band');
    });
}

test('UI-01 [baseline]: prototype keeps its own dock band and body scroller (other lane; must not be weakened to pass UI-01)', () => {
    const css = read('prototype/app.css');
    assert.match(css, /html\s*\{[^}]*--dock-band:\s*\d+px;[^}]*overflow:\s*hidden;/s);
    assert.match(css, /body\s*\{[^}]*height:\s*calc\(100dvh - var\(--dock-band\)\);[^}]*overflow-y:\s*auto;/s);
});

test('UI-01 [baseline]: brand identity survives (Bodoni Moda + Manrope fonts, Sapphire/Linen/Gold/Ivory tokens)', () => {
    for (const view of ['resources/views/welcome.blade.php', 'resources/views/catalog/index.blade.php']) {
        const blade = read(view);
        assert.match(blade, /bodoni-moda/i, `${view} keeps Bodoni Moda`);
        assert.match(blade, /manrope/i, `${view} keeps Manrope`);
    }
    const catalog = read('resources/views/catalog/index.blade.php');
    for (const token of ['--zafira-sapphire', '--zafira-linen', '--zafira-gold', '--zafira-ivory']) assert.match(catalog, new RegExp(token));
    assert.match(read('resources/css/app.css'), /--sapphire:\s*#[0-9a-f]{6}/i);
});

// ---------------------------------------------------------------------------------------------------------
// UI-03 — favorites: ONE source of truth for signed-in users, explicit local policy for guests
// CONTRACT: signed-in pages expose the canonical endpoint as data-favorites-url on the .storefront element
// (emitted only for authenticated users). Absent attribute == guest == device-local favorites.
// CONTRACT: no automatic merge of device-local favorites into the account (audit: "jangan merge tanpa kebijakan").
// ---------------------------------------------------------------------------------------------------------

const WISHLIST_KEY = 'zafira-wishlist:v1';
const favoritesBoot = (server, { local, signedIn = true } = {}) => appHarness({
    fetchImpl: server.fetch,
    cards: TWO_CARDS,
    dataset: signedIn ? { favoritesUrl: '/favorites' } : {},
    localValues: local ? { [WISHLIST_KEY]: JSON.stringify(local) } : {},
});

test('UI-03: signed-in storefront hydrates favorites from the account API once and ignores device-local slugs', async () => {
    const server = fakeShopServer({ favorites: [11], slugs: { 11: 'robe', 22: 'bag' } });
    const h = favoritesBoot(server, { local: ['bag'] });
    await flush();
    assert.equal(server.favoriteCalls().filter((c) => c.method === 'GET').length, 1, 'exactly one GET /favorites on load');
    assert.deepEqual(h.pressed(), ['true', 'false'], 'robe is a server favorite; bag exists only in this device\'s localStorage');
    assert.equal(String(h.wishlistCount.textContent), '1');
    assert.deepEqual(server.favoriteCalls().filter((c) => c.method !== 'GET'), [], 'local favorites are never auto-posted (no silent merge)');
    assert.equal(h.values.get(WISHLIST_KEY), JSON.stringify(['bag']), 'local list is left byte-for-byte untouched');
});

test('UI-03: signed-in add/remove go through POST /favorites and DELETE /favorites/{id}, never localStorage', async () => {
    const server = fakeShopServer({ favorites: [11], slugs: { 11: 'robe', 22: 'bag' } });
    const h = favoritesBoot(server);
    await flush();
    h.toggles[1].fire('click');
    await flush();
    const post = server.favoriteCalls().find((c) => c.method === 'POST');
    assert.ok(post, 'adding a favorite must call POST /favorites');
    assert.deepEqual(post.body, { product_id: 22 }, 'numeric product_id, not the slug');
    assert.equal(post.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.deepEqual(h.pressed(), ['true', 'true']);
    assert.equal(String(h.wishlistCount.textContent), '2');

    h.toggles[0].fire('click');
    await flush();
    assert.ok(server.favoriteCalls().some((c) => c.method === 'DELETE' && c.url === '/favorites/11'), 'removing calls DELETE /favorites/11');
    assert.deepEqual(h.pressed(), ['false', 'true']);
    assert.equal(String(h.wishlistCount.textContent), '1');
    assert.deepEqual(h.storageWrites.filter((key) => key.includes('wishlist')), [], 'signed-in toggles never touch localStorage');
});

test('UI-03: a failed server write leaves the heart unchanged and says so, without leaking server detail', async () => {
    for (const failure of [500, 401]) {
        const server = fakeShopServer({ favorites: [], failFavorites: failure, slugs: { 22: 'bag' } });
        const h = favoritesBoot(server);
        await flush();
        h.toggles[1].fire('click');
        await flush();
        assert.deepEqual(h.pressed(), ['false', 'false'], `HTTP ${failure}: no phantom favorite`);
        assert.equal(String(h.wishlistCount.textContent), '0');
        assert.notEqual(h.toastMessage.textContent, '', `HTTP ${failure}: the user gets feedback`);
        assert.doesNotMatch(h.toastMessage.textContent, /disimpan dalam favorit|SQLSTATE|boom/i);
        if (failure === 401) assert.match(h.toastMessage.textContent, /masuk|sesi|login/i, 'expired session tells the user to sign in again');
        assert.deepEqual(h.storageWrites.filter((key) => key.includes('wishlist')), [], 'failure does not fall back to localStorage silently');
    }
});

test('UI-03 [baseline]: guest favorites stay on this device and make no favorites API call', async () => {
    const server = fakeShopServer();
    const h = favoritesBoot(server, { local: ['bag'], signedIn: false });
    await flush();
    assert.deepEqual(server.favoriteCalls(), []);
    assert.deepEqual(h.pressed(), ['false', 'true']);
    h.toggles[0].fire('click');
    await flush();
    assert.deepEqual(server.favoriteCalls(), []);
    assert.equal(h.values.get(WISHLIST_KEY), JSON.stringify(['bag', 'robe']));
});

test('UI-03: the guest toast states the local-only policy and how to keep favorites (explicit, not implied)', async () => {
    const server = fakeShopServer();
    const h = favoritesBoot(server, { signedIn: false });
    await flush();
    h.toggles[0].fire('click');
    assert.match(h.toastMessage.textContent, /perangkat ini/i, 'tells the guest the favorite lives on this device only');
    assert.match(h.toastMessage.textContent, /masuk/i, 'points to signing in to keep favorites in the account');
});

test('UI-03: Blade marks the storefront with the canonical favorites endpoint for signed-in users only', () => {
    for (const view of ['resources/views/welcome.blade.php', 'resources/views/products/show.blade.php']) {
        const body = read(view).match(/<body\b(?:\{\{[\s\S]*?\}\}|[^>])*>/s)?.[0];
        assert.ok(body, `${view} has a body.storefront`);
        assert.match(body, /data-favorites-url="\{\{\s*route\('favorites\.index'\)\s*\}\}"/, `${view} must expose route('favorites.index')`);
        const attributeAt = body.indexOf('data-favorites-url');
        assert.match(body.slice(0, attributeAt), /@auth|@if\s*\(\s*auth\(\)->check\(\)\s*\)/, `${view}: the attribute is emitted only inside an @auth guard`);
    }
});

test('UI-03: rapid account toggles serialize; a failed hydration never silently uses local favorites', async () => {
    const net = manualFetch();
    const h = appHarness({ fetchImpl: net.fetch, cards: TWO_CARDS, dataset: { favoritesUrl: '/favorites' }, localValues: { [WISHLIST_KEY]: JSON.stringify(['robe']) } });
    await flush();
    net.pending().find((r) => r.url === '/cart').respond(200, { items: [], subtotal: 0 });
    net.pending().find((r) => r.url === '/favorites').respond(500, { message: 'SQLSTATE secret' });
    await flush();
    assert.deepEqual(h.pressed(), ['false', 'false']);
    assert.equal(h.values.get(WISHLIST_KEY), JSON.stringify(['robe']));
    h.toggles[0].fire('click');
    h.toggles[0].fire('click');
    await flush();
    assert.equal(net.pending().length, 1);
    assert.equal(net.pending()[0].method, 'GET', 'retry reconciles before a mutation');
    net.pending()[0].respond(200, { favorites: [] });
    await flush();
    assert.equal(net.pending().length, 1);
    assert.equal(net.pending()[0].method, 'POST');
    net.pending()[0].respond(200, { favorite: { product_id: 11 } });
    await flush();
    assert.deepEqual(h.pressed(), ['true', 'false']);
    assert.equal(net.pending().length, 1);
    assert.equal(net.pending()[0].method, 'DELETE');
    net.pending()[0].respond(200, { ok: true });
    await flush();
    assert.deepEqual(h.pressed(), ['false', 'false']);
    assert.deepEqual(h.storageWrites.filter((key) => key.includes('wishlist')), []);
});

test('UI-03: account favorites include products outside displayed cards and refresh on return to the tab', async () => {
    const server = fakeShopServer({ favorites: [99] });
    const trigger = flatNode();
    const h = appHarness({ fetchImpl: server.fetch, cards: TWO_CARDS, dataset: { favoritesUrl: '/favorites', favoritesPage: '/belanja#favorit' }, extra: { '[data-show-wishlist]': trigger } });
    await flush();
    assert.equal(String(h.wishlistCount.textContent), '1');
    trigger.fire('click');
    assert.equal(h.window.location.href, '/belanja#favorit', 'full account list, not partial card filtering');
    server.favorited.add(22);
    h.document.fire('visibilitychange');
    await flush();
    assert.deepEqual(h.pressed(), ['false', 'true']);
    assert.equal(String(h.wishlistCount.textContent), '2');
});

test('UI-03: favorites copy exists in every locale', () => {
    assertLangKeys('app_js', ['wish_saved_local', 'wish_failed']);
});

// ---------------------------------------------------------------------------------------------------------
// UI-04 — search must never present a partial card index as a complete result
// ---------------------------------------------------------------------------------------------------------

test('UI-04: PDP search panel never claims results (or "none found") from the ≤3 related cards on the page', async () => {
    const footer = read('resources/views/partials/product-footer.blade.php');
    const input = flatNode();
    const results = flatNode();
    const statusNode = flatNode();
    const extra = { '[data-search-input]': [input] };
    if (/data-search-results/.test(footer)) extra['[data-search-results]'] = [results];
    if (/data-search-status/.test(footer)) extra['[data-search-status]'] = [statusNode];
    // PDP of "Chemise Riviere": the page only carries one related card.
    appHarness({ fetchImpl: manualFetch().fetch, cards: [{ id: 'robe-aurore', productId: '31', name: 'Robe Aurore', category: 'Busana' }], extra });
    for (const query of ['chemise', 'robe']) {
        input.value = query;
        input.fire('input');
        assert.equal(results.innerHTML, '', `"${query}": no in-page result list built from related cards`);
        assert.equal(statusNode.textContent, '', `"${query}": no status claim from a partial index`);
    }
});

test('UI-04: PDP search is a real GET to the canonical catalog search (/koleksi?q=…)', () => {
    const footer = read('resources/views/partials/product-footer.blade.php');
    const form = footer.match(/<form\b[^>]*role="search"[^>]*>/)?.[0];
    assert.ok(form, 'PDP search form exists');
    assert.match(form, /method="get"/i);
    assert.match(form, /action="\{\{\s*route\('catalog\.index'\)\s*\}\}"/);
    assert.doesNotMatch(form, /onsubmit=/, 'no preventDefault: Enter must navigate');
    const field = footer.match(/<input\b[^>]*data-search-input[^>]*>/)?.[0] ?? footer.match(/<input\b[^>]*type="search"[^>]*>/)?.[0];
    assert.ok(field, 'search input exists');
    assert.match(field, /name="q"/);
    assert.match(field, /maxlength="100"/, 'matches CatalogFilterRequest q max:100');
});

test('UI-04: PDP suggestion chips lead to catalog results instead of filtering the partial in-page index', () => {
    const footer = read('resources/views/partials/product-footer.blade.php');
    // Blade-aware tag match: `=>` inside {{ route('catalog.index', ['q' => 'x']) }} must not end the tag early.
    const chips = [...footer.matchAll(/<(a|button)\b(?:\{\{[\s\S]*?\}\}|[^>])*data-search-term(?:\{\{[\s\S]*?\}\}|[^>])*>/g)];
    assert.ok(chips.length >= 1, 'suggestions still exist');
    for (const [element, tag] of chips) {
        assert.equal(element.startsWith('<a'), true, `chip must be a link, found <${tag}>`);
        assert.match(element, /href="\{\{\s*route\('catalog\.index'/);
    }
});

test('R1: raw Blade PHP blocks cannot swallow loop directives on home/PDP', () => {
    for (const view of ['welcome', 'products/show']) {
        const source = read(`resources/views/${view}.blade.php`);
        // Matches the verified storePhpBlocks() regex, not a pretend full Blade compiler.
        const compiledSurface = source.replace(/(?<!@)@php([\s\S]*?)@endphp/g, (block, php) => {
            assert.doesNotMatch(php, /@(foreach|endforeach|if|endif|auth|endauth)\b/, `${view}: inline @php captured Blade markup`);
            return 'PHP-BLOCK';
        });
        assert.equal([...compiledSurface.matchAll(/@foreach\b/g)].length, [...compiledSurface.matchAll(/@endforeach\b/g)].length, `${view}: loop directives remain balanced outside PHP blocks`);
    }
});

test('R1: bounded home category cards/pills and inline search navigate to full catalog, with a persistent view-all exit', () => {
    const home = read('resources/views/welcome.blade.php');
    assert.doesNotMatch(home, /data-filter=|data-category-link=/, 'bounded featured cards must not be treated as the category result set');
    assert.match(home, /route\('catalog.index', \['kategori' => 'tas-tangan'\]\)/);
    assert.match(home, /route\('catalog.index', \['category' => \$category->cat_id\]\)/);
    const inlineForm = home.match(/<form\b[^>]*id="home-catalog-search"[^>]*>/)?.[0];
    assert.ok(inlineForm);
    assert.match(inlineForm, /method="get"/);
    assert.match(inlineForm, /action="\{\{ route\('catalog.index'\) \}\}"/);
    assert.doesNotMatch(inlineForm, /onsubmit=/);
    assert.match(home, /id="home-catalog-query"[^>]*name="q"[^>]*maxlength="100"/);
    assert.doesNotMatch(home, /id="live-catalog-search-input"/);
    assert.match(home, /href="\{\{ route\('catalog.index'\) \}\}"[^>]*data-view-all/);
    assert.match(home, /shop_ui.welcome.featured_note/);
    assert.match(home, /class="empty-products"[^>]*>[\s\S]*?shop_ui.welcome.featured_empty/);
});

test('R1: legacy home query params cannot filter bounded featured cards into a false empty result', async () => {
    const net = fakeShopServer();
    const h = appHarness({ fetchImpl: net.fetch, cards: TWO_CARDS, dataset: { catalogUrl: '/koleksi' }, search: '?kategori=Missing&cari=not-in-featured' });
    await flush();
    assert.deepEqual(h.cardNodes.map((card) => card.hidden), [false, false]);
    assert.deepEqual(net.calls.map((call) => call.url), ['/cart'], 'no full-catalog hydration to repair the bounded selection');
});

test('R1: localized bounded-home, cancel policy, admin refresh and recovery copy cover all five locales', () => {
    assertLangKeys('welcome', ['featured_note', 'featured_empty', 'view_all']);
    assertLangKeys('shop', ['paid_cancel_unavailable', 'contact_concierge']);
    for (const locale of LOCALES) {
        const admin = read(`lang/${locale}/admin_ui.php`);
        assert.match(admin, /'saved_refresh_failed' =>\s*'[^']+'/);
        for (const file of ['admin_ui', 'account_ui']) {
            const source = read(`lang/${locale}/${file}.php`);
            const recovery = source.slice(source.indexOf("'recovery' =>"));
            assert.match(recovery.match(/'intro' => '([^']+)'/)?.[1] || '', /5/, `${locale}/${file}: one-time page opening TTL is explained`);
        }
    }
});

test('R1: category suggestions use kategori; product-text suggestions retain q', () => {
    for (const view of ['welcome', 'partials/product-footer']) {
        const source = read(`resources/views/${view}.blade.php`);
        assert.match(source, /route\('catalog.index', \['kategori' => 'tas-tangan'\]\)/);
        assert.doesNotMatch(source, /\['q' => 'Tas Tangan'\]/);
        assert.match(source, /\['q' => 'Saphir'\]/);
        assert.match(source, /\['urut' => 'terbaru'\]/);
    }
});

test('UI-04: home header searches the full catalog even when its displayed cards are bounded', () => {
    const home = read('resources/views/welcome.blade.php');
    const form = home.match(/<form\b[^>]*id="catalog-header-search"[^>]*>/)?.[0];
    assert.ok(form);
    assert.match(form, /method="get"/i);
    assert.match(form, /action="\{\{\s*route\('catalog\.index'\)\s*\}\}"/);
    assert.doesNotMatch(form, /onsubmit=/);
    assert.doesNotMatch(home, /data-search-results|data-search-status/);
    const field = home.match(/<input\b[^>]*data-search-input[^>]*>/)?.[0];
    assert.match(field, /name="q"/);
    assert.match(field, /maxlength="100"/);
});

// ---------------------------------------------------------------------------------------------------------
// UI-05 — language switch: reload only after the server persisted the choice
// CONTRACT: server-rendered copy (cart, concierge, app-copy blob) cannot be re-translated client-side, so after the
// write queue drains successfully the page reloads (location.reload / replace / assign). No data-locale-url => no reload.
// ---------------------------------------------------------------------------------------------------------

function bootLocale({ locale = 'fr', fetcher = async () => ({ ok: true }), endpoint = true } = {}) {
    const source = read('public/js/zafira-core.js');
    const attrs = { 'data-server-locale': locale };
    if (endpoint) attrs['data-locale-url'] = '/locale';
    const listeners = {};
    const store = { zafira_lang: 'en' };
    const statusNode = { textContent: '', setAttribute(k, v) { this[k] = v; } };
    const document = {
        readyState: 'loading',
        documentElement: { getAttribute: (key) => attrs[key], setAttribute: (key, value) => { attrs[key] = value; } },
        body: { setAttribute(k, v) { this[k] = v; }, classList: { contains: (c) => c === 'storefront' } }, head: { appendChild() {} },
        createElement: () => ({}),
        querySelector: (selector) => (selector.includes('csrf-token') ? { content: 'test-csrf' } : null),
        querySelectorAll: () => [],
        getElementById: (id) => (id === 'locale-status' ? statusNode : null),
        addEventListener: (type, fn) => { listeners[type] = fn; },
    };
    const calls = [];
    const navigations = [];
    let api;
    const record = (kind) => () => navigations.push({ kind, href: window.location.href, stored: store.zafira_lang, lang: api.getLanguage(), writes: calls.length });
    const window = {
        dispatchEvent() {},
        location: { href: 'https://shop.test/?locale=fr#collection', reload: record('reload'), replace: record('replace'), assign: record('assign') },
        history: { replaceState(_s, _t, url) { window.location.href = String(url); } },
    };
    vm.runInNewContext(source, {
        document, window, URL, fetch: (...args) => { calls.push(args); return fetcher(...args); },
        localStorage: { getItem: (key) => store[key] ?? null, setItem: (key, value) => { store[key] = value; } },
        CustomEvent: function () {}, setTimeout, clearTimeout,
    });
    listeners.DOMContentLoaded();
    api = window.Zafira;
    return { api, calls, navigations, store, status: statusNode, window };
}

test('UI-05: no reload on initial load (server locale already wins)', () => {
    assert.equal(bootLocale({ locale: 'it' }).navigations.length, 0);
});

test('UI-05: after the POST /locale succeeds, the page reloads once with the choice persisted and ?locale stripped', async () => {
    const r = bootLocale({ locale: 'fr' });
    await r.api.setLanguage('it');
    await flush();
    assert.equal(r.navigations.length, 1, 'exactly one navigation');
    const [nav] = r.navigations;
    assert.equal(nav.writes, 1);
    assert.equal(nav.stored, 'it', 'localStorage already holds the confirmed locale when the page reloads');
    assert.equal(nav.lang, 'it');
    assert.equal(new URL(nav.href).searchParams.has('locale'), false, 'stale ?locale= must not survive into the reload');
    assert.equal(new URL(nav.href).hash, '#collection');
});

test('UI-05: HTTP and transport failures never reload and keep the confirmed locale with an accessible error', async () => {
    for (const fetcher of [async () => ({ ok: false }), async () => { throw Error('offline'); }]) {
        const r = bootLocale({ locale: 'fr', fetcher });
        await r.api.setLanguage('it');
        await flush();
        assert.equal(r.navigations.length, 0);
        assert.equal(r.api.getLanguage(), 'fr');
        assert.ok(r.status.textContent, 'locale-status explains the failure');
    }
});

test('UI-05: rapid choices serialize — no reload while a newer write is pending; one reload after the last confirmed write', async () => {
    const pending = [];
    const r = bootLocale({ locale: 'fr', fetcher: () => new Promise((resolve) => pending.push(resolve)) });
    r.api.setLanguage('en');
    r.api.setLanguage('it');
    pending.shift()({ ok: true });
    await flush();
    assert.equal(r.calls.length, 2, 'second write starts only after the first resolved');
    assert.equal(r.navigations.length, 0, 'older confirmation must not reload while a newer choice is still being saved');
    pending.shift()({ ok: true });
    await flush();
    await flush();
    assert.equal(r.navigations.length, 1);
    assert.equal(r.navigations[0].stored, 'it');
    assert.equal(r.navigations[0].writes, 2);
});

test('UI-05: older write confirmed + newer write failed => never reloads before the queue drains, and never into the failed locale', async () => {
    const pending = [];
    const r = bootLocale({ locale: 'fr', fetcher: () => new Promise((resolve) => pending.push(resolve)) });
    r.api.setLanguage('en');
    r.api.setLanguage('it');
    pending.shift()({ ok: true });
    await flush();
    assert.equal(r.navigations.length, 0);
    pending.shift()({ ok: false });
    await flush();
    await flush();
    assert.equal(r.api.getLanguage(), 'en', 'falls back to the last locale the server accepted');
    for (const nav of r.navigations) assert.equal(nav.lang, 'en');
});

test('UI-05 [baseline]: without data-locale-url (offline/prototype shell) the switch applies in place, with no request and no reload', async () => {
    const r = bootLocale({ locale: 'fr', endpoint: false });
    await r.api.setLanguage('it');
    await flush();
    assert.equal(r.calls.length, 0);
    assert.equal(r.navigations.length, 0);
    assert.equal(r.api.getLanguage(), 'it');
});

// ---------------------------------------------------------------------------------------------------------
// UI-07 — catalog "set quantity" forms: serial mutations, final state follows the last accepted intent
// The Blade inline script is executed as shipped. Either a serial queue (latest-wins coalescing allowed) or a pending
// guard that disables the control is accepted; a design that lets two SET requests overlap is not.
// ---------------------------------------------------------------------------------------------------------

function catalogScript() {
    const blade = read('resources/views/catalog/index.blade.php');
    const scripts = [...blade.matchAll(/<script(?![^>]*type=)[^>]*>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
    const code = scripts.find((script) => script.includes('catalog-quick-dialog'));
    assert.ok(code, 'catalog view keeps its inline cart/favorite script');
    return code
        .replace(/@json\(\$t\['(\w+)'\]\)/g, (_, key) => JSON.stringify(`[${key}]`))
        .replace(/\{\{\s*url\('([^']+)'\)\s*\}\}/g, '$1');
}

function catalogHarness({ forms = 2 } = {}) {
    const byId = {};
    const get = (id) => (byId[id] ??= flatNode());
    byId['catalog-quick-dialog'] = Object.assign(flatNode(), { open: false, showModal() { this.open = true; }, close() { this.open = false; }, getBoundingClientRect: () => ({ top: 0, left: 0, width: 0, height: 0 }) });
    byId['catalog-filters'] = Object.assign(flatNode({ activeCount: '0' }), { open: false });
    byId['catalog-purchase-copy'] = Object.assign(flatNode(), { textContent: '{}' });
    const feedback = get('catalog-feedback');
    const cartForms = Array.from({ length: forms }, (_, index) => {
        const form = flatNode();
        form.action = '/cart/items';
        form.fields = { product_id: String(7 + index), quantity: '1' };
        const button = Object.assign(flatNode(), { type: 'submit' });
        const quantity = Object.assign(flatNode(), { name: 'quantity' });
        form.controls = [button, quantity];
        form.elements = form.controls;
        form.querySelector = (selector) => (/quantity|number/.test(selector) ? quantity : button);
        form.querySelectorAll = () => form.controls;
        return form;
    });
    const document = {
        getElementById: get,
        querySelector: (selector) => (selector === 'meta[name="csrf-token"]' ? { content: 'csrf-token' } : null),
        querySelectorAll: (selector) => (selector === 'form[action$="/cart/items"]' ? cartForms : []),
    };
    const requests = [];
    let inFlight = 0;
    let maxInFlight = 0;
    const fetch = (url, init = {}) => new Promise((resolve) => {
        inFlight += 1;
        maxInFlight = Math.max(maxInFlight, inFlight);
        const request = {
            url, method: init.method, body: JSON.parse(init.body), done: false,
            respond(status = 200, data = {}) { request.done = true; inFlight -= 1; resolve({ ok: status < 400, status, json: async () => data }); },
        };
        requests.push(request);
    });
    class FormDataStub { constructor(form) { this.form = form; } get(key) { return this.form.fields[key] ?? null; } }
    vm.runInNewContext(catalogScript(), { document, window: { matchMedia: () => ({ matches: false, addEventListener() {} }), location: { origin: 'https://zafira.test' } }, fetch, FormData: FormDataStub, URL });
    return {
        forms: cartForms, feedback, requests,
        inFlight: () => inFlight,
        maxInFlight: () => maxInFlight,
        /** What a real user can do: a disabled control cannot be submitted. */
        userSubmit(form, quantity) {
            if (form.controls.some((control) => control.disabled)) return false;
            form.fields.quantity = String(quantity);
            form.fire('submit', { preventDefault() {}, target: form });
            return true;
        },
        async drain(order = 'fifo', status = 200) {
            for (let guard = 0; guard < 12; guard += 1) {
                await flush();
                const waiting = requests.filter((r) => !r.done);
                if (!waiting.length) return;
                (order === 'lifo' ? waiting.at(-1) : waiting[0]).respond(status);
            }
        },
    };
}

const successFor = (quantity) => new RegExp(`Jumlah ${quantity} menggantikan`);
const isSubsequence = (sent, accepted) => { let at = 0; for (const value of sent) { at = accepted.indexOf(value, at); if (at < 0) return false; at += 1; } return true; };

test('UI-07: rapid SET submissions on one product never overlap; the last accepted quantity is the final request and message', async () => {
    const h = catalogHarness();
    const accepted = [2, 3, 5].filter((quantity) => h.userSubmit(h.forms[0], quantity));
    await flush();
    assert.ok(h.inFlight() <= 1, `at most one SET request in flight, got ${h.inFlight()}`);
    h.requests[0].respond(200);
    await flush();
    const last = accepted.at(-1);
    if (accepted.length > 1) assert.doesNotMatch(h.feedback.textContent, successFor(accepted[0]), 'a stale success must not be shown while a newer intent is unresolved');
    await h.drain();
    assert.ok(h.maxInFlight() <= 1, 'never more than one in flight');
    const sent = h.requests.map((r) => r.body.quantity);
    assert.equal(sent.at(-1), last, 'last request carries the last accepted intent');
    assert.ok(isSubsequence(sent, accepted), `requests ${JSON.stringify(sent)} keep submission order ${JSON.stringify(accepted)}`);
    assert.match(h.feedback.textContent, successFor(last));
});

test('UI-07: SET submissions for different products cannot finish out of order and leave a stale message', async () => {
    const h = catalogHarness();
    const accepted = [[0, 2], [1, 1]].filter(([form, quantity]) => h.userSubmit(h.forms[form], quantity)).map(([, quantity]) => quantity);
    await h.drain('lifo');
    assert.ok(h.maxInFlight() <= 1);
    assert.match(h.feedback.textContent, successFor(accepted.at(-1)), 'the visible message belongs to the most recent submission');
    assert.doesNotMatch(h.feedback.textContent, successFor(accepted[0] === accepted.at(-1) ? 0 : accepted[0]));
});

test('UI-07 [baseline]: a failed last SET never reports success', async () => {
    const h = catalogHarness();
    h.userSubmit(h.forms[0], 2);
    await flush();
    h.requests[0].respond(200);
    await flush();
    h.userSubmit(h.forms[0], 4);
    await h.drain('fifo', 500);
    assert.notEqual(h.feedback.textContent, '');
    assert.doesNotMatch(h.feedback.textContent, /menggantikan/);
});

// ---------------------------------------------------------------------------------------------------------
// UI-08 — shop requests: bounded time (headers AND body); a timeout keeps the checkout idempotency key
// CONTRACT: shopRequest uses setTimeout + AbortController (same primitives as cartRequest), arms the timer before
// fetch, keeps it armed until the body is parsed, and rejects with Error{timeout:true} and NO .status.
// ---------------------------------------------------------------------------------------------------------

const abortError = () => Object.assign(new Error('The operation was aborted'), { name: 'AbortError' });
const abortsWith = (signal, reject) => signal?.addEventListener('abort', () => reject(abortError()));

function loadShopRequest({ fetchImpl, copy = {} }) {
    const code = [...read('resources/views/shop/request.blade.php').matchAll(/<script(?![^>]*type=)[^>]*>([\s\S]*?)<\/script>/g)][0][1];
    const timers = [];
    const window = {};
    const context = {
        window,
        document: { getElementById: () => ({ textContent: JSON.stringify(copy) }), querySelector: () => ({ content: 'csrf-token' }) },
        fetch: fetchImpl, AbortController,
        setTimeout(fn, ms) { const timer = { id: timers.length + 1, fn, ms, cleared: false, fired: false }; timers.push(timer); return timer.id; },
        clearTimeout(id) { const timer = timers.find((t) => t.id === id); if (timer) timer.cleared = true; },
    };
    vm.runInNewContext(code, context);
    const armed = () => timers.filter((t) => !t.cleared && !t.fired);
    return { request: window.shopRequest, timers, armed, fire(timer) { timer.fired = true; timer.fn(); } };
}

test('UI-08: every shop request is bounded — signal passed, 5s–60s timer armed, and cleared after success', async () => {
    let options;
    const shop = loadShopRequest({ fetchImpl: async (_url, init) => { options = init; return { ok: true, status: 200, json: async () => ({ fine: true }) }; } });
    assert.deepEqual(await shop.request('/x'), { fine: true });
    assert.ok(options?.signal && typeof options.signal.aborted === 'boolean', 'fetch receives an AbortSignal');
    assert.ok(shop.timers.length >= 1, 'a timeout is armed');
    for (const timer of shop.timers) assert.ok(timer.ms >= 5000 && timer.ms <= 60000, `timeout ${timer.ms}ms is bounded`);
    assert.equal(shop.armed().length, 0, 'no timer leaks after completion');
});

test('UI-08: stalled response headers => friendly timeout error without status, outcome flagged as uncertain for writes', async () => {
    const shop = loadShopRequest({ fetchImpl: (_url, init) => new Promise((_, reject) => abortsWith(init.signal, reject)) });
    const pending = shop.request('/checkout', 'POST', { a: 1 });
    await flush();
    assert.ok(shop.armed().length >= 1, 'request must arm a timeout (setTimeout + AbortController)');
    shop.fire(shop.armed()[0]);
    const error = await pending.then(() => null, (e) => e);
    assert.ok(error, 'rejects');
    assert.equal(error.timeout, true);
    assert.equal(error.status, undefined, 'no HTTP status, so callers that only reset on 409/422 keep the idempotency key');
    assert.doesNotMatch(error.message, /abort/i, 'no raw browser error text');
    assert.match(error.message, /periksa/i, 'a write timeout tells the user the outcome is uncertain and to check Pesanan first');
});

test('UI-08: stalled response BODY after headers also times out (timer is not cleared at headers)', async () => {
    const shop = loadShopRequest({
        copy: { timeout: 'COPY-TIMEOUT' },
        fetchImpl: async (_url, init) => ({ ok: true, status: 200, json: () => new Promise((_, reject) => abortsWith(init.signal, reject)) }),
    });
    const pending = shop.request('/cart');
    await flush();
    assert.ok(shop.armed().length >= 1, 'timer still armed while the body is being read');
    shop.fire(shop.armed()[0]);
    const error = await pending.then(() => null, (e) => e);
    assert.equal(error?.timeout, true);
    assert.equal(error.message, 'COPY-TIMEOUT', 'translated copy key shop_ui.request.timeout is used when provided');
});

test('UI-08 [baseline]: non-timeout failures keep their existing mapping (network, 5xx, 429, validation)', async () => {
    const copy = { network: 'NET', server: 'SRV', 429: 'RATE' };
    const network = loadShopRequest({ copy, fetchImpl: async () => { throw new TypeError('socket detail'); } });
    const netError = await network.request('/x').then(() => null, (e) => e);
    assert.equal(netError.message, 'NET');
    assert.equal(netError.timeout, undefined);
    for (const [status, expected] of [[503, 'SRV'], [429, 'RATE']]) {
        const shop = loadShopRequest({ copy, fetchImpl: async () => ({ ok: false, status, json: async () => ({ message: 'internal' }) }) });
        const error = await shop.request('/x').then(() => null, (e) => e);
        assert.equal(error.message, expected);
        assert.equal(error.status, status);
    }
    const invalid = loadShopRequest({ fetchImpl: async () => ({ ok: false, status: 422, json: async () => ({ errors: { a: ['Satu.'], b: ['Dua.'] } }) }) });
    assert.equal((await invalid.request('/x').then(() => null, (e) => e)).message, 'Satu. Dua.');
});

test('UI-08: guest checkout that times out keeps the idempotency key and replays the SAME payload; controls are usable again', async () => {
    const index = read('resources/views/shop/index.blade.php');
    const code = [...index.matchAll(/<script(?![^>]*type=)[^>]*>([\s\S]*?)<\/script>/g)].map((m) => m[1]).find((s) => s.includes('const api = window.shopRequest'));
    assert.ok(code, 'shop index inline script found');
    const script = code
        .replace(/@json\(auth\(\)->check\(\)\)/g, 'false')
        .replace(/@json\(route\('([^']+)'\)\)/g, (_, name) => JSON.stringify(`/${name}`))
        .replace(/@json\(url\('([^']*)'\)\)/g, (_, path) => JSON.stringify(path));

    class El {
        constructor() { Object.assign(this, { children: [], attrs: {}, style: { setProperty() {} }, textContent: '', value: '', disabled: false, hidden: false, className: '', dataset: {}, handlers: {}, offsetHeight: 0, fields: {}, classList: { toggle() {}, add() {}, remove() {}, contains: () => false } }); }
        append(...c) { this.children.push(...c); } appendChild(c) { this.children.push(c); } replaceChildren(...c) { this.children = c; }
        setAttribute(k, v) { this.attrs[k] = String(v); } removeAttribute(k) { delete this.attrs[k]; } getAttribute(k) { return this.attrs[k] ?? null; }
        addEventListener(t, f) { (this.handlers[t] ??= []).push(f); } querySelectorAll() { return []; } querySelector() { return null; }
        remove() {} replaceWith() {} reset() {} focus() {} scrollIntoView() {} getBoundingClientRect() { return { top: 0, bottom: 0 }; }
    }
    const els = new Map();
    const el = (id) => { if (!els.has(id)) els.set(id, new El()); return els.get(id); };
    const form = el('guest-checkout-form');
    form.fields = { customer_name: 'Vivienne', customer_email: 'v@example.test', customer_phone: '08123', label: 'Rumah', recipient_name: 'V', phone: '08123', line1: 'Jl. A', city: 'Jakarta', province: 'DKI', postal_code: '12730' };
    const feedback = el('feedback');
    const guestButton = el('guest-checkout-button');
    const clearCart = el('clear-cart');
    const main = new El();
    // Models native submit gating before a submit event (not form.submit(), which bypasses validation).
    const browserSubmit = () => {
        const valid = Boolean(form.fields.customer_name?.trim()) && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.fields.customer_email);
        if (guestButton.disabled || (!guestButton.formNoValidate && !valid)) return false;
        form.onsubmit({ preventDefault() {}, target: form, submitter: guestButton });
        return true;
    };

    const timers = [];
    const calls = [];
    let checkoutPosts = 0;
    const cart = { items: [{ product_id: 7, product_title: 'Tas', unit_price: 100, quantity: 1, subtotal: 100, available: true }], subtotal: 100 };
    const fetch = (url, init = {}) => {
        const method = init.method || 'GET';
        const body = init.body ? JSON.parse(init.body) : null;
        calls.push({ url, method, body });
        const ok = (data) => Promise.resolve({ ok: true, status: 200, json: async () => data });
        if (url === '/cart.show') return ok(cart);
        if (url === '/checkout.store') {
            checkoutPosts += 1;
            if (checkoutPosts === 1) return new Promise((_, reject) => abortsWith(init.signal, reject));
            return ok({ order: { id: 9, status: 'pending', total: 100, items: [] } });
        }
        return ok({});
    };
    let uuid = 0;
    const window = { matchMedia: () => ({ matches: false }) };
    const context = {
        window, fetch, AbortController, crypto: { randomUUID: () => `uuid-${++uuid}` }, URL,
        FormData: class { constructor(f) { this.entries = Object.entries(f.fields); } [Symbol.iterator]() { return this.entries[Symbol.iterator](); } get(k) { return this.entries.find(([key]) => key === k)?.[1] ?? null; } },
        setTimeout(fn, ms) { const timer = { id: timers.length + 1, fn, ms, cleared: false, fired: false }; timers.push(timer); return timer.id; },
        clearTimeout(id) { const timer = timers.find((t) => t.id === id); if (timer) timer.cleared = true; },
        confirm: () => true,
        document: {
            documentElement: { style: { setProperty() {} } },
            getElementById: (id) => el(id),
            querySelector: (selector) => (selector === 'main' ? main : selector.includes('csrf') ? { content: 'csrf-token' } : el(selector)),
            querySelectorAll: () => [guestButton, clearCart],
            createElement: () => new El(),
        },
    };
    vm.createContext(context);
    vm.runInContext([...read('resources/views/shop/request.blade.php').matchAll(/<script(?![^>]*type=)[^>]*>([\s\S]*?)<\/script>/g)][0][1], context);
    vm.runInContext(script, context);
    await flush();
    await flush();
    assert.equal(calls.filter((c) => c.url === '/cart.show').length, 1, 'initial bag load');

    form.fields.customer_email = 'invalid-email';
    assert.equal(browserSubmit(), false, 'normal checkout keeps native email validation');
    assert.equal(calls.filter((c) => c.url === '/checkout.store').length, 0);
    form.fields.customer_email = 'v@example.test';
    assert.equal(browserSubmit(), true);
    await flush();
    await flush();
    const armed = timers.filter((t) => !t.cleared && !t.fired);
    assert.ok(armed.length >= 1, 'checkout POST must be bounded by a timeout');
    const firstPost = calls.find((c) => c.url === '/checkout.store');
    assert.ok(firstPost?.body.idempotency_key, 'first attempt carries an idempotency key');
    armed[0].fired = true;
    armed[0].fn();
    await flush();
    await flush();
    assert.equal(feedback.attrs.role, 'alert', 'timeout is announced as an alert');
    assert.match(feedback.textContent, /terlalu lama|periksa/i);
    assert.equal(guestButton.disabled, false, 'user can retry; controls are not left locked');

    const cartReads = calls.filter((c) => c.url === '/cart.show').length;
    form.fields.customer_name = '';
    form.fields.customer_email = 'invalid-email';
    assert.equal(guestButton.formNoValidate, true, 'only the pending replay bypasses native constraints');
    for (const listener of form.handlers.input) listener({ target: form });
    assert.match(feedback.textContent, /rincian pesanan semula/, 'edits cannot silently create a second uncertain order');
    clearCart.onclick();
    await flush();
    await flush();
    assert.equal(calls.filter((c) => c.method === 'DELETE').length, 0, 'cart mutations cannot discard an uncertain checkout');
    assert.equal(browserSubmit(), true, 'required/email invalid edits cannot block frozen-payload replay');
    await flush();
    await flush();
    assert.equal(guestButton.formNoValidate, false, 'normal validation is restored after confirmed success');
    assert.equal(browserSubmit(), false, 'a subsequent new checkout still validates edited inputs');
    const posts = calls.filter((c) => c.url === '/checkout.store');
    assert.equal(posts.length, 2);
    assert.equal(posts[1].body.idempotency_key, posts[0].body.idempotency_key, 'retry after timeout reuses the same key');
    assert.deepEqual(posts[1].body, posts[0].body, 'and the same payload');
    assert.equal(calls.filter((c) => c.url === '/cart.show').length, cartReads + 1, 'replay does not re-validate before POST (only the post-success reload reads the bag)');
});

test('UI-08: deadline rejects even a body reader that does not react to abort, and clears on all terminal outcomes', async () => {
    const stalled = loadShopRequest({ fetchImpl: async () => ({ ok: true, status: 200, json: () => new Promise(() => {}) }) });
    const waiting = stalled.request('/checkout', 'POST', {});
    await flush();
    stalled.fire(stalled.armed()[0]);
    assert.equal((await waiting.catch((error) => error)).timeout, true);
    assert.equal(stalled.armed().length, 0);
    for (const fetchImpl of [async () => { throw Error('offline'); }, async () => ({ ok: false, status: 403, json: async () => ({}) }), async () => ({ ok: true, status: 200, json: async () => { throw Error('bad JSON'); } })]) {
        const shop = loadShopRequest({ fetchImpl });
        const error = await shop.request('/checkout', 'POST', {}).catch((e) => e);
        assert.ok(error instanceof Error || error?.message);
        assert.equal(shop.armed().length, 0);
        if (error.status !== 403) assert.equal(error.status, undefined, 'uncertain outcomes are not confirmed checkout rejection');
    }
});

test('UI-08: timeout copy exists in every locale', () => {
    assertLangKeys('request', ['timeout']);
    assertLangKeys('shop', ['checkout_pending']);
});

// ---------------------------------------------------------------------------------------------------------
// UI-09 — cart errors must be actionable and safe; a network retry is not the answer to "stock exhausted"
// CONTRACT: recognized stock 422 => `err_stock`; malformed/other validation 422 => `err_bag`;
// 429 => `err_rate`; other 4xx => `err_bag`; the server's own message text is
// never reflected. Transport failures remain `err_network`.
// ---------------------------------------------------------------------------------------------------------

async function failAddToBag(status, payload, copy = {}) {
    const net = manualFetch();
    const h = appHarness({ fetchImpl: net.fetch, cards: [{ id: 'bag', productId: '22', name: 'Tas Kulit', category: 'Tas' }], copy });
    await flush();
    net.requests[0].respond(200, { items: [], subtotal: 0 });
    await flush();
    h.adds[0].fire('click');
    await flush();
    net.pending()[0].respond(200, { items: [], subtotal: 0 }); // fresh GET /cart before the mutation
    await flush();
    const mutation = net.pending()[0];
    assert.ok(mutation && mutation.method === 'POST', 'the delta mutation is sent');
    if (status === 'transport') mutation.fail(new TypeError('socket secret detail'));
    else mutation.respond(status, payload);
    await flush();
    return { status: h.status.textContent, toast: h.toastMessage.textContent, requests: net.requests.length };
}

test('UI-09: a 422 stock rejection says stock is insufficient and what to do — not "network problem" — and hides server internals', async () => {
    for (const payload of [
        { message: 'SQLSTATE[23000] /var/www/app/Http/Controllers/CartController.php', errors: { delta: ['Stok produk tidak mencukupi, batas jumlah terlampaui, atau produk tidak aktif.'] } },
        { message: 'Stok produk tidak mencukupi atau produk tidak aktif.' },
    ]) {
        const result = await failAddToBag(422, payload);
        for (const text of [result.status, result.toast]) {
            assert.match(text, /stok/i);
            assert.match(text, /kurangi|ubah|pilih|periksa|hapus/i, 'actionable next step');
            assert.doesNotMatch(text, /jaringan|koneksi|network/i, 'a retry-the-network message is wrong for exhausted stock');
            assert.doesNotMatch(text, /SQLSTATE|CartController|\/var\/www/);
        }
        assert.equal(result.requests, 3, 'no automatic retry of a business rejection');
    }
});

test('UI-09: localized stock classification uses exact trusted copy, not arbitrary server keywords', async () => {
    const copy = { stock_rejection: 'Product stock is insufficient or the product is inactive.', adjust_stock_rejection: 'Product stock is insufficient, quantity limit exceeded, or product is inactive.', err_stock: 'SAFE-STOCK' };
    for (const payload of [{ message: copy.stock_rejection }, { errors: { delta: [copy.adjust_stock_rejection] } }]) {
        const result = await failAddToBag(422, payload, copy);
        assert.equal(result.status, 'SAFE-STOCK');
        assert.equal(result.toast, 'SAFE-STOCK');
    }
    const result = await failAddToBag(422, { message: 'stock SQLSTATE secret' }, copy);
    assert.equal(result.status, 'Tas belum dapat diperbarui.');
});

test('UI-09: malformed and non-stock 422 stay generic, never mislabelled as stock or network', async () => {
    for (const payload of [null, {}, { message: 'SQLSTATE secret' }, { errors: { delta: ['The delta field must be an integer.'] } }, { errors: { product_id: ['Invalid product.'] } }]) {
        const result = await failAddToBag(422, payload);
        assert.equal(result.status, 'Tas belum dapat diperbarui.');
        assert.equal(result.toast, result.status);
        assert.equal(result.requests, 3);
    }
});

test('UI-09: 429 explains throttling; other 4xx are a generic bag error; neither is called a network problem', async () => {
    const rate = await failAddToBag(429, { message: 'Too Many Attempts. internal' });
    for (const text of [rate.status, rate.toast]) {
        assert.match(text, /terlalu banyak|tunggu|sebentar/i);
        assert.doesNotMatch(text, /jaringan|koneksi|internal/i);
    }
    for (const status of [400, 403, 404]) {
        const other = await failAddToBag(status, { message: 'internal db detail' });
        for (const text of [other.status, other.toast]) {
            assert.equal(text, 'Tas belum dapat diperbarui.', `HTTP ${status} => err_bag copy`);
        }
    }
});

test('UI-09 [baseline]: 419, 5xx and genuine transport failures keep their dedicated messages', async () => {
    assert.equal((await failAddToBag(419, { message: 'x' })).status, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    assert.equal((await failAddToBag(503, { message: 'x' })).status, 'Layanan sedang bermasalah. Coba lagi sebentar lagi.');
    const transport = await failAddToBag('transport');
    assert.equal(transport.status, 'Jaringan bermasalah. Periksa koneksi lalu coba lagi.');
    assert.doesNotMatch(transport.status + transport.toast, /socket secret/);
});

test('UI-09: stock and rate-limit copy exists in every locale', () => {
    assertLangKeys('app_js', ['err_stock', 'err_rate']);
});

// ---------------------------------------------------------------------------------------------------------
// UI-10 — after a validation error focus lands on the summary even when it lives OUTSIDE the form
// ---------------------------------------------------------------------------------------------------------

class TNode {
    constructor(tag, attrs = {}, children = []) {
        this.tag = tag; this.attrs = { ...attrs }; this.parent = null; this.children = []; this.handlers = {}; this.disabled = false; this.textContent = '';
        for (const child of children) this.append(child);
    }
    append(child) { child.parent = this; this.children.push(child); return child; }
    get parentElement() { return this.parent; }
    get parentNode() { return this.parent; }
    get previousElementSibling() { const siblings = this.parent?.children ?? []; return siblings[siblings.indexOf(this) - 1] ?? null; }
    get nextElementSibling() { const siblings = this.parent?.children ?? []; return siblings[siblings.indexOf(this) + 1] ?? null; }
    getAttribute(name) { return this.attrs[name] ?? null; }
    setAttribute(name, value) { this.attrs[name] = String(value); }
    hasAttribute(name) { return name in this.attrs; }
    removeAttribute(name) { delete this.attrs[name]; }
    addEventListener(type, fn) { (this.handlers[type] ??= []).push(fn); }
    contains(other) { return other === this || this.children.some((child) => child.contains(other)); }
    matches(selector) { return parseSelectors(selector).some((tokens) => matchTokens(this, tokens, tokens.length - 1, this)); }
    closest(selector) { for (let node = this; node; node = node.parent) if (node.matches(selector)) return node; return null; }
    querySelectorAll(selector) {
        const parsed = parseSelectors(selector);
        const found = [];
        const walk = (node) => { for (const child of node.children) { if (parsed.some((tokens) => matchTokens(child, tokens, tokens.length - 1, this))) found.push(child); walk(child); } };
        walk(this);
        return found;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
    focus() { this.ownerDocument.activeElement = this; this.ownerDocument.focusLog.push(this); }
}

function tokenizeSelector(selector) {
    const parts = [];
    let current = '';
    let depth = 0;
    let quote = null;
    for (const ch of selector) {
        if (quote) { current += ch; if (ch === quote) quote = null; continue; }
        if (ch === '"' || ch === "'") { quote = ch; current += ch; continue; }
        if (ch === '[') depth += 1;
        if (ch === ']') depth -= 1;
        if (depth === 0 && /\s/.test(ch)) { if (current) { parts.push(current); current = ''; } continue; }
        if (depth === 0 && ch === '>') { if (current) { parts.push(current); current = ''; } parts.push('>'); continue; }
        current += ch;
    }
    if (current) parts.push(current);
    return parts;
}
const parseSelectors = (selector) => selector.split(',').map((part) => tokenizeSelector(part.trim()));

function parseCompound(text) {
    const lead = text.match(/^(\*|[a-zA-Z][\w-]*)?/);
    let rest = text.slice(lead[0].length);
    const compound = { tag: lead[1] && lead[1] !== '*' ? lead[1].toLowerCase() : null, id: null, classes: [], attrs: [], scope: false };
    while (rest) {
        let m;
        if ((m = rest.match(/^:scope/))) compound.scope = true;
        else if ((m = rest.match(/^\.([\w-]+)/))) compound.classes.push(m[1]);
        else if ((m = rest.match(/^#([\w-]+)/))) compound.id = m[1];
        else if ((m = rest.match(/^\[([\w-]+)(?:([~|^$*]?=)(?:"([^"]*)"|'([^']*)'|([^\]]*)))?\]/))) compound.attrs.push({ name: m[1], op: m[2] ?? null, value: m[3] ?? m[4] ?? m[5] ?? null });
        else throw new Error(`mini-DOM: unsupported selector syntax "${text}" (supports tag, .class, #id, [attr], [attr=v], :scope, > and descendant)`);
        rest = rest.slice(m[0].length);
    }
    return compound;
}

function matchTokens(node, tokens, index, scopeRoot) {
    const compound = parseCompound(tokens[index]);
    if (compound.tag && node.tag !== compound.tag) return false;
    if (compound.id && node.attrs.id !== compound.id) return false;
    if (compound.scope && node !== scopeRoot) return false;
    const classes = (node.attrs.class ?? '').split(/\s+/);
    if (!compound.classes.every((c) => classes.includes(c))) return false;
    for (const { name, op, value } of compound.attrs) {
        if (!(name in node.attrs)) return false;
        const actual = node.attrs[name];
        if (op === '=' && actual !== value) return false;
        if (op === '^=' && !actual.startsWith(value)) return false;
        if (op === '$=' && !actual.endsWith(value)) return false;
        if (op === '*=' && !actual.includes(value)) return false;
    }
    if (index === 0) return true;
    if (tokens[index - 1] === '>') return node.parent ? matchTokens(node.parent, tokens, index - 2, scopeRoot) : false;
    for (let ancestor = node.parent; ancestor; ancestor = ancestor.parent) if (matchTokens(ancestor, tokens, index - 1, scopeRoot)) return true;
    return false;
}

function authPage(...bodyChildren) {
    const htmlRoot = new TNode('html');
    const body = htmlRoot.append(new TNode('body'));
    for (const child of bodyChildren) body.append(child);
    const document = {
        documentElement: htmlRoot, body, activeElement: body, focusLog: [], readyState: 'complete', visibilityState: 'visible',
        querySelector: (s) => htmlRoot.querySelector(s), querySelectorAll: (s) => htmlRoot.querySelectorAll(s),
        getElementById: (id) => htmlRoot.querySelector(`#${id}`),
        createElement: (tag) => { const node = new TNode(tag); node.ownerDocument = document; return node; },
        addEventListener() {},
    };
    const stamp = (node) => { node.ownerDocument = document; node.children.forEach(stamp); };
    stamp(htmlRoot);
    const context = {
        document, window: { addEventListener() {}, matchMedia: () => ({ matches: false }), location: { href: 'https://zafira.test/masuk', search: '' } },
        navigator: {}, localStorage: { getItem: () => null, setItem() {}, removeItem() {} }, fetch: () => new Promise(() => {}),
        initAdminTables() {}, searchMatches, URL, URLSearchParams, history: { replaceState() {} }, setTimeout() { return 0; }, clearTimeout() {},
    };
    vm.runInNewContext(appSource(), context);
    return document;
}
const summary = (attrs = {}) => new TNode('div', { class: 'auth-errors', role: 'alert', ...attrs });
const authForm = (attrs = { 'data-auth-form': '' }) => new TNode('form', attrs, [new TNode('button', { type: 'submit' })]);

test('UI-10: a role=alert summary that precedes the form (login/register/account markup) receives focus and is programmatically focusable', () => {
    const alert = summary();
    const document = authPage(new TNode('main', {}, [new TNode('section', { class: 'auth-content' }, [alert, authForm()])]));
    assert.equal(document.activeElement, alert);
    assert.equal(alert.getAttribute('tabindex'), '-1');
});

test('UI-10 [baseline]: a summary rendered inside the form keeps receiving focus', () => {
    const alert = summary();
    const form = authForm();
    form.append(alert);
    const document = authPage(new TNode('main', {}, [form]));
    assert.equal(document.activeElement, alert);
});

test('UI-10: with a success status AND an alert in the same column, the alert wins focus (login.blade renders both)', () => {
    const success = summary({ role: 'status' });
    const alert = summary();
    const document = authPage(new TNode('section', {}, [success, alert, authForm()]));
    assert.equal(document.activeElement, alert);
});

test('UI-10: focus only follows the summary that belongs to the form — an unrelated alert elsewhere on the page is not stolen', () => {
    const unrelated = summary();
    const otherSection = new TNode('section', { class: 'danger-zone' }, [unrelated, new TNode('form', {}, [new TNode('button', { type: 'submit' })])]);
    const authSection = new TNode('section', { class: 'profile' }, [authForm()]);
    const document = authPage(otherSection, authSection);
    assert.notEqual(document.activeElement, unrelated);
    assert.equal(document.focusLog.length, 0, 'nothing is focused when the auth form has no summary');
});

test('UI-10: explicit summary association crosses wrappers and shared summaries focus only once', () => {
    const alert = summary({ id: 'totp-errors' });
    const document = authPage(new TNode('main', {}, [alert,
        new TNode('section', {}, [authForm({ 'data-auth-form': '', 'data-error-summary': 'totp-errors' })]),
        new TNode('details', {}, [authForm({ 'data-auth-form': '', 'data-error-summary': 'totp-errors' })]),
    ]));
    assert.equal(document.activeElement, alert);
    assert.equal(document.focusLog.length, 1);
});

test('UI-10: with two auth forms only the one with errors is focused', () => {
    const alert = summary();
    const document = authPage(
        new TNode('section', {}, [authForm()]),
        new TNode('section', {}, [alert, authForm()]),
    );
    assert.equal(document.activeElement, alert);
    assert.equal(document.focusLog.length, 1);
});

test('UI-10: editable auth/account text fields link conditional server errors across multiline markup', () => {
    const views = ['auth/login', 'auth/register', 'auth/totp', 'auth/password-email', 'auth/password-reset', 'account/show', 'account/totp-enrol', 'account/totp-recovery'];
    let checked = 0;
    for (const view of views) {
        const source = read(`resources/views/${view}.blade.php`);
        for (const [input] of source.matchAll(/<input\b(?:\{\{[\s\S]*?\}\}|[^>])*>/g)) {
            if (/type="(?:hidden|checkbox)"|\bdisabled\b/.test(input)) continue;
            const name = input.match(/\bname="([^"]+)"/)?.[1];
            if (!name) continue;
            checked++;
            const where = `${view}: ${name}`;
            assert.match(input, new RegExp(`@error\\('` + name + `'\\)\\s*aria-invalid="true"`), where);
            const description = input.match(/aria-describedby="([^"]+)"/)?.[1];
            assert.ok(description, `${where}: linked error description`);
            const message = new RegExp(`@error\\('` + name + `'\\)\\s*<(?:small|p)\\b[^>]*id="` + description + `"`);
            assert.match(source, message, `${where}: conditional message has matching id`);
            const inputId = input.match(/\bid="([^"]+)"/)?.[1];
            assert.ok(inputId && source.includes(`for="${inputId}"`), `${where}: label stays associated`);
        }
    }
    assert.ok(checked >= 25, 'non-vacuous coverage of all public auth/account text inputs');
});
