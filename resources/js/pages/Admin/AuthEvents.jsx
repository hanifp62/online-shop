export default function AuthEvents({ locale, copy: { common: c, events: e }, url, filters, eventOptions, rows, pagination: p }) {
    const active = Object.entries(filters).filter(([, value]) => value);
    return <div lang={locale}>
        <p style={{ marginBottom: 16, color: 'var(--zafira-muted)' }}>{e.intro}</p>
        {active.length > 0 && <div className="active-filters-box"><span>{c.active_filters}</span>{active.map(([key, value]) => <span className="badge" data-active-filter={`${key}: ${value}`} key={key}>{key}: {value}</span>)}</div>}
        <form method="GET" action={url} className="filters">
            <div className="filter-field"><label htmlFor="auth-event-filter">{e.event}</label><select id="auth-event-filter" name="event" defaultValue={filters.event ?? ''}><option value="">{e.all_events}</option>{eventOptions.map(value => <option key={value}>{value}</option>)}</select></div>
            <div className="filter-field"><label htmlFor="auth-outcome-filter">{e.outcome}</label><select id="auth-outcome-filter" name="outcome" defaultValue={filters.outcome ?? ''}><option value="">{e.all_outcomes}</option><option value="success">{e.success}</option><option value="failure">{e.failure}</option></select></div>
            <div className="filter-field"><label htmlFor="auth-request-filter">{e.request_id}</label><input id="auth-request-filter" name="request_id" type="text" defaultValue={filters.request_id ?? ''} placeholder={e.request_hint} maxLength={64} spellCheck={false} /></div>
            <button type="submit">{c.filter}</button>{Object.keys(filters).length > 0 && <a href={url}>{c.reset}</a>}
        </form>
        <p data-result-count>{c.results.replace(':count', p.total)}</p>
        <div className="auth-events-table-wrap" tabIndex={0} role="region" aria-label={e.region}><table>
            <caption className="sr-only">{e.caption}</caption><thead><tr>{['ID', e.time, e.actor, e.event, e.outcome, 'IP', e.request_id, e.context].map((label, i) => <th scope="col" key={i}>{label}</th>)}</tr></thead>
            <tbody>{rows.map(row => <tr key={row.id}>
                <td>#{row.id}</td><td>{row.time ?? '-'}</td><td>{row.actor_type ? <><strong>{row.actor_type}</strong>{row.actor_id ? ` (ID: ${row.actor_id})` : ''}</> : '-'}</td>
                <td><code>{row.event}</code></td><td><span className={`badge ${['success', 'failure'].includes(row.outcome) ? `badge-${row.outcome}` : ''}`}>{e[row.outcome] ?? row.outcome}</span></td>
                <td>{row.ip ?? '-'}</td><td><code>{row.request_id ?? '-'}</code></td><td>{Object.keys(row.context).length ? <pre style={{ fontSize: 11, margin: 0 }}>{JSON.stringify(row.context)}</pre> : '-'}</td>
            </tr>)}{rows.length === 0 && <tr><td colSpan={8}>{e.empty}</td></tr>}</tbody>
        </table></div>
        <nav className="pagination-container" aria-label={c.table_pages.replace(':label', e.heading)}>
            {p.previous && <a href={p.previous}>{c.previous}</a>} <span>{c.page_of.replace(':page', p.current).replace(':total', p.last)}</span> {p.next && <a href={p.next}>{c.next}</a>}
        </nav>
    </div>;
}
