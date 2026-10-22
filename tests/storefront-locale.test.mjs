import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8');
function boot(locale = 'fr', fetcher = async () => ({ ok: true }), blocked = false) {
    const attrs = { 'data-server-locale': locale, 'data-locale-url': '/locale' };
    const listeners = {};
    const store = { zafira_lang: 'en' };
    const status = { textContent: '', setAttribute(k, v) { this[k] = v; } };
    const document = {
        readyState: 'loading',
        documentElement: { getAttribute: key => attrs[key], setAttribute: (key, value) => { attrs[key] = value; } },
        body: { setAttribute(k, v) { this[k] = v; }, classList: { contains: c => c === 'storefront' } }, head: { appendChild() {} },
        createElement: () => ({}),
        querySelector: selector => selector.includes('csrf-token') ? { content: 'test-csrf' } : null,
        querySelectorAll: () => [],
        getElementById: id => id === 'locale-status' ? status : null,
        addEventListener: (type, fn) => { listeners[type] = fn; },
    };
    const calls = [];
    const window = { dispatchEvent() {}, location: { href: 'https://shop.test/?locale=fr#collection', reload() {} }, history: { replaceState(_s, _t, url) { window.location.href = String(url); } } };
    runInNewContext(source, { document, window, URL, fetch: (...args) => { calls.push(args); return fetcher(...args); }, localStorage: { getItem: key => { if (blocked) throw Error('blocked'); return store[key] ?? null; }, setItem: (key, value) => { if (blocked) throw Error('blocked'); store[key] = value; } }, CustomEvent: function () {}, setTimeout, clearTimeout });
    listeners.DOMContentLoaded();
    return { api: window.Zafira, attrs, calls, status, store, listeners, window, body: document.body };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
test('server locale wins over old storage without initial POST, including zh compatibility', () => {
    for (const locale of ['id', 'en', 'fr', 'it', 'zh']) {
        const r = boot(locale);
        assert.equal(r.api.getLanguage(), locale);
        assert.equal(r.attrs.lang, locale);
        assert.equal(r.attrs['data-lang'], locale);
        assert.equal(r.calls.length, 0);
    }
});
test('real selector POST uses CSRF and updates persisted state and stale query only after success', async () => {
    const r = boot();
    r.listeners.click({ preventDefault() {}, target: { closest: selector => selector === '[data-set-lang]' ? { getAttribute: () => 'it' } : null } });
    await tick();
    assert.equal(r.calls.length, 1);
    const [url, options] = r.calls[0];
    assert.equal(url, '/locale');
    assert.equal(options.method, 'POST');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(JSON.parse(options.body).locale, 'it');
    assert.equal(r.api.getLanguage(), 'it');
    assert.equal(r.store.zafira_lang, 'it');
    assert.equal(new URL(r.window.location.href).searchParams.has('locale'), false);
    assert.equal(new URL(r.window.location.href).hash, '#collection');
});
test('requests serialize, obsolete responses cannot overwrite newer choice', async () => {
    const pending = [];
    const r = boot('fr', () => new Promise(resolve => pending.push(resolve)));
    r.api.setLanguage('en');
    r.api.setLanguage('it');
    assert.equal(r.calls.length, 1);
    pending.shift()({ ok: true });
    await tick();
    assert.equal(r.calls.length, 2);
    assert.equal(r.api.getLanguage(), 'fr');
    pending.shift()({ ok: true });
    await tick();
    assert.equal(r.api.getLanguage(), 'it');
    assert.equal(r.store.zafira_lang, 'it');
});
test('HTTP and transport failures keep confirmed locale and show accessible error; blocked storage is safe', async () => {
    for (const fetcher of [async () => ({ ok: false }), async () => { throw Error('offline'); }]) {
        const r = boot('fr', fetcher, true);
        await r.api.setLanguage('it');
        assert.equal(r.api.getLanguage(), 'fr');
        assert.ok(r.status.textContent);
        assert.equal(r.calls.length, 1);
    }
});
test('failed newer choice falls back to latest successful server write', async () => {
    const pending = [];
    const r = boot('fr', () => new Promise(resolve => pending.push(resolve)));
    r.api.setLanguage('en');
    r.api.setLanguage('it');
    pending.shift()({ ok: true });
    await tick();
    pending.shift()({ ok: false });
    await tick();
    assert.equal(r.api.getLanguage(), 'en');
    assert.equal(r.store.zafira_lang, 'en');
    assert.equal(new URL(r.window.location.href).searchParams.has('locale'), false);
});
test('zh marks storefront body Chinese while preserving Chinese preference', async () => {
    const r = boot('zh');
    assert.equal(r.api.getLanguage(), 'zh');
    assert.equal(r.attrs.lang, 'zh');
    assert.equal(r.attrs['data-lang'], 'zh');
    assert.equal(r.body.lang, 'zh');
    await r.api.setLanguage('en');
    assert.equal(r.body.lang, 'en');
});
test('welcome language switchers expose zh and switching en -> zh -> fr round-trips', async () => {
    const view = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
    const groups = [...view.matchAll(/<div class="lang-switcher"[^>]*>([\s\S]*?)<\/div>/g)].map(m => m[1]);
    assert.equal(groups.length, 2);
    for (const group of groups) {
        assert.deepEqual([...group.matchAll(/data-set-lang="(\w+)"/g)].map(m => m[1]), ['id', 'en', 'fr', 'it', 'zh']);
    }
    const r = boot('en');
    const click = lang => r.listeners.click({ preventDefault() {}, target: { closest: s => s === '[data-set-lang]' ? { getAttribute: () => lang } : null } });
    click('zh');
    await tick();
    assert.equal(r.api.getLanguage(), 'zh');
    assert.equal(r.body.lang, 'zh');
    assert.equal(JSON.parse(r.calls[0][1].body).locale, 'zh');
    click('fr');
    await tick();
    assert.equal(r.api.getLanguage(), 'fr');
    assert.equal(r.body.lang, 'fr');
});
test('invalid locale does not POST', async () => {
    const r = boot();
    await r.api.setLanguage('../en');
    assert.equal(r.calls.length, 0);
});
