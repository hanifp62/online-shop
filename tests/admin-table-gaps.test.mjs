import test from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { ALL_TABLE_KEYS, env, linkClick, flush, registry } from "./helpers/admin-table.mjs";

const url = "http://x/admin?page=2";

function controls(keys = ALL_TABLE_KEYS) {
    const sections = new Map();
    const storage = new Map();
    const announcement = { textContent: "" };
    const make = (key, label = "old") => {
        const attrs = {};
        const body = { attrs: {}, setAttribute(name, value) { this.attrs[name] = value; } };
        const button = { attrs: { "data-table-density": key }, getAttribute(name) { return this.attrs[name]; }, setAttribute(name, value) { this.attrs[name] = value; }, closest(sel) { return sel === "[data-table-density]" ? this : null; } };
        const boxes = [1, 2].map((value) => ({ value: String(value), checked: false, closest(sel) { return sel === "[data-table-select]" ? this : null; }, getAttribute() { return key; } }));
        const all = { checked: false, indeterminate: false, closest(sel) { return sel === "[data-table-select-all]" ? this : null; }, getAttribute() { return key; } };
        const copy = { disabled: true };
        const count = { textContent: "" };
        const heading = { textContent: `Tabel ${key}`, focus() {} };
        const status = { textContent: label === "old" ? "Halaman 1 dari 3" : `Halaman 2 dari 3 (${label})` };
        const error = { hidden: true, textContent: "" };
        return { key, body, button, boxes, all, copy, count, heading, status, error, forms: [], attrs, querySelectorAll(sel) { return sel === "form" ? this.forms : []; }, querySelector(sel) { return sel === "h2" ? heading : sel === ".table-pagination-status" ? status : sel === "[data-table-error]" ? error : null; }, getAttribute(name) { return name === "data-admin-table" ? key : attrs[name]; }, setAttribute(name, value) { attrs[name] = value; }, replaceWith(next) { sections.set(key, next); } };
    };
    keys.forEach((key) => sections.set(key, make(key)));
    const handlers = {};
    const requests = [];
    const document = {
        activeElement: null,
        addEventListener(type, fn) { handlers[type] = fn; },
        querySelectorAll(sel) {
            if (sel === "[data-admin-table]") return [...sections.values()];
            if (sel === "[data-table-enhancement]") return [];
            if (sel === "[data-table-density]") return [...sections.values()].map((s) => s.button);
            if (sel === "[data-table-select-all]") return [...sections.values()].map((s) => s.all);
            const match = sel.match(/^\[data-table-select="([\w-]+)"\]$/);
            return match ? sections.get(match[1])?.boxes ?? [] : [];
        },
        querySelector(sel) {
            if (sel === "[data-table-announcement]") return announcement;
            const match = sel.match(/^\[data-([\w-]+)="([\w-]+)"\]$/);
            if (!match) return null;
            const [, attr, key] = match;
            const section = sections.get(key);
            return attr === "admin-table" ? section : attr === "table-body" ? section?.body : attr === "table-select-all" ? section?.all : attr === "table-selection-count" ? section?.count : attr === "table-copy-selection" ? section?.copy : null;
        },
    };
    const window = { addEventListener() {}, location: { href: "http://x/admin", origin: "http://x" }, history: { state: null, pushState() {} }, localStorage: { getItem(key) { return storage.get(key) ?? null; }, setItem(key, value) { storage.set(key, value); } } };
    const fetch = (href, options) => new Promise((resolve) => requests.push({ href, options, respond(html = "fresh", ok = true) { resolve({ ok, text: async () => html }); } }));
    const parse = (html) => ({ querySelector(sel) { const key = sel.match(/data-admin-table="([\w-]+)"/)?.[1]; return key ? make(key, html) : null; } });
    const { load } = initAdminTables({ document, window, fetch, parse });
    return { sections, storage, announcement, handlers, requests, load };
}

test("NFR-R44-headers: partial requests accept HTML and identify XMLHttpRequest", async () => {
    const { handlers, requests } = env(["user"]);
    const click = linkClick(url);
    handlers.click(click.event);
    assert.equal(requests[0].options.headers.Accept, "text/html");
    assert.equal(requests[0].options.headers["X-Requested-With"], "XMLHttpRequest");
    requests[0].respond("fresh");
    await flush();
});

test("FR-R44-selection: deselect-all clears boxes and disables action", () => {
    const fixture = controls(["pesanan"]);
    const section = fixture.sections.get("pesanan");
    section.all.checked = true;
    fixture.handlers.change({ target: section.all });
    assert.equal(section.copy.disabled, false);
    section.all.checked = false;
    fixture.handlers.change({ target: section.all });
    assert.deepEqual(section.boxes.map((box) => box.checked), [false, false]);
    assert.equal(section.copy.disabled, true);
    assert.equal(section.count.textContent, "0 dipilih");
});

test("FR-R44-selection: swap clears selection in replacement markup", async () => {
    const fixture = controls(["pesanan"]);
    const old = fixture.sections.get("pesanan");
    old.boxes[0].checked = true;
    fixture.handlers.change({ target: old.boxes[0] });
    const pending = fixture.load("pesanan", url);
    fixture.requests[0].respond();
    await pending;
    const fresh = fixture.sections.get("pesanan");
    assert.notEqual(fresh, old);
    assert.deepEqual(fresh.boxes.map((box) => box.checked), [false, false]);
    assert.equal(fresh.copy.disabled, true);
    assert.equal(fresh.count.textContent, "0 dipilih");
});

test("FR-R44-selection: orders selection never modifies another table", () => {
    const fixture = controls(["pesanan", "user"]);
    const orders = fixture.sections.get("pesanan");
    const users = fixture.sections.get("user");
    orders.all.checked = true;
    fixture.handlers.change({ target: orders.all });
    assert.deepEqual(users.boxes.map((box) => box.checked), [false, false]);
    assert.equal(users.copy.disabled, true);
    assert.equal(users.count.textContent, "0 dipilih");
});

test("a11y-R44-announcement: successful swap reads response page summary", async () => {
    const fixture = controls(["user"]);
    const pending = fixture.load("user", url);
    fixture.requests[0].respond("hasil terbaru");
    await pending;
    assert.ok(fixture.announcement.textContent);
    assert.match(fixture.announcement.textContent, /Halaman 2 dari 3 \(hasil terbaru\)/);
});

for (const key of ALL_TABLE_KEYS) {
    test(`FR-R44-density: ${key} defaults compact and restores comfortable after swap`, async () => {
        const fixture = controls();
        const section = fixture.sections.get(key);
        assert.equal(section.body.attrs["data-density"], "compact");
        assert.equal(section.button.attrs["aria-pressed"], "true");
        fixture.handlers.click({ target: section.button });
        assert.equal(section.body.attrs["data-density"], "comfortable");
        assert.equal(fixture.storage.get(`admin-table-density:${key}`), "comfortable");
        for (const other of ALL_TABLE_KEYS.filter((candidate) => candidate !== key)) {
            assert.equal(fixture.sections.get(other).body.attrs["data-density"], "compact");
            assert.equal(fixture.storage.has(`admin-table-density:${other}`), false);
        }
        const pending = fixture.load(key, url);
        fixture.requests[0].respond();
        await pending;
        assert.equal(fixture.sections.get(key).body.attrs["data-density"], "comfortable");
        assert.equal(fixture.sections.get(key).button.attrs["aria-pressed"], "false");
    });
}

test("UX-R44-click: modified clicks on [data-table-link] are ignored", () => {
    const { handlers, requests } = env(["user"]);
    const modifiedVariants = [
        { metaKey: true },
        { shiftKey: true },
        { altKey: true },
        { button: 1 },
    ];
    for (const mods of modifiedVariants) {
        const click = linkClick(url);
        Object.assign(click.event, mods);
        handlers.click(click.event);
        assert.equal(click.prevented, false);
    }
    assert.equal(requests.length, 0);
});

test("UX-R44-error: exact error text on failure and retry hides previous banner immediately", async () => {
    const { handlers, requests } = env(["user"]);
    const click = linkClick(url);
    handlers.click(click.event);
    assert.equal(requests.length, 1);
    requests[0].respond("error", false);
    await flush();

    const section = registry.get("user");
    assert.equal(section.error.hidden, false);
    // HCI-03: non-OK HTTP response (ok: false) uses the retry copy, not the network copy.
    assert.equal(
        section.error.textContent,
        "Tabel belum dapat diperbarui. Coba lagi.",
    );

    const retry = linkClick(url);
    handlers.click(retry.event);
    assert.equal(requests.length, 2);
    assert.equal(section.error.hidden, true);
    requests[1].respond("fresh", true);
    await flush();
});

test("UX-R44-dirty: dirty control in GET filter form never triggers window.confirm", async () => {
    let confirmCalled = false;
    const { handlers, requests, window } = env(["user"]);
    window.confirm = () => {
        confirmCalled = true;
        return false;
    };
    const userSection = registry.get("user");
    const input = {
        tagName: "INPUT",
        type: "text",
        defaultValue: "old",
        value: "dirty-search-term",
    };
    const getForm = {
        method: "get",
        querySelectorAll(sel) {
            return sel === "input, textarea, select" ? [input] : [];
        },
    };
    userSection.forms = [getForm];

    const click = linkClick(url);
    handlers.click(click.event);
    assert.equal(confirmCalled, false);
    assert.equal(requests.length, 1);
    requests[0].respond("fresh", true);
    await flush();
    assert.equal(confirmCalled, false);
});

for (const [reason, target] of [
    ["wrong origin", "http://evil.com/admin"],
    ["wrong pathname", "http://x/phishing"],
]) {
    test(`UX-R44-api: direct load with ${reason} fails closed, clears announcement, reveals error banner`, async () => {
        const fixture = controls(["user"]);
        fixture.announcement.textContent = "Previous announcement";
        const section = fixture.sections.get("user");
        assert.equal(section.error.hidden, true);

        await fixture.load("user", target);

        assert.equal(fixture.requests.length, 0);
        assert.equal(fixture.announcement.textContent, "");
        assert.equal(section.error.hidden, false);
        assert.equal(
            section.error.textContent,
            "Data tabel gagal dimuat. Periksa koneksi lalu coba lagi.",
        );
    });
}

