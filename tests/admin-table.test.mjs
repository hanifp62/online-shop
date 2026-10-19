import { test } from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { env, registry, linkClick, flush } from "./helpers/admin-table.mjs";

// Minimal elements for the R-44 selection/density enhancements.
function el(attrs = {}) {
    return {
        attrs: { ...attrs },
        checked: false,
        indeterminate: false,
        disabled: false,
        textContent: "",
        getAttribute(n) {
            return n in this.attrs ? this.attrs[n] : null;
        },
        setAttribute(n, v) {
            this.attrs[n] = String(v);
        },
        removeAttribute(n) {
            delete this.attrs[n];
        },
        closest(sel) {
            const m = sel.match(/^\[([\w-]+)\]$/);
            return m && m[1] in this.attrs ? this : null;
        },
    };
}

function enhancedEnv({ storage, clipboard } = {}) {
    const handlers = {};
    const nodes = {};
    const build = () => {
        nodes.toolbar = el({ "data-table-enhancement": "", hidden: "" });
        nodes.density = el({ "data-table-density": "pesanan" });
        nodes.table = el({ "data-table-body": "pesanan" });
        nodes.all = el({ "data-table-select-all": "pesanan" });
        nodes.count = el({
            "data-table-selection-count": "pesanan",
            "aria-live": "polite",
        });
        nodes.live = el({ "data-table-announcement": "" });
        nodes.copy = el({ "data-table-copy-selection": "pesanan" });
        nodes.boxes = [1, 2].map((id) =>
            Object.assign(el({ "data-table-select": "pesanan" }), {
                value: String(id),
            }),
        );
    };
    build();
    const document = {
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        querySelector(sel) {
            if (sel === "[data-table-announcement]") return nodes.live;
            if (sel === '[data-table-body="pesanan"]') return nodes.table;
            if (sel === '[data-table-select-all="pesanan"]') return nodes.all;
            if (sel === '[data-table-selection-count="pesanan"]')
                return nodes.count;
            if (sel === '[data-table-copy-selection="pesanan"]')
                return nodes.copy;
            return null;
        },
        querySelectorAll(sel) {
            if (sel === "[data-table-enhancement]") return [nodes.toolbar];
            if (sel === "[data-table-density]") return [nodes.density];
            if (sel === "[data-table-select-all]") return [nodes.all];
            if (sel === '[data-table-select="pesanan"]') return nodes.boxes;
            return [];
        },
    };
    const window = {
        location: { href: "http://x/admin", origin: "http://x" },
        history: { pushState() {} },
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        localStorage: storage,
        navigator: { clipboard },
    };
    initAdminTables({
        document,
        window,
        fetch: () => new Promise(() => {}),
        parse: () => ({ querySelector: () => null }),
    });
    return { handlers, nodes, rebuild: build };
}

test("FR-R44-002: bulk selection reveals toolbar, select-all checks visible rows, partial state is indeterminate", () => {
    const { handlers, nodes } = enhancedEnv();
    assert.equal(
        "hidden" in nodes.toolbar.attrs,
        false,
        "enhancement visible with JS",
    );
    assert.equal(nodes.copy.disabled, true);
    nodes.all.checked = true;
    handlers.change({ target: nodes.all });
    assert.deepEqual(
        nodes.boxes.map((b) => b.checked),
        [true, true],
    );
    assert.equal(nodes.count.textContent, "2 dipilih");
    assert.equal(nodes.copy.disabled, false);
    nodes.boxes[0].checked = false;
    handlers.change({ target: nodes.boxes[0] });
    assert.equal(nodes.all.checked, false);
    assert.equal(nodes.all.indeterminate, true);
    assert.equal(nodes.count.textContent, "1 dipilih");
});

test("FR-R44-003: density defaults compact, persists choice and survives localStorage failure", () => {
    const saved = new Map();
    const storage = {
        getItem: (k) => saved.get(k) ?? null,
        setItem: (k, v) => saved.set(k, v),
    };
    const { handlers, nodes } = enhancedEnv({ storage });
    assert.equal(nodes.table.getAttribute("data-density"), "compact");
    assert.equal(nodes.density.getAttribute("aria-pressed"), "true");
    handlers.click({ target: nodes.density });
    assert.equal(nodes.table.getAttribute("data-density"), "comfortable");
    assert.equal(nodes.density.getAttribute("aria-pressed"), "false");
    assert.equal(
        nodes.density.textContent,
        "",
        "toggle keeps its static label; state lives in aria-pressed",
    );
    assert.equal(saved.get("admin-table-density:pesanan"), "comfortable");
    const again = enhancedEnv({ storage });
    assert.equal(
        again.nodes.table.getAttribute("data-density"),
        "comfortable",
        "preference restored",
    );
    const throwing = {
        getItem() {
            throw new Error("denied");
        },
        setItem() {
            throw new Error("denied");
        },
    };
    const safe = enhancedEnv({ storage: throwing });
    assert.equal(safe.nodes.table.getAttribute("data-density"), "compact");
    assert.doesNotThrow(() =>
        safe.handlers.click({ target: safe.nodes.density }),
    );
    assert.equal(safe.nodes.table.getAttribute("data-density"), "comfortable");
});

test("FR-R44-002: copy with two boxes checked writes formatted IDs to clipboard and announces success via live region", async () => {
    const written = [];
    const { handlers, nodes } = enhancedEnv({
        clipboard: { writeText: async (text) => written.push(text) },
    });
    nodes.boxes[0].checked = true;
    nodes.boxes[1].checked = true;
    handlers.change({ target: nodes.boxes[1] });
    assert.equal(nodes.copy.disabled, false);

    assert.doesNotThrow(() => handlers.click({ target: nodes.copy }));
    await flush();

    assert.deepEqual(written, ["#1, #2"]);
    assert.equal(nodes.count.textContent, "No. pesanan tersalin.");
    assert.equal(nodes.count.attrs["aria-live"], "polite");
});

test("FR-R44-002: writeText rejection produces accessible failure message without throwing", async () => {
    const { handlers, nodes } = enhancedEnv({
        clipboard: {
            writeText: () => Promise.reject(new Error("Permission denied")),
        },
    });
    nodes.boxes[0].checked = true;
    nodes.boxes[1].checked = true;
    handlers.change({ target: nodes.boxes[1] });

    assert.doesNotThrow(() => handlers.click({ target: nodes.copy }));
    await flush();

    assert.equal(
        nodes.count.textContent,
        "Tidak dapat menyalin. Pilih ulang lalu coba lagi.",
    );
    assert.equal(nodes.count.attrs["aria-live"], "polite");
});

test("FR-R44-002: missing navigator.clipboard produces accessible failure/fallback message without throwing", async () => {
    const { handlers, nodes } = enhancedEnv(); // clipboard undefined
    nodes.boxes[0].checked = true;
    handlers.change({ target: nodes.boxes[0] });

    assert.doesNotThrow(() => handlers.click({ target: nodes.copy }));
    await flush();

    assert.equal(
        nodes.count.textContent,
        "Tidak dapat menyalin. Pilih ulang lalu coba lagi.",
    );
    assert.equal(nodes.count.attrs["aria-live"], "polite");
});

test("a11y: persistent live region announces loading, then the new page summary after swap", async () => {
    const { handlers, requests, document } = env();
    const live = { textContent: "" };
    const query = document.querySelector.bind(document);
    document.querySelector = (sel) =>
        sel === "[data-table-announcement]" ? live : query(sel);
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    assert.equal(live.textContent, "Memuat data tabel…");
    requests[0].respond("page2");
    await flush();
    // makeSection's h2 has no text; fake summary parts come from the swapped region.
    assert.notEqual(
        live.textContent,
        "Memuat data tabel…",
        "loading text must not linger",
    );
});

test("a11y: popstate targeting table A keeps focus in table B at swap time", async () => {
    const { handlers, requests, window, document } = env(["user", "produk"]);
    window.location.href = "http://x/admin?upage=2";
    handlers.popstate({ state: { adminTable: "user" } });
    document.activeElement = registry.get("produk").heading;

    requests[0].respond("page2");
    await flush();

    // NFR-006 restores focus within its original region, not the history target.
    assert.equal(registry.get("produk").heading.focused, true);
    assert.equal(document.activeElement, registry.get("produk").heading);
});

test("a11y: popstate leaves focus outside all tables untouched", async () => {
    const { handlers, requests, window, document } = env(["user", "produk"]);
    const outside = {};
    window.location.href = "http://x/admin?upage=2";
    handlers.popstate({ state: { adminTable: "user" } });
    document.activeElement = outside;

    requests[0].respond("page2");
    await flush();

    assert.equal(document.activeElement, outside);
    assert.equal(registry.get("user").heading.focused, false);
    assert.equal(registry.get("produk").heading.focused, false);
});

// Multi-table density: one toolbar button + one body per table key, swappable per section.
function densityEnv({ storage, keys = ["kategori", "produk"] } = {}) {
    const handlers = {};
    const requests = [];
    const live = el({ "data-table-announcement": "" });
    const sections = new Map();
    const build = (key) => {
        const section = Object.assign(el({ "data-admin-table": key }), {
            density: el({ "data-table-density": key }),
            body: el({ "data-table-body": key }),
            querySelectorAll: () => [],
            querySelector: () => null,
            contains: () => false,
            replaceWith(fresh) {
                sections.set(key, fresh);
            },
        });
        sections.set(key, section);
        return section;
    };
    keys.forEach(build);
    const current = (key) => sections.get(key);
    const document = {
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        querySelector(sel) {
            if (sel === "[data-table-announcement]") return live;
            const table = sel.match(/^\[data-admin-table="([\w-]+)"\]$/);
            if (table) return sections.get(table[1]) ?? null;
            const body = sel.match(/^\[data-table-body="([\w-]+)"\]$/);
            return body ? (sections.get(body[1])?.body ?? null) : null;
        },
        querySelectorAll(sel) {
            const all = Array.from(sections.values());
            if (sel === "[data-admin-table]") return all;
            if (sel === "[data-table-density]")
                return all.map((s) => s.density);
            return [];
        },
    };
    const window = {
        location: { href: "http://x/admin", origin: "http://x" },
        history: { pushState() {} },
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        localStorage: storage,
    };
    const fetch = (url, options) =>
        new Promise((resolve) => {
            requests.push({
                url,
                options,
                respond: () => resolve({ ok: true, text: async () => "ok" }),
            });
        });
    // Fresh server markup: no data-density yet, aria-pressed absent.
    const parse = () => ({
        querySelector: (sel) => {
            const m = sel.match(/data-admin-table="([\w-]+)"/);
            return m && keys.includes(m[1])
                ? Object.assign(el({ "data-admin-table": m[1] }), {
                      density: el({ "data-table-density": m[1] }),
                      body: el({ "data-table-body": m[1] }),
                      querySelectorAll: () => [],
                      querySelector: () => null,
                      contains: () => false,
                      replaceWith() {},
                  })
                : null;
        },
    });
    initAdminTables({ document, window, fetch, parse });
    const swap = async () => {
        const link = {
            href: "http://x/admin?kpage=2",
            closest: () => current(keys[0]),
        };
        handlers.click({
            target: {
                closest: (sel) => (sel === "a[data-table-link]" ? link : null),
            },
            button: 0,
            preventDefault() {},
        });
        requests.at(-1).respond();
        await flush();
    };
    return { handlers, current, swap };
}

function memoryStorage(seed = {}) {
    const saved = new Map(Object.entries(seed));
    return {
        saved,
        getItem: (k) => saved.get(k) ?? null,
        setItem: (k, v) => saved.set(k, v),
    };
}

test("FR-R44-003: density toggles are independent per table key", () => {
    const storage = memoryStorage();
    const { handlers, current } = densityEnv({ storage });
    const produkBefore = current("produk").body.getAttribute("data-density");
    const kategoriBefore =
        current("kategori").body.getAttribute("data-density");
    assert.ok(
        produkBefore && kategoriBefore,
        "both bodies get a density on enhance",
    );

    handlers.click({ target: current("kategori").density });
    const flipped = kategoriBefore === "compact" ? "comfortable" : "compact";
    assert.equal(
        current("kategori").body.getAttribute("data-density"),
        flipped,
    );
    assert.equal(
        current("produk").body.getAttribute("data-density"),
        produkBefore,
        "produk body untouched by kategori toggle",
    );
    assert.equal(
        current("produk").density.getAttribute("aria-pressed"),
        String(produkBefore === "compact"),
        "produk button untouched by kategori toggle",
    );
    assert.equal(storage.saved.get("admin-table-density:kategori"), flipped);
    assert.equal(
        storage.saved.has("admin-table-density:produk"),
        false,
        "produk preference not written by kategori toggle",
    );

    handlers.click({ target: current("produk").density });
    assert.equal(
        current("kategori").body.getAttribute("data-density"),
        flipped,
        "kategori body untouched by produk toggle",
    );
    assert.equal(
        storage.saved.get("admin-table-density:produk"),
        produkBefore === "compact" ? "comfortable" : "compact",
    );
});

test("FR-R44-003: stored density is restored per key on enhance, other keys fall back", () => {
    const { current } = densityEnv({
        storage: memoryStorage({ "admin-table-density:kategori": "compact" }),
    });
    assert.equal(
        current("kategori").body.getAttribute("data-density"),
        "compact",
    );
    assert.equal(
        current("kategori").density.getAttribute("aria-pressed"),
        "true",
    );

    const mixed = densityEnv({
        storage: memoryStorage({
            "admin-table-density:kategori": "compact",
            "admin-table-density:produk": "comfortable",
        }),
    });
    assert.equal(
        mixed.current("kategori").body.getAttribute("data-density"),
        "compact",
    );
    assert.equal(
        mixed.current("produk").body.getAttribute("data-density"),
        "comfortable",
    );
    assert.equal(
        mixed.current("produk").density.getAttribute("aria-pressed"),
        "false",
    );
    assert.equal(
        current("produk").body.getAttribute("data-density"),
        "compact",
        "unset key falls back to the compact default",
    );
});

test("FR-R44-003: density is reapplied per key after the table regions are swapped", async () => {
    const storage = memoryStorage({
        "admin-table-density:kategori": "compact",
        "admin-table-density:produk": "comfortable",
    });
    const { handlers, current, swap } = densityEnv({ storage });
    const oldKategori = current("kategori");
    handlers.click({ target: current("produk").density }); // produk -> compact
    handlers.click({ target: current("kategori").density }); // kategori -> comfortable

    await swap();

    assert.notEqual(
        current("kategori"),
        oldKategori,
        "region was really replaced",
    );
    assert.equal(
        current("kategori").body.getAttribute("data-density"),
        "comfortable",
    );
    assert.equal(
        current("kategori").density.getAttribute("aria-pressed"),
        "false",
    );
    assert.equal(
        current("produk").body.getAttribute("data-density"),
        "compact",
    );
    assert.equal(
        current("produk").density.getAttribute("aria-pressed"),
        "true",
    );
});

test("FR-R44-status: status-form submit intercepts, POSTs with JSON headers, and reloads table region", async () => {
    const { handlers, requests } = env(undefined, { ignoreAbort: true });
    const orderForm = {
        action: "http://x/admin/orders/42",
        resetCalled: false,
        reset() {
            this.resetCalled = true;
        },
        closest(sel) {
            if (sel === "form[data-admin-table-form]") return null;
            if (sel === "form[data-order-status-form]") return this;
            if (sel === "[data-admin-table]")
                return { getAttribute: () => "pesanan" };
            return null;
        },
    };
    let prevented = false;
    const event = {
        target: { closest: (sel) => orderForm.closest(sel) },
        preventDefault: () => {
            prevented = true;
        },
    };

    const submitPromise = handlers.submit(event, () => ({ fake: "body" }));
    assert.equal(prevented, true, "default form submission prevented");
    assert.equal(requests.length, 1, "fetch triggered for status update");
    assert.equal(requests[0].url, "http://x/admin/orders/42");
    assert.equal(requests[0].options.headers.Accept, "application/json");

    // Respond OK to status update
    requests[0].respond('{"order":{"id":42,"status":"processing"}}', true);
    await flush();

    // Second request is the table region reload
    assert.equal(requests.length, 2, "triggers table region reload");
    assert.equal(requests[1].url, "http://x/admin");
    assert.equal(requests[1].options.headers.Accept, "text/html");

    // Respond with fresh table markup
    requests[1].respond("fresh-table-html", true);
    await flush();
    await submitPromise;
    assert.equal(
        orderForm.resetCalled,
        true,
        "form reset called after successful status update",
    );
});
