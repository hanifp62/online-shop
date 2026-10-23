import { useEffect, useRef, useState } from 'react';
import { TABLES, interpolate, tableUrl } from '../../lib/admin-dashboard';

export function PostForm({ action, method = 'PATCH', token, confirmMessage, children, inline = false, onSubmit, ...rest }) {
    return <form method="POST" action={action} data-inline-draft={inline ? '' : undefined} onSubmit={event => {
        if (confirmMessage && !window.confirm(confirmMessage)) { event.preventDefault(); return; }
        onSubmit?.(event);
    }} {...rest}><input type="hidden" name="_token" value={token} />{method !== 'POST' && <input type="hidden" name="_method" value={method} />}{children}</form>;
}
export function CheckAll({ checked, mixed, ...props }) {
    const ref = useRef(null);
    useEffect(() => { if (ref.current) ref.current.indeterminate = mixed; }, [mixed]);
    return <input ref={ref} type="checkbox" checked={checked} {...props} />;
}
export function definitions(c, d) {
    return {
        kategori: { heading: d.category_heading, label: c.category, search: d.search_category, hint: d.category_hint, empty: d.category_empty, filtered: d.category_empty_filtered, sorts: { cat_id: 'ID', cat_title: d.title_col, products_count: d.product_count } },
        produk: { heading: d.product_heading, label: c.product, search: d.search_product, hint: d.product_hint, empty: d.product_empty, filtered: d.product_empty_filtered, sorts: { product_id: d.catalog_number, product_title: d.title_col, product_price: c.price, stock: c.stock } },
        pesanan: { heading: d.orders_reports, label: c.order, search: d.search_order, empty: d.orders_empty, filtered: d.orders_empty_filtered, sorts: { id: d.order_number, created_at: c.date, total: d.value }, statuses: ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'] },
        user: { heading: d.users_heading, label: c.user, search: d.search_user, empty: d.users_empty, filtered: d.users_empty, sorts: { created_at: d.created_at, name: c.name, email: c.email_short } },
        audit: { heading: d.audit_heading, label: c.audit, search: d.search_name_email, empty: d.audit_empty, filtered: d.audit_empty_filtered, sorts: { created_at: d.archive_date, email: c.email_short, first_name: c.name, user_id: d.client_number } },
        concierge: { heading: d.concierge_heading, label: c.concierge, search: d.search_contact, empty: d.requests_empty, filtered: d.requests_empty, sorts: { created_at: d.created_at, name: c.name, email: c.email_short, status: c.status, preferred_date: d.schedule }, statuses: ['new', 'contacted', 'confirmed', 'completed', 'cancelled'] },
        subscriber: { heading: d.subscriber_heading, label: c.subscriber, search: d.search_email, empty: d.subscriber_empty, filtered: d.subscriber_empty_filtered, sorts: { created_at: d.signup_date, email: c.email_short } },
    };
}
export default function Table({ tableKey: key, definition: def, snapshot, copy: { common: c, dashboard: d }, base, navigate, busy, locked, epoch, children, extra, hint, external }) {
    const [density, setDensity] = useState(() => { try { return localStorage.getItem(`admin-table-density:${key}`) === 'comfortable' ? 'comfortable' : 'compact'; } catch { return 'compact'; } });
    const [qName, sortName, dirName] = TABLES[key];
    const statusName = key === 'pesanan' ? 'ostatus' : 'status';
    const table = snapshot.tables[key], p = table.pagination, filters = snapshot.filters;
    const active = [qName, ...(def.statuses ? [statusName] : [])].filter(name => filters[name]);
    const reset = tableUrl(base, snapshot.tableState, key, {}, true);
    const link = (event, href) => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
        event.preventDefault(); navigate(href, key);
    };
    return <section className="admin-panel" id={`panel-${key}`}>
        <div data-admin-table={key} aria-busy={busy}>
            <h2 tabIndex={-1}>{def.heading}</h2>{(hint || def.hint) && <p className="panel-hint">{hint || def.hint}</p>}
            <form key={`filters-${epoch}`} className={`inline-form ${def.statuses ? 'inline-form--mobile-stack' : ''}`} method="GET" action={`${base}#panel-${key}`} style={{ gridTemplateColumns: def.statuses ? '1fr 1fr auto' : '1fr auto' }} onSubmit={event => {
                event.preventDefault(); const values = Object.fromEntries(new FormData(event.currentTarget)); navigate(tableUrl(base, snapshot.tableState, key, values), key);
            }} data-admin-table-form={key}>
                {Object.entries(snapshot.tableState).filter(([name]) => !TABLES[key].includes(name)).map(([name, value]) => <input key={name} type="hidden" name={name} value={value} />)}
                {def.statuses && <div className="field"><label htmlFor={`filter-${statusName}`}>{c.filter_status}</label><select id={`filter-${statusName}`} name={statusName} defaultValue={filters[statusName] || ''}><option value="">{c.all_statuses}</option>{def.statuses.map(status => <option key={status} value={status}>{d[status]}</option>)}</select></div>}
                <div className="field"><label htmlFor={`filter-${qName}`}>{def.search}</label><input id={`filter-${qName}`} name={qName} type="search" maxLength={255} autoComplete="off" defaultValue={filters[qName] || ''} /></div>
                <button className="button button-dark" disabled={locked} type="submit">{def.statuses ? c.filter : c.search}</button>
                <div className="table-sort-controls"><div className="field"><label htmlFor={`${key}-sort`}>{c.sort_by}</label><select id={`${key}-sort`} name={sortName} defaultValue={filters[sortName]}>{Object.entries(def.sorts).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><div className="field"><label htmlFor={`${key}-dir`}>{c.sort_direction}</label><select id={`${key}-dir`} name={dirName} defaultValue={filters[dirName]}><option value="desc">{c.descending}</option><option value="asc">{c.ascending}</option></select></div></div>
            </form>
            <div className="panel-context" data-panel-count={key} aria-live="polite">{active.length > 0 && <>{c.active_filters} {active.map(name => <span key={name} className="badge" data-active-filter={`${name}: ${filters[name]}`}>{name === qName ? def.search : c.status}: {name === qName ? filters[name] : d[filters[name]]}</span>)} <a href={reset} onClick={event => link(event, reset)}>{c.reset}</a> · </>}{interpolate(c.showing, { count: table.rows.length, total: p.total })}</div>
            <div className="table-density-controls"><button type="button" aria-pressed={density === 'compact'} onClick={() => { const next = density === 'compact' ? 'comfortable' : 'compact'; setDensity(next); try { localStorage.setItem(`admin-table-density:${key}`, next); } catch { /* Session state remains usable. */ } }}>{c.compact_rows}</button></div>
            {table.rows.length === 0 ? <><p className="admin-empty">{active.length ? def.filtered : def.empty}</p>{key === 'produk' && <div className="admin-empty-action">{active.length ? <a href={reset} onClick={event => link(event, reset)}>{d.clear_search}</a> : <><span>{d.empty_store}</span><a href="#form-produk">{d.add_new_product}</a></>}</div>}</> : <>{extra}<div className="admin-table-wrap" tabIndex={0} role="region" aria-label={interpolate(c.table_region, { label: def.label })}><table className="admin-table" data-density={density} data-table-body={key}><caption className="sr-only">{def.label}</caption>{children}</table></div></>}
            {p.last > 1 && <nav className="table-pagination" aria-label={interpolate(c.table_pages, { label: def.label })}>{p.previous ? <a rel="prev" href={p.previous} onClick={event => link(event, p.previous)}>{c.previous}</a> : <span className="is-disabled" aria-disabled="true">{c.previous}</span>}<span className="table-pagination-status" aria-current="page">{interpolate(c.page_of, { page: p.current, total: p.last })}</span>{p.next ? <a rel="next" href={p.next} onClick={event => link(event, p.next)}>{c.next}</a> : <span className="is-disabled" aria-disabled="true">{c.next}</span>}</nav>}
        </div>
        {external}
    </section>;
}
export function Head({ columns, sort, dir }) {
    return <thead><tr>{columns.map(([label, field, num], index) => <th key={index} scope="col" className={num ? 'num' : undefined} aria-sort={field && sort === field ? (dir === 'asc' ? 'ascending' : 'descending') : undefined}>{label}</th>)}</tr></thead>;
}
