import { useEffect, useRef, useState } from 'react';

const statuses = { ok: '✓ Tidak ada peringatan terukur', attention: '△ Perlu perhatian', critical: '! Kritis', unknown: '? Belum diperiksa' };
const reasons = { source_unavailable: 'Sumber belum tersedia. Coba segarkan kembali.', unsupported_driver: 'Penyimpanan ini belum didukung.', not_in_minimal_slice: 'Belum dihitung pada ringkasan ini.' };
const groups = [
    ['Konteks volume dan status', [['MET-01', 'Order dibuat'], ['MET-02', 'Order per status saat ini']]],
    ['Tersangkut', [['MET-05', 'Baris reservasi kedaluwarsa, tidak ditahan'], ['MET-06', 'Dead letter terbuka']]],
    ['Rekonsiliasi', [['MET-04', 'Event anomali pembayaran']]],
    ['Antrean', [['MET-07', 'Notifikasi mail belum diantrekan'], ['MET-08', 'Job pada penyimpanan antrean'], ['MET-09', 'Job gagal dalam periode']]],
    ['Integritas — Belum diperiksa', [['MET-10', 'Stok negatif'], ['MET-11', 'Lunas tanpa ledger'], ['MET-12', 'Ledger lunas, order pending'], ['MET-13', 'Batal dengan reservasi'], ['MET-14', 'Aritmetika order']]],
];
const details = { held_count: 'Reservasi ditahan dead letter (terpisah)', exhausted_count: 'Percobaan ≥10 (subset)', leased_count: 'Lease aktif (subset)', ready_count: 'Siap', delayed_count: 'Ditunda', reserved_count: 'Sedang diambil worker' };

export function validReport(report) {
    return report?.contract_version === 'ops-metrics.v1' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(report.as_of)
        && ['partial', 'complete'].includes(report.summary?.coverage) && Object.hasOwn(statuses, report.summary?.status)
        && report.summary.main_source_available === true && typeof report.window?.from === 'string' && typeof report.window?.to_exclusive === 'string'
        && Array.isArray(report.metrics) && groups.flatMap(([, items]) => items).every(([id]) => report.metrics.some(m => m.id === id
            && Object.hasOwn(statuses, m.status) && (m.value === null || Number.isFinite(m.value))
            && (m.oldest_age_seconds === null || Number.isFinite(m.oldest_age_seconds))));
}

export default function OperationsSummary({ url, filters, report: initial, validationErrors = [] }) {
    const [report, setReport] = useState(initial);
    const [errors, setErrors] = useState(validationErrors);
    const [busy, setBusy] = useState(false);
    const [status, setStatus] = useState('');
    const pending = useRef(null);
    useEffect(() => () => pending.current?.abort(), []);
    async function refresh(event) {
        event.preventDefault();
        if (pending.current) return;
        const target = new URL(url, window.location.href);
        target.search = new URLSearchParams(new FormData(event.currentTarget)).toString();
        const controller = new AbortController();
        pending.current = controller;
        setBusy(true);
        setStatus('Memuat pembacaan baru; snapshot sebelumnya tetap ditampilkan.');
        let timer;
        try {
            const next = await Promise.race([
                (async () => {
                    const response = await fetch(target.href, { method: 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error', headers: { Accept: 'application/json' }, signal: controller.signal });
                    if (!response.ok || response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error('unavailable');
                    const data = await response.json();
                    if (!validReport(data)) throw new Error('invalid_report');
                    return data;
                })(),
                new Promise((_, reject) => { timer = setTimeout(() => { controller.abort(); reject(new Error('timeout')); }, 15000); }),
            ]);
            if (controller.signal.aborted) return;
            setReport(next);
            setErrors([]);
            setStatus(`Pembacaan diperbarui: ${next.as_of}. Cakupan dan periode tercantum pada laporan.`);
        } catch {
            setStatus(report?.as_of ? `Data lama — penyegaran gagal. Snapshot ${report.as_of} tetap ditampilkan; filter baru belum diterapkan. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.` : 'Ringkasan belum tersedia. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.');
        } finally {
            clearTimeout(timer);
            pending.current = null;
            setBusy(false);
        }
    }
    const metrics = new Map((report?.metrics ?? []).map(m => [m.id, m]));
    return <div lang="id">
        <section className="admin-panel" aria-labelledby="ops-filter-heading"><h2 id="ops-filter-heading">Periode kejadian</h2>
            <p id="ops-window-help" className="panel-hint">Tanggal UTC, maksimal 366 hari. Pilih Khusus untuk memakai tanggal mulai dan akhir (inklusif). Filter hanya mengubah metrik kejadian, bukan backlog. Tidak ada penyegaran otomatis.</p>
            {errors.length > 0 && <div className="auth-errors" data-ops-validation-errors role="alert"><p>Periksa rentang tanggal UTC.</p><ul>{errors.map((error, i) => <li key={i}>{error}</li>)}</ul></div>}
            <form className="ops-filters" data-ops-filters method="GET" action={url} onSubmit={refresh} aria-describedby="ops-window-help">
                <div className="field"><label htmlFor="ops-period">Periode</label><select id="ops-period" name="period" defaultValue={filters.period}>{Object.entries({ today: 'Hari ini', '7d': '7 hari', '30d': '30 hari', custom: 'Khusus' }).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                <div className="field"><label htmlFor="ops-from">Mulai (UTC)</label><input id="ops-from" name="from" type="date" defaultValue={filters.from} /></div>
                <div className="field"><label htmlFor="ops-to">Akhir (UTC, inklusif)</label><input id="ops-to" name="to" type="date" defaultValue={filters.to} /></div>
                <button className="button button-sapphire" type="submit" disabled={busy}>Terapkan / Segarkan</button>
            </form>
        </section>
        <p id="ops-refresh-status" data-ops-refresh-status role="status" aria-live="polite" aria-atomic="true">{status}</p>
        <div data-ops-report data-as-of={report?.as_of ?? ''} aria-describedby="ops-refresh-status" aria-busy={busy}>
            {report && <><section className="admin-panel" aria-labelledby="ops-reading-heading"><h2 id="ops-reading-heading">Hasil pembacaan</h2>
                {report.message && <p className="auth-errors" role="alert">{report.message}</p>}
                <p><strong>{statuses[report.summary.status] ?? statuses.unknown}</strong> · Cakupan {report.summary.coverage === 'complete' ? 'lengkap' : 'sebagian'}.</p>
                <p className="panel-hint">Bukan kesehatan sistem menyeluruh, bukti worker hidup, pendapatan, atau kapasitas transaksi. Jangan jumlahkan angka berlainan unit atau subset.</p>
                <p>Cutoff permintaan: <time dateTime={report.as_of}>{report.as_of}</time>. Pembacaan best-effort, bukan snapshot atomik.</p>
                <p>Kejadian: {report.window.from} ≤ waktu &lt; {report.window.to_exclusive} (UTC).</p>
                <p><strong>Backlog saat ini — seluruh umur</strong>: tidak dibatasi periode kejadian. Angka hanya berlaku pada pembacaan ini; bila memakai halaman lama, perlakukan sebagai <strong>Data lama</strong> dan segarkan manual.</p>
            </section>{groups.map(([heading, items]) => <section className="admin-panel" key={heading}><h2>{heading}</h2>
                <div className="admin-table-wrap ops-table" tabIndex={0} role="region" aria-label={heading}><table className="admin-table"><caption className="sr-only">{heading} — agregat tanpa detail pelanggan</caption>
                    <thead><tr>{['Metrik / basis', 'Jumlah', 'Status dan rincian', 'Umur terlama'].map(label => <th scope="col" key={label}>{label}</th>)}</tr></thead>
                    <tbody>{items.map(([id, label]) => { const m = metrics.get(id) ?? { status: 'unknown', value: null, oldest_age_seconds: null }; return <tr key={id}>
                        <th scope="row">{label}<small className="ops-detail">{id} · {m.time_basis === 'snapshot' ? 'Backlog/status saat ini' : 'Kejadian dalam periode'}<br />{m.basis}</small></th>
                        <td className="num">{m.value == null ? '—' : new Intl.NumberFormat('id-ID').format(m.value)}{m.value === 0 && <small className="ops-detail">Tidak ada pada pembacaan ini</small>}</td>
                        <td>{statuses[m.status] ?? statuses.unknown}{m.reason && <small className="ops-detail">{reasons[m.reason] ?? reasons.source_unavailable}</small>}
                            {Object.entries(details).filter(([key]) => Object.hasOwn(m, key)).map(([key, text]) => <small className="ops-detail" key={key}>{text}: {m[key] ?? '—'}</small>)}
                            {['by_status', 'by_outcome'].map(key => m[key] && <dl key={key}>{Object.entries(m[key]).map(([name, count]) => <div key={name}><dt>{name}</dt><dd>{count}</dd></div>)}</dl>)}
                        </td><td>{m.oldest_age_seconds == null ? '—' : `${m.oldest_age_seconds} detik`}{id === 'MET-08' && <small className="ops-detail">Hanya job siap, sejak available_at</small>}</td>
                    </tr>; })}</tbody>
                </table></div></section>)}</>}
        </div>
    </div>;
}
