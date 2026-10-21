// Progressive enhancement for admin dashboard tables.
// Without JS the forms/links are plain GET navigation; with JS only the table regions are swapped.

const withoutHash = (href, base) => {
    const url = new URL(href, base);
    url.hash = "";
    return url;
};

export function initAdminTables({
    document: doc = document,
    window: win = window,
    fetch: fetcher = win.fetch?.bind(win),
    parse = (html) => new DOMParser().parseFromString(html, "text/html"),
} = {}) {
    // No fetch = keep native GET navigation untouched.
    if (typeof fetcher !== "function") return { load: async () => {} };
    let tableCopy = {};
    try {
        tableCopy = JSON.parse(doc.querySelector?.('#zafira-admin-table-copy')?.textContent || '{}');
    } catch {
        // Keep Indonesian fallbacks if the copy blob is unavailable or malformed.
    }
    const getAdminTableCopy = (key, fallback) =>
        typeof tableCopy?.[key] === 'string' ? tableCopy[key] : fallback;
    let controller = null;
    let statusSubmitting = false;
    const tableKeys = () =>
        Array.from(doc.querySelectorAll("[data-admin-table]"))
            .map((section) => section.getAttribute("data-admin-table"))
            .filter(Boolean);
    // Only same-origin requests to this very page may be swapped into the tables.
    const dashboard = new URL(win.location.href);
    const isDashboard = (url) =>
        url.origin === dashboard.origin && url.pathname === dashboard.pathname;
    // Query string the visible tables were rendered for; hash-only history moves need no request.
    let renderedSearch = withoutHash(win.location.href).search;
    // Full URL/state of the rendered tables, to undo a cancelled back/forward move.
    let renderedHref = win.location.href;
    let renderedState = win.history.state;

    // Keep session choices through swaps even when localStorage denies persistence.
    const densities = new Map();
    const stored = (key, value) => {
        try {
            if (value === undefined) return win.localStorage?.getItem(key);
            win.localStorage?.setItem(key, value);
        } catch {
            return null;
        }
        return null;
    };
    const densityKey = (key) => `admin-table-density:${key}`;
    const applyDensity = (button, density) => {
        const key = button.getAttribute("data-table-density");
        doc.querySelector(`[data-table-body="${key}"]`)?.setAttribute(
            "data-density",
            density,
        );
        // Label tetap "Baris ringkas"; state dinyatakan lewat aria-pressed saja.
        button.setAttribute("aria-pressed", String(density === "compact"));
    };
    const announce = (message) => {
        const live = doc.querySelector("[data-table-announcement]");
        if (live) live.textContent = message;
    };
    // Selection lives in the DOM only: a swapped page starts with nothing selected.
    const syncSelection = (key) => {
        const boxes = Array.from(
            doc.querySelectorAll(`[data-table-select="${key}"]`),
        );
        const picked = boxes.filter((box) => box.checked).length;
        const all = doc.querySelector(`[data-table-select-all="${key}"]`);
        if (all) {
            all.checked = boxes.length > 0 && picked === boxes.length;
            all.indeterminate = picked > 0 && picked < boxes.length;
        }
        const count = doc.querySelector(
            `[data-table-selection-count="${key}"]`,
        );
        if (count) count.textContent = getAdminTableCopy('selected', ':count dipilih').replace(':count', String(picked));
        const copy = doc.querySelector(`[data-table-copy-selection="${key}"]`);
        if (copy) copy.disabled = picked === 0;
    };
    // Re-run after every swap: fresh server markup is hidden; density defaults to compact.
    const enhance = () => {
        doc.querySelectorAll("[data-table-enhancement]").forEach((node) =>
            node.removeAttribute("hidden"),
        );
        doc.querySelectorAll("[data-table-density]").forEach((button) => {
            const key = button.getAttribute("data-table-density");
            if (!densities.has(key))
                densities.set(
                    key,
                    stored(densityKey(key)) === "comfortable"
                        ? "comfortable"
                        : "compact",
                );
            applyDensity(button, densities.get(key));
        });
        doc.querySelectorAll("[data-table-select-all]").forEach((all) =>
            syncSelection(all.getAttribute("data-table-select-all")),
        );
    };
    enhance();

    // Every region is replaced on load, so unsaved POST edits anywhere would be lost.
    // A single <select> with no selected attribute still shows its first enabled option,
    // while that option's defaultSelected stays false: compare the whole selection instead.
    // An option inside a disabled <optgroup> is also disabled in HTML: check :disabled first.
    const optionDisabled = (option) =>
        Boolean(option.matches?.(":disabled") ?? option.disabled);
    const defaultOption = (options) =>
        options.filter((option) => option.defaultSelected).pop() ??
        options.find((option) => !optionDisabled(option));
    const selectDirty = (control) => {
        const options = Array.from(control.options ?? []);
        // A multi-select or list box really can start with nothing selected.
        return control.multiple || Number(control.size) > 1
            ? options.some(
                  (option) => option.selected !== option.defaultSelected,
              )
            : options.find((option) => option.selected) !==
                  defaultOption(options);
    };
    const isDirty = (control) =>
        control.tagName === "SELECT"
            ? selectDirty(control)
            : control.type === "checkbox" || control.type === "radio"
              ? control.checked !== control.defaultChecked
              : control.value !== control.defaultValue;
    // Snapshot of an accepted draft, to tell "still the draft the user already
    // agreed to discard" apart from "typed something new during the fetch".
    const controlValue = (control) =>
        control.tagName === "SELECT"
            ? Array.from(control.options ?? [])
                  .map((option) => String(option.selected))
                  .join(",")
            : control.type === "checkbox" || control.type === "radio"
              ? String(control.checked)
              : control.value;
    const dirtyControls = () =>
        Array.from(doc.querySelectorAll("[data-admin-table]")).flatMap(
            (section) =>
                Array.from(section.querySelectorAll("form"))
                    .filter(
                        (form) => String(form.method).toLowerCase() !== "get",
                    )
                    .flatMap((form) =>
                        Array.from(
                            form.querySelectorAll("input, textarea, select"),
                        ),
                    )
                    .filter(isDirty),
        );
    // null = the user refused; otherwise the snapshot of what they agreed to discard.
    const mayDiscard = (accepted) => {
        const dirty = dirtyControls();
        const agreed = (control) =>
            accepted?.has(control) &&
            accepted.get(control) === controlValue(control);
        if (
            dirty.length &&
            !dirty.every(agreed) &&
            !win.confirm(
                getAdminTableCopy("unsaved_confirm", "Ada perubahan yang belum disimpan di tabel admin. Lanjutkan dan buang perubahan?"),
            )
        )
            return null;
        return new Map(
            dirty.map((control) => [control, controlValue(control)]),
        );
    };

    // Status change posts JSON (FormData carries _token + _method=PATCH), then swaps
    // only the table regions; without JS the form stays a native POST.
    const submitOrderStatus = async (form, key, body) => {
        if (statusSubmitting || form.getAttribute?.("data-submitting") === "true") return;
        const targetStatus = (typeof body?.get === "function" ? body.get("status") : body?.status) || "";
        if (targetStatus === "cancelled" && !win.confirm(getAdminTableCopy("cancel_confirm", "Batalkan pesanan ini secara permanen? Tindakan ini tidak dapat diurungkan."))) {
            form.reset?.();
            return;
        }
        statusSubmitting = true;
        form.setAttribute?.("data-submitting", "true");
        const section = doc.querySelector(`[data-admin-table="${key}"]`);
        const controls = Array.from(
            form.querySelectorAll?.("select, button, input") ?? [],
        );
        controls.forEach((c) => (c.disabled = true));
        controller?.abort();
        controller = null;
        section?.setAttribute("aria-busy", "true");
        announce(getAdminTableCopy("saving", "Menyimpan status pesanan…"));
        try {
            const response = await fetcher(form.action, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body,
            });
            if (!response.ok) {
                let data = null;
                try {
                    data = await response.json?.();
                } catch {
                    data = null;
                }
                const msg =
                    data?.errors?.status?.[0] ??
                    data?.message ??
                    getAdminTableCopy("save_failed", "Gagal memperbarui status pesanan.");
                announce(msg);
                if (section) showError(section, "http", msg);
                return;
            }
            await load(key, renderedHref, { focus: "if-inside" });
            announce(getAdminTableCopy("saved", "Status pesanan berhasil diperbarui."));
            // The submitted select is no longer a draft; do not ask to discard it.
            form.reset?.();
        } catch {
            if (section) showError(section);
        } finally {
            statusSubmitting = false;
            form.removeAttribute?.("data-submitting");
            controls.forEach((c) => (c.disabled = false));
            section?.setAttribute("aria-busy", "false");
        }
    };

    const showError = (section, kind = "network", customMessage = null) => {
        tableKeys().forEach((k) =>
            doc
                .querySelector(`[data-admin-table="${k}"]`)
                ?.setAttribute("aria-busy", "false"),
        );
        const box = section.querySelector("[data-table-error]");
        if (!box) return;
        box.textContent =
            customMessage ||
            (kind === "login"
                ? getAdminTableCopy("session_inactive", "Sesi admin tidak aktif. Masuk kembali untuk melanjutkan.")
                : kind === "http"
                  ? getAdminTableCopy("http_error", "Tabel belum dapat diperbarui. Coba lagi.")
                  : getAdminTableCopy("network_error", "Data tabel gagal dimuat. Periksa koneksi lalu coba lagi."));
        if (kind === "login" && doc.createElement && box.appendChild) {
            const configured = box.getAttribute?.("data-login-url");
            let login = "/admin/login";
            if (configured) {
                try {
                    const url = new URL(configured, win.location.href);
                    if (
                        url.origin === win.location.origin &&
                        /^https?:$/.test(url.protocol)
                    )
                        login = url.href;
                } catch {
                    // Invalid configuration falls back to the same-origin login route.
                }
            }
            const link = doc.createElement("a");
            link.href = login;
            link.textContent = getAdminTableCopy("login_again", "Masuk kembali");
            box.appendChild(link);
        }
        box.hidden = false;
    };

    const load = async (
        key,
        href,
        { push = false, focus = true, restoreOnCancel = false } = {},
    ) => {
        const current = doc.querySelector(`[data-admin-table="${key}"]`);
        if (!current) return;
        const accepted = mayDiscard();
        if (!accepted) {
            // A cancelled history move supersedes pending navigation before restoring URL.
            if (restoreOnCancel) {
                controller?.abort();
                controller = null;
                tableKeys().forEach((k) =>
                    doc
                        .querySelector(`[data-admin-table="${k}"]`)
                        ?.setAttribute("aria-busy", "false"),
                );
                announce("");
                // Recovery repairs the entry the browser already moved to; a push would
                // leave the rejected URL behind as an extra Back step.
                win.history.replaceState(renderedState, "", renderedHref);
            }
            return;
        }
        const target = withoutHash(href, win.location.href);
        if (!isDashboard(target)) {
            announce("");
            showError(current);
            return;
        }
        controller?.abort();
        const mine = new AbortController();
        controller = mine;
        // Snapshot the region keys once: the swap below changes the live DOM.
        const keys = tableKeys();
        // Every region gets replaced on success, so every region announces the update.
        keys.forEach((k) =>
            doc
                .querySelector(`[data-admin-table="${k}"]`)
                ?.setAttribute("aria-busy", "true"),
        );
        announce(getAdminTableCopy("loading", "Memuat data tabel…"));
        const staleError = current.querySelector("[data-table-error]");
        if (staleError) {
            staleError.hidden = true;
            staleError.textContent = "";
        }
        let errorKind = "network";
        try {
            const response = await fetcher(target.toString(), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
                credentials: "same-origin",
                signal: mine.signal,
            });
            if (!response.ok) {
                errorKind = "http";
                throw new Error("HTTP response not OK");
            }
            if (
                response.url &&
                !isDashboard(new URL(response.url, win.location.href))
            ) {
                errorKind = "login";
                throw new Error("Redirected outside dashboard");
            }
            const html = await response.text();
            if (controller !== mine) return;
            const fresh = parse(html);
            // Every region must be present before any swap, else a half-replaced DOM
            // leaves stale links/hidden inputs while the URL has already moved on.
            if (
                keys.some(
                    (k) => !fresh.querySelector(`[data-admin-table="${k}"]`),
                )
            ) {
                throw new Error("Table region missing");
            }
            if (!mayDiscard(accepted)) {
                announce("");
                if (restoreOnCancel)
                    win.history.replaceState(renderedState, "", renderedHref);
                return;
            }
            // Decide at swap time: the user may have moved focus while the fetch was pending.
            const active = doc.activeElement;
            const focusedKey = keys.find((k) => {
                const section = doc.querySelector(`[data-admin-table="${k}"]`);
                return (
                    section &&
                    (active === section || section.contains?.(active))
                );
            });
            // "unless-elsewhere": also take focus from <body> (e.g. Safari does not focus clicked links).
            const idle = !active || active === doc.body;
            // If focus moves between regions during fetch, restore it to its region at swap time.
            const focusKey =
                focus === "if-inside"
                    ? (focusedKey ?? null)
                    : focus === "unless-elsewhere"
                      ? (focusedKey ?? (idle ? key : null))
                      : focus
                        ? key
                        : null;
            keys.forEach((k) => {
                const next = fresh.querySelector(`[data-admin-table="${k}"]`);
                const prev = doc.querySelector(`[data-admin-table="${k}"]`);
                if (!next || !prev) return;
                prev.replaceWith(next);
            });
            enhance();
            const updated = doc.querySelector(`[data-admin-table="${key}"]`);
            const summary = [
                updated?.querySelector("h2")?.textContent,
                updated?.querySelector(".table-pagination-status")?.textContent,
                updated?.querySelector(`[data-panel-count="${key}"]`)
                    ?.textContent,
            ]
                .map((part) => part?.replace(/\s+/g, " ").trim())
                .filter(Boolean)
                .join(". ");
            announce(summary);
            renderedSearch = target.search;
            renderedHref = href;
            renderedState = push ? { adminTable: key } : win.history.state;
            if (push) win.history.pushState(renderedState, "", href);
            // Focus goes to the region the user was in at swap time, not the trigger.
            if (focusKey)
                doc.querySelector(`[data-admin-table="${focusKey}"]`)
                    ?.querySelector("h2")
                    ?.focus();
        } catch (error) {
            if (error.name === "AbortError" || controller !== mine) return;
            if (restoreOnCancel)
                win.history.replaceState(renderedState, "", renderedHref);
            announce("");
            showError(current, errorKind);
        } finally {
            if (controller === mine) {
                tableKeys().forEach((k) =>
                    doc
                        .querySelector(`[data-admin-table="${k}"]`)
                        ?.setAttribute("aria-busy", "false"),
                );
                controller = null;
            }
        }
    };

    doc.addEventListener("change", (event) => {
        const all = event.target.closest?.("[data-table-select-all]");
        const box = event.target.closest?.("[data-table-select]");
        const key =
            all?.getAttribute("data-table-select-all") ??
            box?.getAttribute("data-table-select");
        if (!key) return;
        if (all)
            doc.querySelectorAll(`[data-table-select="${key}"]`).forEach(
                (item) => {
                    item.checked = all.checked;
                },
            );
        syncSelection(key);
    });

    doc.addEventListener("click", (event) => {
        const density = event.target.closest?.("[data-table-density]");
        if (typeof density?.getAttribute === "function") {
            const key = density.getAttribute("data-table-density");
            const next =
                density.getAttribute("aria-pressed") === "true"
                    ? "comfortable"
                    : "compact";
            densities.set(key, next);
            applyDensity(density, next);
            stored(densityKey(key), next);
            return;
        }
        const copy = event.target.closest?.("[data-table-copy-selection]");
        if (typeof copy?.getAttribute === "function") {
            const key = copy.getAttribute("data-table-copy-selection");
            const ids = Array.from(
                doc.querySelectorAll(`[data-table-select="${key}"]`),
            )
                .filter((box) => box.checked)
                .map((box) => `#${box.value}`)
                .join(", ");
            const count = doc.querySelector(
                `[data-table-selection-count="${key}"]`,
            );
            const failed = () => {
                if (count)
                    count.textContent =
                        getAdminTableCopy("copy_failed", "Tidak dapat menyalin. Pilih ulang lalu coba lagi.");
            };
            if (!win.navigator?.clipboard?.writeText) failed();
            else
                win.navigator.clipboard.writeText(ids).then(() => {
                    if (count) count.textContent = getAdminTableCopy("copied", "No. pesanan tersalin.");
                }, failed);
            return;
        }
        const link = event.target.closest?.("a[data-table-link]");
        if (
            !link ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        if (!isDashboard(new URL(link.href, win.location.href))) return;
        const key = link
            .closest("[data-admin-table]")
            ?.getAttribute("data-admin-table");
        if (!key) return;
        event.preventDefault();
        load(key, link.href, { push: true, focus: "unless-elsewhere" });
    });

    doc.addEventListener(
        "submit",
        (event, entries = (form) => new FormData(form)) => {
            const form = event.target.closest?.("form[data-admin-table-form]");
            if (!form) {
                const orderForm = event.target.closest?.(
                    "form[data-order-status-form]",
                );
                const key = orderForm
                    ?.closest?.("[data-admin-table]")
                    ?.getAttribute("data-admin-table");
                if (!key) return;
                event.preventDefault();
                return submitOrderStatus(orderForm, key, entries(orderForm));
            }
            const action = new URL(
                form.action || win.location.href,
                win.location.href,
            );
            if (!isDashboard(action)) return;
            event.preventDefault();
            const url = withoutHash(action);
            url.search = ""; // Native GET submission replaces the action query string.
            for (const [name, value] of entries(form)) {
                if (typeof value === "string" && value !== "")
                    url.searchParams.append(name, value);
            }
            url.hash = action.hash;
            load(form.getAttribute("data-admin-table-form"), url.toString(), {
                push: true,
                focus: "unless-elsewhere",
            });
        },
    );

    win.addEventListener("popstate", (event) => {
        // Same query and nothing in flight = hash-only move; an in-flight click must still be superseded.
        if (
            !controller &&
            withoutHash(win.location.href).search === renderedSearch
        ) {
            // The current entry still shows these tables; later recovery must return here.
            renderedHref = win.location.href;
            renderedState = win.history.state;
            return;
        }
        const keys = tableKeys();
        const key = keys.includes(event.state?.adminTable)
            ? event.state.adminTable
            : keys[0];
        // Decide at swap time: the user may move focus while the fetch is pending.
        load(key, win.location.href, {
            focus: "if-inside",
            restoreOnCancel: true,
        });
    });

    return { load };
}
