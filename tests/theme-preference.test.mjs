import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { JSDOM } from 'jsdom';

// NFR-THEME-20261025 / NFR-SAFE-20261025: execute the actual synchronous head runtime.
const component = new URL('../resources/views/components/theme-preference.blade.php', import.meta.url);
const source = existsSync(component) ? readFileSync(component, 'utf8').match(/<script[^>]*>([\s\S]*?)<\/script>/)?.[1] ?? '' : '';
const select = '<select data-theme-select><option value="system">Otomatis</option><option value="light">Terang</option><option value="dark">Gelap</option></select>';
test('auth success and profile deletion errors keep semantic theme pairs in both renderers', () => {
    for (const path of ['resources/views/auth/login.blade.php', 'resources/views/auth/password-email.blade.php', 'resources/js/components/AuthForm.jsx']) {
        const source = readFileSync(path, 'utf8');
        assert.ok(source.includes('var(--success)') && source.includes('var(--success-surface)'), path);
        assert.doesNotMatch(source, /#15803d|#16a34a/);
    }
    for (const path of ['resources/views/account/show.blade.php', 'resources/js/pages/Account/Show.jsx']) {
        const source = readFileSync(path, 'utf8');
        assert.ok(source.includes('var(--danger)'), path);
        assert.doesNotMatch(source, /#a32\b/);
    }
});
function boot(t, { values = {}, dark = false, blocked = false, writeBlocked = false, legacyMedia = false, html = '' } = {}) {
    const dom = new JSDOM(`<!doctype html><html><head></head><body>${html}</body></html>`, { url: 'https://shop.test/', runScripts: 'outside-only' });
    t.after(() => dom.window.close());
    const { window: w } = dom;
    for (const [key, value] of Object.entries(values)) w.localStorage.setItem(key, value);
    const media = new w.EventTarget(); media.matches = dark;
    if (legacyMedia) {
        media.addListener = listener => w.EventTarget.prototype.addEventListener.call(media, 'change', listener);
        media.addEventListener = undefined;
    }
    w.matchMedia = () => media;
    if (blocked) Object.defineProperty(w, 'localStorage', { get() { throw new Error('blocked'); } });
    else if (writeBlocked) w.Storage.prototype.setItem = () => { throw new Error('quota'); };
    w.eval(source);
    return { w, root: w.document.documentElement, media, api: w.ZafiraTheme };
}
function check(runtime, setting, resolved) {
    assert.equal(runtime.api?.get(), setting);
    assert.equal(runtime.root.dataset.theme, resolved);
    assert.equal(runtime.root.dataset.themeSetting, setting);
    assert.equal(runtime.root.dataset.themePreference, setting);
    assert.equal(runtime.root.style.colorScheme, resolved);
}
function external(w, key, newValue, storageArea = w.localStorage) {
    if (key === null) storageArea.clear();
    else if (newValue === null) storageArea.removeItem(key);
    else storageArea.setItem(key, newValue);
    w.dispatchEvent(new w.StorageEvent('storage', { key, newValue, storageArea }));
}

test('all application HTML heads include the initializer exactly once before styles or bundles', () => {
    const views = new URL('../resources/views/', import.meta.url);
    const heads = readdirSync(views, { recursive: true }).filter(path => path.endsWith('.blade.php') && !/(^|\/)(mail|emails|pdf|pdfs|prototype)\//.test(path)).map(path => ({ path, html: readFileSync(new URL(path, views), 'utf8') })).filter(({ html }) => /<head[\s>]/i.test(html));
    assert.equal(heads.length, 19);
    for (const { path, html } of heads) {
        const head = html.match(/<head[^>]*>([\s\S]*?)<\/head>/i)[1];
        assert.equal(head.match(/<x-theme-preference\s*\/>/g)?.length, 1, path);
        assert.ok(head.indexOf('<x-theme-preference') < head.search(/<style\b|rel="stylesheet"|@vite/), path);
    }
    const css = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
    assert.ok(css.startsWith("@import 'tailwindcss';\n@import './profile.css';\n@import './theme.css';"));
});

test('head applies saved dark synchronously without writing a system default', t => {
    const r = boot(t, { values: { zafira_theme: 'dark' } });
    check(r, 'dark', 'dark');
    assert.equal(r.w.localStorage.getItem('zafira_theme'), 'dark');
});
test('absent preference follows the OS without persisting its resolved value', t => {
    const r = boot(t, { dark: true }); check(r, 'system', 'dark');
    assert.equal(r.w.localStorage.getItem('zafira_theme'), null);
});
test('only an absent canonical key migrates a valid legacy preference', t => {
    const r = boot(t, { values: { 'zafira-theme': 'dark' } }); check(r, 'dark', 'dark');
    assert.equal(r.w.localStorage.getItem('zafira_theme'), 'dark');
    for (const value of ['light', 'invalid']) {
        const other = boot(t, { values: { zafira_theme: value, 'zafira-theme': 'dark' } });
        check(other, value === 'light' ? 'light' : 'system', 'light');
    }
    check(boot(t, { values: { 'zafira-theme': 'invalid' } }), 'system', 'light');
});
test('blocked reads and migration writes cannot discard a usable preference or prevent interaction', t => {
    const r = boot(t, { blocked: true, dark: true }); check(r, 'system', 'dark');
    r.api.set('light'); check(r, 'light', 'light');
    r.w.dispatchEvent(new r.w.Event('pageshow')); check(r, 'light', 'light');
    check(boot(t, { values: { 'zafira-theme': 'dark' }, writeBlocked: true }), 'dark', 'dark');
});
test('system changes resolve only system, including legacy MediaQueryList listeners', t => {
    for (const legacyMedia of [false, true]) {
        const r = boot(t, { legacyMedia });
        r.media.matches = true; r.media.dispatchEvent(new r.w.Event('change')); check(r, 'system', 'dark');
        r.api.set('light'); r.media.dispatchEvent(new r.w.Event('change')); check(r, 'light', 'light');
        r.api.set('invalid'); check(r, 'light', 'light');
    }
});
test('storage changes, removal and clear synchronize without echo writes; sessionStorage is ignored', t => {
    const r = boot(t, { dark: true });
    external(r.w, 'zafira_theme', 'light'); check(r, 'light', 'light');
    external(r.w, 'zafira_theme', 'dark', r.w.sessionStorage); check(r, 'light', 'light');
    external(r.w, 'zafira-theme', 'dark'); check(r, 'light', 'light');
    external(r.w, 'zafira_theme', null); check(r, 'system', 'dark');
    assert.equal(r.w.localStorage.getItem('zafira_theme'), null);
    external(r.w, null, null); check(r, 'system', 'dark');
    external(r.w, 'zafira-theme', 'light'); check(r, 'light', 'light');
    external(r.w, 'zafira_theme', 'invalid'); check(r, 'system', 'dark');
});
test('legacy storefront core delegates without duplicate OS listeners, writes or events', async t => {
    const r = boot(t, { values: { zafira_theme: 'dark', zafira_lang: 'fr' }, html: `${select}<span id="dock-theme-text"></span>` });
    let mediaReads = 0; r.w.matchMedia = () => { mediaReads++; return r.media; };
    let changes = 0; r.w.addEventListener('zafira:theme-changed', () => changes++);
    r.root.dataset.serverLocale = 'id';
    r.w.eval(readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8'));
    if (r.w.document.readyState === 'loading') await new Promise(resolve => r.w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    assert.equal(mediaReads, 0); assert.equal(changes, 0); check(r, 'dark', 'dark');
    assert.equal(r.w.Zafira.getLanguage(), 'id');
    r.w.Zafira.setTheme('light'); check(r, 'light', 'light'); assert.equal(changes, 1);
    external(r.w, 'zafira_theme', 'dark'); check(r, 'dark', 'dark');
    assert.equal(r.w.Zafira.getTheme(), 'dark');
    assert.equal(r.w.document.getElementById('dock-theme-text').textContent, r.w.Zafira.I18N.id.theme_dark);
    r.api.set('system'); r.media.matches = true; r.media.dispatchEvent(new r.w.Event('change')); check(r, 'system', 'dark');
});
test('welcome retains query/server/legacy locale initialization without inline theme or price patches', t => {
    const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
    const locale = welcome.match(/<script>\s*(\(function\(\)\s*\{[\s\S]*?)<\/script>/)[1];
    assert.doesNotMatch(locale, /zafira[_-]theme|data-theme/);
    assert.doesNotMatch(welcome, /Dark Theme Price|html\[data-theme="dark"\]/);
    for (const [server, query, stored, expected] of [['fr', '', 'en', 'fr'], ['fr', 'it', 'en', 'it'], ['', '', 'zh', 'zh'], ['fr', 'invalid', 'en', 'fr']]) {
        const r = boot(t, { values: { 'zafira-lang': stored, zafira_theme: 'dark' } });
        if (server) r.root.dataset.serverLocale = server;
        if (query) r.w.history.replaceState(null, '', `?locale=${query}`);
        r.w.eval(locale); assert.equal(r.root.lang, expected); check(r, 'dark', 'dark');
        if (query === 'it') assert.equal(r.w.localStorage.getItem('zafira_lang'), 'it');
    }
});
test('BFCache restoration rechecks changes made while the document was suspended', t => {
    const r = boot(t); r.w.localStorage.setItem('zafira_theme', 'dark');
    r.w.dispatchEvent(new r.w.Event('pageshow')); check(r, 'dark', 'dark');
});
test('initial controls and late React controls sync defaults without dirtying unrelated fields or moving focus', async t => {
    const r = boot(t, { values: { zafira_theme: 'dark' }, html: `<div id="app" data-react-boot="pending">${select}<input value="saved"></div>` });
    const app = r.w.document.getElementById('app');
    const entry = readFileSync(new URL('../resources/js/inertia.jsx', import.meta.url), 'utf8');
    r.w.eval(`${entry.slice(entry.indexOf('function hasEditedForm'), entry.indexOf('function MountedPage'))}; window.canTakeOver = canTakeOver;`);
    const assertSelect = node => {
        assert.equal(node.value, r.api.get());
        for (const option of node.options) assert.equal(option.selected, option.defaultSelected);
    };
    assertSelect(app.querySelector('select'));
    assert.equal(r.w.canTakeOver(app), true);
    app.insertAdjacentHTML('beforeend', select); await Promise.resolve();
    assertSelect(app.querySelector('select:last-child'));
    const input = app.querySelector('input'); input.value = 'edited'; input.focus();
    external(r.w, 'zafira_theme', 'light');
    assert.equal(input.value, 'edited'); assert.equal(r.w.document.activeElement, input);
    assert.equal(r.w.canTakeOver(app), false);
    app.querySelectorAll('select').forEach(assertSelect);
});
test('theme controls work even when a stalled fallback loader stops bubbling; user interaction still blocks takeover', t => {
    const r = boot(t, { html: `<div id="app">${select}<button type="button" data-set-theme="dark">Dark</button></div>` });
    const handoff = readFileSync(new URL('../resources/views/components/react-root.blade.php', import.meta.url), 'utf8').match(/<script data-frontend-handoff>([\s\S]*?)<\/script>/)[1];
    r.w.eval(handoff);
    const app = r.w.document.getElementById('app');
    for (const type of ['click', 'change']) app.addEventListener(type, event => event.stopPropagation(), true);
    app.querySelector('button').click(); check(r, 'dark', 'dark');
    assert.equal(app.dataset.reactBoot, 'fallback');
    const control = app.querySelector('select'); control.value = 'light';
    control.dispatchEvent(new r.w.Event('change', { bubbles: true })); check(r, 'light', 'light');
    assert.equal(app.dataset.reactBoot, 'fallback');
});
test('selects and radio buttons stay in sync; duplicate initialization never attaches another owner', t => {
    const r = boot(t, { html: `${select}<select class="zafira-theme-select"><option value="light">Light</option><option value="dark">Dark</option><option value="system">System</option></select><button data-set-theme="dark" role="radio"><span>Dark</span></button>` });
    let changed = 0; r.w.addEventListener('zafira:theme-changed', () => changed++);
    r.w.eval(source); assert.equal(r.w.ZafiraTheme, r.api);
    r.w.document.querySelector('button span').click(); check(r, 'dark', 'dark'); assert.equal(changed, 1);
    assert.equal(r.w.document.querySelector('button').getAttribute('aria-checked'), 'true');
    const control = r.w.document.querySelector('select'); control.value = 'light';
    control.dispatchEvent(new r.w.Event('change', { bubbles: true })); check(r, 'light', 'light'); assert.equal(changed, 2);
    assert.equal(r.w.document.querySelector('.zafira-theme-select').value, 'light');
    assert.equal(r.w.localStorage.getItem('zafira_theme'), 'light');
});
