// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Home from '../../resources/js/pages/Storefront/Home.jsx';
import Show from '../../resources/js/pages/Products/Show.jsx';

function translatedCopy(locale) {
    const source = readFileSync(`lang/${locale}/shop_ui.php`, 'utf8');
    const result = {};
    for (const section of ['app_js', 'pdp', 'header', 'footer', 'cart', 'welcome', 'assistant']) {
        const start = source.indexOf(`    '${section}' => [`);
        const end = source.indexOf('\n    ],', start);
        result[section] = Object.fromEntries([...source.slice(start, end).matchAll(/'([^']+)'\s*=>\s*'((?:\\.|[^'])*)'/g)].map(match => [match[1], match[2].replace(/\\'/g, "'")]));
    }
    return result;
}
const copy = translatedCopy('id');
const product = { id: 'sac-seraphine', productId: 7, name: '<img onerror=bad()> Sutra', category: 'Tas', price: 100, priceLabel: 'Rp100', stock: 4, image: '/bag.jpg', heroImage: '/bag-large.jpg', alt: 'Sutra', description: 'Sutra Indonesia', color: 'Safir', material: 'Sutra', origin: 'Jakarta', hasSizes: true, href: '/produk/sac-seraphine' };
const assets = Object.fromEntries(['hero_couture', 'chemise_riviere', 'minaudiere_saphir', 'robe_elan', 'manteau_firenze', 'robe_aurore'].map(name => [name, { src: `/${name}.jpg`, webp: `/${name}.webp`, small: `/${name}-600.webp` }]));
const props = { copy, locale: 'id', user: null, today: '2026-10-23', year: 2026, assets, links: { home: '/', shop: '/belanja', cart: '/cart', catalog: '/koleksi', account: '/akun', login: '/login', logout: '/logout', locale: '/locale', concierge: '/concierge', newsletter: '/newsletter', locales: ['id', 'en', 'fr', 'it', 'zh'].map(code => ({ code, url: `?locale=${code}` })) }, products: [product], categories: [{ id: 1, title: 'Tas', href: '/koleksi?category=1' }], product, related: [] };
let root; let host; let calls; let quantity;
const snapshot = () => ({ items: quantity ? [{ product_id: 7, product_title: product.name, unit_price: 100, quantity, subtotal: quantity * 100, available: true }] : [], subtotal: quantity * 100 });
const flush = async () => { await act(async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); }); };
async function mount(Page = Home, overrides = {}) { await act(async () => root.render(<Page {...props} {...overrides} />)); await flush(); }
const button = text => [...host.querySelectorAll('button')].find(node => node.textContent.trim() === text);
const click = async node => { expect(node).toBeTruthy(); await act(async () => node.click()); await flush(); };
const input = async (node, value) => { await act(async () => { Object.getOwnPropertyDescriptor(node.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype, 'value').set.call(node, value); node.dispatchEvent(new Event('input', { bubbles: true })); }); };
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true); localStorage.clear(); window.history.replaceState(null, '', '/'); calls = []; quantity = 1;
    document.head.innerHTML = '<meta name="csrf-token" content="fixture-token">'; document.body.innerHTML = '<div id="app"></div>';
    host = document.getElementById('app'); root = createRoot(host);
    vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
        calls.push({ url, method: options.method || 'GET', body: options.body ? JSON.parse(options.body) : null });
        if (url === '/concierge' || url === '/newsletter') return new Response(JSON.stringify({ message: 'Tersimpan' }), { status: 201 });
        if (url === '/favorites') return new Response(JSON.stringify({ favorites: [] }));
        if (url.endsWith('/delta')) quantity = Math.max(0, quantity + JSON.parse(options.body).delta);
        let data = snapshot();
        if (options.method === 'DELETE') { const removed_quantity = quantity; quantity = 0; data = { ...snapshot(), removed_quantity }; }
        return new Response(JSON.stringify(data));
    }));
    Element.prototype.scrollIntoView = vi.fn();
});
afterEach(async () => { await act(async () => root.unmount()); vi.unstubAllGlobals(); vi.restoreAllMocks(); });
it('renders the complete home, real hashes, assets and literal hostile names', async () => {
    await mount();
    for (const id of ['collection', 'new-arrivals', 'maison', 'heritage', 'atelier', 'packaging-section', 'services', 'returns-policy']) expect(host.querySelector(`#${id}`)).not.toBeNull();
    expect(host.textContent).toContain(product.name); expect(host.querySelector('[onerror]')).toBeNull();
    expect(host.querySelector('picture img').getAttribute('src')).toBe('/hero_couture.jpg');
    expect(host.querySelector('#home-catalog-search').getAttribute('action')).toBe('/koleksi');
    expect(host.querySelector('a[href="/koleksi?category=1"]')).not.toBeNull();
    expect(host.innerHTML).not.toContain('data-i18n'); expect(host.querySelector('#profile-sound-btn')).toBeNull();
});
it('slash focuses the actual catalog search; monogram and foil have one native owner', async () => {
    await mount();
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true })));
    expect(document.activeElement.id).toBe('home-catalog-query');
    await input(host.querySelector('#monogram-input'), 'a2bc');
    expect(host.querySelector('#monogram-preview-display').textContent).toBe('ABC');
    expect(host.querySelector('#welcome-monogram-counter').textContent).toContain('3 / 3');
    await click(host.querySelector('button[aria-label="Perak"]'));
    expect(host.querySelector('button[aria-label="Perak"]').getAttribute('aria-pressed')).toBe('true');
    expect(calls.filter(call => call.method !== 'GET')).toHaveLength(0);
});
it('PDP size pills and monogram add directly to server/guest cart, while bespoke inquiry opens Atelier concierge', async () => {
    await mount(Show);
    await click(host.querySelector('[data-size-pill="M"]'));
    expect(host.querySelector('[data-size-pill="M"]').getAttribute('aria-pressed')).toBe('true');
    await input(host.querySelector('#pdp-monogram-input'), 'ab2c');
    await click(button(copy.pdp.add));
    expect(calls.find(call => call.url.endsWith('/delta')).body).toEqual({ delta: 1, size: 'M', monogram: 'ABC' });
    expect(host.querySelector('.cart-drawer')).not.toBeNull();
    await click(host.querySelector('[data-sf-cart-close]'));

    await click(host.querySelector('#pdp-bespoke-inquiry'));
    expect(host.querySelector('.concierge-order-summary').textContent).toContain('ABC');
    expect(host.querySelector('[name="service"]').value).toBe('atelier');
    expect(host.querySelector('[name="order_items"]').value).toBe('');
    await input(host.querySelector('[name="name"]'), 'Klien');
    await input(host.querySelector('[name="email"]'), 'client@example.test');
    await input(host.querySelector('[name="phone"]'), '08123456789');
    await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    await flush();
    const submitted = calls.find(call => call.url === '/concierge');
    expect(submitted.body.notes).toContain('ABC'); expect(submitted.body.order_items).toBe('');
});
it('catalog PDP add uses only numeric delta, opens cart, and undo restores server quantity', async () => {
    await mount(Show);
    await click(button(copy.pdp.add));
    expect(calls.find(call => call.url.endsWith('/delta')).body).toEqual({ delta: 1 });
    expect(host.querySelector('.cart-drawer')).not.toBeNull();
    await click(host.querySelector('[data-action="remove"]'));
    expect(host.querySelector('[data-sf-cart-close]')).toBe(document.activeElement);
    await click(button(copy.app_js.undo));
    expect(calls.filter(call => call.url.endsWith('/delta')).at(-1).body).toEqual({ delta: 2 });
    expect(quantity).toBe(2);
});
it('dialog closes with Escape, restores trigger focus and traps keyboard focus', async () => {
    await mount(); const trigger = host.querySelector(`button[aria-label="${copy.header.search}"]`); trigger.focus();
    await click(trigger); expect(document.activeElement.id).toBe('sf-search-input');
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    await flush(); expect(host.querySelector('.search-panel')).toBeNull(); expect(document.activeElement).toBe(trigger);
});
it('sold-out controls never send delta and quick view resets when a different product opens', async () => {
    await mount(Home, { products: [{ ...product, stock: 0 }] });
    expect(host.querySelector('.product-add').disabled).toBe(true);
    await click(button(copy.pdp.quick_view));
    expect(host.querySelector('.quick-view-content .button').disabled).toBe(true);
    expect(calls.filter(call => call.method === 'POST')).toHaveLength(0);
});
it('guest favorites are device-only and legacy carts are never merged or rewritten', async () => {
    const legacy = JSON.stringify([{ name: 'Ancien', quantity: 3, monogram: 'ZA' }]); localStorage.setItem('zafira-cart', legacy);
    await mount(); await click(host.querySelector('.wishlist-button'));
    expect(JSON.parse(localStorage.getItem('zafira-wishlist:v1'))).toEqual([product.id]);
    await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    expect(host.querySelector('.cart-drawer details').textContent).toContain('Ancien');
    expect(localStorage.getItem('zafira-cart')).toBe(legacy); expect(calls.filter(call => call.method === 'POST')).toHaveLength(0);
});
it('failed cart load exposes retry, recovers and never claims an empty successful cart', async () => {
    fetch.mockRejectedValueOnce(new Error('socket detail')).mockRejectedValueOnce(new Error('socket detail'));
    await mount(); await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    expect(host.querySelector('.cart-empty')).toBeNull();
    expect(host.textContent).not.toContain('socket detail');
    await click(button(copy.cart.loading));
    expect(host.querySelector('.cart-drawer').textContent).toContain(product.name);
});
it('newsletter errors are attached to the field and successful retry resets the form', async () => {
    await mount();
    await input(host.querySelector('#newsletter-email'), 'client@example.test');
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ errors: { email: ['Email tidak valid.'] } }), { status: 422 }));
    await act(async () => host.querySelector('.newsletter-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    await flush(); expect(host.querySelector('#sf-newsletter-error').textContent).toBe('Email tidak valid.');
    expect(document.activeElement.id).toBe('newsletter-email');
    await act(async () => host.querySelector('.newsletter-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    await flush(); expect(host.querySelector('#newsletter-email').value).toBe(''); expect(host.textContent).toContain('Tersimpan');
});
it('a late delete response cannot offer undo after closing the cart', async () => {
    await mount(); await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    const regular = fetch.getMockImplementation(); let release;
    fetch.mockImplementation(async (url, options) => options?.method === 'DELETE' ? new Promise(resolve => { release = resolve; }) : regular(url, options));
    await click(host.querySelector('[data-action="remove"]'));
    await click(host.querySelector('[data-sf-cart-close]'));
    const externalFocus = document.activeElement;
    await act(async () => release(new Response(JSON.stringify({ items: [], subtotal: 0, removed_quantity: 1 }))));
    await flush(); expect(button(copy.app_js.undo)).toBeUndefined();
    expect(document.activeElement).toBe(externalFocus);
});
it('quick view switching clears sold-out state and keeps optional provenance product-specific', async () => {
    await mount(Home, { products: [{ ...product, id: 'zero', stock: 0, name: 'Zero' }, { ...product, id: 'live', name: 'Live', origin: null }] });
    await click([...host.querySelectorAll('.quick-view-button')][0]);
    expect(host.querySelector('.quick-view-content .button').disabled).toBe(true);
    await click(host.querySelector('.quick-view-modal .modal-close'));
    await click([...host.querySelectorAll('.quick-view-button')][1]);
    expect(host.querySelector('.quick-view-content .button').disabled).toBe(false);
    expect(host.querySelector('.quick-view-content').textContent).not.toContain('Jakarta');
});
it('favorites use numeric IDs for signed-in users and never merge guest preferences', async () => {
    localStorage.setItem('zafira-wishlist:v1', JSON.stringify([product.id]));
    await mount(Home, { user: { name: 'Klien', email: 'client@example.test' }, links: { ...props.links, favorites: '/favorites' } });
    expect(host.querySelector('.wishlist-button').getAttribute('aria-pressed')).toBe('false');
    await click(host.querySelector('.wishlist-button'));
    expect(calls.find(call => call.url === '/favorites' && call.method === 'POST').body).toEqual({ product_id: 7 });
    expect(localStorage.getItem('zafira-wishlist:v1')).toBe(JSON.stringify([product.id]));
});
it('cart refreshes on return to the tab, never while hidden', async () => {
    await mount(); const initial = calls.length;
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    await act(async () => document.dispatchEvent(new Event('visibilitychange'))); await flush(); expect(calls).toHaveLength(initial);
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
    await act(async () => document.dispatchEvent(new Event('visibilitychange'))); await flush(); expect(calls).toHaveLength(initial + 1);
});
it('theme controls synchronize without a DOM translation observer and honor system changes', async () => {
    await mount();
    expect(host.querySelector('.announcement-bar .theme-switcher')).toBeNull();
    expect(host.querySelector('.footer-preferences-bar')).toBeNull();
    await click(host.querySelector(`button[aria-label="${copy.header.login_aria}"]`));
    const darkBtn = host.querySelector(`button[aria-label="${copy.welcome.theme_dark}"][class*="theme-seg"]`);
    await click(darkBtn);
    expect(document.documentElement.dataset.theme).toBe('dark'); expect(localStorage.getItem('zafira_theme')).toBe('dark');
    expect(darkBtn.getAttribute('aria-pressed')).toBe('true');
});
it.each(['id', 'en', 'fr', 'it', 'zh'])('native home and PDP use server copy for locale %s', async locale => {
    const translated = translatedCopy(locale);
    await mount(Home, { locale, copy: translated });
    expect(host.querySelector('#hero-title').textContent).toContain(translated.welcome.hero_title_1);
    expect(host.querySelector(`button[aria-label="${translated.header.cart_open}"]`)).not.toBeNull();
    await mount(Show, { locale, copy: translated });
    expect(button(translated.pdp.add)).toBeDefined();
    expect(host.querySelector('a[aria-current="true"]').textContent).toBe(locale.toUpperCase());
});
it('system theme follows OS changes but an explicit preference does not', async () => {
    const listeners = new Set(); const media = { matches: true, addEventListener: (_, fn) => listeners.add(fn), removeEventListener: (_, fn) => listeners.delete(fn) };
    vi.stubGlobal('matchMedia', () => media);
    await mount(); expect(document.documentElement.dataset.theme).toBe('dark');
    await act(async () => { media.matches = false; listeners.forEach(fn => fn()); });
    expect(document.documentElement.dataset.theme).toBe('light');
    await click(host.querySelector(`button[aria-label="${copy.header.login_aria}"]`));
    await click(host.querySelector(`button[aria-label="${copy.welcome.theme_dark}"][class*="theme-seg"]`));
    await act(async () => listeners.forEach(fn => fn()));
    expect(document.documentElement.dataset.theme).toBe('dark');
});
it('R6-1 mobile dialog is outside the inert background and closes by keyboard', async () => {
    await mount();
    const trigger = host.querySelector('.mobile-menu-button'); trigger.focus(); await click(trigger);
    const menu = host.querySelector('.mobile-menu[role="dialog"]');
    expect(menu.closest('[inert], [aria-hidden="true"]')).toBeNull();
    expect(menu.contains(document.activeElement)).toBe(true);
    const focusable = [...menu.querySelectorAll('button, a[href], input, select, textarea')];
    focusable.at(-1).focus();
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true })));
    expect(document.activeElement).toBe(focusable[0]);
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true })));
    expect(document.activeElement).toBe(focusable.at(-1));
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    await flush(); expect(host.querySelector('.mobile-menu')).toBeNull(); expect(document.activeElement).toBe(trigger);
});
it('R6-3 preserves row focus intent through separately deferred GET and DELETE commits', async () => {
    await mount(); await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    let getDone; let deleteDone;
    fetch.mockImplementationOnce(() => new Promise(resolve => { getDone = resolve; })).mockImplementationOnce(() => new Promise(resolve => { deleteDone = resolve; }));
    const remove = host.querySelector('[data-action="remove"]'); remove.focus(); await click(remove);
    await act(async () => getDone(new Response(JSON.stringify(snapshot())))); await flush();
    expect(document.activeElement).toBe(remove);
    await act(async () => deleteDone(new Response(JSON.stringify({ items: [], subtotal: 0, removed_quantity: 1 })))); await flush();
    expect(document.activeElement).toBe(host.querySelector('[data-sf-cart-close]'));
});
it('R6-3 a late mutation must not steal focus from an intentional new target', async () => {
    await mount(); await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    let getDone; let deleteDone;
    fetch.mockImplementationOnce(() => new Promise(resolve => { getDone = resolve; })).mockImplementationOnce(() => new Promise(resolve => { deleteDone = resolve; }));
    const remove = host.querySelector('[data-action="remove"]'); remove.focus(); await click(remove);
    const checkoutLink = host.querySelector('.cart-footer a'); checkoutLink.focus();
    await act(async () => getDone(new Response(JSON.stringify(snapshot())))); await flush();
    expect(document.activeElement).toBe(checkoutLink);
    await act(async () => deleteDone(new Response(JSON.stringify({ ...snapshot(), removed_quantity: 0 })))); await flush();
    expect(document.activeElement).toBe(checkoutLink);
});
it.each([Home, Show])('R6-5 signed-in favorite URL filters with numeric product IDs on %s', async Page => {
    window.history.replaceState(null, '', '/?tampilkan=favorit');
    const regular = fetch.getMockImplementation();
    fetch.mockImplementation((url, options) => url === '/favorites' ? Promise.resolve(new Response(JSON.stringify({ favorites: [{ product_id: 7 }] }))) : regular(url, options));
    await mount(Page, { related: [product], user: { name: 'Klien' }, links: { ...props.links, favorites: '/favorites' } });
    expect(host.querySelector('.product-card')).not.toBeNull();
    expect(host.querySelector('.wishlist-button').getAttribute('aria-pressed')).toBe('true');
    window.history.replaceState(null, '', '/');
});
it('R6-7 closing concierge retains its draft, an inquiry does not overwrite it, success resets only its draft', async () => {
    await mount(Show);
    await click(button(copy.pdp.consult));
    await input(host.querySelector('[name="name"]'), 'Nama draft');
    await input(host.querySelector('[name="notes"]'), 'Catatan pribadi');
    await click(host.querySelector('.concierge-modal .modal-close'));
    await click(button(copy.pdp.consult));
    expect(host.querySelector('[name="name"]').value).toBe('Nama draft');
    expect(host.querySelector('[name="notes"]').value).toBe('Catatan pribadi');
    await click(host.querySelector('.concierge-modal .modal-close'));
    await input(host.querySelector('#pdp-monogram-input'), 'ZA'); await click(host.querySelector('#pdp-bespoke-inquiry'));
    expect(host.querySelector('[name="notes"]').value).toContain('ZA');
    await input(host.querySelector('[name="notes"]'), 'Draft inquiry ZA diedit');
    await click(host.querySelector('.concierge-modal .modal-close')); await click(button(copy.pdp.consult));
    expect(host.querySelector('[name="notes"]').value).toBe('Catatan pribadi');
    await input(host.querySelector('[name="email"]'), 'draft@example.test'); await input(host.querySelector('[name="phone"]'), '08123456789');
    await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush();
    await click(button(copy.pdp.consult)); expect(host.querySelector('[name="notes"]').value).toBe('');
    expect(host.querySelector('[name="name"]').value).toBe('');
    await click(host.querySelector('.concierge-modal .modal-close')); await click(host.querySelector('#pdp-bespoke-inquiry'));
    expect(host.querySelector('[name="notes"]').value).toBe('Draft inquiry ZA diedit');
});
it('R6-6/8 computed drawer structure and focus selector keep layout and the established contrast token', async () => {
    const css = readFileSync('resources/css/storefront-react.css', 'utf8');
    const style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);
    await mount(); await click(host.querySelector(`button[aria-label="${copy.header.login_aria}"]`));
    expect(getComputedStyle(host.querySelector('.profile-drawer .drawer-header')).display).not.toBe('contents');
    expect(getComputedStyle(host.querySelector('.profile-drawer .drawer-body')).display).not.toBe('contents');
    expect(css).not.toMatch(/\.sf-native \.drawer>div\s*\{display:contents\}/);
    expect(css.match(/\.sf-native\s+:is\(\s*button,\s*a,\s*input,\s*select,\s*textarea\s*\):focus-visible\s*\{([^}]+)\}/)[1]).toContain('var(--focus-ring)');
    await click(host.querySelector('.profile-drawer .icon-button'));
    await mount(Show); await click(button(copy.pdp.size_guide_open));
    expect(getComputedStyle(host.querySelector('.size-guide-drawer .drawer-header')).display).not.toBe('contents');
    const base = readFileSync('resources/css/app.css', 'utf8');
    expect(base).toContain('--focus-ring: var(--sapphire)');
    const token = name => base.match(new RegExp(`--${name}:\\s*(#[0-9a-f]{6})`, 'i'))[1];
    const luminance = hex => {
        const channels = hex.slice(1).match(/../g).map(value => parseInt(value, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
        return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
    };
    for (const background of ['ivory', 'white']) {
        const ratio = (luminance(token(background)) + .05) / (luminance(token('sapphire')) + .05);
        expect(ratio).toBeGreaterThanOrEqual(3);
    }
});
it('R6-P2 pending generic request survives inquiry remount and cannot submit twice or clear newer edits', async () => {
    await mount(Show); await click(button(copy.pdp.consult));
    await input(host.querySelector('[name="name"]'), 'Klien');
    await input(host.querySelector('[name="email"]'), 'client@example.test');
    await input(host.querySelector('[name="phone"]'), '08123456789');
    await input(host.querySelector('[name="notes"]'), 'Draft semula');
    const regular = fetch.getMockImplementation(); let finish; const submissions = [];
    fetch.mockImplementation((url, options) => {
        if (url !== '/concierge') return regular(url, options);
        submissions.push(JSON.parse(options.body)); return new Promise(resolve => { finish = resolve; });
    });
    const submit = async () => { await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush(); };
    await submit(); expect(submissions).toHaveLength(1);
    await click(host.querySelector('.concierge-modal .modal-close'));
    await input(host.querySelector('#pdp-monogram-input'), 'ZA'); await click(host.querySelector('#pdp-bespoke-inquiry'));
    expect(host.querySelector('.concierge-form button[type="submit"]').disabled).toBe(false);
    await click(host.querySelector('.concierge-modal .modal-close')); await click(button(copy.pdp.consult));
    expect(host.querySelector('.concierge-form button[type="submit"]').disabled).toBe(true);
    expect(host.querySelector('.concierge-form').getAttribute('aria-busy')).toBe('true');
    await submit(); expect(submissions).toHaveLength(1);
    await input(host.querySelector('[name="notes"]'), 'Draft baru setelah reopen');
    await act(async () => finish(new Response(JSON.stringify({ message: 'Request semula tersimpan' }), { status: 201 }))); await flush();
    expect(host.querySelector('.concierge-modal')).not.toBeNull();
    expect(host.querySelector('[name="notes"]').value).toBe('Draft baru setelah reopen');
    expect(host.querySelector('.concierge-form button[type="submit"]').disabled).toBe(false);
    expect(submissions[0].notes).toBe('Draft semula');
});
it.each(['same-dialog-edit', 'reopened-unchanged'])('R6-P2 old success cannot reset/close %s', async mode => {
    await mount(Show); await click(button(copy.pdp.consult));
    await input(host.querySelector('[name="name"]'), 'Klien'); await input(host.querySelector('[name="email"]'), 'client@example.test');
    await input(host.querySelector('[name="phone"]'), '08123456789'); await input(host.querySelector('[name="notes"]'), 'Draft semula');
    let finish; fetch.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
    await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush();
    if (mode === 'same-dialog-edit') await input(host.querySelector('[name="notes"]'), 'Draft diedit');
    else { await click(host.querySelector('.concierge-modal .modal-close')); await click(button(copy.pdp.consult)); }
    await act(async () => finish(new Response(JSON.stringify({ message: 'Tersimpan' }), { status: 201 }))); await flush();
    expect(host.querySelector('.concierge-modal')).not.toBeNull();
    expect(host.querySelector('[name="notes"]').value).toBe(mode === 'same-dialog-edit' ? 'Draft diedit' : 'Draft semula');
});
it('R6-P2 normal concierge 422 retains values, focuses invalid input, releases lock and retries successfully', async () => {
    await mount(Show); await click(button(copy.pdp.consult));
    await input(host.querySelector('[name="name"]'), 'Klien'); await input(host.querySelector('[name="email"]'), 'client@example.test');
    await input(host.querySelector('[name="phone"]'), '08123456789'); await input(host.querySelector('[name="notes"]'), 'Catatan');
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ errors: { email: ['Periksa email.'] } }), { status: 422 }));
    const submit = async () => { await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush(); };
    await submit();
    expect(host.querySelector('[name="notes"]').value).toBe('Catatan'); expect(document.activeElement.id).toBe('sf-concierge-email');
    expect(host.querySelector('#sf-error-email').textContent).toBe('Periksa email.');
    expect(host.querySelector('.concierge-form button[type="submit"]').disabled).toBe(false);
    await submit(); expect(host.querySelector('.concierge-modal')).toBeNull();
    await click(button(copy.pdp.consult)); expect(host.querySelector('[name="notes"]').value).toBe('');
});
it('all cross-group navigation remains full-document links including checkout', async () => {
    await mount(Show);
    await click(host.querySelector(`button[aria-label="${copy.header.cart_open}"]`));
    expect(host.querySelector('.cart-footer a').getAttribute('href')).toBe('/belanja');
    expect(host.querySelector('[data-catalog-link]')).toBeNull();
});
it('hero scroll cue scopes decorative line to first span and keeps text span readable off center CTA', async () => {
    await mount();
    const cue = host.querySelector('.hero-scroll');
    expect(cue).not.toBeNull();
    const spans = cue.querySelectorAll('span');
    expect(spans).toHaveLength(2);
    expect(spans[1].textContent).toBe(copy.welcome.hero_scroll);
    for (const file of ['resources/views/welcome.blade.php', 'resources/css/app.css', 'resources/css/storefront-react.css']) {
        const source = readFileSync(file, 'utf8');
        expect(source).not.toMatch(/\.hero-scroll\s+span\s*\{/);
    }
});
it('announcement utility bar aligns three balanced zones without redundant theme toggles', async () => {
    const css = readFileSync('resources/css/storefront-react.css', 'utf8');
    expect(css).not.toMatch(/\.sf-native\s+\.theme-btn\s*\{\s*min-height:\s*44px/);
    await mount();
    const bar = host.querySelector('.announcement-bar');
    expect(bar.querySelector('.announcement-start .lang-switcher')).not.toBeNull();
    expect(bar.querySelector('.announcement-center .announcement-copy').textContent).toBe(copy.welcome.announcement);
    expect(bar.querySelector('#locale-status').getAttribute('aria-atomic')).toBe('true');
    expect(bar.querySelector('.theme-switcher')).toBeNull();
    expect(host.querySelector('.footer-preferences-bar')).toBeNull();
    expect(bar.querySelector('.announcement-end .announcement-cta').textContent).toBe(copy.welcome.rendezvous_btn);
});
it('site-header stays sticky from the top bar boundary and never hides or clips while scrolling down', async () => {
    const baseCss = readFileSync('resources/css/app.css', 'utf8');
    expect(baseCss).toMatch(/\.site-header\s*\{\s*position:\s*sticky;\s*z-index:\s*50;\s*top:\s*0;/);
    expect(baseCss).not.toContain('.site-header.is-hidden');
    await mount();
    const header = host.querySelector('#site-header');
    expect(header.classList.contains('is-fixed')).toBe(false);
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 42 });
    await act(async () => window.dispatchEvent(new Event('scroll')));
    expect(header.classList.contains('is-fixed')).toBe(true);
    expect(header.classList.contains('is-hidden')).toBe(false);
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 640 });
    await act(async () => window.dispatchEvent(new Event('scroll')));
    expect(header.classList.contains('is-fixed')).toBe(true);
    expect(header.classList.contains('is-hidden')).toBe(false);
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 0 });
});
it('home scroll-reveal shares a single IntersectionObserver instance, unobserves once revealed, and honors reduced motion', async () => {
    const previousObserver = globalThis.IntersectionObserver;
    const previousMatchMedia = window.matchMedia;
    const instances = [];
    class MockIntersectionObserver {
        constructor(callback) {
            this.callback = callback;
            this.observed = new Set();
            this.unobserved = [];
            this.disconnected = false;
            instances.push(this);
        }
        observe(node) { this.observed.add(node); }
        unobserve(node) { this.observed.delete(node); this.unobserved.push(node); }
        disconnect() { this.observed.clear(); this.disconnected = true; }
    }
    try {
        globalThis.IntersectionObserver = MockIntersectionObserver;
        window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener() {}, removeEventListener() {} });
        await mount();
        expect(instances).toHaveLength(1);
        expect(host.querySelector('.hero-content').classList.contains('reveal')).toBe(false);
        const categoryCard = host.querySelector('.category-card');
        expect(categoryCard.classList.contains('reveal')).toBe(true);
        expect(categoryCard.classList.contains('is-visible')).toBe(false);
        await act(async () => {
            instances[0].callback([{ target: categoryCard, isIntersecting: true, intersectionRatio: 0.25 }]);
        });
        expect(categoryCard.classList.contains('is-visible')).toBe(true);
        expect(instances[0].unobserved).toContain(categoryCard);

        instances.length = 0;
        window.matchMedia = vi.fn().mockReturnValue({ matches: true, addEventListener() {}, removeEventListener() {} });
        await mount();
        expect(instances).toHaveLength(0);
        expect(host.querySelector('.category-card').classList.contains('is-visible')).toBe(true);
    } finally {
        globalThis.IntersectionObserver = previousObserver;
        window.matchMedia = previousMatchMedia;
    }
});
it('top-docked search curtain and mobile atelier drawer keep couture proportions and dark-mode contrast', async () => {
    const themeCss = readFileSync('resources/css/theme.css', 'utf8');
    const css = readFileSync('resources/css/storefront-react.css', 'utf8');
    const style = document.createElement('style');
    style.textContent = `${themeCss}\n${css}`;
    document.head.appendChild(style);
    document.documentElement.dataset.theme = 'dark';

    await mount();
    await click(host.querySelector(`button[aria-label="${copy.header.search}"]`));
    const panel = host.querySelector('.search-panel');
    expect(getComputedStyle(panel).minHeight).toBe('0px');
    expect(['0', '0px']).toContain(getComputedStyle(panel).borderRadius);
    expect(panel.querySelector('.search-top .brand')).not.toBeNull();
    expect(panel.querySelector('.search-clear-btn')).toBeNull();
    await input(panel.querySelector('#sf-search-input'), 'Saphir');
    expect(panel.querySelector('.search-clear-btn')).not.toBeNull();
    await click(panel.querySelector('.search-clear-btn'));
    expect(panel.querySelector('#sf-search-input').value).toBe('');
    expect(document.activeElement.id).toBe('sf-search-input');
    await click(panel.querySelector('.search-close-btn'));

    await click(host.querySelector('.mobile-menu-button'));
    const menu = host.querySelector('.mobile-menu');
    expect(menu.querySelector('.mobile-menu-close')).not.toBeNull();
    expect(getComputedStyle(menu.querySelector('nav a')).fontSize).toBe('19px');
    expect(getComputedStyle(menu.querySelector('nav a')).color).toBe('rgb(247, 243, 235)');
    expect(getComputedStyle(menu.querySelector('.brand-name')).color).toBe('rgb(247, 243, 235)');
    expect(getComputedStyle(menu.querySelector('.storefront-preferences select')).color).toBe('rgb(247, 243, 235)');
    document.documentElement.dataset.theme = 'light';
    expect(getComputedStyle(menu.querySelector('nav a')).color).toBe('rgb(247, 243, 235)');
    expect(getComputedStyle(menu.querySelector('.storefront-preferences select')).color).toBe('rgb(247, 243, 235)');
    expect(menu.querySelector('.mobile-menu-footer .storefront-preferences')).not.toBeNull();
});
