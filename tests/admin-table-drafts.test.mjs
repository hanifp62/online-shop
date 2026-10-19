import { test } from "node:test";
import assert from "node:assert/strict";
import { env, registry, linkClick, flush } from "./helpers/admin-table.mjs";

const dirtyControls = [
    { tagName: "INPUT", type: "text", value: "edit", defaultValue: "" },
    { tagName: "TEXTAREA", value: "edit", defaultValue: "" },
    {
        tagName: "INPUT",
        type: "checkbox",
        checked: true,
        defaultChecked: false,
    },
    { tagName: "INPUT", type: "radio", checked: false, defaultChecked: true },
    {
        // Single select moved off its implicit first option.
        tagName: "SELECT",
        options: [
            { selected: false, defaultSelected: false },
            { selected: true, defaultSelected: false },
        ],
    },
];

for (const control of dirtyControls) {
    test(`FR-R44-001: cancel dirty ${control.tagName}/${control.type ?? ""} in companion POST form`, () => {
        const { handlers, requests, pushed, window } = env();
        registry.get("produk").forms = [
            { method: "post", querySelectorAll: () => [control] },
        ];
        let confirmations = 0;
        window.confirm = (message) => {
            confirmations++;
            assert.match(message, /belum disimpan/);
            return false;
        };
        handlers.click(linkClick("http://x/admin?upage=2").event);
        assert.equal(confirmations, 1);
        assert.equal(requests.length, 0);
        assert.equal(pushed.length, 0);
        for (const section of registry.values())
            assert.equal(section.attrs["aria-busy"], "false");
    });
}

test("FR-R44-001: filter submit also guards dirty POST controls", () => {
    const { handlers, requests, pushed, window } = env();
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => [dirtyControls[0]] },
    ];
    window.confirm = () => false;
    const form = {
        action: "http://x/admin#panel-user",
        getAttribute: () => "user",
    };
    handlers.submit(
        { target: { closest: () => form }, preventDefault() {} },
        () => [["uq", "new"]],
    );
    assert.equal(requests.length, 0);
    assert.equal(pushed.length, 0);
});

test("FR-R44-001: accepting dirty-form warning permits normal fetch and push", async () => {
    const { handlers, requests, pushed, window } = env();
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => [dirtyControls[0]] },
    ];
    let confirmations = 0;
    window.confirm = () => {
        confirmations++;
        return true;
    };
    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(confirmations, 1);
    requests[0].respond("accepted");
    await flush();
    assert.equal(registry.get("produk").label, "accepted");
    assert.equal(pushed.length, 1);
});

test("FR-R44-001: cancelled popstate restores last rendered URL and query state", async () => {
    const { handlers, requests, pushed, replaced, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    requests[0].respond("page2");
    await flush();
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => [dirtyControls[0]] },
    ];
    window.confirm = () => false;
    window.location.href = "http://x/admin?upage=1";
    handlers.popstate({ state: null });
    assert.equal(requests.length, 1);
    // Back already moved the entry; recovery repairs it in place instead of pushing.
    assert.equal(pushed.length, 1, "cancel must not add a history entry");
    assert.deepEqual(replaced, [{
        state: { adminTable: "user" },
        url: "http://x/admin?upage=2#panel-user",
    }]);
    assert.equal(window.location.href, "http://x/admin?upage=2#panel-user");
    handlers.popstate({ state: null });
    assert.equal(
        requests.length,
        1,
        "restored query still matches renderedSearch",
    );
});

test("FR-R44-001: rejecting Back aborts pending fetch without losing POST draft or rendered URL", async () => {
    const { handlers, requests, pushed, replaced, window } = env();
    handlers.click(linkClick("http://x/admin?upage=2#panel-user").event);
    assert.equal(requests.length, 1);
    // Local clone keeps the shared fixture untouched if cancellation ever mutates the control.
    const draft = { ...dirtyControls[0] };
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => [draft] },
    ];
    window.confirm = () => false;
    window.location.href = "http://x/admin?upage=1";
    handlers.popstate({ state: null });
    assert.equal(requests[0].options.signal.aborted, true);
    assert.deepEqual(pushed, [], "cancel must not add a history entry");
    assert.deepEqual(replaced, [{ state: null, url: "http://x/admin" }]);
    assert.equal(window.location.href, "http://x/admin");
    assert.equal(window.history.state, null);
    requests[0].respond("stale");
    await flush();
    assert.equal(registry.get("produk").label, "old");
    assert.equal(
        registry.get("produk").forms[0].querySelectorAll()[0].value,
        "edit",
    );
});

test("FR-R44-001: unchanged POST controls never prompt", () => {
    const { handlers, requests, window } = env();
    const controls = [
        { tagName: "INPUT", type: "text", value: "same", defaultValue: "same" },
        {
            tagName: "INPUT",
            type: "checkbox",
            checked: false,
            defaultChecked: false,
        },
        {
            tagName: "SELECT",
            options: [{ selected: true, defaultSelected: true }],
        },
    ];
    registry.get("produk").forms = [
        { method: "post", querySelectorAll: () => controls },
    ];
    window.confirm = () => assert.fail("clean controls must not prompt");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(requests.length, 1);
});

// Browser behaviour: a single select without any `selected` attribute still shows its
// first enabled option as selected, while option.defaultSelected stays false.
const opt = (
    selected,
    defaultSelected,
    disabled = false,
    inDisabledGroup = false,
) => ({
    selected,
    defaultSelected,
    disabled,
    matches: (selector) =>
        selector === ":disabled" && (disabled || inDisabledGroup),
});
const selectCases = [
    {
        name: "single select with no selected attribute and untouched first option is pristine",
        select: {
            tagName: "SELECT",
            options: [opt(true, false), opt(false, false)],
        },
        dirty: false,
    },
    {
        name: "single select with no selected attribute moved to a later option is dirty",
        select: {
            tagName: "SELECT",
            options: [opt(false, false), opt(true, false)],
        },
        dirty: true,
    },
    {
        name: "single select implicit default skips a disabled first option",
        select: {
            tagName: "SELECT",
            options: [opt(false, false, true), opt(true, false)],
        },
        dirty: false,
    },
    {
        name: "single select implicit default skips an option inside a disabled optgroup",
        select: {
            tagName: "SELECT",
            options: [opt(false, false, false, true), opt(true, false)],
        },
        dirty: false,
    },
    {
        name: "single select with explicit size 1 behaves like a dropdown",
        select: {
            tagName: "SELECT",
            size: 1,
            options: [opt(true, false), opt(false, false)],
        },
        dirty: false,
    },
    {
        name: "multiple select that keeps its default selections is pristine",
        select: {
            tagName: "SELECT",
            multiple: true,
            options: [opt(true, true), opt(false, false)],
        },
        dirty: false,
    },
    {
        name: "multiple select that loses a default selection is dirty",
        select: {
            tagName: "SELECT",
            multiple: true,
            options: [opt(false, true), opt(false, false)],
        },
        dirty: true,
    },
    {
        name: "single select with explicit default after a disabled first option is pristine",
        select: {
            tagName: "SELECT",
            options: [
                opt(false, false, true),
                opt(false, false),
                opt(true, true),
            ],
        },
        dirty: false,
    },
    {
        name: "single select with explicit default stays pristine on that option",
        select: {
            tagName: "SELECT",
            options: [opt(false, false), opt(true, true)],
        },
        dirty: false,
    },
    {
        name: "single select with explicit default is dirty after moving to the first option",
        select: {
            tagName: "SELECT",
            options: [opt(true, false), opt(false, true)],
        },
        dirty: true,
    },
    {
        name: "single select with several selected attributes defaults to the last one",
        select: {
            tagName: "SELECT",
            options: [opt(false, true), opt(true, true)],
        },
        dirty: false,
    },
    {
        name: "multiple select keeps per-option comparison: first option selected without default is dirty",
        select: {
            tagName: "SELECT",
            multiple: true,
            options: [opt(true, false), opt(false, false)],
        },
        dirty: true,
    },
    {
        name: "multiple select with nothing selected and no defaults is pristine",
        select: {
            tagName: "SELECT",
            multiple: true,
            options: [opt(false, false), opt(false, false)],
        },
        dirty: false,
    },
    {
        name: "listbox (size>1) gets no implicit first-option default: nothing selected is pristine",
        select: {
            tagName: "SELECT",
            size: 4,
            options: [opt(false, false), opt(false, false)],
        },
        dirty: false,
    },
    {
        name: "listbox (size>1) with a picked option is dirty",
        select: {
            tagName: "SELECT",
            size: 4,
            options: [opt(true, false), opt(false, false)],
        },
        dirty: true,
    },
    {
        name: "select without options is pristine",
        select: { tagName: "SELECT", options: [] },
        dirty: false,
    },
];

for (const { name, select, dirty } of selectCases) {
    test(`FR-R44-001: ${name}`, () => {
        const { handlers, requests, window } = env();
        registry.get("produk").forms = [
            { method: "post", querySelectorAll: () => [select] },
        ];
        let confirmations = 0;
        window.confirm = () => {
            confirmations++;
            return false;
        };
        handlers.click(linkClick("http://x/admin?upage=2").event);
        assert.equal(confirmations, dirty ? 1 : 0);
        assert.equal(requests.length, dirty ? 0 : 1);
    });
}

test("FR-R44-001: a draft in a form outside the table regions survives the swap and never prompts", async () => {
    const { handlers, requests, window, document } = env();
    const draft = {
        method: "post",
        value: "draft kategori",
        querySelectorAll: () => [
            {
                tagName: "INPUT",
                type: "text",
                value: "draft kategori",
                defaultValue: "",
            },
        ],
    };
    const details = { open: true };
    const original = registry.get("kategori");
    const panel = { children: [original, details, draft] };
    // Page-wide form queries see the draft; only region-scoped checks may skip it.
    const regions = document.querySelectorAll.bind(document);
    document.querySelectorAll = (sel) =>
        sel === "[data-admin-table]"
            ? regions(sel)
            : sel.includes("form")
              ? [draft]
              : [];
    const replace = original.replaceWith.bind(original);
    original.replaceWith = (fresh) => {
        panel.children.splice(panel.children.indexOf(original), 1, fresh);
        replace(fresh);
    };
    window.confirm = () => assert.fail("outside-region draft must not prompt");
    handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.equal(requests.length, 1);
    requests[0].respond("page2");
    await flush();
    assert.deepEqual(panel.children, [
        registry.get("kategori"),
        details,
        draft,
    ]);
    assert.equal(draft.value, "draft kategori");
    assert.equal(details.open, true);
    assert.equal(registry.get("user").label, "page2");
});

test("a11y: click navigation does not steal focus when the user moved outside the tables meanwhile", async () => {
    const { handlers, requests, document } = env();
    document.activeElement = registry.get("user").heading;
    handlers.click(linkClick("http://x/admin?upage=2").event);
    document.activeElement = { outside: true };
    requests[0].respond("page2");
    await flush();
    assert.equal(registry.get("user").heading.focused, false);
});

