import { test } from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { env, registry } from "./helpers/admin-table.mjs";

const SESSION = "Sesi admin tidak aktif. Masuk kembali untuk melanjutkan.";
const HTTP = "Tabel belum dapat diperbarui. Coba lagi.";
const NETWORK = "Data tabel gagal dimuat. Periksa koneksi lalu coba lagi.";

function setup(loginUrl) {
    const { document, window } = env();
    const box = registry.get("user").error;
    let text = "";
    box.children = [];
    Object.defineProperty(box, "textContent", {
        get: () => text,
        set(value) { text = value; box.children = []; },
    });
    box.getAttribute = (name) => name === "data-login-url" ? loginUrl : null;
    box.appendChild = (node) => box.children.push(node);
    document.createElement = (tag) => ({ tagName: tag.toUpperCase() });
    const pending = [];
    const api = initAdminTables({
        document, window,
        fetch: () => new Promise((resolve, reject) => pending.push({ resolve, reject })),
        parse: () => document,
    });
    return { ...api, box, pending, window };
}

for (const loginUrl of [undefined, "/atelier/login", "https://untrusted.test/login", "javascript:alert(1)"]) {
    test(`HCI-03: redirect offers trusted login link (${loginUrl ?? "fallback"})`, async () => {
        const { load, box, pending, window } = setup(loginUrl);
        const loading = load("user", "http://x/admin?upage=2");
        pending[0].resolve({ ok: true, url: "https://untrusted.test/raw-secret", text: async () => assert.fail("must not parse redirect") });
        await loading;
        assert.equal(box.textContent.trim(), SESSION);
        assert.equal(box.hidden, false);
        assert.equal(box.children.length, 1);
        assert.equal(box.children[0].tagName, "A");
        assert.equal(new URL(box.children[0].href, window.location.href).href,
            loginUrl === "/atelier/login" ? "http://x/atelier/login" : "http://x/admin/login");
        assert.equal(box.children[0].textContent, "Masuk kembali");
        assert.equal(window.location.href, "http://x/admin");
    });
}

for (const status of [403, 500, 503]) {
    test(`HCI-03: HTTP ${status} gets retry copy without raw details`, async () => {
        const { load, box, pending } = setup();
        const loading = load("user", "http://x/admin?upage=2");
        pending[0].resolve({ ok: false, status });
        await loading;
        assert.equal(box.textContent, HTTP);
        assert.deepEqual(box.children, []);
    });
}

test("HCI-03: fetch failure keeps network copy", async () => {
    const { load, box, pending } = setup();
    const loading = load("user", "http://x/admin?upage=2");
    pending[0].reject(new TypeError("private network details"));
    await loading;
    assert.equal(box.textContent, NETWORK);
});

test("HCI-03: successful retry clears login link", async () => {
    const { load, box, pending } = setup();
    const first = load("user", "http://x/admin?upage=2");
    pending[0].resolve({ ok: true, url: "http://x/admin/login" });
    await first;
    assert.equal(box.children.length, 1);
    const retry = load("user", "http://x/admin?upage=2");
    pending[1].resolve({ ok: true, text: async () => "success" });
    await retry;
    assert.equal(box.hidden, true);
    assert.deepEqual(box.children, []);
});

test("HCI-03: abort and stale failure remain silent", async () => {
    const { load, box, pending } = setup();
    const first = load("user", "http://x/admin?upage=2");
    const second = load("user", "http://x/admin?upage=3");
    pending[0].resolve({ ok: true, url: "http://x/admin/login" });
    pending[1].reject(Object.assign(new Error("aborted"), { name: "AbortError" }));
    await Promise.all([first, second]);
    assert.equal(box.hidden, true);
    assert.equal(box.textContent, "");
    assert.deepEqual(box.children, []);
});
