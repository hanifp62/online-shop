import { test } from "node:test";
import assert from "node:assert/strict";
import {
    env,
    registry,
    linkClick,
    flush,
    ALL_TABLE_KEYS,
} from "./helpers/admin-table.mjs";

const postDraft = (value = "") => {
    const draft = { tagName: "INPUT", type: "text", value, defaultValue: "" };
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => [draft] },
    ];
    return draft;
};

test("FR-R44-001 HCI-02: reject a draft typed during fetch without replacing any table region", async () => {
    const { handlers, requests, pushed, window } = env();
    const original = new Map(registry);
    const draft = { tagName: "INPUT", type: "text", value: "", defaultValue: "" };
    original.get("produk").forms = [
        { method: "post", querySelectorAll: () => [draft] },
    ];
    let confirmations = 0;
    window.confirm = (message) => {
        confirmations++;
        assert.match(message, /belum disimpan/);
        return false;
    };

    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(requests.length, 1, "clean form permits request to start");
    assert.equal(confirmations, 0, "no draft existed before request");
    draft.value = "Catatan yang belum disimpan";
    requests[0].respond("page2");
    await flush();

    assert.equal(confirmations, 1, "new draft requires confirmation at swap time");
    for (const [key, section] of original) {
        assert.equal(registry.get(key), section, `${key} remains the original DOM region`);
        assert.equal(section.replacedBy, null, `${key} was not swapped`);
    }
    assert.equal(draft.value, "Catatan yang belum disimpan");
    assert.equal(registry.get("produk").forms[0].querySelectorAll()[0], draft);
    assert.deepEqual(pushed, [], "rejected swap does not advance history");
    assert.equal(window.location.href, "http://x/admin");
});

test("FR-R44-001: unchanged accepted POST draft prompts only once", async () => {
    const { handlers, requests, pushed, window } = env();
    postDraft("Accepted draft");
    let confirmations = 0;
    window.confirm = () => ++confirmations === 1;

    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(confirmations, 1);
    requests[0].respond("page2");
    await flush();

    assert.equal(confirmations, 1, "accepted unchanged draft needs no second prompt");
    assert.equal(registry.get("produk").label, "page2");
    assert.equal(pushed.length, 1);
});

test("FR-R44-001 HCI-02: changed accepted draft is re-confirmed and rejected swap clears loading announcement", async () => {
    const { handlers, requests, pushed, window, document } = env();
    const live = { textContent: "" };
    const querySelector = document.querySelector.bind(document);
    document.querySelector = (selector) =>
        selector === "[data-table-announcement]" ? live : querySelector(selector);
    const draft = postDraft("Accepted draft");
    const original = new Map(registry);
    let confirmations = 0;
    window.confirm = () => ++confirmations === 1;

    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(live.textContent, "Memuat data tabel…");
    draft.value = "New draft during fetch";
    requests[0].respond("page2");
    await flush();

    assert.equal(confirmations, 2, "changed draft needs fresh consent");
    assert.equal(live.textContent, "", "rejected swap is no longer loading");
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(registry.get(key), original.get(key));
        assert.equal(registry.get(key).attrs["aria-busy"], "false");
    }
    assert.equal(draft.value, "New draft during fetch");
    assert.deepEqual(pushed, []);
});
