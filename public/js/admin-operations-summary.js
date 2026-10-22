// FR-REM-007: progressive manual refresh; the SSR snapshot stays in the DOM until success.
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
    const button = form.querySelector('[type="submit"]');
    let busy = false;

    async function refresh(url) {
        if (busy) return false;
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
            report.replaceChildren(...result.nodes);
            report.dataset.asOf = result.asOf;
            document.querySelector("[data-ops-validation-errors]")?.remove();
            status.textContent = `Pembacaan diperbarui: ${result.asOf}. Cakupan dan periode tercantum pada laporan.`;
            return true;
        } catch {
            status.textContent = report.dataset.asOf
                ? `Data lama — penyegaran gagal. Snapshot ${report.dataset.asOf} tetap ditampilkan; filter baru belum diterapkan. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.`
                : "Ringkasan belum tersedia. Periksa tanggal atau koneksi, lalu coba lagi. Jika sesi berakhir, masuk kembali.";
            return false;
        } finally {
            timers.clearTimeout(timer);
            report.setAttribute("aria-busy", "false");
            button.disabled = false;
            busy = false;
        }
    }

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        const url = new URL(form.action, document.location.href);
        const FormData = document.defaultView.FormData;
        url.search = new URLSearchParams(new FormData(form)).toString();
        void refresh(url.href);
    });
    return { refresh };
}

if (typeof document !== "undefined") initOperationsSummary();
