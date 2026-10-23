import test, { describe, before, after, beforeEach, afterEach, mock } from 'node:test';
import { JSDOM } from 'jsdom';
import { webcrypto } from 'node:crypto';
import assert from 'node:assert/strict';
import { createDashboardCoordinator, TABLES, tableUrl, installTraversalBridge } from '../resources/js/lib/admin-dashboard.js';
import { installTraversalDispatch } from '../resources/js/lib/frontend-traversal.js';
const snapshot = (url = '/admin') => ({ contract: 'admin-dashboard.v1', url, filters: {}, tableState: {}, categoryOptions: [], tables: Object.fromEntries(Object.keys(TABLES).map(key => [key, { rows: [], pagination: { current: 1, last: 1, total: 0 } }])) });
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b; }); return { promise, resolve, reject }; };
function env(overrides = {}) {
    const location = { href: 'https://shop.test/admin' }, visits = [], commits = [], statuses = [];
    let draft = new Map(), agree = true, frozen = false, fatal = 0, clears = 0;
    const router = { clearHistory() { clears++; }, push: visit, replace: options => visit(options, true),
            async decryptHistory() { const { options } = visits.at(-1); return { component: 'Admin/Dashboard', url: options.url, encryptHistory: true, props: options.props({ errors: { old: 'Old error' } }) }; },
        };
    function visit(options, replace = false) { visits.push({ options, replace }); if (!overrides.manual) { location.href = new URL(options.url, location.href).href; options.onSuccess(); options.onFinish(); } }
    const coordinator = createDashboardCoordinator({ initial: snapshot(), router, location, read: async href => snapshot(href), drafts: () => draft, confirm: () => agree, freeze: () => { frozen = true; return () => { frozen = false; }; }, onCommit: value => commits.push(value), onStatus: value => statuses.push(value), onFatal: () => { fatal++; }, timeout: 30, ...overrides });
    return { coordinator, location, visits, commits, statuses, setDraft: value => { draft = value; }, setAgree: value => { agree = value; }, get frozen() { return frozen; }, get fatal() { return fatal; }, get clears() { return clears; } };
}
test('FR-FE-COMP-20261023 preserves peer keys, drops own page and reset keys', () => {
    const state = { uq: 'a', upage: 3, pq: 'silk', ppage: 2 };
    const url = new URL(tableUrl('https://shop.test/admin', state, 'user', { uq: 'b', usort: 'email', udir: 'asc' }));
    assert.equal(url.searchParams.get('ppage'), '2'); assert.equal(url.searchParams.has('upage'), false); assert.equal(url.hash, '#panel-user');
    assert.equal(new URL(tableUrl(url, state, 'user', {}, true)).searchParams.has('uq'), false);
});
test('pre-request refusal never reads or commits', async () => {
    let reads = 0; const e = env({ read: async () => { reads++; } }); e.setDraft(new Map([['field', 'a']])); e.setAgree(false);
    assert.equal(await e.coordinator.navigate('/admin?uq=a'), false); assert.equal(reads, 0); assert.equal(e.visits.length, 0);
});
test('latest read wins even if aborted body resolves late', async () => {
    const first = deferred(), second = deferred(); let reads = 0;
    const e = env({ read: () => ++reads === 1 ? first.promise : second.promise });
    const a = e.coordinator.navigate('/admin?uq=a'); const b = e.coordinator.navigate('/admin?uq=b');
    second.resolve(snapshot('/admin?uq=b')); await b; first.resolve(snapshot('/admin?uq=a')); await a;
    assert.equal(e.commits.length, 1); assert.match(e.coordinator.url, /uq=b/);
});
test('late draft requires new consent and leaves all regions untouched', async () => {
    const request = deferred(); const e = env({ read: () => request.promise }); const pending = e.coordinator.navigate('/admin?uq=a');
    e.setDraft(new Map([['field', 'late']])); e.setAgree(false); request.resolve(snapshot('/admin?uq=a')); await pending;
    assert.equal(e.commits.length, 0); assert.equal(e.visits.length, 0);
});
test('read failure retains snapshot; failed Back replaces rendered URL without push', async () => {
    const e = env({ read: async () => { throw new Error('offline'); } }); e.location.href = 'https://shop.test/admin?upage=2';
    await e.coordinator.navigate(e.location.href, { traversal: true });
    assert.equal(e.visits.length, 1); assert.equal(e.visits[0].replace, true); assert.equal(e.location.href, 'https://shop.test/admin'); assert.equal(e.commits.length, 0);
});
test('critical lock spans callback and URL confirmation, queues only latest intent', async () => {
    const e = env({ manual: true }); const pending = e.coordinator.navigate('/admin?uq=a'); await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(e.frozen, true); await e.coordinator.navigate('/admin?uq=b'); await e.coordinator.navigate('/admin?uq=c');
    assert.equal(e.visits.length, 1); e.location.href = 'https://shop.test/admin?uq=a'; e.visits[0].options.onSuccess(); await pending;
    await new Promise(resolve => setTimeout(resolve, 0)); assert.equal(e.visits.length, 2); assert.match(e.visits[1].options.url, /uq=c/);
    e.location.href = 'https://shop.test/admin?uq=c'; e.visits[1].options.onSuccess();
});
test('callback without matching URL fails closed, preserves drafts, never replays', async () => {
    const e = env({ manual: true }); const pending = e.coordinator.navigate('/admin?uq=a'); await new Promise(resolve => setTimeout(resolve, 0));
    e.visits[0].options.onSuccess(); await pending; assert.equal(e.fatal, 1); assert.equal(e.commits.length, 0);
    assert.equal(await e.coordinator.navigate('/admin?uq=b'), false); assert.equal(e.visits.length, 1);
});
test('missing callback times out and late success cannot commit local state', async () => {
    const e = env({ manual: true, timeout: 5 }); await e.coordinator.navigate('/admin?uq=a'); assert.equal(e.fatal, 1);
    e.location.href = 'https://shop.test/admin?uq=a'; e.visits[0].options.onSuccess(); assert.equal(e.commits.length, 0);
});
test('private leave invalidates reads and clears again after in-flight client completion', async () => {
    const e = env({ manual: true }); const pending = e.coordinator.navigate('/admin?uq=a'); await new Promise(resolve => setTimeout(resolve, 0));
    e.coordinator.leave(); e.location.href = 'https://shop.test/admin?uq=a'; e.visits[0].options.onSuccess(); await pending;
    assert.equal(e.commits.length, 0); assert.ok(e.clears >= 2);
});
test('write lock serializes reads and does not replay mutations', async () => {
    const e = env(); assert.equal(e.coordinator.beginWrite(), true); assert.equal(e.coordinator.beginWrite(), false);
    await e.coordinator.navigate('/admin?uq=b'); assert.equal(e.visits.length, 0); assert.equal(e.coordinator.endWrite(), true);
    await new Promise(resolve => setTimeout(resolve, 0)); assert.equal(e.commits.length, 1);
});
test('early traversal dispatcher precedes core and suppresses only the active dashboard owner', () => {
    const dom = new JSDOM('', { url: 'https://shop.test/admin?uq=b' });
    const win = dom.window, calls = [], handlers = [];
    const add = win.addEventListener.bind(win);
    win.addEventListener = (name, handler, options) => { if (name === 'popstate') handlers.push(handler); add(name, handler, options); };
    installTraversalDispatch(win);
    win.addEventListener('popstate', () => calls.push('core'));
    const cleanup = installTraversalBridge(win, { url: 'https://shop.test/admin', navigate: () => calls.push('dashboard'), leave() {} });
    const dispatchAtWindow = () => {
        let stopped = false;
        for (const handler of handlers) { if (!stopped) handler({ stopImmediatePropagation() { stopped = true; } }); }
    };
    dispatchAtWindow(); assert.deepEqual(calls, ['dashboard']);
    win.history.replaceState({}, '', '/account'); dispatchAtWindow(); assert.deepEqual(calls, ['dashboard', 'core']);
    cleanup(); win.history.replaceState({}, '', '/admin'); dispatchAtWindow(); assert.deepEqual(calls, ['dashboard', 'core', 'core']);
    dom.window.close();
});

// Real installed core + real AES-GCM in one Node realm, with a JSDOM browser.
// React control tests remain in tests/react/admin-dashboard.spec.jsx.
describe('installed Inertia core encrypted-history regressions', { concurrency: false }, () => {
    let dom, core, seed, instances, cleanups;
    const savedGlobals = new Map();
    const href = path => new URL(path, dom.window.location.origin).href;
    const waitFor = async assertion => {
        const deadline = Date.now() + 1000;
        while (true) {
            try { assertion(); return; } catch (error) {
                if (Date.now() >= deadline) throw error;
                await new Promise(resolve => setTimeout(resolve, 5));
            }
        }
    };
    const coreReplace = options => new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Core client visit did not acknowledge completion')), 1000);
        core.replace({ ...options, onSuccess: page => { clearTimeout(timer); resolve(page); }, onError: errors => { clearTimeout(timer); reject(errors); } });
    });
    before(async () => {
        dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://shop.test/admin', pretendToBeVisual: true });
        Object.defineProperty(dom.window, 'crypto', { configurable: true, value: webcrypto });
        dom.window.scrollTo = () => {};
        // JSDOM has no navigation timing entries; this is a fresh navigation.
        dom.window.performance.getEntriesByType = () => [];
        for (const [key, value] of Object.entries({ window: dom.window, document: dom.window.document, navigator: dom.window.navigator, CustomEvent: dom.window.CustomEvent, requestAnimationFrame: dom.window.requestAnimationFrame.bind(dom.window) })) {
            savedGlobals.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
            Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
        }
        installTraversalDispatch(dom.window);
        ({ router: core } = await import('@inertiajs/core'));
        await new Promise(resolve => {
            const remove = core.on('navigate', event => { if (event.detail.type === 'initial') { remove(); resolve(); } });
            core.init({ initialPage: { component: 'Admin/Dashboard', url: href('/admin'), version: 'dashboard-regression', encryptHistory: true, clearHistory: false, props: { errors: {}, dashboardSnapshot: snapshot(href('/admin')) }, rememberedState: {} }, resolveComponent: async () => ({}), swapComponent: async () => {} });
        });
    });
    beforeEach(async () => {
        instances = []; cleanups = []; seed = snapshot(href('/admin'));
        core.clearHistory();
        await coreReplace({ component: 'Admin/Dashboard', url: href('/admin'), encryptHistory: true, clearHistory: false, preserveState: true, preserveScroll: true, props: { errors: {}, dashboardSnapshot: seed, dashboardCommit: webcrypto.randomUUID() } });
        assert.ok(dom.window.history.state.page instanceof ArrayBuffer, 'entry really is encrypted, not a mock page');
        assert.ok(dom.window.history.state.page.byteLength > 28, 'AES-GCM entry includes ciphertext');
        assert.equal((await core.decryptHistory()).component, 'Admin/Dashboard');
    });
    afterEach(() => {
        // Remove injected faults BEFORE the following case reseeds core's queue.
        mock.restoreAll();
        cleanups.forEach(cleanup => cleanup()); instances.forEach(instance => instance.leave());
    });
    after(() => {
        dom?.window.close();
        for (const [key, descriptor] of savedGlobals) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });
    function fixture(overrides = {}) {
        const commits = []; let fatal = 0, locked = false;
        const instance = createDashboardCoordinator({ initial: seed, router: core, location: dom.window.location, read: async url => ({ ...seed, url }), drafts: () => new Map(), confirm: () => true, freeze: () => { locked = true; return () => { locked = false; }; }, onCommit: value => commits.push(value), onFatal: () => { fatal++; }, timeout: 1000, ...overrides });
        instances.push(instance);
        return { instance, commits, get fatal() { return fatal; }, get locked() { return locked; } };
    }
    const quotaFault = () => {
        const log = mock.method(console, 'error', () => {});
        const write = mock.method(dom.window.history, 'replaceState', () => { throw Object.assign(new Error('quota'), { name: 'QuotaExceededError' }); });
        return { log, write };
    };
    test('public decryptHistory reads persisted encrypted entry, not the live client page', async () => {
        const before = await core.decryptHistory(), observed = [];
        cleanups.push(core.on('clientVisit', event => observed.push(event.detail.page)));
        const { log } = quotaFault();
        await coreReplace({ url: href('/admin'), preserveState: true, preserveScroll: true, props: { errors: {}, dashboardCommit: 'not-persisted' } });
        assert.equal(observed.at(-1).props.dashboardCommit, 'not-persisted', 'swallowed write still invokes client success');
        assert.ok(log.mock.callCount() > 0);
        assert.equal((await core.decryptHistory()).props.dashboardCommit, before.props.dashboardCommit);
    });
    for (const mode of ['refused', 'offline']) test(`restores Back after ${mode} with identical recovery page`, async () => {
        let dirty = false;
        const e = fixture({ drafts: () => dirty ? new Map([['inline', 'draft']]) : new Map(), confirm: () => mode !== 'refused', read: async url => { if (dirty) throw new Error('offline'); return { ...seed, url }; } });
        assert.equal(await e.instance.navigate(href('/admin?uq=next#panel-user')), true);
        const rendered = dom.window.location.href, first = (await core.decryptHistory()).props.dashboardCommit;
        dirty = true; const length = dom.window.history.length;
        cleanups.push(installTraversalBridge(dom.window, e.instance));
        const pushes = mock.method(dom.window.history, 'pushState'), replacements = mock.method(dom.window.history, 'replaceState');
        dom.window.history.back();
        await waitFor(() => { assert.equal(replacements.mock.callCount(), 1); assert.equal(e.locked, false); assert.equal(dom.window.location.href, rendered); });
        const restored = await core.decryptHistory();
        assert.notEqual(restored.props.dashboardCommit, first); assert.equal(restored.url, rendered);
        assert.equal(e.commits.length, 1); assert.equal(e.fatal, 0); assert.equal(pushes.mock.callCount(), 0); assert.equal(dom.window.history.length, length);
    });
    test('same-URL refresh quota failure fires success but old persisted marker prevents local commit', async () => {
        const prior = await core.decryptHistory(), draft = 'Keep external and inline drafts';
        const e = fixture({ drafts: () => new Map([['draft', draft]]) }), events = [];
        cleanups.push(core.on('clientVisit', event => events.push(event.detail.page)));
        const { log } = quotaFault();
        assert.equal(await e.instance.navigate(href('/admin'), { replace: true, refresh: true }), false);
        assert.ok(log.mock.callCount() > 0); assert.equal(events.length, 1, 'core completed the swallowed-write client visit');
        assert.notEqual(events[0].props.dashboardCommit, prior.props.dashboardCommit);
        assert.equal(dom.window.location.href, href('/admin')); assert.equal((await core.decryptHistory()).props.dashboardCommit, prior.props.dashboardCommit);
        assert.equal(e.commits.length, 0); assert.equal(e.fatal, 1); assert.equal(e.instance.stopped, true); assert.equal(e.locked, false); assert.equal(draft, 'Keep external and inline drafts');
    });
    test('clears stale errors without clearing external validation display props', async () => {
        await coreReplace({ url: href('/admin'), preserveState: true, preserveScroll: true, props: { errors: {}, validationErrors: ['External form error'], dashboardSnapshot: seed, dashboardCommit: webcrypto.randomUUID() } });
        await new Promise(resolve => core.replace({ url: href('/admin'), preserveState: true, preserveScroll: true, props: props => ({ ...props, errors: { cat_title: 'Stale validation error' } }), onError: resolve }));
        assert.equal((await core.decryptHistory()).props.errors.cat_title, 'Stale validation error');
        const e = fixture(); assert.equal(await e.instance.navigate(href('/admin'), { replace: true, refresh: true }), true);
        const persisted = await core.decryptHistory(); assert.deepEqual(persisted.props.errors, {}); assert.deepEqual(persisted.props.validationErrors, ['External form error']);
        assert.equal(e.commits.length, 1); assert.equal(e.fatal, 0);
    });
    test('holds local state and lock until persisted-entry verification finishes', async () => {
        const decrypt = core.decryptHistory.bind(core); let resolve;
        mock.method(core, 'decryptHistory', () => new Promise(done => { resolve = done; }));
        const e = fixture(), pending = e.instance.navigate(href('/admin?uq=next'));
        await waitFor(() => assert.equal(typeof resolve, 'function'));
        assert.equal(e.locked, true); assert.equal(e.commits.length, 0);
        resolve(await decrypt()); assert.equal(await pending, true); assert.equal(e.commits.length, 1); assert.equal(e.locked, false);
    });
    for (const mode of ['reject', 'stall']) test(`decrypt ${mode} is bounded and cannot commit later`, async () => {
        let resolve; const decrypt = core.decryptHistory.bind(core);
        mock.method(core, 'decryptHistory', () => mode === 'reject' ? Promise.reject(new Error('unreadable history')) : new Promise(done => { resolve = done; }));
        const e = fixture({ timeout: 40 });
        assert.equal(await e.instance.navigate(href('/admin?uq=next')), false); assert.equal(e.fatal, 1); assert.equal(e.commits.length, 0); assert.equal(e.locked, false);
        if (resolve) { resolve(await decrypt()); await new Promise(done => setTimeout(done, 0)); }
        assert.equal(e.commits.length, 0);
    });
    test('leave during decrypt invalidates local commit and clears keys again on completion', async () => {
        let resolve; const decrypt = core.decryptHistory.bind(core), clear = mock.method(core, 'clearHistory');
        mock.method(core, 'decryptHistory', async () => { const persisted = await decrypt(); return new Promise(done => { resolve = () => done(persisted); }); });
        const e = fixture(), pending = e.instance.navigate(href('/admin?uq=next'));
        await waitFor(() => assert.equal(typeof resolve, 'function')); e.instance.leave();
        assert.equal(await pending, false); const before = clear.mock.callCount();
        resolve(); await waitFor(() => assert.ok(clear.mock.callCount() > before));
        assert.equal(e.commits.length, 0); assert.equal(e.fatal, 0); assert.equal(e.locked, false); await assert.rejects(decrypt());
    });
});
