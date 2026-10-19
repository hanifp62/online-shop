import { initAdminTables } from "../../resources/js/admin-table.js";


// Minimal fake DOM: one table section per key; replaceWith swaps the registry entry.
export const ALL_TABLE_KEYS = [
    "kategori",
    "produk",
    "pesanan",
    "user",
    "audit",
    "concierge",
    "subscriber",
];

function makeSection(key, label = "old") {
    const heading = {
        focused: false,
        focus() {
            this.focused = true;
            if (currentDoc) currentDoc.activeElement = this;
        },
    };
    const error = { hidden: true, textContent: "" };
    const children = new Set([heading, error]);
    const section = {
        key,
        label,
        attrs: { "aria-busy": "false" },
        heading,
        error,
        replacedBy: null,
        forms: [],
        querySelectorAll(sel) {
            return sel === "form" ? this.forms : [];
        },
        getAttribute(name) {
            return name === "data-admin-table" ? key : this.attrs[name];
        },
        setAttribute(name, value) {
            this.attrs[name] = value;
        },
        querySelector(sel) {
            return sel === "h2"
                ? heading
                : sel === "[data-table-error]"
                  ? error
                  : null;
        },
        contains(node) {
            return children.has(node) || node === this;
        },
        replaceWith(fresh) {
            this.replacedBy = fresh;
            registry.set(key, fresh);
        },
    };
    return section;
}
export const registry = new Map();
// Fake focus() updates the active document's activeElement like a real browser.
let currentDoc = null;

export function env(keys = ALL_TABLE_KEYS, { ignoreAbort = false } = {}) {
    registry.clear();
    for (const k of keys) {
        registry.set(k, makeSection(k));
    }
    const handlers = {};
    const requests = [];
    const pushed = [];
    const replaced = [];
    const historyEntries = [{ state: null, url: "http://x/admin" }];
    let historyIndex = 0;
    const setLocation = (url) => {
        window.location.href = new URL(url, window.location.href).href;
    };
    const document = {
        activeElement: null,
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        querySelector(sel) {
            if (sel === "[data-admin-table]") {
                return registry.values().next().value ?? null;
            }
            const m = sel.match(/data-admin-table="([\w-]+)"/);
            return m ? (registry.get(m[1]) ?? null) : null;
        },
        querySelectorAll(sel) {
            return sel === "[data-admin-table]"
                ? Array.from(registry.values())
                : [];
        },
    };
    const window = {
        location: { href: "http://x/admin", origin: "http://x" },
        history: {
            get state() {
                return historyEntries[historyIndex].state;
            },
            pushState(state, _t, url) {
                setLocation(url);
                pushed.push({ state, url });
                historyEntries.splice(++historyIndex, Infinity, {
                    state: structuredClone(state), url: window.location.href,
                });
            },
            replaceState(state, _t, url) {
                setLocation(url);
                replaced.push({ state, url: window.location.href });
                historyEntries[historyIndex] = {
                    state: structuredClone(state), url: window.location.href,
                };
            },
            back() {
                if (historyIndex === 0) return;
                const entry = historyEntries[--historyIndex];
                setLocation(entry.url);
                handlers.popstate?.({ state: structuredClone(entry.state) });
            },
            forward() {
                if (historyIndex === historyEntries.length - 1) return;
                const entry = historyEntries[++historyIndex];
                setLocation(entry.url);
                handlers.popstate?.({ state: structuredClone(entry.state) });
            },
        },
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
    };
    const fetch = (url, options) =>
        new Promise((resolve, reject) => {
            const entry = {
                url,
                options,
                fail: reject,
                respond(html, ok = true) {
                    resolve({ ok, text: async () => html });
                },
            };
            if (!ignoreAbort) options.signal.addEventListener("abort", () => {
                const e = new Error("aborted");
                e.name = "AbortError";
                reject(e);
            });
            requests.push(entry);
        });
    // 'missing' drops every region; 'no-<key>' drops only that companion region.
    const parse = (html) => ({
        querySelector: (sel) => {
            if (html === "missing") return null;
            const m = sel.match(/data-admin-table="([\w-]+)"/);
            if (!m) return null;
            const key = m[1];
            if (html === `no-${key}`) return null;
            return keys.includes(key) ? makeSection(key, html) : null;
        },
    });
    currentDoc = document;
    initAdminTables({ document, window, fetch, parse });
    return { handlers, requests, pushed, replaced, window, document };
}

export function linkClick(href) {
    let prevented = false;
    const link = {
        href,
        closest: (sel) =>
            sel === "[data-admin-table]" ? registry.get("user") : null,
    };
    return {
        event: {
            target: {
                closest: (sel) => (sel === "a[data-table-link]" ? link : null),
            },
            button: 0,
            preventDefault() {
                prevented = true;
            },
        },
        get prevented() {
            return prevented;
        },
    };
}

export const flush = () => new Promise((r) => setTimeout(r, 0));

