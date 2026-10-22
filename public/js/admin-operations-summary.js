// FR-REM-007: progressive manual refresh; the SSR snapshot stays in the DOM until success.
const bindings = new WeakMap();
export function initOperationsSummary({
    document = globalThis.document,
    fetch = globalThis.fetch,
    parse = (html) => new DOMParser().parseFromString(html, "text/html"),
    timeoutMs = 15000,
    timers = globalThis,
} = {}) {
    const form = document?.querySelector("[data-ops-filters]");
    const report = document?.querySelector("[data-ops-report]");
    const status = document?.querySelector("[data-ops-refresh-status]");
    if (!form || !report || !status || !fetch) return null;
    const root = form.closest?.('[data-react-fallback]');
    if (root?.dataset.reactBoot === 'ready') return null;
    if (bindings.has(form)) return bindings.get(form);
    const button = form.querySelector('[type="submit"]');
    let busy = false;
    let disposed = false;
    let cancelRefresh;

    function dispose() {
        disposed = true;
        form.removeEventListener('submit', onSubmit);
        root?.removeEventListener('zafira:react-claim', dispose);
        cancelRefresh?.();
    }

    async function refresh(url) {
        if (busy || disposed) return false;
        const target = new URL(url, document.location.href);
        const endpoint = new URL(form.action, document.location.href);
        if (target.origin !== endpoint.origin || target.pathname !== endpoint.pathname) return false;
        busy = true;
        button.disabled = true;
        report.setAttribute("aria-busy", "true");
        status.textContent = "Memuat pembacaan baru; snapshot sebelumnya tetap ditampilkan.";
        const controller = new AbortController();
        let timer;
        try {
            // The deadline includes headers AND the response body. A late body cannot commit.
            const result = await Promise.race([
                new Promise((_, reject) => {
                    cancelRefresh = () => { controller.abort(); reject(new Error('react_claim')); };
                }),
                (async () => {
                    const response = await fetch(target.href, {
                        method: "GET", credentials: "same-origin", cache: "no-store",
                        redirect: "error", headers: { Accept: "text/html" }, signal: controller.signal,
                    });
                    if (!response.ok || response.redirected || !response.headers.get("Content-Type")?.includes("text/html")) {
                        throw new Error("refresh_unavailable");
                    }
                    const next = parse(await response.text()).querySelector("[data-ops-report]");
                    const asOf = next?.dataset.asOf;
                    if (!asOf || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(asOf) || !next.querySelector("table")) {
                        throw new Error("invalid_report");
                    }
                    return { asOf, nodes: Array.from(next.childNodes, (node) => document.importNode(node, true)) };
                })(),
                new Promise((_, reject) => {
                    timer = timers.setTimeout(() => {
                        controller.abort();
                        reject(new Error("refresh_timeout"));
                    }, timeoutMs);
                }),
            ]);
            if (disposed) return false;
            report.replaceChildren(...result.nodes);
            report.dataset.asOf = result.asOf;
            document.querySelector("[data-ops-validation-errors]")?.remove();
            status.textContent = `Pembacaan diperbarui: ${result.asOf}. Cakupan dan periode tercantum pada laporan.`;
            return true;
        } catch {
            if (disposed) return false;
            status.textContent = report.dataset.asOf
                ? `Data lama — penyegaran gagal. Snapshot ${report.dataset.asOf} tetap ditampilkan; filter baru belum diterapkan. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.`
                : "Ringkasan belum tersedia. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.";
            return false;
        } finally {
            timers.clearTimeout(timer);
            cancelRefresh = null;
            if (!disposed) {
                report.setAttribute("aria-busy", "false");
                button.disabled = false;
            }
            busy = false;
        }
    }

    function onSubmit(event) {
        event.preventDefault();
        const url = new URL(form.action, document.location.href);
        const FormData = document.defaultView.FormData;
        url.search = new URLSearchParams(new FormData(form)).toString();
        void refresh(url.href);
    }
    form.addEventListener('submit', onSubmit);
    root?.addEventListener('zafira:react-claim', dispose, { once: true });
    const api = { refresh };
    bindings.set(form, api);
    return api;
}

if (typeof document !== "undefined") initOperationsSummary();
