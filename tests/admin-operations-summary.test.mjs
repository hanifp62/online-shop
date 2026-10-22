import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { JSDOM } from "jsdom";
import { initOperationsSummary } from "../public/js/admin-operations-summary.js";

const OLD = "2026-10-21T12:00:00Z";
const NEW = "2026-10-21T12:01:00Z";
const URL = "https://maison.test/admin/reports/operations-summary?period=today";
const tick = () => new Promise((resolve) => setImmediate(resolve));
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};

function response(overrides = {}) {
    return {
        ok: true, redirected: false,
        headers: { get: () => "text/html; charset=UTF-8" },
        text: async () => "new-report",
        ...overrides,
    };
}

function setup(fetch = async () => response(), parseOverride, initialValidationError = false) {
    const oldNodes = [{ textContent: "Old count: 7", asOf: OLD }];
    const attributes = new Map();
    const report = {
        dataset: { asOf: OLD }, childNodes: oldNodes, commits: 0,
        setAttribute: (name, value) => attributes.set(name, value),
        replaceChildren(...nodes) { this.childNodes = nodes; this.commits++; },
    };
    const button = { disabled: false };
    const listeners = new Map();
    const form = {
        action: URL.split("?")[0],
        querySelector: () => button,
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
    const announcements = [];
    const status = {
        get textContent() { return announcements.at(-1) ?? ""; },
        set textContent(value) { announcements.push(value); },
    };
    const validationBanner = {
        removed: false,
        remove() {
            assert.equal(report.commits, 1, "clear validation only after applying valid report");
            assert.equal(report.dataset.asOf, NEW);
            this.removed = true;
        },
    };
    const document = {
        location: { href: URL },
        defaultView: { FormData: class { *[Symbol.iterator]() { yield ["period", "today"]; } } },
        querySelector: (selector) => ({
            "[data-ops-filters]": form, "[data-ops-report]": report, "[data-ops-refresh-status]": status,
            "[data-ops-validation-errors]": initialValidationError && !validationBanner.removed ? validationBanner : null,
        })[selector],
        importNode: (node) => structuredClone(node),
    };
    const next = { dataset: { asOf: NEW }, childNodes: [{ textContent: "New count: 9", asOf: NEW }], querySelector: () => ({}) };
    const parse = parseOverride ?? (() => ({ querySelector: () => next }));
    let timerId = 0;
    const callbacks = new Map();
    const timers = {
        setTimeout(callback, ms) { assert.equal(ms, 15000); callbacks.set(++timerId, callback); return timerId; },
        clearTimeout(id) { callbacks.delete(id); },
    };
    const api = initOperationsSummary({ document, fetch, parse, timers });
    return { ...api, report, button, status, attributes, announcements, oldNodes, callbacks, listeners, validationBanner };
}

function assertOld(env) {
    assert.equal(env.report.dataset.asOf, OLD);
    assert.equal(env.report.childNodes, env.oldNodes, "old DOM nodes must not be discarded");
    assert.equal(env.report.commits, 0);
    assert.match(env.status.textContent, /Data lama/);
    assert.match(env.status.textContent, new RegExp(OLD));
    assert.equal(env.button.disabled, false);
    assert.equal(env.attributes.get("aria-busy"), "false");
    assert.equal(env.callbacks.size, 0);
}

test("manual success atomically replaces snapshot and as_of; no initial request", async () => {
    let calls = 0;
    const env = setup(async (url, options) => {
        calls++;
        assert.equal(url, URL);
        assert.equal(options.method, "GET");
        assert.equal(options.cache, "no-store");
        assert.equal(options.redirect, "error");
        assert.equal(options.credentials, "same-origin");
        assert.equal(options.headers.Accept, "text/html");
        return response();
    });
    assert.equal(calls, 0);
    assert.equal(await env.refresh(URL), true);
    assert.equal(calls, 1);
    assert.equal(env.report.commits, 1);
    assert.equal(env.report.dataset.asOf, NEW);
    assert.equal(env.report.childNodes[0].asOf, NEW);
    assert.match(env.status.textContent, /Pembacaan diperbarui/);
    assert.equal(env.attributes.get("aria-busy"), "false");
    assert.equal(env.callbacks.size, 0);
});

for (const code of [401, 403, 422, 500, 503]) {
    test(`HTTP ${code} preserves old snapshot and announces stale without raw body`, async () => {
        const env = setup(async () => response({ ok: false, status: code, text: () => assert.fail("must not read error body") }));
        assert.equal(await env.refresh(URL), false);
        assertOld(env);
    });
}

test("network failure does not leak source details or discard snapshot", async () => {
    const env = setup(async () => { throw new Error("PRIVATE_SOURCE SQLSTATE"); });
    assert.equal(await env.refresh(URL), false);
    assertOld(env);
    assert.doesNotMatch(env.status.textContent, /PRIVATE_SOURCE|SQLSTATE/);
});

for (const invalid of [
    { redirected: true },
    { headers: { get: () => "application/json" } },
]) {
    test(`unexpected response ${JSON.stringify(invalid)} preserves snapshot`, async () => {
        const env = setup(async () => response(invalid));
        assert.equal(await env.refresh(URL), false);
        assertOld(env);
    });
}

test("login page or malformed report cannot replace old report", async () => {
    const env = setup(undefined, () => ({ querySelector: () => null }));
    assert.equal(await env.refresh(URL), false);
    assertOld(env);
});

test("body timeout aborts and retains old snapshot; late body never commits", async () => {
    const body = deferred();
    let signal;
    const env = setup(async (_, options) => {
        signal = options.signal;
        return response({ text: () => body.promise });
    });
    const pending = env.refresh(URL);
    await tick();
    assert.equal(env.button.disabled, true);
    assert.equal(env.attributes.get("aria-busy"), "true");
    assert.equal(env.report.childNodes, env.oldNodes);
    env.callbacks.values().next().value();
    assert.equal(await pending, false);
    assert.equal(signal.aborted, true);
    assertOld(env);
    body.resolve("late-report");
    await tick();
    assertOld(env);
});

test("header timeout and overlapping submit cannot discard snapshot", async () => {
    const headers = deferred();
    let calls = 0;
    const env = setup(() => { calls++; return headers.promise; });
    const pending = env.refresh(URL);
    assert.equal(await env.refresh(URL), false);
    assert.equal(calls, 1);
    env.callbacks.values().next().value();
    assert.equal(await pending, false);
    assertOld(env);
    headers.resolve(response());
    await tick();
    assertOld(env);
});

test("retry succeeds after failure; late old body cannot overwrite newer success", async () => {
    const body = deferred();
    let calls = 0;
    const env = setup(async () => ++calls === 1 ? response({ text: () => body.promise }) : response());
    const pending = env.refresh(URL);
    await tick();
    env.callbacks.values().next().value();
    assert.equal(await pending, false);
    assertOld(env);
    assert.equal(await env.refresh(URL), true);
    body.resolve("late-old-body");
    await tick();
    assert.equal(env.report.commits, 1);
    assert.equal(env.report.dataset.asOf, NEW);
    assert.match(env.status.textContent, /Pembacaan diperbarui/);
});

test("form interception stays manual and same-origin; SSR action stays intact", async () => {
    let calls = 0;
    const env = setup(async () => { calls++; return response(); });
    assert.equal(await env.refresh("https://other.test/admin/reports/operations-summary"), false);
    assert.equal(calls, 0);
    let prevented = false;
    env.listeners.get("submit")({ preventDefault() { prevented = true; } });
    await tick();
    assert.equal(prevented, true);
    assert.equal(calls, 1);
    assert.equal(env.report.dataset.asOf, NEW);
});

test("view-only module wiring retains GET fallback and accessible stale live region", () => {
    const view = readFileSync(new globalThis.URL("../resources/views/admin/operations-summary.blade.php", import.meta.url), "utf8");
    const source = readFileSync(new globalThis.URL("../public/js/admin-operations-summary.js", import.meta.url), "utf8");
    assert.match(view, /data-ops-filters method="GET"/);
    assert.match(view, /data-ops-refresh-status role="status" aria-live="polite" aria-atomic="true"/);
    assert.match(view, /data-ops-report data-as-of=/);
    assert.match(view, /data-ops-validation-errors role="alert"/);
    assert.match(view, /type="module" src="\{\{ asset\('js\/admin-operations-summary.js'\)/);
    assert.doesNotMatch(source, /localStorage|sessionStorage|setInterval/);
});

test("initial SSR 422 then corrected 200 removes banner only after new report and as_of commit", async () => {
    const headers = deferred();
    const env = setup(() => headers.promise, undefined, true);
    env.report.dataset.asOf = "";
    env.report.childNodes = [];
    const pending = env.refresh(URL);
    assert.equal(env.validationBanner.removed, false);
    assert.equal(env.report.commits, 0);
    headers.resolve(response());
    assert.equal(await pending, true);
    assert.equal(env.validationBanner.removed, true);
    assert.equal(env.report.commits, 1);
    assert.equal(env.report.dataset.asOf, NEW);
    assert.equal(env.report.childNodes[0].asOf, NEW);
});

for (const failure of ["http", "invalid-report", "body-timeout"]) {
    test(`initial SSR 422 banner remains after ${failure}, including late response`, async () => {
        const body = deferred();
        const env = setup(
            async () => failure === "http" ? response({ ok: false, status: 422 })
                : failure === "body-timeout" ? response({ text: () => body.promise }) : response(),
            failure === "invalid-report" ? () => ({ querySelector: () => null }) : undefined,
            true,
        );
        env.report.dataset.asOf = "";
        const emptyNodes = [];
        env.report.childNodes = emptyNodes;
        const pending = env.refresh(URL);
        if (failure === "body-timeout") {
            await tick();
            env.callbacks.values().next().value();
        }
        assert.equal(await pending, false);
        body.resolve("late-body");
        await tick();
        assert.equal(env.validationBanner.removed, false);
        assert.equal(env.report.commits, 0);
        assert.equal(env.report.dataset.asOf, "");
        assert.equal(env.report.childNodes, emptyNodes);
        assert.equal(env.button.disabled, false);
    });
}

// NFR-FE-001/002/004, FR-REM-007: fallback owns refresh until React claims the root.
function ownershipFixture(state = 'pending', fetch = async () => response({ ok: false })) {
    const dom = new JSDOM(`<div id="app" data-react-fallback data-react-boot="${state}"><form data-ops-filters action="${URL.split('?')[0]}"><input name="period" value="today"><button type="submit">Refresh</button></form><p data-ops-refresh-status></p><div data-ops-report data-as-of="${OLD}"><table><tbody><tr><td>Old snapshot</td></tr></tbody></table></div></div>`, { url: URL });
    const document = dom.window.document;
    const root = document.getElementById('app');
    const form = root.querySelector('form');
    const options = { document, fetch, parse: () => new dom.window.DOMParser().parseFromString(`<div data-ops-report data-as-of="${NEW}"><table></table></div>`, 'text/html') };
    return { dom, document, root, form, options, submit: () => form.dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true })) };
}

for (const state of ['pending', 'fallback']) {
    test(`fallback ${state} keeps the snapshot on manual refresh failure`, async () => {
        let calls = 0;
        const env = ownershipFixture(state, async () => { calls++; return response({ ok: false }); });
        try {
            initOperationsSummary(env.options);
            assert.equal(env.submit(), false);
            await tick();
            assert.equal(calls, 1);
            assert.equal(env.root.querySelector('[data-ops-report]').dataset.asOf, OLD);
            assert.match(env.root.querySelector('[data-ops-refresh-status]').textContent, /Data lama/);
        } finally { env.dom.window.close(); }
    });
}

test('React-ready DOM is never bound by the late legacy module', () => {
    const env = ownershipFixture('ready');
    try {
        assert.equal(initOperationsSummary(env.options), null);
        assert.equal(env.submit(), true, 'legacy must not intercept React submit');
    } finally { env.dom.window.close(); }
});

test('claim removes the legacy submit listener and cancels in-flight work without touching React DOM', async () => {
    const body = deferred();
    let signal;
    const env = ownershipFixture('pending', async (_, options) => { signal = options.signal; return response({ text: () => body.promise }); });
    try {
        const api = initOperationsSummary(env.options);
        const pending = api.refresh(URL);
        await tick();
        env.root.dispatchEvent(new env.dom.window.Event('zafira:react-claim'));
        env.root.dataset.reactBoot = 'ready';
        const report = env.root.querySelector('[data-ops-report]');
        const status = env.root.querySelector('[data-ops-refresh-status]');
        report.textContent = 'React-owned snapshot';
        status.textContent = 'React-owned status';
        assert.equal(env.submit(), true, 'listener must be detached synchronously on claim');
        assert.equal(signal.aborted, true);
        assert.equal(await pending, false);
        body.resolve('late-report');
        await tick();
        assert.equal(report.textContent, 'React-owned snapshot');
        assert.equal(status.textContent, 'React-owned status');
        assert.equal(await api.refresh(URL), false);
    } finally { env.dom.window.close(); }
});

test('initialization is idempotent for the same fallback form', () => {
    const env = ownershipFixture();
    try {
        assert.equal(initOperationsSummary(env.options), initOperationsSummary(env.options));
    } finally { env.dom.window.close(); }
});

test('fallback module is available even when the response contains an Inertia page', () => {
    const view = readFileSync(new globalThis.URL('../resources/views/admin/operations-summary.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(view, /@unless\s*\(isset\(\$page\)\)/);
});

test('auth-event Blade context uses recorder sanitization, never raw context serialization', () => {
    const view = readFileSync(new globalThis.URL('../resources/views/admin/auth-events.blade.php', import.meta.url), 'utf8');
    assert.match(view, /sanitizeContext\(\(array\)\s*\$event->context\)/);
    assert.doesNotMatch(view, /json_encode\(\$event->context/);
});

test("queue SQL aliases avoid MySQL/MariaDB DELAYED and retain JSON contract", () => {
    const source = readFileSync(new globalThis.URL("../app/Services/OperationalMetrics.php", import.meta.url), "utf8");
    assert.doesNotMatch(source, /\bAS\s+(delayed|reserved)\b/i);
    assert.match(source, /AS delayed_count/);
    assert.match(source, /AS reserved_count/);
    assert.match(source, /'delayed_count' => \(int\) \$row->delayed_count/);
    assert.match(source, /'reserved_count' => \(int\) \$row->reserved_count/);
});
