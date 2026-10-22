import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import Index, { CatalogQuickView } from '../../resources/js/pages/Catalog/Index.jsx';

const inertia = vi.hoisted(() => ({ url: '/koleksi', get: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Head: ({ title, children }) => <><title>{title}</title>{children}</>,
    Link: ({ preserveState, prefetch, children, ...props }) => <a {...props} data-catalog-link="inertia">{children}</a>,
    router: { get: inertia.get },
    usePage: () => ({ url: inertia.url }),
}));
import {
    buildCatalogFilterUrl,
    createCatalogActions,
    createSerialQueue,
    postCatalogJson,
    watchCatalogFilters,
} from '../../resources/js/lib/catalog.js';

const copy = {
    login: 'Silakan masuk terlebih dahulu.',
    failed: 'Permintaan gagal. Coba lagi.',
    network: 'Jaringan terputus. Coba lagi.',
    processing: 'Memproses…',
    updated: 'Tas belanja diperbarui. Jumlah :qty menggantikan jumlah sebelumnya',
    saved: 'Tersimpan di favorit',
};

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

function requestEnvironment(fetchImpl) {
    const meta = { content: 'test-csrf-first' };
    return {
        meta,
        fetchImpl,
        document: { querySelector: vi.fn(() => meta) },
        location: { href: 'https://maison.test/koleksi', origin: 'https://maison.test' },
    };
}

function jsonResponse(data = { items: [] }, status = 200) {
    return new Response(JSON.stringify(data), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

afterEach(() => {
    vi.restoreAllMocks();
    inertia.url = '/koleksi';
});

describe('FE-MIG catalog filter wire contract', () => {
    it('appends literal repeated kategori keys, retaining Unicode and zero values', () => {
        const result = buildCatalogFilterUrl('/koleksi?page=9&q=lama', [
            ['locale', 'fr'], ['q', 'été & sutra'], ['kategori', 'tas'], ['kategori', 'sepatu'],
            ['category', '4'], ['harga_min', '0'], ['harga_max', '1000000'],
            ['color', 'Biru'], ['material', 'Sutra'], ['urut', 'harga'], ['tersedia', '1'],
            ['page', '9'], ['unused', ''],
        ]);
        expect(result).toBe('/koleksi?locale=fr&q=%C3%A9t%C3%A9+%26+sutra&kategori=tas&kategori=sepatu&category=4&harga_min=0&harga_max=1000000&color=Biru&material=Sutra&urut=harga&tersedia=1');
        expect(result).not.toContain('%5B');
        expect(new URL(result, 'https://maison.test').searchParams.getAll('kategori')).toEqual(['tas', 'sepatu']);
    });

    it('does not turn search 0 into an absent query or keep stale action filters', () => {
        expect(buildCatalogFilterUrl('https://maison.test/koleksi?page=4#main-catalog', [['q', '0']]))
            .toBe('https://maison.test/koleksi?q=0');
        expect(buildCatalogFilterUrl('/koleksi?q=lama', [['q', ''], ['color', '']])).toBe('/koleksi');
    });
});

describe('catalog SET mutation ordering and feedback', () => {
    it('snapshots quantities before serial writes and reports only the latest intent', async () => {
        const first = deferred();
        const post = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue({});
        const feedback = vi.fn();
        const actions = createCatalogActions({ post, feedback, copy });
        const payload = { product_id: '7', quantity: '2' };
        const a = actions.setQuantity('/cart/items', payload);
        payload.quantity = '90';
        const b = actions.setQuantity('/cart/items', { product_id: 7, quantity: '4' });
        await Promise.resolve();
        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenNthCalledWith(1, '/cart/items', { product_id: 7, quantity: 2 });
        first.resolve({});
        await Promise.all([a, b]);
        expect(post).toHaveBeenNthCalledWith(2, '/cart/items', { product_id: 7, quantity: 4 });
        expect(feedback.mock.calls.map(([text]) => text)).toEqual([
            copy.processing, copy.processing, copy.updated.replace(':qty', '4'),
        ]);
    });

    it.each([['0', 1], ['-5', 1], ['100', 99], ['invalid', 1], ['3.8', 3]])(
        'clamps snapshot quantity %s to %i', async (quantity, expected) => {
            const post = vi.fn().mockResolvedValue({});
            const actions = createCatalogActions({ post, feedback: vi.fn(), copy });
            await actions.setQuantity('/cart/items', { product_id: 7, quantity });
            expect(post).toHaveBeenCalledWith('/cart/items', { product_id: 7, quantity: expected });
        },
    );

    it('does not retry a failed mutation or poison later explicit submissions', async () => {
        const post = vi.fn().mockRejectedValueOnce(new Error('Stok tidak cukup.')).mockResolvedValue({});
        const feedback = vi.fn();
        const actions = createCatalogActions({ post, feedback, copy });
        expect(await actions.setQuantity('/cart/items', { product_id: 7, quantity: 3 })).toBe(false);
        expect(feedback).toHaveBeenLastCalledWith('Stok tidak cukup.');
        expect(await actions.setQuantity('/cart/items', { product_id: 7, quantity: 1 })).toBe(true);
        expect(post).toHaveBeenCalledTimes(2);
        expect(feedback).toHaveBeenLastCalledWith(copy.updated.replace(':qty', '1'));
    });

    it('guards favorite and cart feedback with the same latest-intent version', async () => {
        const favorite = deferred();
        const post = vi.fn((url) => url === '/favorites' ? favorite.promise : Promise.resolve({}));
        const feedback = vi.fn();
        const actions = createCatalogActions({ post, feedback, copy });
        const a = actions.saveFavorite('/favorites', '7');
        await actions.setQuantity('/cart/items', { product_id: 7, quantity: 2 });
        favorite.resolve({});
        await a;
        expect(feedback).toHaveBeenLastCalledWith(copy.updated.replace(':qty', '2'));
        expect(post).toHaveBeenCalledWith('/favorites', { product_id: 7 });
        expect(feedback).not.toHaveBeenCalledWith(copy.saved);
    });

    it('does not let an older cart failure overwrite a newer favorite success', async () => {
        const cart = deferred();
        const feedback = vi.fn();
        const post = vi.fn((url) => url === '/cart/items' ? cart.promise : Promise.resolve({}));
        const actions = createCatalogActions({ post, feedback, copy });
        const pending = actions.setQuantity('/cart/items', { product_id: 7, quantity: 2 });
        await actions.saveFavorite('/favorites', 8);
        cart.reject(new Error('Older stock error'));
        await pending;
        expect(feedback).toHaveBeenLastCalledWith(copy.saved);
        expect(feedback).not.toHaveBeenCalledWith('Older stock error');
    });

    it('keeps accepted writes ordered across page remounts but stops obsolete feedback', async () => {
        const first = deferred();
        const queue = createSerialQueue();
        const post = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue({});
        const oldFeedback = vi.fn();
        const oldPage = createCatalogActions({ post, feedback: oldFeedback, copy, queue });
        const a = oldPage.setQuantity('/cart/items', { product_id: 7, quantity: 2 });
        oldPage.dispose();
        const feedback = vi.fn();
        const nextPage = createCatalogActions({ post, feedback, copy, queue });
        const b = nextPage.setQuantity('/cart/items', { product_id: 7, quantity: 5 });
        await Promise.resolve();
        expect(post).toHaveBeenCalledTimes(1);
        first.resolve({});
        await Promise.all([a, b]);
        expect(oldFeedback).toHaveBeenCalledTimes(1);
        expect(feedback).toHaveBeenLastCalledWith(copy.updated.replace(':qty', '5'));
    });

    it('supports effect cleanup/setup replay without reviving stale callbacks', async () => {
        const first = deferred();
        const feedback = vi.fn();
        const post = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue({});
        const actions = createCatalogActions({ post, feedback, copy });
        const a = actions.saveFavorite('/favorites', 7);
        actions.dispose();
        actions.activate();
        first.resolve({});
        await a;
        expect(feedback).toHaveBeenCalledTimes(1);
        await actions.saveFavorite('/favorites', 8);
        expect(feedback).toHaveBeenLastCalledWith(copy.saved);
    });
});

describe('catalog JSON request boundary', () => {
    it('reads current meta CSRF for every request, never from page props', async () => {
        const fetchImpl = vi.fn().mockImplementation(() => Promise.resolve(jsonResponse()));
        const env = requestEnvironment(fetchImpl);
        await postCatalogJson('/cart/items', { product_id: 7, quantity: 1 }, copy, env);
        env.meta.content = 'test-csrf-refreshed';
        await postCatalogJson('/favorites', { product_id: 7 }, copy, env);
        expect(env.document.querySelector).toHaveBeenCalledTimes(2);
        expect(fetchImpl.mock.calls[0][1]).toEqual({
            method: 'POST', credentials: 'same-origin', mode: 'same-origin', redirect: 'error',
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'test-csrf-first',
            },
            body: JSON.stringify({ product_id: 7, quantity: 1 }),
        });
        expect(fetchImpl.mock.calls[1][1].headers['X-CSRF-TOKEN']).toBe('test-csrf-refreshed');
    });

    it('refreshes CSRF when an already-enqueued mutation finally executes', async () => {
        const first = deferred();
        const fetchImpl = vi.fn().mockReturnValueOnce(first.promise).mockImplementation(() => Promise.resolve(jsonResponse()));
        const env = requestEnvironment(fetchImpl);
        const actions = createCatalogActions({
            post: (url, payload) => postCatalogJson(url, payload, copy, env),
            feedback: vi.fn(), copy,
        });
        const a = actions.setQuantity('/cart/items', { product_id: 7, quantity: 1 });
        const b = actions.setQuantity('/cart/items', { product_id: 7, quantity: 2 });
        await Promise.resolve();
        env.meta.content = 'test-csrf-after-queueing';
        first.resolve(jsonResponse());
        await Promise.all([a, b]);
        expect(fetchImpl.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('test-csrf-first');
        expect(fetchImpl.mock.calls[1][1].headers['X-CSRF-TOKEN']).toBe('test-csrf-after-queueing');
    });

    it('refuses off-origin destinations and missing CSRF before sending credentials', async () => {
        const fetchImpl = vi.fn();
        const env = requestEnvironment(fetchImpl);
        await expect(postCatalogJson('https://other.test/cart/items', {}, copy, env)).rejects.toThrow(copy.failed);
        env.meta.content = '';
        await expect(postCatalogJson('/cart/items', {}, copy, env)).rejects.toThrow(copy.failed);
        expect(fetchImpl).not.toHaveBeenCalled();
    });

    it.each([
        [401, { message: 'Internal login detail' }, copy.login],
        [419, { message: 'TokenMismatchException' }, copy.failed],
        [422, { message: 'Stok tidak cukup.' }, 'Stok tidak cukup.'],
        [422, { errors: { quantity: ['Jumlah tidak valid.'] } }, 'Jumlah tidak valid.'],
        [500, { message: 'SQLSTATE private server detail' }, copy.failed],
    ])('maps HTTP %i to safe, actionable feedback', async (status, data, message) => {
        const env = requestEnvironment(vi.fn().mockResolvedValue(jsonResponse(data, status)));
        await expect(postCatalogJson('/cart/items', {}, copy, env)).rejects.toThrow(message);
    });

    it.each([
        () => new Response('<html>Login</html>', { headers: { 'Content-Type': 'text/html' } }),
        () => new Response('{broken', { headers: { 'Content-Type': 'application/json' } }),
        () => jsonResponse(null),
        () => jsonResponse([]),
        () => ({ redirected: true, ok: true, status: 200 }),
    ])('does not report success for HTML, malformed JSON, or redirected responses', async (response) => {
        const env = requestEnvironment(vi.fn().mockResolvedValue(response()));
        await expect(postCatalogJson('/cart/items', {}, copy, env)).rejects.toThrow(copy.failed);
    });

    it('reports network failure without automatically replaying the POST', async () => {
        const fetchImpl = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
        await expect(postCatalogJson('/cart/items', {}, copy, requestEnvironment(fetchImpl))).rejects.toThrow(copy.network);
        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });
});

describe('catalog responsive disclosure lifecycle (not browser evidence)', () => {
    it('opens active mobile filters, responds to breakpoint changes, and removes its listener', () => {
        const details = { open: true };
        const media = { matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() };
        const cleanup = watchCatalogFilters(details, media, 0);
        expect(details.open).toBe(false);
        const listener = media.addEventListener.mock.calls[0][1];
        media.matches = false;
        listener();
        expect(details.open).toBe(true);
        cleanup();
        expect(media.removeEventListener).toHaveBeenCalledWith('change', listener);
        media.matches = true;
        const cleanupActive = watchCatalogFilters(details, media, 2);
        expect(details.open).toBe(true);
        cleanupActive();
    });
});

const t = {
    title: 'Koleksi', eyebrow: 'La Collection', lead: 'Cari berdasarkan nama, material, atau warna.',
    search: 'Cari', search_ph: 'Nama, material, warna', category: 'Kategori', color: 'Warna', material: 'Material',
    all: 'Semua', apply: 'Terapkan', reset: 'Atur ulang', results: 'kreasi ditemukan',
    empty: 'Tidak ada kreasi yang cocok. Atur ulang filter Anda.', quick: 'Lihat cepat', close: 'Tutup',
    set_one: 'Set 1 item di tas', set_quantity: 'Terapkan jumlah ke tas',
    replace_hint: 'Jumlah baru ini menggantikan jumlah sebelumnya di tas', detail: 'Lihat detail',
    prev: 'Sebelumnya', next: 'Berikutnya', page: 'Halaman', skip: 'Lewati ke konten utama', language: 'Bahasa',
    currency_note: 'Harga dalam Rupiah (IDR).', qty: 'Jumlah', filters: 'Filter', stock: 'Stok', sold_out: 'Stok habis',
    harga_min: 'Harga Minimum', harga_max: 'Harga Maksimum', available: 'Tersedia', urut: 'Urutkan',
    price_sort: 'Harga Terendah', newest: 'Terbaru', active_filters: 'Filter Aktif', remove_filter: 'Hapus filter',
    empty_hint: 'Coba perluas rentang harga atau atur ulang kategori untuk melihat kreasi lainnya.',
    clear_all: 'Atur ulang semua filter', fuzzy_notice: 'Menampilkan kreasi dengan ejaan terdekat.',
    search_empty_hint: 'Periksa ejaan atau coba nama, material, atau warna yang lebih singkat.',
    clear_search: 'Hapus pencarian', search_narrow: 'Persempit kategori, material, atau warna untuk mencari ejaan terdekat.',
};

function product(overrides = {}) {
    return {
        id: 7, title: 'Sac Lumière', slug: 'sac-lumiere', href: '/produk/sac-lumiere', category: 'Tas',
        priceLabel: 'Rp2.450.000', stock: 3, image: '/storage/sac-600.webp',
        imageSrcset: '/storage/sac-400.webp 400w, /storage/sac-600.webp 600w, /storage/sac-800.webp 800w',
        quickImage: '/storage/sac-800.webp', alt: 'Tas sutra biru', description: 'Sutra tenun tangan.',
        material: 'Sutra', color: 'Biru', ...overrides,
    };
}

function pageProps(overrides = {}) {
    return {
        t,
        catalogCopy: {
            bag: 'Belanja & tas', notice: 'Jumlah baru menggantikan jumlah lama. Personalisasi dari',
            home: 'beranda', notice_end: 'dan jangan lanjutkan checkout. Lihat', shop: 'Belanja',
            favorite: 'Simpan ke favorit', js: copy,
        },
        products: { data: [product()], total: 1, current_page: 1, last_page: 1, prev_url: null, next_url: null },
        filters: { locale: 'id' }, categories: [{ slug: 'tas', title: 'Tas' }, { slug: 'sepatu', title: 'Sepatu' }],
        colors: ['Biru', 'Emas'], materials: ['Sutra', 'Kulit'], chips: [],
        links: {
            home: '/', shop: '/belanja', index: '/koleksi', reset: '/koleksi?locale=id', clear: '/koleksi',
            withoutSearch: '/koleksi?kategori=tas', cart: '/cart/items', favorites: '/favorites',
            locales: ['id', 'en', 'zh', 'fr', 'it'].map((code) => ({ code, label: code.toUpperCase(), url: `/koleksi?locale=${code}` })),
        },
        signedIn: false, isFuzzy: false, searchRecovery: null,
        canonical: 'https://maison.test/koleksi', year: 2026, locale: 'id',
        ...overrides,
    };
}

function renderCatalog(overrides = {}) {
    return renderToStaticMarkup(<Index {...pageProps(overrides)} />);
}

function tagById(markup, id) {
    const match = markup.match(new RegExp(`<[^>]+\\bid="${id}"[^>]*>`));
    expect(match, `Expected element #${id}`).not.toBeNull();
    return match[0];
}

function anchors(markup) {
    return markup.match(/<a\b[^>]*>[\s\S]*?<\/a>/g) || [];
}

describe('FE-MIG native catalog markup (not Blade fallback or browser evidence)', () => {
    it('renders the existing landmarks, IDs, styles and server-provided title/canonical/year', () => {
        const errors = vi.spyOn(console, 'error').mockImplementation(() => {});
        const html = renderCatalog();
        expect(html).toContain('<title>Koleksi — ZAFIRA: La Maison de Lumière</title>');
        expect(html).toContain('head-key="canonical" rel="canonical" href="https://maison.test/koleksi"');
        expect(html).toContain('<a class="catalog-skip" href="#main-catalog">');
        expect(html).toContain('<header class="catalog-header">');
        expect(html).toContain('<main id="main-catalog" class="catalog-container">');
        expect(html).toContain('<h1 class="catalog-title">Koleksi</h1>');
        expect(tagById(html, 'catalog-shop-status')).toContain('role="status" aria-live="polite"');
        expect(tagById(html, 'catalog-feedback')).toBe('<strong id="catalog-feedback">');
        expect(html).toContain('© 2026 ZAFIRA Maison.');
        expect(html).not.toMatch(/<script|<style|name="_token"/);
        expect(errors).not.toHaveBeenCalled();
    });

    it('preserves native GET controls and defaults for every service filter', () => {
        const html = renderCatalog({ filters: {
            locale: 'id', q: '0', category: 4, kategori: ['tas', 'sepatu'], color: 'Biru', material: 'Sutra',
            harga_min: '0', harga_max: '9000000', urut: 'harga', tersedia: '1',
        } });
        const form = html.match(/<form\b[^>]*class="catalog-form"[^>]*>/)[0];
        for (const attribute of ['autoComplete="off"', 'method="GET"', 'action="/koleksi"', 'role="search"', 'aria-label="Filter"']) {
            expect(form).toContain(attribute);
        }
        expect(tagById(html, 'filter-q')).toContain('maxLength="100"');
        expect(tagById(html, 'filter-q')).toContain('value="0"');
        expect(html).toContain('name="category" value="4"');
        const categories = html.match(/<input[^>]+name="kategori"[^>]*>/g);
        expect(categories).toHaveLength(2);
        expect(categories.every((input) => input.includes('checked=""'))).toBe(true);
        expect(html).not.toContain('name="kategori[');
        expect(html).toContain('<fieldset class="catalog-field catalog-category-set"><legend>Kategori</legend>');
        expect(html).toContain('<option value="Biru" selected="">Biru</option>');
        expect(html).toContain('<option value="Sutra" selected="">Sutra</option>');
        expect(html).toContain('<option value="harga" selected="">Harga Terendah</option>');
        expect(tagById(html, 'filter-harga-min')).toContain('value="0"');
        expect(tagById(html, 'filter-harga-max')).toContain('value="9000000"');
        expect(tagById(html, 'filter-harga-max')).toContain('min="0" step="1"');
        expect(html.match(/<input[^>]+name="tersedia"[^>]*>/)[0]).toContain('checked=""');
        expect(html).toContain('style="width:20px;height:20px"');
    });

    it('uses Inertia only for catalog navigation, never home/shop/product links', () => {
        const html = renderCatalog({ chips: [{ label: 'Tas', url: '/koleksi?locale=id' }] });
        for (const anchor of anchors(html)) {
            const href = anchor.match(/href="([^"]*)"/)[1];
            expect(anchor.includes('data-catalog-link="inertia"')).toBe(href.startsWith('/koleksi'));
        }
        expect(html).toContain('data-active-count="1"');
        expect(html).toContain('<span class="catalog-filter-count">(1)</span>');
        expect(html).toContain('aria-label="Hapus filter: Tas"');
        expect(html).toContain('<span aria-hidden="true">✕</span>');
        expect(html).toContain('style="min-height:44px;padding:0 12px;font-size:11px"');
        expect(anchors(html).find((anchor) => anchor.includes('>ID</a>'))).toContain('class="active" aria-current="true"');
        expect(anchors(html).find((anchor) => anchor.includes('>EN</a>'))).toContain('aria-current="false"');
        expect(html).not.toContain('prefetch');
    });

    it('renders server prices, image variants, stock and SET replacement semantics without reformatting', () => {
        const html = renderCatalog({ signedIn: true });
        expect(html).toContain('srcSet="/storage/sac-400.webp 400w, /storage/sac-600.webp 600w, /storage/sac-800.webp 800w"');
        expect(html).toContain('sizes="(max-width: 700px) 50vw, 25vw"');
        expect(html).toContain('decoding="async" alt="Tas sutra biru" loading="lazy" width="400" height="500"');
        expect(html).toContain('<div class="catalog-card-price" translate="no">Rp2.450.000</div>');
        expect(html).toContain('Stok: 3');
        expect(html).toContain('aria-describedby="replace-hint-7">Set 1 item di tas</button>');
        expect(html).toContain('<form action="/cart/items" method="POST">');
        expect(html).toContain('name="product_id" value="7"');
        expect(html).toContain('name="quantity" value="1"');
        expect(tagById(html, 'replace-hint-7')).toContain('style="margin:6px 0 0"');
        expect(html).toContain('data-favorite="7">Simpan ke favorit</button>');
        expect(html).toContain(t.replace_hint);
    });

    it('clamps negative stock to zero and disables cart while retaining quick view for guests', () => {
        const html = renderCatalog({ products: { data: [product({ stock: -2, image: null, quickImage: null, imageSrcset: null, material: null, color: null })], total: 1, current_page: 1, last_page: 1 } });
        expect(html).toContain('Stok: 0');
        expect(html).toContain('aria-describedby="replace-hint-7" disabled="">Stok habis</button>');
        expect(html).toContain('data-quick-view=""');
        expect(html).not.toContain('data-favorite');
        expect(html).not.toContain('src=""');
        expect(html).toContain('<div class="catalog-card-media"></div>');
    });

    it('escapes product and translation content as text, including quick-view description', () => {
        const unsafe = '<img src=x onerror="alert(1)">';
        const item = product({ title: unsafe, description: unsafe, material: unsafe });
        const html = renderCatalog({ products: { data: [item], total: 1, current_page: 1, last_page: 1 } });
        const dialog = renderToStaticMarkup(<CatalogQuickView selection={{ product: item }} t={t} cartUrl="/cart/items" onClose={() => {}} onSetQuantity={() => {}} />);
        expect(html).not.toContain(unsafe);
        expect(dialog).not.toContain(unsafe);
        expect(html).toContain('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;');
        expect(dialog).toContain('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;');
    });

    it('distinguishes empty catalog, search 0, narrow recovery and fuzzy results', () => {
        const products = { data: [], total: 0, current_page: 1, last_page: 1 };
        const empty = renderCatalog({ products });
        expect(empty).toContain(t.empty_hint);
        expect(empty).not.toContain(t.search_empty_hint);
        expect(empty).not.toContain('catalog-pagination');
        const search = renderCatalog({ products, filters: { q: '0' }, searchRecovery: 'narrow' });
        expect(search).toContain(t.search_empty_hint);
        expect(search).toContain(t.search_narrow);
        expect(search).toContain('href="/koleksi?kategori=tas"');
        expect(search).toContain(t.clear_search);
        expect(search).not.toContain(t.empty_hint);
        expect(renderCatalog({ isFuzzy: true, searchRecovery: 'similar' })).toContain(t.fuzzy_notice);
        expect(renderCatalog()).not.toContain(t.fuzzy_notice);
    });

    it.each([
        [1, null, '/koleksi?kategori=tas&kategori=sepatu&page=2', t.prev],
        [3, '/koleksi?kategori=tas&kategori=sepatu&page=2', null, t.next],
    ])('preserves pagination URLs and disabled boundaries on page %i', (current_page, prev_url, next_url, disabled) => {
        const html = renderCatalog({ products: { data: [product()], total: 36, current_page, last_page: 3, prev_url, next_url } });
        expect(html).toContain(`Halaman ${current_page} / 3`);
        expect(html).toContain(`aria-disabled="true" style="opacity:0.4">${disabled}</span>`);
        expect(html).toContain('href="/koleksi?kategori=tas&amp;kategori=sepatu&amp;page=2"');
        expect(anchors(html).find((anchor) => anchor.includes('page=2'))).toContain('data-catalog-link="inertia"');
    });

    it('keeps the native dialog labels, IDs and ordinary detail URL, disabling sold-out quantity', () => {
        const renderDialog = (stock) => renderToStaticMarkup(<CatalogQuickView selection={{ product: product({ stock }) }} t={t} cartUrl="/cart/items" onClose={() => {}} onSetQuantity={() => {}} />);
        const available = renderDialog(3);
        expect(tagById(available, 'catalog-quick-dialog')).toContain('aria-modal="true" aria-labelledby="dialog-title"');
        expect(tagById(available, 'catalog-quick-dialog')).not.toContain('open=');
        expect(tagById(available, 'dialog-close-btn')).toContain('aria-label="Tutup"');
        expect(tagById(available, 'dialog-qty')).toContain('min="1" max="99" aria-describedby="dialog-replace-hint"');
        expect(tagById(available, 'dialog-qty')).not.toContain('disabled');
        expect(tagById(available, 'dialog-add-button')).not.toContain('disabled');
        expect(tagById(available, 'dialog-image')).toContain('src="/storage/sac-800.webp"');
        expect(tagById(available, 'dialog-detail-link')).toContain('href="/produk/sac-lumiere"');
        expect(tagById(available, 'dialog-detail-link')).not.toContain('data-catalog-link');
        const soldOut = renderDialog(0);
        expect(tagById(soldOut, 'dialog-qty')).toContain('disabled=""');
        expect(tagById(soldOut, 'dialog-add-button')).toContain('disabled=""');
        expect(soldOut).toContain('>Stok habis</button>');
    });

    it('keys local state to the Inertia URL and locale so navigation cannot keep old defaults', () => {
        const props = pageProps();
        const initial = Index(props).key;
        inertia.url = '/koleksi?q=baru';
        expect(Index(props).key).not.toBe(initial);
        expect(Index({ ...props, locale: 'fr' }).key).not.toBe(Index(props).key);
        expect(renderCatalog({ filters: { q: 'baru', kategori: ['sepatu'], color: 'Emas' } })).toContain('<option value="Emas" selected="">Emas</option>');
    });
});
