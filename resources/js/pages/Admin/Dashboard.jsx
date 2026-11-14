import { useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import { router } from '@inertiajs/react';
import { createDashboardCoordinator, draftSnapshot, freezeInline, installTraversalBridge, interpolate as text, readDashboard, TABLES } from '../../lib/admin-dashboard';
import Table, { CheckAll, definitions, Head, PostForm } from '../../components/admin-dashboard/Table';
import { CategoryCreate, ProductCreate, ReportForm, SecurityForm } from '../../components/admin-dashboard/ExternalForms';
import '../../../css/admin-dashboard.css';

const PANEL_VIEWS = new Set(['pesanan', 'kategori', 'produk', 'user', 'audit', 'concierge', 'subscriber', 'keamanan']);
function resolveWorkspaceView(hash = '') {
    if (hash === '#semua') return 'all';
    if (hash.startsWith('#panel-')) {
        const key = hash.slice('#panel-'.length);
        if (PANEL_VIEWS.has(key)) return key;
    }
    return 'overview';
}

export default function Dashboard(props) {
    const { copy, csrfToken, urls, locale, validationErrors = [], flashMessage } = props;
    const { common: c, dashboard: d, table: messages } = copy;
    const [snapshot, setSnapshot] = useState(props.dashboardSnapshot);
    const [activeView, setActiveView] = useState(() => resolveWorkspaceView(globalThis.location?.hash || ''));
    const [epoch, setEpoch] = useState(0);
    const [busy, setBusy] = useState(false), [locked, setLocked] = useState(false);
    const [fatal, setFatal] = useState(false), [status, setStatus] = useState(flashMessage || '');
    const [error, setError] = useState(''), [saving, setSaving] = useState(false);
    const [selected, setSelected] = useState([]);
    const root = useRef(null), coordinator = useRef(null), focusTarget = useRef(null), mounted = useRef(true);
    const initialOpen = useRef(snapshot.tables.produk.rows.length === 0 && !snapshot.filters.pq);
    const defs = definitions(c, d);
    useEffect(() => {
        const syncView = () => {
            if (mounted.current) setActiveView(resolveWorkspaceView(window.location.hash || ''));
        };
        window.addEventListener('hashchange', syncView);
        return () => window.removeEventListener('hashchange', syncView);
    }, []);
    useEffect(() => {
        mounted.current = true;
        const instance = createDashboardCoordinator({
            initial: props.dashboardSnapshot, router, location: window.location,
            read: readDashboard, drafts: () => draftSnapshot(root.current), confirm: () => window.confirm(messages.unsaved_confirm),
            freeze: () => freezeInline(root.current), onBusy: value => { if (mounted.current) setBusy(value); },
            onLock: value => { if (mounted.current) setLocked(value); },
            onFatal: () => { if (mounted.current) { setFatal(true); setError('Riwayat halaman tidak dapat dikonfirmasi. Data dan draf tetap dipertahankan. Navigasi tabel dihentikan; jangan kirim ulang aksi yang sudah tersimpan.'); } },
            onStatus: key => {
                if (!mounted.current) return;
                if (['network_error', 'session_inactive'].includes(key)) setError(messages[key]);
                else if (key === 'loading') { setError(''); setStatus(messages.loading); }
                else if (!key) setStatus('');
            },
            onCommit: (next, url, { traversal }) => {
                if (!mounted.current) return;
                const active = document.activeElement;
                const inside = active?.closest?.('[data-admin-table]')?.getAttribute('data-admin-table');
                const idle = !active || active === document.body;
                const nextHash = new URL(url, window.location.href).hash;
                const anchor = nextHash.slice('#panel-'.length);
                const requestedKey = Object.hasOwn(TABLES, anchor) ? anchor : focusTarget.current;
                const target = inside || (!traversal && idle ? requestedKey : null);
                flushSync(() => {
                    setSnapshot(next);
                    setEpoch(value => value + 1);
                    setSelected([]);
                    if (nextHash) setActiveView(resolveWorkspaceView(nextHash));
                });
                const panel = root.current?.querySelector(`[data-admin-table="${target}"]`);
                if (target) panel?.querySelector('h2')?.focus();
                const key = requestedKey || 'kategori';
                setStatus(`${defs[key].heading}. ${text(c.showing, { count: next.tables[key].rows.length, total: next.tables[key].pagination.total })}`);
            },
        });
        coordinator.current = instance;
        const remove = installTraversalBridge(window, instance);
        return () => { mounted.current = false; remove(); };
    }, []);
    function navigate(href, key, options) { focusTarget.current = key; return coordinator.current?.navigate(href, options); }
    const shared = { urls, csrfToken, copy };
    const columns = (key, values) => <Head columns={values} sort={snapshot.filters[TABLES[key][1]]} dir={snapshot.filters[TABLES[key][2]]} />;
    const badge = value => <span className={`badge-status badge-${value}`}>{d[value] || value}</span>;
    const save = label => <button type="submit" aria-label={label}>{c.save}</button>;
    const destroy = (row, message, label) => <PostForm action={row.destroy} method="DELETE" token={csrfToken} confirmMessage={message}><button className="status-form button danger" type="submit" aria-label={label}>{c.delete}</button></PostForm>;
    const tableProps = key => ({ tableKey: key, definition: defs[key], snapshot, copy, base: urls.dashboard, navigate, busy, locked: locked || fatal || saving, epoch });
    async function updateOrder(event) {
        event.preventDefault();
        const form = event.currentTarget, body = new FormData(form), target = body.get('status');
        if (target === 'cancelled' && !window.confirm(messages.cancel_confirm)) { form.reset(); return; }
        const instance = coordinator.current;
        if (!instance?.beginWrite()) { setStatus(messages.saving); return; }
        setSaving(true); setError(''); setStatus(messages.saving);
        const fields = [...form.querySelectorAll('input,select,button')], disabled = fields.map(field => field.disabled);
        fields.forEach(field => { field.disabled = true; });
        let saved = false;
        try {
            const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', redirect: 'error', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body });
            let data;
            try { data = await response.json(); } catch { data = null; }
            if (instance.stopped) return;
            if (!response.ok || response.redirected || !data?.order || data.order.status !== target) {
                setError(data?.errors?.status?.[0] || (response.ok ? messages.save_failed : data?.message) || messages.save_failed);
                return;
            }
            saved = true;
            const select = form.querySelector('select');
            if (select) [...select.options].forEach(option => { option.defaultSelected = option.value === target; });
        } catch {
            if (!instance.stopped) setError(messages.save_failed);
        } finally {
            fields.forEach((field, index) => { field.disabled = disabled[index]; });
            if (mounted.current) setSaving(false);
            const queued = instance.endWrite();
            if (saved && !queued && !instance.stopped) {
                const refreshed = await navigate(instance.url, 'pesanan', { replace: true, refresh: true });
                if (mounted.current && !instance.stopped) {
                    if (refreshed === true) setStatus(messages.saved);
                    else if (refreshed !== 'superseded' && refreshed !== 'queued') { setError(messages.saved_refresh_failed); setStatus(messages.saved_refresh_failed); }
                }
            }
        }
    }
    const orders = snapshot.tables.pesanan.rows;
    const hasMsiKpis = Number.isInteger(snapshot.stats.revenue_paid);
    const rp = value => 'Rp' + Number(value || 0).toLocaleString('id-ID');
    const selection = <div className="table-selection-toolbar"><label><CheckAll checked={orders.length > 0 && selected.length === orders.length} mixed={selected.length > 0 && selected.length < orders.length} onChange={event => setSelected(event.target.checked ? orders.map(row => row.id) : [])} />{d.select_all}</label><span className="table-selection-count" aria-live="polite">{text(c.selected, { count: selected.length })}</span><button className="button button-dark" type="button" disabled={!selected.length} onClick={async () => { try { await navigator.clipboard.writeText(selected.map(id => `#${id}`).join(', ')); setStatus(messages.copied); } catch { setStatus(messages.copy_failed); } }}>{d.copy_orders}</button></div>;
    const showOverview = event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
        event.preventDefault();
        window.history.pushState(null, '', window.location.pathname + window.location.search);
        setActiveView('overview');
        window.dispatchEvent(new Event('hashchange'));
    };
    return <div ref={root} className="native-admin-dashboard" data-workspace-view={activeView} lang={locale}>
        <p className="admin-page-intro">{d.intro}</p>
        <p className="sr-only" data-table-announcement role="status" aria-live="polite">{status}</p>
        {(flashMessage || status === messages.copied || status === messages.copy_failed) && <div className="admin-flash-banner" role="status"><span>{status}</span></div>}
        {urls.enrol && <div className="admin-totp-callout" role="status"><p className="admin-totp-copy">{d.totp_prompt || d.enable_totp}</p><a className="button button-sapphire admin-totp-cta" href={urls.enrol}>{d.enable_totp}</a></div>}
        {validationErrors.length > 0 && <div className="auth-errors" role="alert"><ul>{validationErrors.map((message, index) => <li key={index}>{message}</li>)}</ul></div>}
        {error && <div className="auth-errors" role="alert"><p>{error}</p>{error === messages.session_inactive && <a href={urls.login}>{messages.login_again}</a>}{!fatal && <button type="button" className="button button-dark" disabled={locked || saving} onClick={() => navigate(coordinator.current.url, focusTarget.current || 'kategori', { replace: true, refresh: true })}>Coba lagi</button>}</div>}
        <div className="admin-overview">
            {hasMsiKpis && <dl className="msi-kpi-strip" aria-label={d.kpi_heading}>
                <div className="msi-kpi-card msi-kpi-card--primary" data-msi-metric="revenue_paid"><dt>{d.kpi_paid_revenue}</dt><dd>{rp(snapshot.stats.revenue_paid)}</dd></div>
                <div className="msi-kpi-card" data-msi-metric="gmv_active"><dt>{d.kpi_gmv_active}</dt><dd>{rp(snapshot.stats.orders_non_cancelled_value)}</dd></div>
                <div className="msi-kpi-card" data-msi-metric="pending_orders" data-alert={snapshot.stats.orders_pending > 0 ? 'true' : undefined}><dt><a href="#panel-pesanan">{d.kpi_pending_orders}</a></dt><dd>{snapshot.stats.orders_pending} / {snapshot.stats.orders_processing}</dd></div>
                <div className="msi-kpi-card" data-msi-metric="low_stock" data-alert={(snapshot.stats.low_stock_products > 0 || snapshot.stats.out_of_stock_products > 0) ? 'true' : undefined}><dt><a href="#panel-produk">{d.kpi_low_stock}</a></dt><dd>{snapshot.stats.low_stock_products} / {snapshot.stats.out_of_stock_products}</dd></div>
                <div className="msi-kpi-card" data-msi-metric="concierge_open" data-alert={snapshot.stats.concierge_open > 0 ? 'true' : undefined}><dt><a href="#panel-concierge">{d.kpi_open_concierge}</a></dt><dd>{snapshot.stats.concierge_open}</dd></div>
            </dl>}
            <div className="admin-module-ledger">
                <dl className="stat-grid">{[['orders', c.order, 'pesanan'], ['products', c.product, 'produk'], ['concierge', c.concierge, 'concierge'], ['users', c.user, 'user']].map(([key, label, panel]) => <div className="stat-card" key={key} data-dashboard-metric={key}><dt><a href={`#panel-${panel}`}>{label}</a></dt><dd>{snapshot.stats[key]}</dd></div>)}</dl>
                <dl className="stat-secondary">{[['categories', c.category, 'kategori'], ['backups', c.audit, 'audit'], ['subscribers', c.subscriber, 'subscriber']].map(([key, label, panel]) => <div className="stat-secondary-card" key={key} data-dashboard-metric={key}><dt><a href={`#panel-${panel}`}>{label}</a></dt><dd>{snapshot.stats[key]}</dd></div>)}</dl>
            </div>
        </div>
        <nav className="admin-nav" aria-label={d.navigation}>
            <a href={urls.dashboard} data-panel-tab="overview" data-active={activeView === 'overview' ? 'true' : undefined} onClick={showOverview}>{d.workspace_overview || c.dashboard}</a>
            {['pesanan', 'produk', 'kategori', 'concierge', 'user', 'subscriber', 'audit'].map(key => <a key={key} href={`#panel-${key}`} data-panel-tab={key} data-active={activeView === key ? 'true' : undefined}>{key === 'pesanan' ? d.orders_reports : defs[key].label}</a>)}
            {urls.operations && <a href={urls.operations}>Ringkasan operasional</a>}
            <a href="#panel-keamanan" data-panel-tab="keamanan" data-active={activeView === 'keamanan' ? 'true' : undefined}>{c.security}</a>
            <a href="#semua" className="admin-nav-all" data-panel-tab="all" data-active={activeView === 'all' ? 'true' : undefined}>{d.workspace_all}</a>
        </nav>
        <Table {...tableProps('pesanan')} hint={text(d.order_count_hint, { count: snapshot.stats.orders })} extra={selection} external={<ReportForm {...shared} />}>
            {columns('pesanan', [[<span className="sr-only">{d.select}</span>], [d.order_number, 'id'], [c.date, 'created_at'], [d.client], [d.recorded_value, 'total'], [c.status], [d.documents]])}
            <tbody key={epoch}>{orders.map(row => <tr key={row.id}><td className="table-select-cell"><label className="table-select-label"><input type="checkbox" checked={selected.includes(row.id)} aria-label={text(d.select_order, { id: row.id })} onChange={event => setSelected(value => event.target.checked ? [...value, row.id] : value.filter(id => id !== row.id))} /></label></td><td className="num">#{row.id}</td><td className="num">{row.date}</td><td className="dashboard-wrap"><strong>{row.customer}</strong>{row.itemsSummary && <small className="order-items-summary">{row.itemsSummary}</small>}</td><td className="num">{row.price}</td><td>{badge(row.status)}{row.transitions.length > 0 && <PostForm inline action={row.update} token={csrfToken} className="status-form" onSubmit={updateOrder}><select name="status" required defaultValue="" disabled={saving} aria-label={text(d.order_status_aria, { id: row.id })}><option value="" disabled>{d.choose_status}</option>{row.transitions.map(value => <option key={value} value={value}>{d[value]}</option>)}</select><button type="submit" disabled={saving} aria-label={text(d.save_order_status, { id: row.id })}>{c.save}</button></PostForm>}</td><td><a href={row.invoice}>{d.order_slip}</a></td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('kategori')} external={<CategoryCreate {...shared} />}>
            {columns('kategori', [['ID', 'cat_id', true], [d.title_col, 'cat_title'], [c.product, 'products_count', true], [c.actions]])}
            <tbody key={epoch}>{snapshot.tables.kategori.rows.map(row => <tr key={row.id}><td className="num">#{row.id}</td><td><PostForm inline action={row.update} token={csrfToken} className="status-form"><input name="cat_title" type="text" required maxLength={255} defaultValue={row.title} aria-label={text(d.category_title_aria, { id: row.id })} />{save(text(d.category_save_aria, { id: row.id }))}</PostForm></td><td className="num">{row.count}</td><td>{destroy(row, text(d.category_confirm, { title: row.title }), text(d.category_delete_aria, { title: row.title }))}</td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('produk')} external={<ProductCreate {...shared} options={snapshot.categoryOptions} initiallyOpen={initialOpen.current} />}>
            {columns('produk', [[c.product, 'product_title'], ['ID', 'product_id', true], [c.price, 'product_price', true], [d.user_category_stock, 'stock'], [c.status], [c.actions]])}
            <tbody key={epoch}>{snapshot.tables.produk.rows.map(row => <tr key={row.id}><td className="admin-product-cell"><a className="admin-product-link" href={row.show}>{row.title}</a><small className="admin-product-slug">{row.slug}</small></td><td className="num">#{row.id}</td><td className="num">{row.price}</td><td><PostForm inline action={row.update} token={csrfToken} className="status-form"><select name="product_cat" defaultValue={row.category} aria-label={text(d.category_aria, { title: row.title })}>{snapshot.categoryOptions.map(option => <option key={option.id} value={option.id}>{option.title}</option>)}</select><input name="stock" type="number" required min={0} max={100000} step={1} defaultValue={row.stock} aria-label={text(d.stock_aria, { title: row.title })} className="admin-stock-input" /><select name="is_active" defaultValue={row.active ? '1' : '0'} aria-label={text(d.visibility_aria, { title: row.title })}><option value="1">{d.active}</option><option value="0">{d.inactive}</option></select>{save(text(d.save_product_aria, { title: row.title }))}</PostForm></td><td><span className={`badge-status badge-${row.active ? 'confirmed' : 'cancelled'}`}>{row.active ? d.active : d.inactive}</span></td><td>{destroy(row, text(d.delete_product_confirm, { title: row.title }), text(d.delete_product_aria, { title: row.title }))}</td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('user')}>
            {columns('user', [[c.name, 'name'], [c.email_short, 'email'], [d.phone], [d.address], [d.created, 'created_at']])}<tbody>{snapshot.tables.user.rows.map(row => <tr key={row.id}><td><strong>{row.name}</strong></td><td>{row.email}</td><td>{row.phone}</td><td className="dashboard-wrap">{row.address}</td><td className="num">{row.date}</td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('audit')}>
            {columns('audit', [[d.user_id, 'user_id', true], [c.name, 'first_name'], [c.email_short, 'email'], [d.phone], [d.archived, 'created_at', true]])}<tbody>{snapshot.tables.audit.rows.map(row => <tr key={row.id}><td className="num">#{row.userId}</td><td><strong>{row.name}</strong></td><td>{row.email}</td><td>{row.phone}</td><td className="num">{row.date}</td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('concierge')}>
            {columns('concierge', [[d.name_notes, 'name'], [c.email_short, 'email'], [d.service], [d.schedule, 'preferred_date'], [c.status, 'status']])}<tbody key={epoch}>{snapshot.tables.concierge.rows.map(row => <tr key={row.id}><td className="dashboard-wrap"><small className="concierge-reference">{row.reference}{row.viaAssistant && <> · <span className="badge">{d.via_assistant}</span></>}</small><br /><strong>{row.name}</strong>{row.userName && ` · ${row.userName}`}{row.phone && <><br /><small>WA: {row.phone}</small></>}{row.notes && <div className="concierge-notes-box"><strong>{d.notes}</strong><br />{row.notes}</div>}</td><td className="dashboard-wrap">{row.email}</td><td>{row.service}</td><td>{row.schedule}</td><td>{badge(row.status)}{row.transitions.length > 0 && <PostForm inline token={csrfToken} action={row.update} className="status-form"><select name="status" defaultValue={row.status} aria-label={text(d.request_status_aria, { id: row.id })}>{row.transitions.map(value => <option key={value} value={value}>{d[value]}</option>)}</select>{save(text(d.save_request_status, { id: row.id }))}</PostForm>}</td></tr>)}</tbody>
        </Table>
        <Table {...tableProps('subscriber')}>
            {columns('subscriber', [[c.email_short, 'email'], [d.since, 'created_at', true], [c.actions]])}<tbody>{snapshot.tables.subscriber.rows.map(row => <tr key={row.id}><td className="dashboard-wrap">{row.email}</td><td className="num">{row.date}</td><td>{destroy(row, text(d.subscriber_confirm, { email: row.email }), text(d.subscriber_delete_aria, { email: row.email }))}</td></tr>)}</tbody>
        </Table>
        <SecurityForm {...shared} />
        <div className="admin-workspace-footer"><a className="text-link" href={urls.home}>{c.back_maison}</a></div>
    </div>;
}
