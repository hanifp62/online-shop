// FR-FE-COMP-20261023: one snapshot coordinator; Inertia alone writes history.
import { onDashboardTraversal } from './frontend-traversal.js';

export const TABLES = {
    kategori: ['kq', 'ksort', 'kdir', 'kpage'], produk: ['pq', 'psort', 'pdir', 'ppage'],
    pesanan: ['oq', 'osort', 'odir', 'opage', 'ostatus'], user: ['uq', 'usort', 'udir', 'upage'],
    audit: ['bq', 'bsort', 'bdir', 'bpage'], concierge: ['q', 'csort', 'cdir', 'cpage', 'status'],
    subscriber: ['sq', 'ssort', 'sdir', 'spage'],
};
export function tableUrl(base, state, key, values = {}, reset = false) {
    const url = new URL(base, globalThis.location?.href ?? 'http://localhost');
    url.search = '';
    const own = TABLES[key];
    for (const [name, value] of Object.entries(state)) {
        if (!own.includes(name) && value !== '' && value != null) url.searchParams.set(name, value);
    }
    if (!reset) for (const name of own) {
        if (!name.endsWith('page') && values[name] !== '' && values[name] != null) url.searchParams.set(name, values[name]);
    }
    url.hash = `panel-${key}`;
    return url.href;
}
export const interpolate = (text = '', values = {}) => Object.entries(values).reduce((result, [key, value]) => result.replaceAll(`:${key}`, String(value)), text);
export function validSnapshot(value) {
    return value?.contract === 'admin-dashboard.v1' && typeof value.url === 'string'
        && value.filters && value.tableState && Array.isArray(value.categoryOptions)
        && Object.keys(TABLES).every(key => {
            const table = value.tables?.[key];
            return Array.isArray(table?.rows) && table.rows.every(row => row && ['number', 'string'].includes(typeof row.id))
                && Number.isInteger(table.pagination?.current) && table.pagination.current > 0
                && Number.isInteger(table.pagination.last) && table.pagination.last >= table.pagination.current
                && Number.isInteger(table.pagination.total) && table.pagination.total >= 0;
        });
}
export function draftSnapshot(root) {
    const result = new Map();
    for (const form of root?.querySelectorAll('[data-inline-draft]') ?? []) {
        for (const field of form.querySelectorAll('input,select,textarea')) {
            if (field.type === 'hidden') continue;
            const value = field.type === 'checkbox' ? field.checked : field.value;
            const baseline = field.tagName === 'SELECT'
                ? ([...field.options].findLast(option => option.defaultSelected) ?? [...field.options].find(option => !option.disabled))?.value
                : field.type === 'checkbox' ? field.defaultChecked : field.defaultValue;
            if (value !== baseline) result.set(field, value);
        }
    }
    return result;
}
export function freezeInline(root) {
    const fields = [...root?.querySelectorAll('[data-inline-draft] input,[data-inline-draft] select,[data-inline-draft] textarea,[data-inline-draft] button') ?? []];
    const previous = fields.map(field => field.disabled);
    fields.forEach(field => { field.disabled = true; });
    return () => fields.forEach((field, index) => { field.disabled = previous[index]; });
}
let coordinatorInstances = 0;
export function createDashboardCoordinator({ initial, router, location, read, drafts, confirm, freeze = () => () => {}, onCommit, onStatus = () => {}, onBusy = () => {}, onLock = () => {}, onFatal = () => {}, timeout = 15000 }) {
    let rendered = initial;
    let renderedUrl = new URL(initial.url, location.href).href;
    let generation = 0;
    let pending;
    let critical = false;
    let stopped = false;
    let leaving = false;
    let queued;
    let writing = false;
    let commitSequence = 0;
    let cancelCommit;
    const instanceId = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${++coordinatorInstances}`;
    const sameUrl = (a, b) => new URL(a, location.href).href === new URL(b, location.href).href;
    const permit = accepted => {
        const now = drafts();
        if ([...now].some(([field, value]) => !accepted?.has(field) || accepted.get(field) !== value) && !confirm()) return null;
        return now;
    };
    const fatal = () => {
        stopped = true;
        onFatal();
        onBusy(false);
    };
    function clientCommit(snapshot, url, replace) {
        const marker = `${instanceId}:${++commitSequence}`;
        return new Promise(resolve => {
            let done = false;
            const finish = success => {
                if (done) return;
                done = true;
                clearTimeout(timer);
                cancelCommit = null;
                if (leaving) router.clearHistory();
                resolve(success && !leaving);
            };
            const timer = setTimeout(() => finish(false), timeout);
            cancelCommit = () => finish(false);
            try {
                router[replace ? 'replace' : 'push']({
                    component: 'Admin/Dashboard', url, encryptHistory: true, clearHistory: false,
                    preserveState: true, preserveScroll: true, viewTransition: false,
                    // A fresh marker forces even identical recovery pages through core's
                    // equality check. Stale validation errors must not select onError.
                    props: props => ({ ...props, errors: {}, dashboardSnapshot: snapshot, dashboardCommit: marker }),
                    onSuccess: () => {
                        if (leaving) router.clearHistory();
                        if (done || leaving || !sameUrl(location.href, url)) { finish(false); return; }
                        // The callback and URL alone do not prove persistence: core swallows
                        // quota errors. This PUBLIC API decrypts the actual current history entry,
                        // not the in-memory page (and updates core's history cache as a side effect).
                        Promise.resolve().then(() => {
                            if (done || leaving) return null;
                            return router.decryptHistory();
                        }).then(persisted => {
                            if (done || leaving) return;
                            finish(persisted?.component === 'Admin/Dashboard'
                                && persisted.encryptHistory === true
                                && persisted.props?.dashboardCommit === marker
                                && sameUrl(persisted.url, url) && sameUrl(location.href, url));
                        }).catch(() => finish(false)).finally(() => {
                            // Decryption may settle after leave or the verification deadline.
                            if (leaving) router.clearHistory();
                        });
                    },
                    onError: () => finish(false),
                    onFinish: () => { if (leaving) router.clearHistory(); },
                });
            } catch { finish(false); }
        });
    }
    async function commit(snapshot, url, replace, apply = true, traversal = false) {
        critical = true;
        onLock(true);
        const thaw = freeze();
        try {
            const ok = await clientCommit(snapshot, url, replace);
            if (!ok || leaving) { if (!leaving) fatal(); return false; }
            if (apply) {
                rendered = snapshot;
                renderedUrl = new URL(url, location.href).href;
                onCommit(snapshot, renderedUrl, { traversal });
            }
            return true;
        } finally {
            thaw();
            critical = false;
            onLock(false);
            const next = queued;
            queued = null;
            if (next && !stopped && !leaving) void navigate(...next);
        }
    }
    async function recover() {
        if (leaving || stopped) return false;
        return commit(rendered, renderedUrl, true, false);
    }
    async function navigate(href, { traversal = false, replace = false, refresh = false } = {}) {
        if (stopped || leaving) return false;
        if (critical || writing) { queued = [href, { traversal, replace, refresh }]; return 'queued'; }
        const target = new URL(href, location.href);
        const home = new URL(renderedUrl);
        if (target.origin !== home.origin || target.pathname !== home.pathname) return false;
        const mine = ++generation;
        pending?.abort();
        pending = null;
        const accepted = permit();
        if (!accepted) { onBusy(false); if (traversal) await recover(); return false; }
        if (traversal && !refresh && target.search === home.search) {
            const ok = await commit(rendered, target.href, true, false);
            if (ok) renderedUrl = target.href;
            return ok;
        }
        const controller = new AbortController();
        pending = controller;
        onBusy(true);
        onStatus('loading');
        let timer;
        try {
            const snapshot = await Promise.race([
                read(target.href, controller.signal),
                new Promise((_, reject) => { timer = setTimeout(() => { controller.abort(); reject(new Error('deadline')); }, timeout); }),
            ]);
            if (mine !== generation || leaving) return 'superseded';
            if (!validSnapshot(snapshot)) throw new Error('invalid_snapshot');
            const canonical = new URL(snapshot.url, target);
            if (canonical.origin !== home.origin || canonical.pathname !== home.pathname) throw new Error('invalid_url');
            canonical.hash = target.hash;
            if (!permit(accepted)) {
                onStatus('');
                if (traversal) await recover();
                return false;
            }
            const ok = await commit(snapshot, canonical.href, traversal || replace, true, traversal);
            if (ok) onStatus('loaded');
            return ok;
        } catch (error) {
            if (mine !== generation || leaving) return 'superseded';
            onStatus(error.kind === 'login' ? 'session_inactive' : 'network_error');
            if (traversal) await recover();
            return false;
        } finally {
            clearTimeout(timer);
            if (mine === generation) { pending = null; onBusy(false); }
        }
    }
    return {
        navigate,
        recover,
        get rendered() { return rendered; },
        get url() { return renderedUrl; },
        get stopped() { return stopped || leaving; },
        beginWrite() {
            if (writing || critical || stopped || leaving) return false;
            writing = true;
            ++generation;
            pending?.abort(); pending = null;
            onBusy(false);
            return true;
        },
        endWrite() {
            writing = false;
            const next = queued; queued = null;
            if (next && !stopped && !leaving) { void navigate(...next); return true; }
            return false;
        },
        leave() { leaving = true; ++generation; pending?.abort(); queued = null; cancelCommit?.(); router.clearHistory(); },
    };
}
export function installTraversalBridge(win, coordinator) {
    const popstate = event => {
        const current = new URL(win.location.href);
        const rendered = new URL(coordinator.url);
        if (current.origin !== rendered.origin || current.pathname !== rendered.pathname) return;
        event.preventDefault();
        void coordinator.navigate(current.href, { traversal: true });
    };
    const leave = () => coordinator.leave();
    const removeTraversal = onDashboardTraversal(win, popstate);
    win.addEventListener('zafira:private-leave', leave);
    return () => { removeTraversal(); win.removeEventListener('zafira:private-leave', leave); coordinator.leave(); };
}
export async function readDashboard(href, signal) {
    const response = await fetch(href, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Zafira-Dashboard': 'snapshot' } });
    if (!response.ok || response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) {
        const error = new Error('read_failed'); error.kind = [401, 419].includes(response.status) ? 'login' : 'http'; throw error;
    }
    return response.json();
}
