import { test } from "node:test";
import assert from "node:assert/strict";
import { initAdminTables } from "../resources/js/admin-table.js";
import { ALL_TABLE_KEYS, env, registry, linkClick, flush } from "./helpers/admin-table.mjs";

test("FR-R47-001: pagination link fetches and swaps only the table region, then pushes URL and focuses heading", async () => {
    const { handlers, requests, pushed } = env();
    const old = registry.get("user");
    const click = linkClick("http://x/admin?upage=2#panel-user");
    handlers.click(click.event);
    assert.equal(click.prevented, true, "full navigation prevented");
    assert.equal(old.attrs["aria-busy"], "true");
    assert.equal(requests[0].url, "http://x/admin?upage=2");
    assert.equal(
        requests[0].options.headers["X-Requested-With"],
        "XMLHttpRequest",
    );
    requests[0].respond("page2");
    await flush();
    const fresh = registry.get("user");
    assert.equal(fresh.label, "page2");
    assert.equal(fresh.heading.focused, true);
    assert.deepEqual(pushed[0], {
        state: { adminTable: "user" },
        url: "http://x/admin?upage=2#panel-user",
    });
});

test("FR-R47-001: form submit builds GET URL from form fields without reloading", async () => {
    const { handlers, requests } = env();
    let prevented = false;
    const form = {
        action: "http://x/admin#panel-user",
        getAttribute: (n) => (n === "data-admin-table-form" ? "user" : null),
        closest: () => registry.get("user"),
        entries: [
            ["uq", "Viv ne"],
            ["usort", "name"],
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
    assert.equal(requests[0].url, "http://x/admin?uq=Viv+ne&usort=name");
});

test("NFR: newer request aborts the older one so a stale response never replaces fresh results", async () => {
    const { handlers, requests } = env();
    handlers.click(linkClick("http://x/admin?upage=2").event);
    handlers.click(linkClick("http://x/admin?upage=3").event);
    requests[1].respond("page3");
    await flush();
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").label, "page3");
});

test("NFR: network/HTTP failure shows an alert, clears busy state and keeps the old table", async () => {
    const { handlers, requests } = env();
    const old = registry.get("user");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    requests[0].respond("boom", false);
    await flush();
    assert.equal(registry.get("user"), old);
    assert.equal(old.attrs["aria-busy"], "false");
    assert.equal(old.error.hidden, false);
    assert.equal(old.error.textContent, "Tabel belum dapat diperbarui. Coba lagi.");
});

test("NFR: response without the table region is treated as failure", async () => {
    const { handlers, requests } = env();
    handlers.click(linkClick("http://x/admin?upage=2").event);
    requests[0].respond("missing");
    await flush();
    assert.equal(registry.get("user").error.hidden, false);
});

test("NFR: redirects outside admin page are rejected before parsing response HTML", async () => {
    const { handlers, requests, document, window } = env();
    let parsed = false;
    initAdminTables({
        document,
        window,
        fetch: async () => ({
            ok: true,
            url: "http://x/admin/login",
            text: async () => "foreign page",
        }),
        parse: () => {
            parsed = true;
            return { querySelector: () => null };
        },
    });
    const old = registry.get("user");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    await flush();
    assert.equal(parsed, false);
    assert.equal(registry.get("user"), old);
    assert.equal(old.error.hidden, false);
    assert.equal(old.error.textContent, "Sesi admin tidak aktif. Masuk kembali untuk melanjutkan.");
});

test("NFR: links outside current admin path are left to the browser", () => {
    const { handlers, requests } = env();
    const outside = linkClick("http://x/admin/login");
    handlers.click(outside.event);
    assert.equal(outside.prevented, false);
    assert.equal(requests.length, 0);
});

test("FR-R47-001: modified clicks and cross-origin links are left to the browser", () => {
    const { handlers, requests } = env();
    const ctrl = linkClick("http://x/admin?upage=2");
    ctrl.event.ctrlKey = true;
    handlers.click(ctrl.event);
    const foreign = linkClick("http://evil/admin?upage=2");
    handlers.click(foreign.event);
    assert.equal(ctrl.prevented, false);
    assert.equal(foreign.prevented, false);
    assert.equal(requests.length, 0);
});

test("FR-R47-001: back/forward reloads the table for the restored URL without pushing history", async () => {
    const { handlers, requests, pushed, window } = env();
    window.location.href = "http://x/admin?upage=1#panel-user";
    handlers.popstate({ state: { adminTable: "user" } });
    assert.equal(requests[0].url, "http://x/admin?upage=1");
    requests[0].respond("page1");
    await flush();
    assert.equal(registry.get("user").label, "page1");
    assert.equal(pushed.length, 0);
    window.location.href = "http://x/admin";
    handlers.popstate({ state: null });
    assert.equal(
        requests.length,
        2,
        "initial history entry must load both tables",
    );
    requests[1].respond("initial");
    await flush();
    assert.equal(registry.get("user").label, "initial");
    assert.equal(registry.get("concierge").label, "initial");
    assert.equal(pushed.length, 0, "back navigation must not create history");
});

test("FR-R47-001: concierge link swaps concierge and refreshes user region so both carry the same URL state", async () => {
    const { handlers, requests, pushed } = env();
    const link = {
        href: "http://x/admin?cpage=2#panel-concierge",
        closest: (sel) =>
            sel === "[data-admin-table]" ? registry.get("concierge") : null,
    };
    handlers.click({
        target: { closest: () => link },
        button: 0,
        preventDefault() {},
    });
    assert.equal(requests[0].url, "http://x/admin?cpage=2");
    requests[0].respond("c2");
    await flush();
    assert.equal(registry.get("concierge").label, "c2");
    assert.equal(registry.get("concierge").heading.focused, true);
    assert.equal(registry.get("user").label, "c2");
    assert.deepEqual(pushed[0], {
        state: { adminTable: "concierge" },
        url: "http://x/admin?cpage=2#panel-concierge",
    });
});

test("NFR: a cross-origin form action is left to the browser instead of being fetched and swapped", () => {
    const { handlers, requests } = env();
    let prevented = false;
    const form = {
        action: "http://evil/admin",
        getAttribute: (n) => (n === "data-admin-table-form" ? "user" : null),
        closest: () => registry.get("user"),
        entries: [["uq", "x"]],
    };
    handlers.submit(
        {
            target: { closest: () => form },
            preventDefault() {
                prevented = true;
            },
        },
        (f) => f.entries,
    );
    assert.equal(prevented, false);
    assert.equal(requests.length, 0);
});

test("NFR: both swapped regions announce busy and none stays busy after the swap", async () => {
    const { handlers, requests } = env();
    const user = registry.get("user");
    const concierge = registry.get("concierge");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(user.attrs["aria-busy"], "true");
    assert.equal(
        concierge.attrs["aria-busy"],
        "true",
        "the region that also gets replaced must announce it",
    );
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").attrs["aria-busy"], "false");
    assert.equal(registry.get("concierge").attrs["aria-busy"], "false");
});

test("NFR: without fetch support the handlers never bind so plain GET navigation survives", () => {
    const handlers = {};
    const document = {
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
        querySelector: () => null,
    };
    const window = {
        location: { href: "http://x/admin", origin: "http://x" },
        history: {},
        addEventListener(type, fn) {
            handlers[type] = fn;
        },
    };
    initAdminTables({ document, window, fetch: undefined });
    assert.deepEqual(Object.keys(handlers), []);
});

test("FR-R47-001: hash-only history moves (in-page anchors) do not refetch tables", () => {
    const { handlers, requests, window } = env();
    window.location.href = "http://x/admin#panel-concierge";
    handlers.popstate({ state: null });
    assert.equal(requests.length, 0);
});

test("NFR: back during an in-flight request supersedes it so the stale push never lands", async () => {
    const { handlers, requests, pushed, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2").event);
    handlers.popstate({ state: null }); // location still equals the rendered query
    assert.equal(requests.length, 2);
    requests[1].respond("current");
    await flush();
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").label, "current");
    assert.equal(pushed.length, 0);
    assert.equal(window.location.href, "http://x/admin");
});

test("NFR: response missing the companion region swaps nothing and pushes no URL", async () => {
    const { handlers, requests, pushed } = env();
    const user = registry.get("user");
    const concierge = registry.get("concierge");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    requests[0].respond("no-concierge");
    await flush();
    assert.equal(
        registry.get("user"),
        user,
        "navigated table must not be half-swapped",
    );
    assert.equal(registry.get("concierge"), concierge);
    assert.equal(
        pushed.length,
        0,
        "URL must not move when tables keep the old state",
    );
    assert.equal(user.error.hidden, false);
    assert.equal(user.attrs["aria-busy"], "false");
    assert.equal(concierge.attrs["aria-busy"], "false");
});

for (const state of [null, { route: "custom", scroll: 17 }]) {
    test(`NFR: successful popstate preserves ${state === null ? "null" : "custom"} history state for later failure recovery`, async () => {
        const { handlers, requests, replaced, window } = env();
        handlers.click(linkClick("http://x/admin?upage=2").event);
        requests[0].respond("page2");
        await flush();
        window.history.replaceState(state, "", window.location.href);
        handlers.click(linkClick("http://x/admin?upage=3").event);
        requests[1].respond("page3");
        await flush();
        replaced.length = 0;

        window.history.back();
        requests[2].respond("page2");
        await flush();
        assert.deepEqual(window.history.state, state);
        assert.equal(registry.get("user").label, "page2");

        window.history.forward();
        requests[3].respond("boom", false);
        await flush();

        assert.equal(window.location.href, "http://x/admin?upage=2");
        assert.deepEqual(window.history.state, state);
        assert.deepEqual(replaced, [{ state, url: "http://x/admin?upage=2" }]);
    });
}

test("NFR: failed popstate HTTP response restores the last rendered URL and state", async () => {
    const { handlers, requests, pushed, replaced, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    window.location.href = "http://x/admin?upage=1";
    handlers.popstate({ state: null });
    requests[1].respond("boom", false);
    await flush();
    assert.equal(pushed.length, 1, "recovery must not add a history entry");
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.deepEqual(replaced, [{
        state: { adminTable: "user" },
        url: "http://x/admin?upage=2#panel-user",
    }]);
});

test("NFR: failed popstate missing-region response restores the last rendered URL and state", async () => {
    const { handlers, requests, pushed, replaced, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    window.location.href = "http://x/admin?upage=1";
    handlers.popstate({ state: null });
    requests[1].respond("missing");
    await flush();
    assert.equal(pushed.length, 1, "recovery must not add a history entry");
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.deepEqual(replaced, [{
        state: { adminTable: "user" },
        url: "http://x/admin?upage=2#panel-user",
    }]);
});

test("NFR: popstate network rejection restores initial rendered URL and null state", async () => {
    const { handlers, pushed, replaced, window, document } = env();
    initAdminTables({ document, window, fetch: async () => {
        throw new TypeError("Network failure");
    } });
    window.location.href = "http://x/admin?upage=2";
    handlers.popstate({ state: { adminTable: "user" } });
    await flush();
    assert.deepEqual(pushed, []);
    assert.deepEqual(replaced, [{ state: null, url: "http://x/admin" }]);
    assert.equal(window.location.href, "http://x/admin");
    assert.equal(window.history.state, null);
    assert.equal(registry.get("user").label, "old");
    assert.equal(registry.get("user").error.hidden, false);
});

test("NFR: failed popstate restores a non-null state present at page load", async () => {
    const { handlers, pushed, replaced, window, document } = env();
    const initial = { adminTable: "produk", scroll: 1 };
    window.history.replaceState(initial, "", "http://x/admin?ppage=4");
    replaced.length = 0;
    initAdminTables({ document, window, fetch: async () => {
        throw new TypeError("Network failure");
    } });
    window.location.href = "http://x/admin?ppage=1";
    handlers.popstate({ state: null });
    await flush();
    assert.deepEqual(pushed, []);
    assert.deepEqual(replaced, [{ state: initial, url: "http://x/admin?ppage=4" }]);
    assert.deepEqual(window.history.state, initial);
});

test("NFR: cancelled popstate replaces the current entry with the rendered URL and state", async () => {
    const { handlers, requests, pushed, replaced, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    registry.get("produk").forms = [{
        method: "post",
        querySelectorAll: () => [{ tagName: "INPUT", type: "text", value: "edit", defaultValue: "" }],
    }];
    window.confirm = () => false;
    window.location.href = "http://x/admin?upage=1";
    handlers.popstate({ state: null });
    assert.equal(requests.length, 1);
    assert.equal(pushed.length, 1, "cancel must not add a history entry");
    assert.deepEqual(replaced, [{
        state: { adminTable: "user" },
        url: "http://x/admin?upage=2#panel-user",
    }]);
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
});

test("a11y: back/forward recovers focus to the new heading of the table that held it", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = registry.get("concierge").heading;
    window.location.href = "http://x/admin?upage=3";
    handlers.popstate({ state: { adminTable: "user" } });
    requests[0].respond("page3");
    await flush();
    assert.equal(
        registry.get("concierge").heading.focused,
        true,
        "focus must not be dropped onto <body>",
    );
    assert.equal(registry.get("user").heading.focused, false);
    assert.equal(document.activeElement, registry.get("concierge").heading);
});

test("a11y: back/forward leaves focus alone when it was outside the tables", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = { outside: true };
    window.location.href = "http://x/admin?upage=3";
    handlers.popstate({ state: { adminTable: "user" } });
    requests[0].respond("page3");
    await flush();
    assert.equal(registry.get("user").heading.focused, false);
});

test("a11y: popstate respects focus moving outside tables while fetch is pending", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = registry.get("concierge").heading;
    window.location.href = "http://x/admin?upage=3";
    handlers.popstate({ state: { adminTable: "user" } });
    document.activeElement = { outside: true };
    requests[0].respond("page3");
    await flush();
    assert.equal(registry.get("user").heading.focused, false);
});

test("a11y: popstate restores focus moved inside tables while fetch is pending", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = { outside: true };
    window.location.href = "http://x/admin?upage=3";
    handlers.popstate({ state: { adminTable: "user" } });
    document.activeElement = registry.get("concierge").heading;
    requests[0].respond("page3");
    await flush();
    assert.equal(registry.get("concierge").heading.focused, true);
    assert.equal(registry.get("user").heading.focused, false);
    assert.equal(document.activeElement, registry.get("concierge").heading);
});

test("FR-R47-001: GET form fields replace the action query string", () => {
    const { handlers, requests } = env();
    const form = {
        action: "http://x/admin?uq=old&obsolete=1#panel-user",
        getAttribute: () => "user",
        entries: [["uq", "new"]],
    };
    handlers.submit(
        { target: { closest: () => form }, preventDefault() {} },
        (f) => f.entries,
    );
    assert.equal(requests[0].url, "http://x/admin?uq=new");
});

test("FR-R47-001: pending User pagination preserves focus moved to Concierge", async () => {
    const { handlers, requests, document } = env();
    registry.get("user").heading.focus();
    handlers.click(linkClick("http://x/admin?upage=2").event);
    registry.get("concierge").heading.focus();
    assert.equal(document.activeElement, registry.get("concierge").heading);
    requests[0].respond("page2");
    await flush();
    assert.equal(
        registry.get("concierge").label,
        "page2",
        "companion region carries the new URL state",
    );
    assert.equal(registry.get("user").heading.focused, false);
    assert.equal(registry.get("concierge").heading.focused, true);
});

test("FR-R47-001: focus kept in the triggering table still lands on its heading", async () => {
    const { handlers, requests } = env();
    registry.get("user").heading.focus();
    handlers.click(linkClick("http://x/admin?upage=2").event);
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").heading.focused, true);
    assert.equal(registry.get("concierge").heading.focused, false);
});

test("FR-R44-001: discovers every dashboard [data-admin-table] section and swaps all atomically", async () => {
    const { handlers, requests, pushed } = env();
    const link = {
        href: "http://x/admin?ppage=2#panel-produk",
        closest: (sel) =>
            sel === "[data-admin-table]" ? registry.get("produk") : null,
    };
    handlers.click({
        target: { closest: () => link },
        button: 0,
        preventDefault() {},
    });
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(
            registry.get(key).attrs["aria-busy"],
            "true",
            `${key} busy`,
        );
    }
    requests[0].respond("p2");
    await flush();
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(registry.get(key).label, "p2", `${key} swapped`);
        assert.equal(registry.get(key).attrs["aria-busy"], "false");
    }
    assert.equal(registry.get("produk").heading.focused, true);
    assert.deepEqual(pushed[0], {
        state: { adminTable: "produk" },
        url: "http://x/admin?ppage=2#panel-produk",
    });
});

test("FR-R44-001: response missing any dashboard region (e.g. subscriber) swaps nothing", async () => {
    const { handlers, requests, pushed } = env();
    const before = new Map(registry);
    handlers.click(linkClick("http://x/admin?upage=2").event);
    requests[0].respond("no-subscriber");
    await flush();
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(registry.get(key), before.get(key), `${key} untouched`);
        assert.equal(registry.get(key).attrs["aria-busy"], "false");
    }
    assert.equal(pushed.length, 0);
    assert.equal(registry.get("user").error.hidden, false);
});

test("FR-R44-001: HTTP error shows alert in the triggering non-user section and clears busy everywhere", async () => {
    const { handlers, requests } = env();
    const link = {
        href: "http://x/admin?spage=2#panel-subscriber",
        closest: (sel) =>
            sel === "[data-admin-table]" ? registry.get("subscriber") : null,
    };
    handlers.click({
        target: { closest: () => link },
        button: 0,
        preventDefault() {},
    });
    requests[0].respond("err", false);
    await flush();
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(registry.get(key).attrs["aria-busy"], "false");
    }
    assert.equal(registry.get("subscriber").error.hidden, false);
    assert.equal(registry.get("subscriber").error.textContent, "Tabel belum dapat diperbarui. Coba lagi.");
    assert.equal(registry.get("produk").error.hidden, true);
});

test("FR-R44-001: popstate restores a non-user table key and keeps focus in the table that held it", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = registry.get("audit").heading;
    window.location.href = "http://x/admin?kpage=2";
    handlers.popstate({ state: { adminTable: "kategori" } });
    requests[0].respond("k2");
    await flush();
    for (const key of ALL_TABLE_KEYS) {
        assert.equal(registry.get(key).label, "k2");
    }
    assert.equal(registry.get("audit").heading.focused, true);
    assert.equal(registry.get("kategori").heading.focused, false);
    assert.equal(document.activeElement, registry.get("audit").heading);
});

test("FR-R44-001: discovers a new table key without requiring legacy regions", async () => {
    const { handlers, requests, window, document } = env(["custom-table"]);
    document.activeElement = registry.get("custom-table").heading;
    window.location.href = "http://x/admin?page=2";
    handlers.popstate({ state: null });
    assert.equal(requests.length, 1);
    assert.equal(registry.get("custom-table").attrs["aria-busy"], "true");
    requests[0].respond("custom2");
    await flush();
    assert.equal(registry.get("custom-table").label, "custom2");
    assert.equal(registry.get("custom-table").heading.focused, true);
    assert.equal(registry.get("custom-table").attrs["aria-busy"], "false");
});

test("FR-R44-001: popstate with unknown state falls back to the first dashboard table", async () => {
    const { handlers, requests, window, document } = env();
    document.activeElement = registry.get("audit").heading;
    window.location.href = "http://x/admin?kpage=3";
    handlers.popstate({ state: { adminTable: "bogus" } });
    requests[0].respond("k3");
    await flush();
    assert.equal(registry.get("kategori").label, "k3");
    assert.equal(registry.get("audit").heading.focused, true);
    assert.equal(registry.get("kategori").heading.focused, false);
    assert.equal(document.activeElement, registry.get("audit").heading);
});

test("FR-R47-001: navigating pages 2 then 3 and going back twice keeps URL, history state, and table content consistent", async () => {
    const { handlers, requests, pushed, window } = env();

    // Navigate to page 2
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.equal(registry.get("user").label, "page2");

    // Navigate to page 3
    handlers.click(linkClick("http://x/admin?upage=3#panel-user").event);
    requests[1].respond("page3");
    await flush();
    assert.equal(window.location.href, "http://x/admin?upage=3#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.equal(registry.get("user").label, "page3");

    // Back once -> page 2
    window.history.back();
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.equal(requests[2].url, "http://x/admin?upage=2");
    requests[2].respond("page2");
    await flush();
    assert.equal(registry.get("user").label, "page2");
    assert.equal(pushed.length, 2, "back must not push new history entry");

    // Back second time -> initial page
    window.history.back();
    assert.equal(window.location.href, "http://x/admin");
    assert.equal(window.history.state, null);
    assert.equal(requests[3].url, "http://x/admin");
    requests[3].respond("initial");
    await flush();
    assert.equal(registry.get("user").label, "initial");
    assert.equal(pushed.length, 2);

    // Forward -> page 2
    window.history.forward();
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    assert.deepEqual(window.history.state, { adminTable: "user" });
    assert.equal(requests[4].url, "http://x/admin?upage=2");
    requests[4].respond("page2-forward");
    await flush();
    assert.equal(registry.get("user").label, "page2-forward");
    assert.equal(pushed.length, 2);
});

test("NFR: replaceState updates location and current history entry in place", () => {
    const { window } = env();
    window.history.replaceState({ adminTable: "produk" }, "", "http://x/admin?ppage=1#panel-produk");
    assert.equal(window.location.href, "http://x/admin?ppage=1#panel-produk");
    assert.deepEqual(window.history.state, { adminTable: "produk" });
});

test("NFR: stale response that ignores abort and resolves after newer response does not overwrite table", async () => {
    const { handlers, requests, pushed, window } = env(ALL_TABLE_KEYS, {
        ignoreAbort: true,
    });
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    handlers.click(linkClick("http://x/admin?upage=3#panel-user").event);
    requests[1].respond("page3");
    await flush();
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").label, "page3");
    assert.equal(window.location.href, "http://x/admin?upage=3#panel-user");
    assert.equal(pushed.length, 1);
});

test("NFR: stale request failing after a newer request starts shows no error and leaves aria-busy accurate", async () => {
    const { handlers, requests } = env(ALL_TABLE_KEYS, { ignoreAbort: true });
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    handlers.click(linkClick("http://x/admin?upage=3#panel-user").event);

    // First request fails while second is still in flight
    requests[0].respond("boom", false);
    await flush();
    assert.equal(registry.get("user").error.hidden, true);
    assert.equal(
        registry.get("user").attrs["aria-busy"],
        "true",
        "in-flight second request must stay busy",
    );

    // Second request succeeds
    requests[1].respond("page3");
    await flush();
    assert.equal(registry.get("user").label, "page3");
    assert.equal(registry.get("user").error.hidden, true);
    assert.equal(registry.get("user").attrs["aria-busy"], "false");
});

test("NFR: Back supersedes a pending click even when its aborted response arrives last", async () => {
    const { handlers, requests, pushed, window } = env(ALL_TABLE_KEYS, {
        ignoreAbort: true,
    });
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    handlers.click(linkClick("http://x/admin?upage=3#panel-user").event);
    window.history.back();
    assert.equal(window.location.href, "http://x/admin");
    assert.equal(window.history.state, null);
    requests[2].respond("initial");
    await flush();
    requests[1].respond("page3");
    await flush();
    assert.equal(registry.get("user").label, "initial");
    assert.equal(window.location.href, "http://x/admin");
    assert.equal(window.history.state, null);
    assert.equal(pushed.length, 1);
});

test("NFR: stale network rejection after newer request completes shows no error and keeps aria-busy false", async () => {
    const { handlers, requests } = env(ALL_TABLE_KEYS, { ignoreAbort: true });
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    handlers.click(linkClick("http://x/admin?upage=3#panel-user").event);

    requests[1].respond("page3");
    await flush();
    requests[0].fail(new Error("Network down"));
    await flush();

    assert.equal(registry.get("user").label, "page3");
    assert.equal(registry.get("user").error.hidden, true);
    assert.equal(registry.get("user").attrs["aria-busy"], "false");
});



