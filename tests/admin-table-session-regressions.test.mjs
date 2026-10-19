import test from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { env, registry, linkClick, flush } from "./helpers/admin-table.mjs";

for (const recovery of ["reject", "fail"]) {
    test(`FR-R44-001: ${recovery} Back restores latest hash-only traversal URL and state`, async () => {
        const fixture = env(["user"]);
        const { window, handlers, requests } = fixture;
        handlers.click(linkClick("http://x/admin?page=2#original").event);
        requests[0].respond("page-two");
        await flush();
        const latest = "http://x/admin?page=2#latest";
        const state = { adminTable: "user", anchor: "latest" };
        window.history.replaceState(state, "", latest);
        window.history.pushState({ anchor: "next" }, "", "#next");
        window.history.back();
        assert.equal(requests.length, 1, "hash-only traversal never fetches");
        const section = registry.get("user");
        const draft = { tagName: "INPUT", type: "text", value: "edited", defaultValue: "" };
        if (recovery === "reject") {
            section.forms = [{ method: "post", querySelectorAll: () => [draft] }];
            window.confirm = () => false;
        }
        window.history.back();
        if (recovery === "fail") {
            requests[1].fail(new Error("offline"));
            await flush();
        }
        assert.equal(window.location.href, latest);
        assert.deepEqual(window.history.state, state);
        assert.equal(registry.get("user"), section);
        assert.equal(draft.value, "edited");
        assert.equal(requests.length, recovery === "reject" ? 1 : 2);
    });
}

const element = (attrs = {}) => ({
    attrs,
    getAttribute(name) { return this.attrs[name]; },
    setAttribute(name, value) { this.attrs[name] = value; },
    closest(selector) { return selector === "[data-table-density]" ? this : null; },
});

for (const denyRead of [false, true]) {
    test(`FR-R44-003: denied storage writes retain per-table density after swap (read denied: ${denyRead})`, async () => {
        const { document, window, handlers } = env(["user", "produk"]);
        const decorate = (section) => ({
            ...section,
            density: element({ "data-table-density": section.key }),
            body: element(),
        });
        for (const [key, section] of registry) registry.set(key, decorate(section));
        const query = document.querySelector.bind(document);
        const queryAll = document.querySelectorAll.bind(document);
        document.querySelector = (selector) => {
            const key = selector.match(/^\[data-table-body="([\w-]+)"\]$/)?.[1];
            return key ? registry.get(key).body : query(selector);
        };
        document.querySelectorAll = (selector) => selector === "[data-table-density]"
            ? [...registry.values()].map((section) => section.density)
            : queryAll(selector);
        let reads = 0;
        let writes = 0;
        window.localStorage = {
            getItem() {
                reads++;
                if (denyRead) throw new Error("denied");
                return "compact";
            },
            setItem() { writes++; throw new Error("denied"); },
        };
        const { load } = initAdminTables({
            document, window,
            fetch: async () => ({ ok: true, text: async () => "fresh" }),
            parse: () => ({ querySelector: (selector) => decorate(query(selector)) }),
        });
        const old = registry.get("user");
        handlers.click({ target: old.density });
        assert.equal(old.body.getAttribute("data-density"), "comfortable");
        assert.equal(writes, 1);
        await load("user", "http://x/admin?page=2");
        assert.notEqual(registry.get("user"), old);
        assert.equal(registry.get("user").body.getAttribute("data-density"), "comfortable");
        assert.equal(registry.get("user").density.getAttribute("aria-pressed"), "false");
        assert.equal(registry.get("produk").body.getAttribute("data-density"), "compact");
        assert.equal(reads, 2, "read storage only once per table per initializer");
    });
}
