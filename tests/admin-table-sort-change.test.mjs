import test from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { env, flush, registry } from "./helpers/admin-table.mjs";

// Self-contained fake DOM: one "pesanan" table with select-all, row boxes and a toolbar.
function selectionFixture() {
    const key = "pesanan";
    const handlers = {};
    const fetches = [];
    const box = (value) => ({
        value,
        checked: false,
        closest(sel) {
            return sel === "[data-table-select]" ? this : null;
        },
        getAttribute: () => key,
    });
    const all = {
        checked: false,
        indeterminate: false,
        closest(sel) {
            return sel === "[data-table-select-all]" ? this : null;
        },
        getAttribute: () => key,
    };
    const boxes = [box("1"), box("2")];
    const count = { textContent: "" };
    const copy = { disabled: true };
    const section = {
        getAttribute: (n) => (n === "data-admin-table" ? key : null),
        setAttribute() {},
        querySelector: () => null,
        querySelectorAll: () => [],
    };
    const document = {
        activeElement: null,
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        querySelectorAll(sel) {
            if (sel === "[data-admin-table]") return [section];
            if (sel === "[data-table-select-all]") return [all];
            if (sel === `[data-table-select="${key}"]`) return boxes;
            return [];
        },
        querySelector(sel) {
            if (sel === `[data-admin-table="${key}"]`) return section;
            if (sel === `[data-table-select-all="${key}"]`) return all;
            if (sel === `[data-table-selection-count="${key}"]`) return count;
            if (sel === `[data-table-copy-selection="${key}"]`) return copy;
            return null;
        },
    };
    const window = {
        addEventListener() {},
        location: { href: "http://x/admin", origin: "http://x" },
        history: { state: null, pushState() {} },
    };
    const fetch = (...args) => {
        fetches.push(args);
        return new Promise(() => {});
    };
    initAdminTables({ document, window, fetch, parse: () => null });
    return { handlers, fetches, all, boxes, count, copy };
}

test("change on select-all updates the toolbar without fetching", () => {
    const f = selectionFixture();
    f.all.checked = true;
    f.handlers.change({ target: f.all });
    assert.deepEqual(f.boxes.map((b) => b.checked), [true, true]);
    assert.equal(f.count.textContent, "2 dipilih");
    assert.equal(f.copy.disabled, false);
    assert.equal(f.fetches.length, 0);
});

test("change on a row checkbox updates the toolbar without fetching", () => {
    const f = selectionFixture();
    f.boxes[0].checked = true;
    f.handlers.change({ target: f.boxes[0] });
    assert.equal(f.count.textContent, "1 dipilih");
    assert.equal(f.copy.disabled, false);
    assert.equal(f.all.checked, false);
    assert.equal(f.all.indeterminate, true);
    assert.equal(f.fetches.length, 0);

    f.boxes[0].checked = false;
    f.handlers.change({ target: f.boxes[0] });
    assert.equal(f.count.textContent, "0 dipilih");
    assert.equal(f.copy.disabled, true);
    assert.equal(f.fetches.length, 0);
});

test("submitting a GET filter form with sort/dir fetches the query and swaps the table", async () => {
    const { handlers, requests, pushed } = env(["user"]);
    const old = registry.get("user");
    let prevented = false;
    const form = {
        action: "http://x/admin#panel-user",
        getAttribute: (n) => (n === "data-admin-table-form" ? "user" : null),
        entries: [
            ["uq", "Viv ne"],
            ["usort", "name"],
            ["udir", "desc"],
            ["empty", ""],
        ],
    };
    handlers.submit(
        {
            target: {
                closest: (sel) =>
                    sel === "form[data-admin-table-form]" ? form : null,
            },
            preventDefault() {
                prevented = true;
            },
        },
        (f) => f.entries,
    );
    assert.equal(prevented, true);
    assert.equal(requests.length, 1);
    assert.equal(
        requests[0].url,
        "http://x/admin?uq=Viv+ne&usort=name&udir=desc",
    );

    requests[0].respond("fresh");
    await flush();
    assert.notEqual(registry.get("user"), old);
    assert.equal(registry.get("user").label, "fresh");
    assert.equal(pushed.length, 1);
    assert.equal(
        pushed[0].url,
        "http://x/admin?uq=Viv+ne&usort=name&udir=desc#panel-user",
    );
});
