// @vitest-environment jsdom
// FR-FE-COMP-20261023: native controls, local ownership, atomic coordinator integration.
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import Dashboard from '../../resources/js/pages/Admin/Dashboard';
import { TABLES } from '../../resources/js/lib/admin-dashboard';
const routing = vi.hoisted(() => ({ push: vi.fn(), replace: vi.fn(), clearHistory: vi.fn(), decryptHistory: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: routing }));
const words = new Proxy({}, { get: (_, key) => String(key) });
const localUrl = path => new URL(path, window.location.origin).href;
function snapshot(url = localUrl('/admin')) {
    return { contract: 'admin-dashboard.v1', url, tableState: {}, filters: { ksort: 'cat_id', kdir: 'asc', psort: 'product_id', pdir: 'desc', osort: 'id', odir: 'desc', usort: 'created_at', udir: 'desc', bsort: 'created_at', bdir: 'desc', csort: 'created_at', cdir: 'desc', ssort: 'created_at', sdir: 'desc' }, stats: { users: 1, backups: 1, concierge: 1, subscribers: 1, products: 1, categories: 1, orders: 1 }, categoryOptions: [{ id: 1, title: 'Silk' }], tables: {
        kategori: { rows: [{ id: 1, title: 'Silk', count: 1, update: '/admin/categories/1', destroy: '/admin/categories/1' }] },
        produk: { rows: [{ id: 2, title: 'Scarf', slug: 'scarf', price: 'Rp100', category: 1, stock: 2, active: true, update: '/admin/products/2', destroy: '/admin/products/2', show: '/products/scarf' }] },
        pesanan: { rows: [{ id: 3, date: '23 Oct 2026 UTC', customer: '<script>bad</script>', price: 'Rp100', status: 'pending', transitions: ['cancelled'], update: '/admin/orders/3', invoice: '/admin/orders/3/invoice' }] },
        user: { rows: [{ id: 4, name: 'Client', email: 'client@example.test', phone: '-', address: 'Address', date: '23 Oct 2026' }] },
        audit: { rows: [{ id: 5, userId: 4, name: 'Client', email: 'client@example.test', phone: '-', date: '23 Oct 2026' }] },
        concierge: { rows: [{ id: 6, name: 'Client', email: 'client@example.test', status: 'new', transitions: ['new', 'contacted', 'confirmed', 'cancelled'], update: '/admin/concierge/6', notes: '<img src=x>', service: 'atelier', schedule: '-' }] },
        subscriber: { rows: [{ id: 7, email: 'client@example.test', date: '23 Oct 2026', destroy: '/admin/subscribers/7' }] },
    } };
}
function props() {
    const value = snapshot(); for (const table of Object.values(value.tables)) table.pagination = { total: 1, current: 1, last: 2, previous: null, next: localUrl('/admin?upage=2#panel-user') };
    return { dashboardSnapshot: value, copy: { common: words, dashboard: words, table: words }, csrfToken: 'test-csrf', locale: 'id', urls: { dashboard: localUrl('/admin'), home: '/', login: '/admin/login', categoryStore: '/admin/categories', productStore: '/admin/products', report: '/admin/reports/orders.pdf', password: '/admin/password', enrol: '/admin/totp/enrol', operations: '/admin/reports/operations-summary' } };
}
let host, root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true); window.history.replaceState({}, '', '/admin');
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    routing.decryptHistory.mockImplementation(async () => window.history.state.page);
    for (const method of ['push', 'replace']) routing[method].mockImplementation(options => {
        const page = { component: options.component, url: options.url, encryptHistory: options.encryptHistory, props: options.props({ errors: {} }) };
        window.history[method === 'push' ? 'pushState' : 'replaceState']({ page }, '', options.url); options.onSuccess(); options.onFinish();
    });
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); vi.unstubAllGlobals(); vi.clearAllMocks(); });
const mount = async value => act(async () => root.render(<Dashboard {...value} />));
const submit = async form => act(async () => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
const response = value => ({ ok: true, redirected: false, headers: new Headers({ 'Content-Type': 'application/json' }), json: async () => value });
it('renders seven semantic tables, all actions and external forms outside replace regions', async () => {
    await mount(props()); expect(host.querySelectorAll('[data-admin-table]')).toHaveLength(7);
    expect(host.querySelectorAll('table caption')).toHaveLength(7); expect(host.querySelectorAll('[role="region"][tabindex="0"]')).toHaveLength(7);
    expect(host.querySelectorAll('script,img')).toHaveLength(0); expect(host.textContent).toContain('<script>bad</script>');
    for (const action of ['/admin/categories', '/admin/products', '/admin/reports/orders.pdf', '/admin/password']) {
        const form = host.querySelector(`form[action="${action}"]`); expect(form).not.toBeNull(); expect(form.closest('[data-admin-table]')).toBeNull();
    }
    expect(host.querySelector('form[action="/admin/reports/orders.pdf"]').method).toBe('get');
    expect(host.querySelector('a[href="/admin/orders/3/invoice"]')).not.toBeNull();
    expect(host.querySelector('select[name="status"] option[value="paid"]')).toBeNull();
    expect(host.querySelectorAll('#panel-keamanan input[type="password"]')).toHaveLength(3);
    expect(host.querySelector('#panel-kategori [aria-sort="ascending"]')).not.toBeNull();
});
it('commits all seven regions together and preserves external create/report/password nodes and values', async () => {
    const value = props(); await mount(value);
    const nodes = ['#cat_title', '#create-product_title', '#report-from', '#password'].map(selector => host.querySelector(selector));
    const drafts = ['New category', 'New product', '2026-10-23', 'UnsubmittedPassword!1'];
    nodes.forEach((node, index) => { node.value = drafts[index]; });
    const next = structuredClone(value.dashboardSnapshot); next.url = localUrl('/admin?uq=next'); next.tables.kategori.rows[0].title = 'Updated'; next.tables.user.rows[0].name = 'Next client';
    vi.stubGlobal('fetch', vi.fn(async () => response(next)));
    const filter = host.querySelector('[data-admin-table-form="user"]'); filter.querySelector('[name="uq"]').value = 'next'; await submit(filter);
    expect(routing.push).toHaveBeenCalledTimes(1); expect(host.querySelector('[role="alert"]')?.textContent).toBeUndefined();
    expect(host.querySelector('#panel-kategori input[name="cat_title"]').value).toBe('Updated'); expect(host.textContent).toContain('Next client');
    nodes.forEach((node, index) => { expect(node.isConnected).toBe(true); expect(node.value).toBe(drafts[index]); });
    expect(routing.push).toHaveBeenCalledTimes(1); expect(routing.push.mock.calls[0][0]).toMatchObject({ encryptHistory: true, preserveState: true, preserveScroll: true });
});
it('new Inertia props alone never replace rendered tables or inline drafts', async () => {
    const value = props(); await mount(value); const input = host.querySelector('#panel-kategori input[name="cat_title"]'); input.value = 'Local draft';
    const next = props(); next.dashboardSnapshot.tables.kategori.rows[0].title = 'Unconfirmed'; await mount(next);
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Local draft');
});
it('refuses late edits while read is pending, keeping every region and URL', async () => {
    const value = props(); await mount(value); let resolve;
    vi.stubGlobal('fetch', vi.fn(() => new Promise(done => { resolve = done; })));
    await submit(host.querySelector('[data-admin-table-form="user"]'));
    const input = host.querySelector('#panel-kategori input[name="cat_title"]'); input.value = 'Late'; window.confirm.mockReturnValue(false);
    await act(async () => resolve(response(value.dashboardSnapshot)));
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Late'); expect(routing.push).not.toHaveBeenCalled();
});
it('locks only inline controls during client commit, retaining local data on URL mismatch', async () => {
    const value = props(); await mount(value); const input = host.querySelector('#panel-kategori input[name="cat_title"]'); input.value = 'Keep';
    let visit; routing.push.mockImplementation(options => { visit = options; }); vi.stubGlobal('fetch', vi.fn(async () => response({ ...value.dashboardSnapshot, url: localUrl('/admin?uq=next') })));
    await submit(host.querySelector('[data-admin-table-form="user"]'));
    expect(input.disabled).toBe(true); expect(host.querySelector('#cat_title').disabled).toBe(false); expect(host.querySelector('#password').disabled).toBe(false);
    await act(async () => visit.onSuccess()); expect(input.value).toBe('Keep'); expect(input.disabled).toBe(false); expect(host.textContent).toContain('Navigasi tabel dihentikan');
});
it('density and selection survive storage denial; clipboard selection resets on snapshot commit', async () => {
    const value = props(); await mount(value); vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('denied'); });
    const density = host.querySelector('#panel-user .table-density-controls button'); await act(async () => density.click()); expect(density.getAttribute('aria-pressed')).toBe('false');
    const clipboard = { writeText: vi.fn(async () => {}) }; Object.defineProperty(navigator, 'clipboard', { configurable: true, value: clipboard });
    const all = host.querySelector('.table-selection-toolbar input'); await act(async () => all.click()); await act(async () => host.querySelector('.table-selection-toolbar button').click()); expect(clipboard.writeText).toHaveBeenCalledWith('#3');
    vi.stubGlobal('fetch', vi.fn(async () => response(value.dashboardSnapshot)));
    await submit(host.querySelector('[data-admin-table-form="user"]'));
    expect(host.querySelector('.table-selection-toolbar input').checked).toBe(false);
    expect(host.querySelector('.table-selection-toolbar input').indeterminate).toBe(false);
    expect(host.querySelector('.table-selection-toolbar button').disabled).toBe(true);
    expect(host.querySelector('#panel-user .table-density-controls button').getAttribute('aria-pressed')).toBe('false');
});
it('saves order once with CSRF/method spoof, refresh failure never replays the mutation', async () => {
    const value = props(); await mount(value); const fetch = vi.fn().mockResolvedValueOnce(response({ order: { id: 3, status: 'cancelled' } })).mockRejectedValueOnce(new Error('offline')); vi.stubGlobal('fetch', fetch);
    const form = host.querySelector('[data-inline-draft][action="/admin/orders/3"]'); form.querySelector('select').value = 'cancelled'; await submit(form);
    expect(fetch).toHaveBeenCalledTimes(2); const options = fetch.mock.calls[0][1]; expect(options.method).toBe('POST'); expect(options.body.get('_method')).toBe('PATCH'); expect(options.body.get('_token')).toBe('test-csrf');
    expect(host.textContent).toContain('saved_refresh_failed'); expect(routing.push).not.toHaveBeenCalled();
});
it('retains external category choice even if new snapshot removes or reorders its options', async () => {
    const value = props(); value.dashboardSnapshot.categoryOptions.push({ id: 8, title: 'Linen' }); await mount(value);
    const choice = host.querySelector('#create-product-cat'); choice.value = '8';
    const next = structuredClone(value.dashboardSnapshot); next.categoryOptions = [{ id: 9, title: 'New category' }, { id: 1, title: 'Renamed silk' }];
    vi.stubGlobal('fetch', vi.fn(async () => response(next))); await submit(host.querySelector('[data-admin-table-form="user"]'));
    expect(choice.isConnected).toBe(true); expect(choice.value).toBe('8');
    expect([...choice.options].map(option => option.textContent)).toEqual(['Silk', 'Linen']);
    expect([...host.querySelector('#panel-produk select[name="product_cat"]').options].map(option => option.value)).toEqual(['9', '1']);
});
it.each(['refused', 'offline'])('Back %s restores rendered URL with replace only and retains all drafts', async mode => {
    const value = props(); await mount(value);
    const next = structuredClone(value.dashboardSnapshot); next.url = localUrl('/admin?uq=next'); next.tables.user.rows[0].name = 'Rendered next';
    vi.stubGlobal('fetch', vi.fn(async () => response(next))); await submit(host.querySelector('[data-admin-table-form="user"]'));
    const rendered = window.location.href, input = host.querySelector('#panel-kategori input[name="cat_title"]'), external = host.querySelector('#password'); input.value = 'Inline draft'; external.value = 'Private draft'; external.focus();
    routing.push.mockClear(); routing.replace.mockClear();
    const fetch = vi.fn(async () => { throw new Error('offline'); }); vi.stubGlobal('fetch', fetch); window.confirm.mockReturnValue(mode !== 'refused');
    // Browser traversal has already moved the URL before dispatching popstate.
    window.history.replaceState({}, '', '/admin');
    const coreListener = vi.fn(); window.addEventListener('popstate', coreListener);
    try { await act(async () => window.dispatchEvent(new PopStateEvent('popstate', { state: {} }))); }
    finally { window.removeEventListener('popstate', coreListener); }
    expect(coreListener).not.toHaveBeenCalled(); expect(window.location.href).toBe(rendered);
    expect(routing.push).not.toHaveBeenCalled(); expect(routing.replace).toHaveBeenCalledTimes(1);
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Inline draft'); expect(external.value).toBe('Private draft'); expect(document.activeElement).toBe(external);
    expect(fetch).toHaveBeenCalledTimes(mode === 'refused' ? 0 : 1);
});
it('rejects a cross-origin snapshot without changing history or local drafts', async () => {
    const value = props(); await mount(value); const input = host.querySelector('#panel-kategori input[name="cat_title"]'); input.value = 'Keep local';
    vi.stubGlobal('fetch', vi.fn(async () => response({ ...value.dashboardSnapshot, url: 'https://other.example/admin' })));
    await submit(host.querySelector('[data-admin-table-form="user"]'));
    expect(routing.push).not.toHaveBeenCalled(); expect(routing.replace).not.toHaveBeenCalled(); expect(window.location.href).toBe(localUrl('/admin'));
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Keep local'); expect(host.querySelector('[role="alert"]').textContent).toContain('network_error');
});
it('Back with idle focus does not steal it to the previous filter target', async () => {
    const value = props(); await mount(value); vi.stubGlobal('fetch', vi.fn(async () => response(value.dashboardSnapshot)));
    await submit(host.querySelector('[data-admin-table-form="user"]')); document.activeElement.blur();
    const next = structuredClone(value.dashboardSnapshot); next.url = localUrl('/admin?upage=2');
    vi.stubGlobal('fetch', vi.fn(async () => response(next))); window.history.replaceState({}, '', '/admin?upage=2#panel-user');
    await act(async () => window.dispatchEvent(new PopStateEvent('popstate', { state: {} })));
    expect(document.activeElement).toBe(document.body); expect(routing.replace).toHaveBeenCalledTimes(1);
});
it('successful Back reads a fresh snapshot, replaces history and preserves external forms', async () => {
    const value = props(); await mount(value); const external = host.querySelector('#cat_title'); external.value = 'New category draft'; external.focus();
    const next = structuredClone(value.dashboardSnapshot); next.url = localUrl('/admin?upage=2'); next.tables.user.rows[0].name = 'Back snapshot';
    const fetch = vi.fn(async () => response(next)); vi.stubGlobal('fetch', fetch);
    window.history.replaceState({}, '', '/admin?upage=2#panel-user');
    await act(async () => window.dispatchEvent(new PopStateEvent('popstate', { state: {} })));
    expect(fetch).toHaveBeenCalledTimes(1); expect(routing.push).not.toHaveBeenCalled(); expect(routing.replace).toHaveBeenCalledTimes(1);
    expect(host.textContent).toContain('Back snapshot'); expect(external.isConnected).toBe(true); expect(external.value).toBe('New category draft'); expect(document.activeElement).toBe(external);
});
it.each(['inline', 'external', 'idle'])('commit-time focus follows %s ownership, not the original trigger', async ownership => {
    const value = props(); await mount(value); let resolve;
    vi.stubGlobal('fetch', vi.fn(() => new Promise(done => { resolve = done; })));
    await submit(host.querySelector('[data-admin-table-form="user"]'));
    const external = host.querySelector('#cat_title');
    if (ownership === 'inline') host.querySelector('#panel-produk input[name="stock"]').focus();
    else if (ownership === 'external') external.focus();
    await act(async () => resolve(response(value.dashboardSnapshot)));
    expect(document.activeElement).toBe(ownership === 'external' ? external : host.querySelector(`#panel-${ownership === 'inline' ? 'produk' : 'user'} h2`));
});
it('successful order mutation refreshes all tables once with replace and keeps external drafts', async () => {
    const value = props(); await mount(value); const external = host.querySelector('#create-product_title'); external.value = 'New product draft';
    const next = structuredClone(value.dashboardSnapshot); next.tables.pesanan.rows[0].status = 'cancelled'; next.tables.pesanan.rows[0].transitions = []; next.tables.user.rows[0].name = 'Refreshed peer';
    const fetch = vi.fn().mockResolvedValueOnce(response({ order: { id: 3, status: 'cancelled' } })).mockResolvedValueOnce(response(next)); vi.stubGlobal('fetch', fetch);
    const form = host.querySelector('[data-inline-draft][action="/admin/orders/3"]'); form.querySelector('select').value = 'cancelled'; await submit(form);
    expect(fetch).toHaveBeenCalledTimes(2); expect(fetch.mock.calls[1][1].headers['X-Zafira-Dashboard']).toBe('snapshot');
    expect(host.querySelector('#panel-pesanan .badge-cancelled')).not.toBeNull(); expect(host.querySelector('form[action="/admin/orders/3"]')).toBeNull(); expect(host.textContent).toContain('Refreshed peer');
    expect(external.isConnected).toBe(true); expect(external.value).toBe('New product draft'); expect(routing.replace).toHaveBeenCalledTimes(1); expect(routing.push).not.toHaveBeenCalled(); expect(host.querySelector('[data-table-announcement]').textContent).toBe('saved');
});
it.each(['id', 'en', 'fr', 'it', 'zh'])('uses actual %s dictionary users_empty rather than a Proxy-supplied key', async locale => {
    const source = readFileSync(`lang/${locale}/admin_ui.php`, 'utf8');
    const match = source.match(/'users_empty'\s*=>\s*'((?:\\.|[^'\\])*)'/);
    expect(match).not.toBeNull(); expect(source).not.toMatch(/'user_empty'\s*=>/);
    const value = props(); value.copy = { ...value.copy, dashboard: { ...Object.fromEntries(Object.keys(value.dashboardSnapshot.filters).map(key => [key, key])), users_empty: match[1] } };
    value.dashboardSnapshot.tables.user.rows = []; value.dashboardSnapshot.tables.user.pagination = { total: 0, current: 1, last: 1, previous: null, next: null };
    await mount(value); expect(host.querySelector('#panel-user .admin-empty').textContent).toBe(match[1]);
});
it('empty state covers all regions without hiding external controls', async () => {
    const value = props(); Object.values(value.dashboardSnapshot.tables).forEach(table => { table.rows = []; table.pagination = { total: 0, current: 1, last: 1, next: null, previous: null }; }); await mount(value);
    expect(host.querySelectorAll('[data-admin-table]')).toHaveLength(7); expect(host.querySelectorAll('.admin-empty')).toHaveLength(7); expect(host.querySelector('#form-produk').open).toBe(true);
});
