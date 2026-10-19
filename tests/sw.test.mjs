import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../public/sw.js', import.meta.url), 'utf8');
function worker({ offline = false } = {}) {
    const handlers = {};
    const writes = [];
    const deleted = [];
    let network = 0;
    const cached = new Response('stale private HTML');
    const context = {
        URL, Response, location: { origin: 'https://zafira.test' },
        self: { addEventListener: (name, fn) => handlers[name] = fn, skipWaiting() {}, clients: { claim() {} } },
        caches: {
            match: async () => cached,
            keys: async () => ['zafira-cache-v1', 'other-app-cache'],
            delete: async (key) => deleted.push(key),
            open: async () => ({ addAll: async (urls) => writes.push(...urls), put: async (req) => writes.push(req.url) }),
        },
        fetch: async () => { network++; if (offline) throw new Error('offline'); return new Response('fresh HTML'); },
    };
    vm.runInNewContext(source, context);
    return { handlers, writes, deleted, network: () => network };
}

test('navigations use network, never cached personalized HTML, including offline', async () => {
    for (const offline of [false, true]) {
        const w = worker({ offline });
        for (const path of ['/', '/akun', '/produk/tas']) {
            let result;
            w.handlers.fetch({ request: { method: 'GET', mode: 'navigate', url: `https://zafira.test${path}` }, respondWith: (p) => result = p });
            assert.ok(result, 'navigation must use explicit network strategy');
            const response = await result;
            assert.equal(response.status, offline ? 503 : 200);
            assert.notEqual(await response.text(), 'stale private HTML');
        }
        assert.equal(w.network(), 3);
        assert.deepEqual(w.writes, []);
    }
});

test('install excludes HTML; activation removes only obsolete ZAFIRA caches', async () => {
    const w = worker();
    let pending;
    w.handlers.install({ waitUntil: (p) => pending = p });
    await pending;
    assert.ok(!w.writes.includes('/'));
    w.handlers.activate({ waitUntil: (p) => pending = p });
    await pending;
    assert.deepEqual(w.deleted, ['zafira-cache-v1']);
});

test('built assets remain cache-first', async () => {
    const w = worker();
    let pending;
    w.handlers.fetch({ request: { method: 'GET', mode: 'cors', url: 'https://zafira.test/build/assets/app-hash.js' }, respondWith: (p) => pending = p });
    assert.ok(await pending);
    assert.equal(w.network(), 0);
});
