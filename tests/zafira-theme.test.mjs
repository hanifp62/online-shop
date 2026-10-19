import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const corePaths = ['../prototype/zafira-core.js', '../public/js/zafira-core.js'];
const cores = corePaths.map(path => readFileSync(new URL(path, import.meta.url), 'utf8'));
const htmlPaths = ['../prototype/index.html', '../resources/views/welcome.blade.php'];
const htmlFiles = htmlPaths.map(path => readFileSync(new URL(path, import.meta.url), 'utf8'));

function bootCore(source, { values = {}, dark = false, storageBlocked = false } = {}) {
    const attrs = {};
    const listeners = {};
    const mediaListeners = {};
    const store = { ...values };
    const storage = {
        getItem(key) { if (storageBlocked) throw new Error('blocked'); return store[key] ?? null; },
        setItem(key, value) { if (storageBlocked) throw new Error('blocked'); store[key] = String(value); },
        removeItem(key) { delete store[key]; },
        clear() { throw new Error('clear must not be called'); }
    };
    const root = { setAttribute(key, value) { attrs[key] = value; } };
    const document = {
        readyState: 'loading', documentElement: root,
        addEventListener(type, fn) { listeners[type] = fn; },
        querySelector() { return null; }, querySelectorAll() { return []; }, getElementById() { return null; },
        createElement() { return { set name(value) {}, set content(value) {} }; },
        head: { appendChild() {} }, body: {}
    };
    const window = {
        localStorage: storage, matchMedia() { return { matches: dark, addEventListener(type, fn) { mediaListeners.change = fn; } }; },
        addEventListener() {}, dispatchEvent() {}, MutationObserver: null
    };
    window.window = window;
    runInNewContext(source, { window, document, localStorage: storage, CustomEvent: function(type, init) { this.type = type; Object.assign(this, init); }, setTimeout, clearTimeout });
    listeners.DOMContentLoaded();
    return { api: window.Zafira, attrs, store, mediaListeners };
}

test('bootstrap uses canonical keys, migrates only valid legacy values, system default', () => {
    for (const html of htmlFiles) {
        assert.match(html, /zafira_theme/);
        assert.match(html, /zafira_lang/);
        assert.match(html, /zafira-theme/);
        assert.match(html, /zafira-lang/);
        assert.match(html, /system/);
    }
    for (const source of cores) {
        const legacyWins = bootCore(source, { values: { 'zafira-theme': 'dark', 'zafira-lang': 'fr' } });
        assert.equal(legacyWins.api.getTheme(), 'dark');
        assert.equal(legacyWins.api.getLanguage(), 'fr');
        assert.equal(legacyWins.store.zafira_theme, 'dark');
        assert.equal(legacyWins.store.zafira_lang, 'fr');
        const canonicalWins = bootCore(source, { values: { zafira_theme: 'light', 'zafira-theme': 'dark', zafira_lang: 'en', 'zafira-lang': 'fr' } });
        assert.equal(canonicalWins.api.getTheme(), 'light');
        assert.equal(canonicalWins.api.getLanguage(), 'en');
        const invalidLegacy = bootCore(source, { values: { 'zafira-theme': 'pink', 'zafira-lang': 'xx' } });
        assert.equal(invalidLegacy.api.getTheme(), 'system');
        assert.equal(invalidLegacy.api.getLanguage(), 'id');
        assert.equal(bootCore(source).api.getTheme(), 'system');
        const unavailable = bootCore(source, { storageBlocked: true, dark: true });
        assert.equal(unavailable.attrs['data-theme'], 'dark');
        assert.equal(unavailable.attrs['data-theme-setting'], 'system');
        assert.equal(unavailable.api.getLanguage(), 'id');
    }
});

test('theme settings persist and OS changes affect system setting only', () => {
    for (const source of cores) {
        const runtime = bootCore(source);
        runtime.api.setTheme('system');
        assert.equal(runtime.attrs['data-theme'], 'light');
        runtime.mediaListeners.change();
        runtime.api.setTheme('dark');
        runtime.mediaListeners.change();
        assert.equal(runtime.attrs['data-theme'], 'dark');
        assert.equal(runtime.attrs['data-theme-setting'], 'dark');
        assert.equal(runtime.store.zafira_theme, 'dark');
    }
});

test('prototype cart renderer uses existing language API and guarded storage fallback', () => {
    const app = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');
    assert.match(app, /window\.Zafira\.getLanguage\(\)/);
    assert.doesNotMatch(app, /window\.Zafira\.getLang\b/);
    assert.match(app, /zafira-lang/);
});
