// REM-20261021 / FR-REM-005, NFR-REM-004 — RED regression contracts for UI-02 and UI-06.
// Tests only: no source is edited here. Pure Node (no browser, server, PHP, DB).
//
//  Part A (UI-02): admin order-status save vs. draft/dirty tracking, via the shared
//          tests/helpers/admin-table.mjs against resources/js/admin-table.js.
//  Part B (UI-06): prototype rendezvous / keyboard-shortcuts overlays, by running the real
//          public/prototype/app.js (symlink to prototype/app.js) inside a vm with a small fake DOM.
//
// Tests tagged [GUARD] pin behaviour that is already correct today so the fix cannot regress it.
// Everything else is expected RED until the lane that owns the source applies the fix.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";
import { env, registry, linkClick, flush } from "./helpers/admin-table.mjs";

// ===========================================================================
// Part A — UI-02: admin status save must commit before the refresh dirty check
// ===========================================================================

const SAVED_COPY = "Status pesanan berhasil diperbarui.";
const STATUSES = ["new", "processing", "shipped", "cancelled"];

// A real-shaped single <select>: option.selected / option.defaultSelected, and a form.reset()
// that restores defaults like a browser. Not a mock of an empty form (audit UI-02).
function statusForm({ id = 42, from = "new", to = "processing" } = {}) {
    const options = STATUSES.map((value) => ({
        value,
        selected: value === to,
        defaultSelected: value === from,
        disabled: false,
    }));
    const select = {
        tagName: "SELECT",
        multiple: false,
        size: 1,
        disabled: false,
        options,
        get value() {
            return options.find((o) => o.selected)?.value ?? "";
        },
    };
    const button = { tagName: "BUTTON", disabled: false };
    const attrs = {};
    const form = {
        tagName: "FORM",
        method: "post",
        action: `http://x/admin/orders/${id}`,
        select,
        button,
        resetCount: 0,
        attrs,
        querySelectorAll(sel) {
            if (sel === "select, button, input") return [select, button];
            if (sel === "input, textarea, select") return [select];
            return [];
        },
        getAttribute: (name) => attrs[name] ?? null,
        setAttribute: (name, value) => {
            attrs[name] = String(value);
        },
        removeAttribute: (name) => {
            delete attrs[name];
        },
        reset() {
            this.resetCount++;
            options.forEach((o) => {
                o.selected = o.defaultSelected;
            });
        },
        closest(sel) {
            if (sel === "form[data-order-status-form]") return this;
            if (sel === "[data-admin-table]") return registry.get("pesanan");
            return null;
        },
    };
    return form;
}

// Same rule a browser applies to a single select with no `multiple`: dirty = the selected option
// is not the default option.
const isDraft = (select) =>
    select.options.find((o) => o.selected) !==
    (select.options.filter((o) => o.defaultSelected).pop() ?? select.options[0]);

const submitStatus = (handlers, form) =>
    handlers.submit(
        { target: { closest: (sel) => form.closest(sel) }, preventDefault() {} },
        () => ({ get: (name) => (name === "status" ? form.select.value : null) }),
    );

// Records every live-region write (the helper's document has no announcement node).
function watchAnnouncements(document) {
    const live = {
        history: [],
        _text: "",
        set textContent(value) {
            this.history.push(value);
            this._text = value;
        },
        get textContent() {
            return this._text;
        },
    };
    const original = document.querySelector.bind(document);
    document.querySelector = (sel) =>
        sel === "[data-table-announcement]" ? live : original(sel);
    return live;
}

function saveScene({ ignoreAbort = true, confirmAnswer = false, forms } = {}) {
    const ctx = env(undefined, { ignoreAbort });
    const live = watchAnnouncements(ctx.document);
    const form = statusForm();
    registry.get("pesanan").forms = forms ? forms(form) : [form];
    const confirms = [];
    ctx.window.confirm = (message) => {
        confirms.push(message);
        return confirmAnswer;
    };
    return { ...ctx, live, form, confirms };
}

const staleNoticeShown = (live, section) =>
    /belum|muat ulang|segarkan/i.test(live.textContent) ||
    (section.error.hidden === false && section.error.textContent.trim() !== "");

test("UI-02: a successfully saved status is committed before the refresh dirty check (no false discard prompt)", async () => {
    const s = saveScene();
    assert.equal(isDraft(s.form.select), true, "precondition: select is genuinely dirty");

    const done = submitStatus(s.handlers, s.form);
    assert.equal(s.requests.length, 1, "POST is sent");
    assert.equal(s.form.select.disabled, true, "controls are locked while saving");
    assert.equal(s.form.resetCount, 0, "nothing is committed before the server confirms");
    assert.equal(isDraft(s.form.select), true, "still a draft while the request is in flight");

    s.requests[0].respond("{}", true);
    await flush();

    assert.deepEqual(s.confirms, [], "the value that was just saved must not be offered for discard");
    assert.equal(s.requests.length, 2, "the table refresh actually starts");
    assert.equal(s.requests[1].options.headers.Accept, "text/html");

    s.requests[1].respond("fresh", true);
    await flush();
    await done;

    for (const key of registry.keys())
        assert.equal(registry.get(key).label, "fresh", `${key} swapped to fresh markup`);
    assert.deepEqual(s.confirms, [], "no prompt at swap time either");
    assert.equal(s.live.textContent, SAVED_COPY);
    assert.equal(s.form.select.disabled, false);
    assert.equal(s.form.attrs["data-submitting"], undefined);
});

test("UI-02: saved but refresh failed — the control is not left as a discardable draft and success is not overstated", async () => {
    const s = saveScene();
    const done = submitStatus(s.handlers, s.form);
    s.requests[0].respond("{}", true);
    await flush();
    assert.equal(s.requests.length, 2, "refresh starts after the save (no dirty prompt)");

    s.requests[1].fail(new TypeError("Failed to fetch"));
    await flush();
    await done;

    const section = registry.get("pesanan");
    assert.equal(isDraft(s.form.select), false, "saved value is committed, not a draft");
    assert.notEqual(
        s.live.textContent,
        SAVED_COPY,
        "must not claim the whole view is updated while the table still shows the old badge",
    );
    assert.ok(staleNoticeShown(s.live, section), "user is told the table was not refreshed");
    assert.equal(section.error.hidden, false, "saved-but-stale notice is visible");
    assert.match(section.error.textContent, /tersimpan.*belum diperbarui/i);
    assert.equal(section.attrs["aria-busy"], "false");

    s.handlers.click(linkClick("http://x/admin?upage=2").event);
    assert.deepEqual(s.confirms, [], "later navigation must not ask to discard an already-saved status");
    assert.equal(s.requests.length, 3, "navigation proceeds");
});

test("UI-02: saved but refresh refused (another real draft exists) — draft kept, stale view admitted", async () => {
    const s = saveScene({ confirmAnswer: false });
    const otherDraft = { tagName: "INPUT", type: "text", value: "Catatan belum tersimpan", defaultValue: "" };
    registry.get("produk").forms = [{ method: "post", querySelectorAll: () => [otherDraft] }];
    const originals = new Map(registry);

    const done = submitStatus(s.handlers, s.form);
    s.requests[0].respond("{}", true);
    await flush();
    await done;

    assert.equal(s.confirms.length, 1, "exactly one prompt, for the real draft");
    assert.match(s.confirms[0], /belum disimpan/);
    assert.equal(s.requests.length, 1, "refused refresh sends no table request");
    assert.equal(otherDraft.value, "Catatan belum tersimpan", "the real draft survives");
    for (const [key, section] of originals) {
        assert.equal(registry.get(key), section, `${key} region untouched`);
        assert.equal(section.replacedBy, null);
    }
    assert.equal(isDraft(s.form.select), false, "the saved status itself is no longer a draft");
    assert.notEqual(s.live.textContent, SAVED_COPY, "refused refresh must not announce a fully updated view");
    assert.ok(staleNoticeShown(s.live, registry.get("pesanan")), "stale table is surfaced to the user");
    assert.equal(registry.get("pesanan").error.hidden, false, "stale notice is visible, not live-region only");
    assert.match(registry.get("pesanan").error.textContent, /tersimpan.*belum diperbarui/i);
});

const FAILURES = [
    ["HTTP error response", (request) => request.respond("{}", false)],
    ["transport failure", (request) => request.fail(new TypeError("Failed to fetch"))],
];

for (const [label, fail] of FAILURES) {
    test(`[GUARD] UI-02: failed save (${label}) preserves the draft and reports an actionable error`, async () => {
        const s = saveScene({ confirmAnswer: false });
        const first = submitStatus(s.handlers, s.form);
        fail(s.requests[0]);
        await flush();
        await first;

        const section = registry.get("pesanan");
        assert.equal(s.requests.length, 1, "no refresh after a failed save");
        assert.equal(s.form.resetCount, 0, "failed save never resets the form");
        assert.equal(isDraft(s.form.select), true, "draft is preserved");
        assert.equal(s.form.select.value, "processing");
        assert.equal(s.form.select.disabled, false, "select usable again");
        assert.equal(s.form.button.disabled, false, "submit usable again");
        assert.equal(s.form.attrs["data-submitting"], undefined);
        assert.equal(section.attrs["aria-busy"], "false");
        assert.equal(section.error.hidden, false, "error is visible");
        assert.ok(section.error.textContent.trim(), "error says something");
        assert.notEqual(s.live.textContent, SAVED_COPY);

        // The preserved draft is still protected from silent loss.
        s.handlers.click(linkClick("http://x/admin?upage=2").event);
        assert.equal(s.confirms.length, 1);
        assert.match(s.confirms[0], /belum disimpan/);
        assert.equal(s.requests.length, 1, "refused navigation sends nothing");
    });

    test(`UI-02: retry after a failed save (${label}) is sent and the value it saves does not prompt for discard`, async () => {
        const s = saveScene({ confirmAnswer: false });
        const first = submitStatus(s.handlers, s.form);
        fail(s.requests[0]);
        await flush();
        await first;
        assert.equal(isDraft(s.form.select), true, "draft survived the failure");

        const retry = submitStatus(s.handlers, s.form);
        assert.equal(s.requests.length, 2, "retry POST is sent (in-flight guard was released)");
        s.requests[1].respond("{}", true);
        await flush();
        assert.deepEqual(s.confirms, [], "no prompt for the value that was just saved");
        assert.equal(s.requests.length, 3, "refresh starts");
        s.requests[2].respond("fresh", true);
        await flush();
        await retry;
        assert.equal(registry.get("pesanan").label, "fresh");
        assert.equal(s.live.textContent, SAVED_COPY);
    });
}

// "dirty" variants are red until the commit-before-refresh fix lands (the refresh is wrongly
// refused today); "clean" variants isolate stale-response handling and are green guards.
for (const [dirtiness, to] of [["dirty", "processing"], ["clean", "new"]]) {
    for (const order of ["refresh-first", "navigation-first"]) {
        const guard = dirtiness === "clean" ? "[GUARD] " : "";
        test(`${guard}UI-02 stale safety (${dirtiness} select): post-save refresh superseded by a link click never overwrites the newer page (${order} response order)`, async () => {
            const s = saveScene({ ignoreAbort: true });

            s.form.select.options.forEach((o) => {
                o.selected = o.value === to;
            });
            assert.equal(isDraft(s.form.select), dirtiness === "dirty");
            const done = submitStatus(s.handlers, s.form);
            s.requests[0].respond("{}", true);
            await flush();
            assert.equal(s.requests.length, 2, "refresh B in flight");
            assert.deepEqual(s.confirms, []);

            s.handlers.click(linkClick("http://x/admin?upage=3").event);
            assert.equal(s.requests.length, 3, "navigation C supersedes B");
            const [refreshB, navigationC] = [s.requests[1], s.requests[2]];

            if (order === "refresh-first") {
                refreshB.respond("stale-B", true);
                await flush();
                navigationC.respond("page3", true);
            } else {
                navigationC.respond("page3", true);
                await flush();
                refreshB.respond("stale-B", true);
            }
            await flush();
            await done;

            for (const key of registry.keys())
                assert.equal(registry.get(key).label, "page3", `${key} shows the newest request only`);
            assert.equal(s.pushed.length, 1, "history advanced exactly once, by the link");
            assert.match(s.window.location.href, /upage=3/);
            for (const section of registry.values())
                assert.equal(section.attrs["aria-busy"], "false");
        });
    }
}

test("UI-02 stale safety: a second status submit during an in-flight save sends no concurrent POST, keeps its draft, and tells the user", async () => {
    const second = statusForm({ id: 43, from: "new", to: "shipped" });
    const s = saveScene({ forms: (first) => [first, second] });
    const first = submitStatus(s.handlers, s.form);
    assert.equal(s.requests.length, 1);
    const before = s.live.history.length;

    const blocked = submitStatus(s.handlers, second);
    await flush();
    assert.equal(s.requests.length, 1, "saves are serialised: no concurrent POST");
    assert.equal(second.resetCount, 0, "the second draft is not discarded");
    assert.equal(isDraft(second.select), true, "the second draft stays intact");
    assert.ok(
        s.live.history.length > before,
        "the ignored intent is acknowledged to the user instead of being dropped silently",
    );
    await blocked;

    s.requests[0].respond("{}", false);
    await flush();
    await first;

    const again = submitStatus(s.handlers, second);
    assert.equal(s.requests.length, 2, "after the first save settles the second one can be sent");
    assert.equal(s.requests[1].url, "http://x/admin/orders/43");
    s.requests[1].respond("{}", false);
    await flush();
    await again;
});

// ===========================================================================
// Part B — UI-06: prototype rendezvous / keyboard-shortcuts overlay focus lifecycle
// ===========================================================================

const PROTOTYPE_SOURCE = readFileSync(
    new URL("../public/prototype/app.js", import.meta.url),
    "utf8",
);
// Mirrors drawerFocusable in the prototype; kept literal so the harness models native tabbing.
const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// ---- minimal selector engine: comma lists, descendant combinator, tag/#id/.class/[attr]/[attr="v"]/:not(simple)
function splitOutside(source, separator) {
    const parts = [];
    let depth = 0;
    let quote = null;
    let current = "";
    for (const ch of source) {
        if (quote) {
            if (ch === quote) quote = null;
        } else if (ch === '"' || ch === "'") quote = ch;
        else if (ch === "(" || ch === "[") depth++;
        else if (ch === ")" || ch === "]") depth--;
        if (!quote && depth === 0 && ch === separator) {
            if (current.trim()) parts.push(current.trim());
            current = "";
        } else current += ch;
    }
    if (current.trim()) parts.push(current.trim());
    return parts;
}

function compound(source) {
    const checks = [];
    let rest = source;
    const token =
        /^(?:(\*)|([a-zA-Z][\w-]*)|#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:=(?:"([^"]*)"|([^\]]*)))?\]|:not\(((?:[^()]|\([^()]*\))*)\))/;
    while (rest) {
        const m = token.exec(rest);
        if (!m) throw new Error(`fake DOM: unsupported selector "${source}"`);
        if (m[2]) checks.push((n) => n.tagName === m[2].toUpperCase());
        else if (m[3]) checks.push((n) => n.id === m[3]);
        else if (m[4]) checks.push((n) => n.classList.contains(m[4]));
        else if (m[5]) {
            const expected = m[6] ?? m[7];
            checks.push((n) =>
                expected === undefined
                    ? n.hasAttribute(m[5])
                    : n.getAttribute(m[5]) === expected,
            );
        } else if (m[8] !== undefined) {
            const inner = compound(m[8]);
            checks.push((n) => !inner(n));
        }
        rest = rest.slice(m[0].length);
    }
    return (n) => checks.every((check) => check(n));
}

function selector(source) {
    const alternatives = splitOutside(source, ",").map((alt) => {
        if (/[>+~]/.test(alt.replace(/\[[^\]]*\]|\([^)]*\)/g, "")))
            throw new Error(`fake DOM: unsupported combinator in "${alt}"`);
        return splitOutside(alt, " ").map(compound);
    });
    return (node) =>
        alternatives.some((chain) => {
            if (!chain.at(-1)(node)) return false;
            let cursor = node.parentNode;
            for (let i = chain.length - 2; i >= 0; i--) {
                while (cursor && !chain[i](cursor)) cursor = cursor.parentNode;
                if (!cursor) return false;
                cursor = cursor.parentNode;
            }
            return true;
        });
}

class FakeNode {
    constructor(doc, tag, { id = "", classes = [], attrs = {} } = {}) {
        this.ownerDocument = doc;
        this.tagName = tag.toUpperCase();
        this.id = id;
        this.parentNode = null;
        this.children = [];
        this.attrs = new Map(Object.entries(attrs));
        this.inert = false;
        this.hidden = false;
        this.style = { setProperty() {}, removeProperty() {} };
        this.dataset = {};
        this.innerHTML = "";
        this.textContent = "";
        this.value = "";
        const classSet = new Set(classes);
        this.classList = {
            add: (...names) => names.forEach((n) => classSet.add(n)),
            remove: (...names) => names.forEach((n) => classSet.delete(n)),
            contains: (name) => classSet.has(name),
            toggle: (name, force) => {
                const on = force ?? !classSet.has(name);
                on ? classSet.add(name) : classSet.delete(name);
                return on;
            },
        };
    }
    // Keeps assertion diffs readable (nodes are circular).
    [Symbol.for("nodejs.util.inspect.custom")]() {
        const id = this.id ? `#${this.id}` : "";
        return `<${this.tagName.toLowerCase()}${id}>`;
    }
    get isConnected() {
        let n = this;
        while (n.parentNode) n = n.parentNode;
        return n === this.ownerDocument.documentElement;
    }
    getAttribute(name) {
        if (name === "id") return this.id || null;
        return this.attrs.has(name) ? this.attrs.get(name) : null;
    }
    hasAttribute(name) {
        return name === "id" ? Boolean(this.id) : this.attrs.has(name);
    }
    setAttribute(name, value) {
        this.attrs.set(name, String(value));
    }
    removeAttribute(name) {
        this.attrs.delete(name);
    }
    toggleAttribute(name, force) {
        const on = force ?? !this.attrs.has(name);
        on ? this.attrs.set(name, "") : this.attrs.delete(name);
        return on;
    }
    append(...nodes) {
        nodes.forEach((child) => {
            child.remove?.();
            child.parentNode = this;
            this.children.push(child);
        });
    }
    appendChild(child) {
        this.append(child);
        return child;
    }
    remove() {
        if (!this.parentNode) return;
        this.parentNode.children.splice(this.parentNode.children.indexOf(this), 1);
        this.parentNode = null;
    }
    contains(other) {
        for (let n = other; n; n = n.parentNode) if (n === this) return true;
        return false;
    }
    matches(sel) {
        return selector(sel)(this);
    }
    closest(sel) {
        const test = selector(sel);
        for (let n = this; n; n = n.parentNode) if (test(n)) return n;
        return null;
    }
    querySelectorAll(sel) {
        const test = selector(sel);
        const found = [];
        const walk = (node) => {
            for (const child of node.children) {
                if (test(child)) found.push(child);
                walk(child);
            }
        };
        walk(this);
        return found;
    }
    querySelector(sel) {
        return this.querySelectorAll(sel)[0] ?? null;
    }
    focus() {
        if (!this.isConnected || isInert(this)) return;
        this.ownerDocument.activeElement = this;
    }
    blur() {
        if (this.ownerDocument.activeElement === this)
            this.ownerDocument.activeElement = this.ownerDocument.body;
    }
    addEventListener(type, callback) {
        (this.listeners ??= {})[type] ??= [];
        this.listeners[type].push(callback);
    }
    click() {
        if (isInert(this)) return;
        for (const callback of this.listeners?.click ?? []) callback({ stopPropagation() {} });
    }
    removeEventListener() {}
    select() {}
    scrollIntoView() {}
    scrollTo() {}
    getBoundingClientRect() {
        return { top: 0, left: 0, width: 0, height: 0, right: 0, bottom: 0 };
    }
}

// Real semantics: an inert ancestor makes the whole subtree inert and unfocusable.
function isInert(node) {
    for (let n = node; n; n = n.parentNode)
        if (n.inert === true || n.attrs?.has("inert")) return true;
    return false;
}

class FakeDocument {
    [Symbol.for("nodejs.util.inspect.custom")]() {
        return "<document>";
    }
    constructor() {
        this.listeners = {};
        this.documentElement = new FakeNode(this, "html");
        this.body = new FakeNode(this, "body");
        this.documentElement.append(this.body);
        this.activeElement = this.body;
        this.lenient = new Map();
    }
    addEventListener(type, fn) {
        (this.listeners[type] ??= []).push(fn);
    }
    removeEventListener() {}
    createElement(tag) {
        return new FakeNode(this, tag);
    }
    querySelectorAll(sel) {
        return this.documentElement.querySelectorAll(sel);
    }
    querySelector(sel) {
        return this.querySelectorAll(sel)[0] ?? null;
    }
    getElementById(id) {
        const hit = this.documentElement.querySelectorAll(`#${id}`)[0];
        if (hit) return hit;
        // Unknown ids (catalog/kanban render targets) get a detached stub so render code can run.
        if (!this.lenient.has(id)) this.lenient.set(id, new FakeNode(this, "div", { id }));
        return this.lenient.get(id);
    }
}

const DIALOG_IDS = [
    "cart-drawer",
    "profile-drawer",
    "mobile-nav-drawer",
    "rendezvous-modal",
    "keyboard-shortcuts-modal",
    "authenticity-modal",
    "work-order-modal",
];

function loadPrototype() {
    const doc = new FakeDocument();
    const make = (parent, tag, options) => {
        const node = new FakeNode(doc, tag, options);
        parent.append(node);
        return node;
    };
    const dialog = (id, classes, children) => {
        const root = make(doc.body, id.endsWith("modal") ? "div" : "aside", {
            id,
            classes,
            attrs: { role: "dialog", "aria-modal": "true", "aria-label": id },
        });
        for (const [tag, childId, attrs] of children)
            make(root, tag, { id: childId, attrs });
        return root;
    };

    const main = make(doc.body, "main", { id: "sf-main" });
    const hero = make(main, "button", { id: "btn-hero" });
    const cartButton = make(main, "button", { id: "btn-cart" });
    const search = make(main, "input", { id: "sf-search-input", attrs: { type: "search" } });
    make(main, "a", { id: "link-other", attrs: { href: "#" } });
    make(doc.body, "div", { id: "drawer-backdrop", classes: ["drawer-backdrop"] });
    dialog("cart-drawer", ["cart-drawer"], [["button", "cart-close"], ["button", "cart-checkout"]]);
    dialog("profile-drawer", ["profile-drawer"], [["button", "profile-close"]]);
    dialog("mobile-nav-drawer", ["mobile-nav-drawer"], [["a", "mobile-link", { href: "#" }]]);
    dialog("rendezvous-modal", ["modal-window"], [
        ["button", "rv-close"],
        ["input", "rv-name", { type: "text" }],
        ["button", "rv-submit"],
    ]);
    dialog("keyboard-shortcuts-modal", ["modal-window"], [["button", "ks-close"]]);
    dialog("authenticity-modal", ["modal-window"], [["button", "cert-close"]]);
    dialog("work-order-modal", ["modal-window"], [["button", "work-close"]]);
    make(doc.body, "div", { id: "toast-container" });

    const windowListeners = {};
    const context = {
        console,
        URLSearchParams,
        document: doc,
        localStorage: { getItem: () => null, setItem() {}, removeItem() {} },
        navigator: {},
        location: { hash: "", search: "", href: "http://x/prototype/" },
        history: { replaceState() {}, pushState() {} },
        innerWidth: 1280,
        innerHeight: 800,
        matchMedia: () => ({ matches: false, addEventListener() {}, addListener() {} }),
        setTimeout: () => 0,
        clearTimeout() {},
        setInterval: () => 0,
        clearInterval() {},
        requestAnimationFrame: () => 0,
        cancelAnimationFrame() {},
        addEventListener: (type, fn) => (windowListeners[type] ??= []).push(fn),
        removeEventListener() {},
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(PROTOTYPE_SOURCE, context, { filename: "public/prototype/app.js" });
    for (const fn of doc.listeners.DOMContentLoaded ?? []) fn();

    const byId = (id) => doc.documentElement.querySelectorAll(`#${id}`)[0];
    const isOpen = (node) => node.classList.contains("open");
    const openDialogs = () => DIALOG_IDS.map(byId).filter(isOpen);
    const tabbables = () =>
        doc.documentElement
            .querySelectorAll(FOCUSABLE)
            .filter((n) => !isInert(n))
            .filter((n) => {
                const holder = n.closest('[role="dialog"]');
                return !holder || isOpen(holder);
            });

    // Browser-faithful: keydown handlers run first; an unprevented Tab then moves natively,
    // and tabbing past the last stop leaves the document (focus goes to browser chrome = body).
    function press(key, { shift = false } = {}) {
        const event = {
            key,
            shiftKey: shift,
            ctrlKey: false,
            metaKey: false,
            altKey: false,
            defaultPrevented: false,
            target: doc.activeElement,
            preventDefault() {
                this.defaultPrevented = true;
            },
            stopPropagation() {},
        };
        for (const fn of doc.listeners.keydown ?? []) fn(event);
        if (key === "Tab" && !event.defaultPrevented) {
            const stops = tabbables();
            const at = stops.indexOf(doc.activeElement);
            const next = shift ? at - 1 : at + 1;
            doc.activeElement =
                at === -1
                    ? (shift ? stops.at(-1) : stops[0]) ?? doc.body
                    : stops[next] ?? doc.body;
        }
        return event;
    }

    // Every focusable control outside `layer` that is neither hidden in a closed dialog nor inert.
    const reachableOutside = (layer) =>
        doc.documentElement
            .querySelectorAll(FOCUSABLE)
            .filter((n) => !layer.contains(n) && !isInert(n))
            .filter((n) => {
                const holder = n.closest('[role="dialog"]');
                return !holder || isOpen(holder);
            })
            .map((n) => n.id);

    return {
        doc,
        context,
        press,
        byId,
        isOpen,
        openDialogs,
        reachableOutside,
        hero,
        cartButton,
        search,
        store: vm.runInContext("store", context),
        within: (layer) => layer.contains(doc.activeElement),
        // Compare by id: failing deep-equals on circular DOM nodes are very slow and unreadable.
        focusIs: (node, message) =>
            assert.equal(
                doc.activeElement === node ? node.id || node.tagName : `${doc.activeElement?.tagName}#${doc.activeElement?.id}`,
                node.id || node.tagName,
                message ?? `focus should be on ${node.id || node.tagName}`,
            ),
        escapeAll(max = 4) {
            let count = 0;
            while (openDialogs().length && count < max) {
                press("Escape");
                count++;
            }
            return count;
        },
    };
}

const focusables = (p, id) => p.byId(id).querySelectorAll(FOCUSABLE);
// A user who opens a dialog works inside it. Restore-focus tests move focus there first so they
// cannot pass vacuously just because focus never left the opener.
const enter = (p, id) => focusables(p, id)[0].focus();

test("[GUARD] UI-06 harness: the real prototype boots in the fake DOM with its global overlay API", () => {
    const p = loadPrototype();
    for (const name of ["openRendezvousModal", "openKeyboardShortcutsModal", "closeAllModals", "openCartDrawer", "closeCartDrawer"])
        assert.equal(typeof p.context[name], "function", name);
    assert.ok(p.doc.listeners.keydown?.length, "global keydown handler is registered");
    p.focusIs(p.doc.body);
});

test("UI-06: rendezvous modal moves focus into the dialog when it opens", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    const modal = p.byId("rendezvous-modal");
    assert.equal(p.isOpen(modal), true);
    assert.equal(p.isOpen(p.byId("drawer-backdrop")), true);
    assert.ok(p.within(modal), "focus must enter the dialog, not stay on the opener behind the backdrop");
});

test("UI-06: while the rendezvous modal is open the background is inert, and inert is released on close", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    assert.deepEqual(
        p.reachableOutside(p.byId("rendezvous-modal")),
        [],
        "background controls must not be reachable while the modal is open",
    );
    enter(p, "rendezvous-modal");
    p.press("Escape");
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(p.hero, "opener restored (impossible if it were still inert)");
    p.cartButton.focus();
    p.focusIs(p.cartButton, "other page controls are focusable again");
});

test("UI-06: Escape closes the rendezvous modal and restores focus to its opener", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.press("Escape");
    assert.equal(p.isOpen(p.byId("rendezvous-modal")), false);
    assert.equal(p.isOpen(p.byId("drawer-backdrop")), false);
    p.focusIs(p.hero);
});

test("UI-06: the close-button / backdrop path (closeAllModals) restores the opener too", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.context.closeAllModals();
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(p.cartButton);
});

test("UI-06: Tab and Shift+Tab are contained in the rendezvous modal, including drifted focus", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    const modal = p.byId("rendezvous-modal");
    const items = focusables(p, "rendezvous-modal");
    assert.equal(items.length, 3);

    items.at(-1).focus();
    p.press("Tab");
    p.focusIs(items[0], "Tab from the last control wraps to the first");

    p.press("Tab");
    assert.ok(p.within(modal), "Tab inside the dialog keeps focus in the dialog");

    items[0].focus();
    p.press("Tab", { shift: true });
    p.focusIs(items.at(-1), "Shift+Tab from the first control wraps to the last");

    p.doc.activeElement = p.doc.body; // e.g. a re-render removed the focused node
    p.press("Tab");
    assert.ok(p.within(modal), "drifted focus is pulled back into the dialog");
});

test("UI-06: ? opens the shortcuts modal with focus inside; Escape restores the opener", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.press("?");
    const modal = p.byId("keyboard-shortcuts-modal");
    assert.equal(p.isOpen(modal), true);
    assert.ok(p.within(modal), "focus enters the shortcuts dialog");
    assert.deepEqual(p.reachableOutside(modal), [], "background is inert behind the shortcuts dialog");
    enter(p, "keyboard-shortcuts-modal");
    p.press("Escape");
    assert.equal(p.isOpen(modal), false);
    p.focusIs(p.hero);
});

test("UI-06: pressing ? again toggles the shortcuts modal closed and restores the opener", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.press("?");
    enter(p, "keyboard-shortcuts-modal");
    p.press("?");
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(p.cartButton);
});

test("UI-06: a single-control shortcuts modal still traps Tab and Shift+Tab", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.press("?");
    const close = p.byId("ks-close");
    p.focusIs(close, "initial focus lands on the only control");
    p.press("Tab");
    p.focusIs(close);
    p.press("Tab", { shift: true });
    p.focusIs(close);
});

test("UI-06: background shortcuts do not fire behind an open modal", () => {
    const p = loadPrototype();
    p.store.currentView = "account";
    p.hero.focus();
    p.context.openRendezvousModal();
    const modal = p.byId("rendezvous-modal");
    enter(p, "rendezvous-modal");
    for (const key of ["s", "a", "p", "/"]) {
        try {
            p.press(key);
        } catch {
            // A background action that throws in the fake DOM has still fired; state below proves it.
        }
        assert.equal(p.store.currentView, "account", `"${key}" must not navigate behind the modal`);
        assert.ok(p.within(modal), `"${key}" must not move focus out of the modal`);
        assert.notEqual(p.doc.activeElement, p.search);
    }
});

test("UI-06 nesting: the real drawer-to-modal handler sequence returns focus to the original opener", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.context.openCartDrawer();
    assert.ok(p.within(p.byId("cart-drawer")));
    // index.html cart checkout button: onclick="closeCartDrawer(); openRendezvousModal();"
    p.context.closeCartDrawer();
    p.context.openRendezvousModal();
    const modal = p.byId("rendezvous-modal");
    assert.equal(p.isOpen(p.byId("cart-drawer")), false);
    assert.ok(p.within(modal), "focus enters the modal after the drawer closes");
    p.press("Escape");
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(p.cartButton, "not body, not a hidden drawer control");
});

test("UI-06 nesting: a modal opened over a still-open drawer owns focus, Tab, and Escape", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.context.openCartDrawer();
    p.context.openRendezvousModal();
    const modal = p.byId("rendezvous-modal");
    assert.ok(p.within(modal), "the topmost layer takes focus");
    assert.deepEqual(
        p.reachableOutside(modal),
        [],
        "the underlying drawer and page are inert while the modal is on top",
    );

    const items = focusables(p, "rendezvous-modal");
    items.at(-1).focus();
    p.press("Tab");
    assert.ok(p.within(modal), "Tab wraps inside the modal, not into the drawer");
    p.press("Tab", { shift: true });
    assert.ok(p.within(modal), "Shift+Tab stays in the modal");

    p.press("Escape");
    const active = p.doc.activeElement;
    assert.notEqual(active, p.doc.body, "focus is never dropped to <body> by Escape");
    assert.ok(
        p.openDialogs().some((d) => d.contains(active)) || active === p.cartButton,
        "after one Escape focus is in a still-open layer or back on the original opener",
    );
    p.escapeAll();
    assert.equal(p.openDialogs().length, 0);
    assert.equal(p.isOpen(p.byId("drawer-backdrop")), false);
    p.focusIs(p.cartButton, "final focus is the page control that started the chain");
});

test("UI-06 nesting: rendezvous to shortcuts replacement leaves one interactive layer and restores the page opener", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    p.byId("rv-close").focus();
    p.press("?");
    const shortcuts = p.byId("keyboard-shortcuts-modal");
    assert.ok(p.within(shortcuts));
    const interactive = p.openDialogs().filter((d) => !isInert(d));
    assert.deepEqual(interactive.map((d) => d.id), ["keyboard-shortcuts-modal"], "exactly one interactive layer");
    p.escapeAll();
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(
        p.hero,
        "restore target is the page opener, not the control inside the modal that was replaced",
    );
});

test("UI-06 stale safety: opening the same modal twice keeps the original opener", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.context.openRendezvousModal(); // double activation while focus is already inside
    p.press("Escape");
    assert.equal(p.openDialogs().length, 0);
    p.focusIs(p.hero);
});

test("UI-06 stale safety: Escape with nothing open never steals focus; each open remembers its own opener", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.press("Escape");
    p.focusIs(p.hero);

    p.cartButton.focus();
    p.press("Escape");
    p.focusIs(p.cartButton, "no stale restore to the previous opener");

    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.press("Escape");
    p.focusIs(p.cartButton, "second open restores its own opener, not the first");
});

test("UI-06 stale safety: if the opener left the DOM, closing does not throw or strand focus in the closed dialog", () => {
    const p = loadPrototype();
    p.hero.focus();
    p.context.openRendezvousModal();
    enter(p, "rendezvous-modal");
    p.hero.remove();
    assert.doesNotThrow(() => p.press("Escape"));
    assert.equal(p.openDialogs().length, 0);
    assert.equal(
        p.byId("rendezvous-modal").contains(p.doc.activeElement),
        false,
        "focus must not remain inside a closed dialog",
    );
});

test("[GUARD] UI-06: existing drawer lifecycle — focus enters, Tab wraps, Escape restores the opener", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.context.openCartDrawer();
    const drawer = p.byId("cart-drawer");
    const items = focusables(p, "cart-drawer");
    assert.ok(p.within(drawer));
    items.at(-1).focus();
    p.press("Tab");
    p.focusIs(items[0]);
    p.press("Tab", { shift: true });
    p.focusIs(items.at(-1));
    p.press("Escape");
    assert.equal(p.isOpen(drawer), false);
    p.focusIs(p.cartButton);
});

const OVERLAY_OPENERS = [
    ["cart-drawer", "openCartDrawer"],
    ["profile-drawer", "openProfileDrawer"],
    ["mobile-nav-drawer", "openMobileNavDrawer"],
    ["rendezvous-modal", "openRendezvousModal"],
    ["keyboard-shortcuts-modal", "openKeyboardShortcutsModal"],
    ["authenticity-modal", "openAuthenticityModal"],
    ["work-order-modal", "openWorkOrderModal"],
];

for (const [id, open] of OVERLAY_OPENERS) {
    for (const exit of ["close", "backdrop", "Escape"]) {
        test(`R1 UI-06: ${id} enters, contains, replaces, and restores via ${exit}`, () => {
            const p = loadPrototype();
            p.store.concierge.push({ id: 999 });
            p.hero.focus();
            p.context[open](999);
            const layer = p.byId(id);
            assert.equal(p.isOpen(layer), true);
            assert.ok(p.within(layer));
            assert.deepEqual(p.reachableOutside(layer), []);
            const items = focusables(p, id);
            items.at(-1).focus();
            p.press("Tab");
            p.focusIs(items[0]);
            if (exit === "Escape") p.press("Escape");
            else p.context.closeAllModals(); // Both inline close and backdrop use this handler.
            assert.equal(p.isOpen(layer), false);
            p.focusIs(p.hero);
            p.context[open](999);
            const replacement = id === "rendezvous-modal" ? "openKeyboardShortcutsModal" : "openRendezvousModal";
            p.context[replacement]();
            assert.equal(p.openDialogs().length, 1);
            p.press("Escape");
            p.focusIs(p.hero);
        });
    }
}

test("R1 UI-06: real cart Undo toast stays inside overlay focus/inert boundary and restores home", () => {
    const p = loadPrototype();
    const home = p.byId("toast-container").parentNode;
    const item = { productId: 9, quantity: 1, size: "FR 38" };
    p.store.cart.length = 0;
    p.store.cart.push(item);
    p.cartButton.focus();
    p.context.openCartDrawer();
    p.context.removeCartItem(0);
    assert.equal(p.store.cart.length, 0);
    const layer = p.byId("cart-drawer");
    const toasts = p.byId("toast-container");
    const undo = toasts.querySelector("button");
    assert.ok(undo, "real removeCartItem produces an actionable toast");
    assert.ok(layer.contains(undo));
    assert.equal(isInert(undo), false);
    undo.focus();
    p.press("Tab");
    p.focusIs(focusables(p, "cart-drawer")[0]);
    p.press("Tab", { shift: true });
    p.focusIs(undo);
    undo.click();
    assert.equal(p.store.cart.length, 1);
    assert.equal(p.store.cart[0], item);
    assert.ok(p.within(layer), "removing the focused Undo keeps focus in the dialog");
    p.context.openRendezvousModal();
    assert.ok(p.byId("rendezvous-modal").contains(toasts));
    p.press("Escape");
    assert.equal(toasts.parentNode, home);
    assert.equal(isInert(toasts), false);
    p.focusIs(p.cartButton);
});

test("[GUARD] UI-06: switching drawers keeps the first opener", () => {
    const p = loadPrototype();
    p.cartButton.focus();
    p.context.openCartDrawer();
    p.context.openProfileDrawer();
    assert.ok(p.within(p.byId("profile-drawer")));
    p.press("Escape");
    p.focusIs(p.cartButton);
});
