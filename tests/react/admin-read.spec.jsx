// @vitest-environment jsdom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import AuthEvents from '../../resources/js/pages/Admin/AuthEvents';
import OperationsSummary, { validReport } from '../../resources/js/pages/Admin/OperationsSummary';

let root;
let host;
const ids = ['01', '02', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14'];
function snapshot() {
    return { contract_version: 'ops-metrics.v1', as_of: '2026-10-22T12:00:00Z', window: { from: '2026-10-16T00:00:00Z', to_exclusive: '2026-10-23T00:00:00Z' }, summary: { status: 'ok', coverage: 'partial', main_source_available: true }, metrics: ids.map(id => ({ id: `MET-${id}`, value: id === '10' ? null : 0, status: id === '10' ? 'unknown' : 'ok', reason: id === '10' ? 'not_in_minimal_slice' : null, time_basis: 'snapshot', basis: 'test basis', oldest_age_seconds: null })) };
}
const props = () => ({ url: '/admin/reports/operations-summary', filters: { period: '7d', from: '', to: '' }, report: snapshot() });
beforeEach(() => { vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true); host = document.createElement('div'); document.body.append(host); root = createRoot(host); });
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.useRealTimers(); vi.unstubAllGlobals(); });
async function mount(component) { await act(async () => root.render(component)); }
async function submit() { await act(async () => host.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); }
const response = data => ({ ok: true, redirected: false, headers: new Headers({ 'Content-Type': 'application/json' }), json: async () => data });

it('FR-FE-001/002 renders native localized filters, escaped rows and full-document pagination', async () => {
    const e = { event: 'Peristiwa', outcome: 'Hasil', request_id: 'ID Permintaan', region: 'Tabel log', caption: 'Riwayat', success: 'Berhasil', heading: 'Log', empty: 'Kosong' };
    await mount(<AuthEvents locale="fr" copy={{ events: e, common: { results: ':count résultats', table_pages: 'Pages :label', page_of: ':page / :total', previous: 'Précédent', next: 'Suivant' } }} url="/admin/auth-events" filters={{ event: 'LOGIN_SUCCESS' }} eventOptions={['LOGIN_SUCCESS']} rows={[{ id: 1, event: '<script>bad</script>', outcome: 'success', context: { reason: '<img src=x>' } }]} pagination={{ total: 26, current: 1, last: 2, next: '/admin/auth-events?event=LOGIN_SUCCESS&page=2' }} />);
    expect(host.querySelector('[lang]').lang).toBe('fr');
    expect(host.querySelector('form').method).toBe('get');
    expect(host.querySelector('select').value).toBe('LOGIN_SUCCESS');
    expect(host.querySelector('script,img')).toBeNull();
    expect(host.textContent).toContain('<script>bad</script>');
    expect(host.querySelector('nav a').getAttribute('href')).toContain('event=LOGIN_SUCCESS&page=2');
    expect(host.querySelector('[role="region"]').tabIndex).toBe(0);
});
it('FR-REM-007 distinguishes unknown/partial from measured zero; never auto-fetches', async () => {
    const fetch = vi.fn(); vi.stubGlobal('fetch', fetch);
    await mount(<OperationsSummary {...props()} />);
    expect(fetch).not.toHaveBeenCalled();
    expect(host.textContent).toContain('Cakupan sebagian');
    const rows = [...host.querySelectorAll('tbody tr')];
    expect(rows[0].textContent).toContain('Tidak ada pada pembacaan ini');
    const unknown = rows.find(row => row.textContent.includes('MET-10'));
    expect(unknown.textContent).toContain('—');
    expect(unknown.textContent).not.toContain('Tidak ada pada pembacaan ini');
    expect(host.querySelectorAll('[role="region"][tabindex="0"]')).toHaveLength(5);
});
it.each([422, 503, 401])('keeps the last snapshot and announces failure for HTTP %s', async status => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status })));
    await mount(<OperationsSummary {...props()} />); await submit();
    expect(host.querySelector('[data-as-of]').dataset.asOf).toBe(snapshot().as_of);
    expect(host.querySelector('[role="status"]').textContent).toContain('Data lama');
    expect(host.querySelector('button').disabled).toBe(false);
});
it('commits only valid JSON and sends safe manual request options', async () => {
    const next = snapshot(); next.as_of = '2026-10-22T12:01:00Z';
    const fetch = vi.fn(async () => response(next)); vi.stubGlobal('fetch', fetch);
    await mount(<OperationsSummary {...props()} validationErrors={['Rentang salah']} />); await submit();
    expect(fetch.mock.calls[0][1]).toMatchObject({ credentials: 'same-origin', cache: 'no-store', redirect: 'error', headers: { Accept: 'application/json' } });
    expect(host.querySelector('[data-as-of]').dataset.asOf).toBe(next.as_of);
    expect(host.querySelector('[data-ops-validation-errors]')).toBeNull();
    expect(host.querySelector('[role="status"]').textContent).toContain('Pembacaan diperbarui');
});
it('deadline covers a stalled body and a late response cannot overwrite the snapshot', async () => {
    vi.useFakeTimers(); let resolve;
    vi.stubGlobal('fetch', vi.fn(async () => ({ ...response(null), json: () => new Promise(done => { resolve = done; }) })));
    await mount(<OperationsSummary {...props()} />); await submit();
    expect(host.querySelector('[aria-busy]').getAttribute('aria-busy')).toBe('true');
    await act(async () => vi.advanceTimersByTimeAsync(15000));
    expect(host.querySelector('[role="status"]').textContent).toContain('Data lama');
    const late = snapshot(); late.as_of = '2026-10-22T12:02:00Z';
    await act(async () => resolve(late));
    expect(host.querySelector('[data-as-of]').dataset.asOf).toBe(snapshot().as_of);
    expect(host.querySelector('button').disabled).toBe(false);
});
it.each(['network', 'html', 'malformed', 'redirect'])('rejects %s refresh without discarding the last good reading', async mode => {
    vi.stubGlobal('fetch', vi.fn(async () => {
        if (mode === 'network') throw new Error('PRIVATE_NETWORK_DETAILS');
        if (mode === 'html') return { ...response(null), headers: new Headers({ 'Content-Type': 'text/html' }) };
        if (mode === 'redirect') return { ...response(snapshot()), redirected: true };
        return response({ ...snapshot(), metrics: [] });
    }));
    await mount(<OperationsSummary {...props()} />); await submit();
    expect(host.querySelector('[data-as-of]').dataset.asOf).toBe(snapshot().as_of);
    expect(host.querySelector('[role="status"]').textContent).toContain('Data lama');
    expect(host.textContent).not.toContain('PRIVATE_NETWORK_DETAILS');
});
it('prevents overlapping refresh and allows retry after timeout', async () => {
    vi.useFakeTimers();
    const fetch = vi.fn().mockImplementationOnce(() => new Promise(() => {})).mockResolvedValueOnce(response(snapshot()));
    vi.stubGlobal('fetch', fetch);
    await mount(<OperationsSummary {...props()} />); await submit(); await submit();
    expect(fetch).toHaveBeenCalledTimes(1);
    await act(async () => vi.advanceTimersByTimeAsync(15000));
    await submit();
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(host.querySelector('[role="status"]').textContent).toContain('Pembacaan diperbarui');
});
it('rejects incomplete, malformed and unavailable reports rather than showing false zero', () => {
    expect(validReport(snapshot())).toBe(true);
    for (const change of [{ metrics: [] }, { as_of: 'bad' }, { summary: { status: 'ok', coverage: 'partial', main_source_available: false } }]) expect(validReport({ ...snapshot(), ...change })).toBe(false);
});
