// @vitest-environment jsdom
// FR-401/FR-402/NFR-401/NFR-402: one help door and a guided, rule-based assistant.
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Home from '../../resources/js/pages/Storefront/Home.jsx';
import Show from '../../resources/js/pages/Products/Show.jsx';
import { acceptSearchResult, ASSISTANT_STORAGE_KEY, conciergeAvailability, conciergeNotes, matchIntent, NOTES_LIMIT } from '../../resources/js/lib/storefront-assistant.js';

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
const a = copy.assistant;
const product = { id: 'sac-seraphine', productId: 7, name: 'Sac Séraphine', category: 'Tas', price: 100, priceLabel: 'Rp100', stock: 4, image: '/bag.jpg', heroImage: '/bag-large.jpg', alt: 'Sutra', description: 'Sutra Indonesia', color: 'Cognac', material: 'Sutra mulberry', composition: '100% sutra', origin: 'Jakarta', care: 'Dry clean', craft: 'Jahit tangan', hasSizes: false, href: '/produk/sac-seraphine' };
const hits = [
    { id: 'saphir-clutch', productId: 5, name: 'Minaudière Saphir', category: 'Tas Tangan', priceLabel: 'Rp9.000.000', available: true, image: '/clutch.jpg', alt: 'Clutch', href: '/produk/saphir-clutch', hasSizes: false },
    { id: 'elan-dress', productId: 3, name: 'Robe Élan', category: 'Busana Wanita', priceLabel: 'Rp12.000.000', available: true, image: '/robe.jpg', alt: 'Robe', href: '/produk/elan-dress', hasSizes: true },
    { id: 'sold', productId: 9, name: 'Montre Nocturne', category: 'Perhiasan', priceLabel: 'Rp30.000.000', available: false, image: null, alt: 'Montre', href: '/produk/sold', hasSizes: false },
];
const assets = Object.fromEntries(['hero_couture', 'chemise_riviere', 'minaudiere_saphir', 'robe_elan', 'manteau_firenze', 'robe_aurore'].map(name => [name, { src: `/${name}.jpg`, webp: `/${name}.webp`, small: `/${name}-600.webp` }]));
const props = { copy, locale: 'id', user: null, today: '2026-10-30', year: 2026, assets, links: { home: '/', shop: '/belanja', cart: '/cart', catalog: '/koleksi', account: '/akun', login: '/login', logout: '/logout', locale: '/locale', concierge: '/concierge', newsletter: '/newsletter', assistantProducts: '/bantuan/produk', assistantFeedback: '/bantuan/umpan-balik', locales: ['id', 'en', 'fr', 'it', 'zh'].map(code => ({ code, url: `?locale=${code}` })) }, products: [product], categories: [], related: [] };
let root; let host; let calls; let quantity; let searchReply;
const flush = async () => { await act(async () => { for (let i = 0; i < 10; i++) await Promise.resolve(); }); };
async function mount(Page = Home, overrides = {}) { await act(async () => root.render(<Page {...props} {...overrides} />)); await flush(); }
const buttons = scope => [...(scope || host).querySelectorAll('button')];
const button = (text, scope) => buttons(scope).find(node => node.textContent.trim() === text);
const tile = text => button(text, [...host.querySelectorAll('.sf-assistant-tiles')].at(-1));
const follow = text => button(text, host.querySelector('.sf-assistant-follow'));
const click = async node => { expect(node).toBeTruthy(); await act(async () => node.click()); await flush(); };
const launcher = () => host.querySelector('.sf-assistant-launcher');
const panel = () => host.querySelector('.sf-assistant');
const log = () => host.querySelector('.sf-assistant [role="log"]');
const searches = () => calls.filter(call => call.url.startsWith('/bantuan/produk'));
async function ask(text) {
    const input = host.querySelector('#sf-assistant-input');
    await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, text); input.dispatchEvent(new Event('input', { bubbles: true })); });
    await act(async () => input.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush();
}
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true); localStorage.clear(); sessionStorage.clear(); window.history.replaceState(null, '', '/'); calls = []; quantity = 0;
    searchReply = () => new Response(JSON.stringify({ items: hits, total: 6, partial: false, terms: ['tas'], catalog: '/koleksi' }));
    document.head.innerHTML = '<meta name="csrf-token" content="fixture-token">'; document.body.innerHTML = '<div id="app"></div>';
    host = document.getElementById('app'); root = createRoot(host);
    vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
        calls.push({ url, method: options.method || 'GET', body: options.body ? JSON.parse(options.body) : null });
        if (url === '/concierge') return new Response(JSON.stringify({ message: 'Tersimpan', reference: 'ZC-000042' }), { status: 201 });
        if (url.startsWith('/bantuan/produk')) return searchReply(url);
        if (url === '/bantuan/umpan-balik') return new Response(null, { status: 204 });
        if (url.endsWith('/delta')) quantity = Math.max(0, quantity + JSON.parse(options.body).delta);
        return new Response(JSON.stringify({ items: quantity ? [{ product_id: 5, product_title: 'Minaudière Saphir', unit_price: 100, quantity, subtotal: quantity * 100, available: true }] : [], subtotal: quantity * 100 }));
    }));
    Element.prototype.scrollIntoView = vi.fn();
});
afterEach(async () => { await act(async () => root.unmount()); vi.unstubAllGlobals(); vi.restoreAllMocks(); });

describe('assistant logic', () => {
    it('recognises Indonesian, English, mixed phrasing, -nya and one-edit typos', () => {
        expect(matchIntent('Bahannya apa ya?')).toBe('product');
        expect(matchIntent('harganya berapa')).toBe('product');
        expect(matchIntent('ukruan FR 38 muat?')).toBe('size');
        expect(matchIntent('Pengirimn ke Bandung berapa lama')).toBe('delivery');
        expect(matchIntent('cek status pesanan saya')).toBe('order');
        expect(matchIntent('mau booking fitting sabtu')).toBe('appointment');
        expect(matchIntent('cara mencuci kain sutra')).toBe('care');
        expect(matchIntent('bisa dibungkus untuk kado?')).toBe('gift');
        expect(matchIntent('tambah monogram inisial')).toBe('personalize');
        expect(matchIntent('bisa bayar pakai kartu kredit?')).toBe('payment');
        expect(matchIntent('carikan rekomendasi tas')).toBe('search');
    });
    it('routes damaged or billing problems to a person before any other topic', () => {
        expect(matchIntent('pesanan saya datang rusak')).toBe('issue');
        expect(matchIntent('I was charged twice for my order')).toBe('issue');
        expect(matchIntent('mau bicara dengan staf')).toBe('human');
    });
    it('keeps short keywords exact and returns null for unknown text', () => {
        expect(matchIntent('cs')).toBe('human');
        expect(matchIntent('ca')).toBeNull();
        expect(matchIntent('apa kabar hari ini')).toBeNull();
        expect(matchIntent('kebaya')).toBeNull();
    });
    it('knows concierge hours in Jakarta time regardless of the visitor clock', () => {
        expect(conciergeAvailability(new Date('2026-10-30T04:00:00Z'))).toEqual({ open: true, next: null });   // Fri 11.00 WIB
        expect(conciergeAvailability(new Date('2026-10-30T01:00:00Z'))).toEqual({ open: false, next: 'today' }); // Fri 08.00
        expect(conciergeAvailability(new Date('2026-10-30T13:00:00Z'))).toEqual({ open: false, next: 'tomorrow' }); // Fri 20.00
        expect(conciergeAvailability(new Date('2026-10-31T13:00:00Z'))).toEqual({ open: false, next: 'later' }); // Sat 20.00
        expect(conciergeAvailability(new Date('2026-11-01T05:00:00Z'))).toEqual({ open: false, next: 'tomorrow' }); // Sun 12.00, Monday opens
    });
    it('builds editable handoff notes within the server limit and checks search payloads', () => {
        const labels = { product: 'Produk', page: 'Halaman', topic: 'Topik', questions: 'Pertanyaan' };
        expect(conciergeNotes({ heading: 'Dari asisten', labels, product, topic: 'Pengiriman', questions: ['satu', 'dua', 'tiga', 'empat'] }))
            .toBe('[Dari asisten]\nProduk: Sac Séraphine (/produk/sac-seraphine)\nTopik: Pengiriman\nPertanyaan:\n- dua\n- tiga\n- empat');
        expect(conciergeNotes({ heading: 'x', labels, page: 'Beranda', questions: ['y'.repeat(2000)] }).length).toBeLessThanOrEqual(NOTES_LIMIT);
        expect(() => acceptSearchResult({ items: 'x' })).toThrow();
        const accepted = acceptSearchResult({ items: [...hits, hits[0], { name: 'no id' }], total: 9 });
        expect(accepted.items).toHaveLength(4);
        expect(Object.keys(accepted.items[0]).sort()).toEqual(['alt', 'available', 'category', 'hasSizes', 'href', 'id', 'image', 'name', 'priceLabel', 'productId']);
    });
});

it('CB-13/NFR-402 renders a labelled launcher without auto-opening or calling assistant endpoints', async () => {
    await mount();
    expect(launcher().textContent).toBe(a.launcher);
    expect(launcher().getAttribute('aria-keyshortcuts')).toBe('H');
    expect(panel()).toBeNull();
    expect(calls.some(call => call.url.startsWith('/bantuan'))).toBe(false);
});

it('CB-01/02/03/15/17 opens with disclosure, live status, topic menu and a polite log; Escape returns focus', async () => {
    await mount();
    launcher().focus(); await click(launcher());
    const dialog = host.querySelector('[role="dialog"][aria-labelledby="sf-assistant-title"]');
    expect(dialog.textContent).toContain(a.disclosure);
    expect(dialog.querySelector('.sf-assistant-status').textContent).toMatch(new RegExp([a.status_open, a.status_next_today, a.status_next_tomorrow, a.status_next_later].map(text => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')));
    expect(log().getAttribute('aria-label')).toBe(a.log_label);
    expect(log().textContent).toContain(a.greeting);
    expect(buttons(host.querySelector('.sf-assistant-tiles')).map(node => node.textContent)).toEqual([a.topic_search, a.topic_size, a.topic_delivery, a.topic_order, a.topic_gift, a.topic_personalize, a.topic_care, a.topic_payment]);
    expect(log().querySelector('time')).not.toBeNull();
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))); await flush();
    expect(panel()).toBeNull();
    expect(document.activeElement).toBe(launcher());
});

it('greets a signed-in client by first name and H opens help on a product page', async () => {
    await mount(Show, { product, user: { name: 'Vivienne Laurent', email: 'v@example.test', mobile: '0812' } });
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'h', bubbles: true }))); await flush();
    expect(panel()).not.toBeNull();
    expect(log().textContent).toContain('Selamat datang kembali, Vivienne.');
});

it('CB-09 answers from live PDP data, offers relevant next steps and records anonymous feedback', async () => {
    await mount(Show, { product });
    await click(launcher());
    expect(log().textContent).toContain('Anda sekarang melihat Sac Séraphine.');
    expect(tile(a.topic_size)).toBeUndefined();
    await click(tile(a.topic_product));
    const facts = [...log().querySelectorAll('.sf-assistant-facts div')].map(row => row.textContent);
    expect(facts).toEqual(expect.arrayContaining(['HargaRp100', 'WarnaCognac', 'MaterialSutra mulberry', 'AsalJakarta']));
    expect(log().querySelector('a[href="/produk/sac-seraphine"]')).not.toBeNull();
    expect(buttons(host.querySelector('.sf-assistant-follow')).map(node => node.textContent)).toEqual([a.topic_care, a.topic_personalize]);
    await click(button(a.feedback_yes));
    expect(calls.find(call => call.url === '/bantuan/umpan-balik').body).toEqual({ topic: 'product', helpful: true });
    expect(log().textContent).toContain(a.feedback_thanks);
    await click(follow(a.topic_care));
    expect(log().textContent).toContain('Dry clean');
    await click(button(a.feedback_no_button));
    expect(log().textContent).toContain(a.feedback_no);
    expect(follow(a.topic_appointment)).toBeTruthy();
});

it('CB-10/11 searches the catalogue, renders at most four cards and adds to the bag only after confirmation, with undo', async () => {
    await mount();
    await click(launcher());
    await ask('tas sutra');
    expect(searches()[0].url).toBe('/bantuan/produk?q=tas%20sutra');
    const cards = [...log().querySelectorAll('.sf-assistant-card')];
    expect(cards.map(card => card.querySelector('.sf-assistant-card-title').textContent)).toEqual(['Minaudière Saphir', 'Robe Élan', 'Montre Nocturne']);
    expect(cards[1].querySelector('.sf-assistant-card-actions a[href="/produk/elan-dress"]').textContent).toBe(a.choose_size);
    expect(cards[2].textContent).toContain(copy.pdp.sold_out);
    expect(log().querySelector('a[href="/koleksi"]').textContent).toContain('Lihat semua 6');
    await click(button(a.add_to_bag, cards[0]));
    expect(calls.some(call => call.url.endsWith('/delta'))).toBe(false);
    expect(log().textContent).toContain('Tambahkan Minaudière Saphir (Rp9.000.000) ke Tas Belanja?');
    await click(button(a.cart_yes));
    expect(calls.filter(call => call.url.endsWith('/delta')).map(call => call.body.delta)).toEqual([1]);
    expect(log().textContent).toContain('Minaudière Saphir sudah ada di Tas Belanja Anda.');
    await click(button(a.cart_undo));
    expect(calls.filter(call => call.url.endsWith('/delta')).map(call => call.body.delta)).toEqual([1, -1]);
    expect(log().textContent).toContain('Minaudière Saphir dikeluarkan lagi dari tas.');
    expect(button(a.cart_undo)).toBeUndefined();
});

it('the search tile asks first, a colour question on the home page becomes a search, and errors are humane', async () => {
    await mount();
    await click(launcher());
    await click(tile(a.topic_search));
    expect(log().textContent).toContain(a.search_prompt);
    expect(host.querySelector('#sf-assistant-input').placeholder).toBe(a.search_placeholder);
    await ask('kebaya');
    expect(searches().at(-1).url).toBe('/bantuan/produk?q=kebaya');
    await ask('ada warna biru?');
    expect(searches().at(-1).url).toBe('/bantuan/produk?q=ada%20warna%20biru%3F');
    searchReply = () => new Response('{}', { status: 500 });
    await ask('cari syal');
    expect(log().textContent).toContain(a.results_error);
});

it('CB-04/05/06/07 escalates after two unknown questions, confirms, prefills the form and closes with a reference', async () => {
    searchReply = () => new Response(JSON.stringify({ items: [], total: 0, partial: false, terms: [], catalog: '/koleksi' }));
    await mount(Show, { product });
    await click(launcher());
    await ask('halo, selamat siang');
    expect(log().textContent).toContain('Maaf, saya belum memahami “halo, selamat siang”.');
    expect(log().textContent).not.toContain(a.handoff_fallback);
    await ask('xyz qwerty');
    expect(log().textContent).toContain(a.handoff_fallback);
    expect(calls.some(call => call.url === '/concierge')).toBe(false);
    await click(button(a.handoff_continue));
    expect(panel()).toBeNull();
    const notes = host.querySelector('#sf-concierge-notes').value;
    expect(notes).toContain('Sac Séraphine (/produk/sac-seraphine)');
    expect(notes).toContain('- xyz qwerty');
    expect(host.querySelector('#sf-concierge-service').value).toBe('virtual-consultation');
    for (const [id, value] of [['name', 'Ada'], ['email', 'ada@example.test'], ['phone', '0812']]) {
        const input = host.querySelector(`#sf-concierge-${id}`);
        await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value); input.dispatchEvent(new Event('input', { bubbles: true })); });
    }
    await act(async () => host.querySelector('.concierge-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); await flush();
    expect(calls.find(call => call.url === '/concierge').body).toMatchObject({ source: 'assistant', service: 'virtual-consultation', notes });
    expect(log().textContent).toContain('ZC-000042');
    expect(button(a.handoff_continue)).toBeUndefined();
});

it('gift and personalisation answers reuse the official copy and hand off with the matching service', async () => {
    await mount();
    await click(launcher());
    await click(tile(a.topic_gift));
    expect(log().textContent).toContain(copy.welcome.packaging_desc);
    expect(log().querySelector('a[href="/#packaging-section"]')).not.toBeNull();
    await click(follow(a.topic_personalize));
    expect(log().textContent).toContain(copy.welcome.atelier_desc);
    await click(follow(a.topic_appointment));
    expect(log().querySelector('.sf-assistant-notes').textContent).toContain(`Topik: ${a.topic_personalize}`);
    await click(button(a.handoff_cancel));
    expect(log().textContent).toContain(a.handoff_cancelled);
    expect(calls.some(call => call.url === '/concierge')).toBe(false);
});

it('the menu button brings the topics back and order status links only to existing ownership paths', async () => {
    await mount();
    await click(launcher());
    await click(tile(a.topic_order));
    expect(log().querySelector('a[href="/belanja#pesanan"]')).not.toBeNull();
    await click(host.querySelector('.sf-assistant-menu'));
    expect(log().querySelectorAll('.sf-assistant-tiles')).toHaveLength(2);
    await click(tile(a.topic_payment));
    expect(log().textContent).toContain(a.payment_answer);
});

it('CB-21 keeps the conversation across page loads and restart clears it; corrupt or blocked storage is harmless', async () => {
    await mount();
    await click(launcher()); await click(tile(a.topic_delivery));
    expect(JSON.parse(sessionStorage.getItem(ASSISTANT_STORAGE_KEY)).entries.map(item => item.kind)).toEqual(['greeting', 'topic', 'delivery']);
    await act(async () => root.unmount()); root = createRoot(host);
    await mount(Show, { product });
    await click(launcher());
    expect(log().textContent).toContain(copy.welcome.returns_policy);
    await click(button(a.restart));
    expect(log().children).toHaveLength(1);
    await act(async () => root.unmount()); root = createRoot(host);
    sessionStorage.setItem(ASSISTANT_STORAGE_KEY, '{not json');
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('blocked'); });
    await mount();
    await click(launcher());
    expect(log().textContent).toContain(a.greeting);
});

it('every locale ships the same assistant keys and the shortcut list names H', async () => {
    const keys = Object.keys(a).sort();
    for (const locale of ['en', 'fr', 'it', 'zh']) expect(Object.keys(translatedCopy(locale).assistant).sort()).toEqual(keys);
    await mount();
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: '?', bubbles: true }))); await flush();
    expect(host.querySelector('.sf-shortcuts').textContent).toContain(a.shortcut);
});
