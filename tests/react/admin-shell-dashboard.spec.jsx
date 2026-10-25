// @vitest-environment jsdom
// FR-ADM-20261025: overview hierarchy must not replace the seven-region snapshot contract.
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import Dashboard from '../../resources/js/pages/Admin/Dashboard';
import AuthEvents from '../../resources/js/pages/Admin/AuthEvents';
import OperationsSummary from '../../resources/js/pages/Admin/OperationsSummary';
import { TABLES } from '../../resources/js/lib/admin-dashboard';

const routing = vi.hoisted(() => ({ push: vi.fn(), replace: vi.fn(), clearHistory: vi.fn(), decryptHistory: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: routing }));
const words = new Proxy({}, { get: (_, key) => String(key) });
function props() {
    return {
        locale: 'id', csrfToken: 'test-only', copy: { common: words, dashboard: words, table: words },
        urls: { dashboard: new URL('/admin', window.location.origin).href, home: '/', categoryStore: '/admin/categories', productStore: '/admin/products', report: '/admin/reports/orders.pdf', password: '/admin/password' },
        dashboardSnapshot: {
            contract: 'admin-dashboard.v1', url: new URL('/admin', window.location.origin).href, tableState: {}, filters: {}, categoryOptions: [],
            stats: { orders: 41, products: 23, concierge: 0, users: 19, categories: 4, backups: 17, subscribers: 8 },
            tables: Object.fromEntries(Object.keys(TABLES).map(key => [key, { rows: [], pagination: { total: 0, current: 1, last: 1, next: null, previous: null } }])),
        },
    };
}
let host, root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    window.history.replaceState({}, '', '/admin');
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.unstubAllGlobals(); vi.clearAllMocks(); });
const mount = async value => act(async () => root.render(<Dashboard {...value} />));

it('uses actual all-record snapshot counts, including zero, with links to existing operational regions', async () => {
    const value = props(); await mount(value);
    const cards = [...host.querySelectorAll('[data-dashboard-metric]')];
    expect(cards).toHaveLength(7);
    for (const card of cards) {
        expect(card.querySelector('dd').textContent).toBe(String(value.dashboardSnapshot.stats[card.dataset.dashboardMetric]));
        expect(card.querySelector('dt').textContent).not.toBe('');
        expect(host.querySelector(card.querySelector('a').getAttribute('href'))).not.toBeNull();
    }
    expect(cards.slice(0, 4).map(card => card.dataset.dashboardMetric)).toEqual(['orders', 'products', 'concierge', 'users']);
    expect(host.querySelector('[data-dashboard-metric="concierge"] dd').textContent).toBe('0');
    expect(host.querySelectorAll('canvas, svg, img')).toHaveLength(0);
});

it('puts the existing orders region first without losing any table, native action or external draft', async () => {
    await mount(props());
    expect([...host.querySelectorAll('[data-admin-table]')].map(node => node.dataset.adminTable)).toEqual(['pesanan', 'kategori', 'produk', 'user', 'audit', 'concierge', 'subscriber']);
    for (const action of ['/admin/categories', '/admin/products', '/admin/reports/orders.pdf', '/admin/password']) {
        const form = host.querySelector(`form[action="${action}"]`);
        expect(form).not.toBeNull(); expect(form.closest('[data-admin-table]')).toBeNull();
    }
    const input = host.querySelector('#cat_title'); input.value = 'Unsubmitted category';
    expect(host.querySelector('[data-dashboard-metric="products"] a').getAttribute('href')).toBe('#panel-produk');
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Unsubmitted category');
    expect(routing.push).not.toHaveBeenCalled(); expect(routing.replace).not.toHaveBeenCalled();
});

it('unconfirmed Inertia props cannot overwrite the displayed summary or local drafts', async () => {
    const value = props(); await mount(value);
    const input = host.querySelector('#cat_title'); input.value = 'Keep';
    const next = props(); next.dashboardSnapshot.stats.orders = 99; await mount(next);
    expect(host.querySelector('[data-dashboard-metric="orders"] dd').textContent).toBe('41');
    expect(input.isConnected).toBe(true); expect(input.value).toBe('Keep');
});

it('read pages use the shared shell typography and report unavailable data without invented zeroes', async () => {
    await act(async () => root.render(<AuthEvents locale="id" copy={{ common: words, events: words }} url="/admin/auth-events" filters={{}} eventOptions={[]} rows={[]} pagination={{ total: 0, current: 1, last: 1 }} />));
    expect(host.querySelector('.admin-page-intro')).not.toBeNull();
    expect(host.querySelector('form').method).toBe('get');
    expect(host.querySelector('[role="region"]').tabIndex).toBe(0);
    await act(async () => root.render(<OperationsSummary url="/admin/reports/operations-summary" filters={{ period: '7d' }} report={null} />));
    expect(host.querySelector('.admin-operations')).not.toBeNull();
    expect(host.querySelector('[data-ops-report] .admin-empty').textContent).toContain('Ringkasan belum tersedia');
    expect(host.querySelectorAll('[data-ops-report] table')).toHaveLength(0);
});

it('Blade fallback retains the same overview counts, region order and seven replace boundaries', () => {
    const blade = readFileSync('resources/views/admin/dashboard.blade.php', 'utf8');
    expect([...blade.matchAll(/data-admin-table="([^"]+)"/g)].map(match => match[1])).toEqual(['pesanan', 'kategori', 'produk', 'user', 'audit', 'concierge', 'subscriber']);
    expect([...blade.matchAll(/data-dashboard-metric="([^"]+)"/g)].map(match => match[1])).toEqual(['orders', 'products', 'concierge', 'users', 'categories', 'backups', 'subscribers']);
    for (const key of Object.keys(props().dashboardSnapshot.stats)) expect(blade).toContain(`{{ $stats['${key}'] }}`);
    expect(blade).toContain('data-admin-dashboard-fallback-loader');
});
