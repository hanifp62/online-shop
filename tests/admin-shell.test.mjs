// FR-ADM-20261025 / NFR-THEME-20261025: Blade-owned shell, independent of React.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const shell = readFileSync('resources/views/admin/layouts/app.blade.php', 'utf8');
const css = readFileSync('resources/css/admin.css', 'utf8');
const dashboardCss = readFileSync('resources/css/admin-dashboard.css', 'utf8');

test('theme initializer and persistent shell precede the native/fallback root', () => {
    assert.ok(shell.includes('<x-theme-preference />'), 'shared head initializer');
    assert.ok(shell.indexOf('<x-theme-preference />') < shell.indexOf('@vite('));
    assert.ok(shell.includes('class="admin-sidebar"'), 'dark navigation rail');
    assert.ok(shell.includes('class="admin-work-topbar"'), 'persistent topbar');
    assert.ok(shell.indexOf('data-theme-select') < shell.indexOf('<x-react-root'));
    assert.ok(shell.includes('<x-private-page-lifecycle />'));
    assert.ok(shell.includes('<x-react-root :page="$page">@yield(\'content\')</x-react-root>'));
    assert.ok(shell.includes('href="#main-content"'));
    assert.ok(shell.includes('id="main-content"'));
});

test('topbar offers the shared theme contract, real profile and CSRF logout, not a fake search', () => {
    const control = shell.match(/<select\b[^>]*data-theme-select[^>]*>([\s\S]*?)<\/select>/);
    assert.ok(control, 'shared theme select');
    assert.deepEqual([...control[1].matchAll(/<option value="([^"]+)"/g)].map(match => match[1]), ['light', 'dark', 'system']);
    assert.ok(shell.includes('for="admin-theme"'));
    assert.ok(shell.includes("route('admin.profile.show')"));
    assert.ok(shell.includes("route('admin.logout')"));
    assert.match(shell, /method="POST" action="\{\{ route\('admin.logout'\) \}\}">\s*@csrf/);
    assert.ok(!shell.includes('type="search"'), 'search remains in the existing working table filters');
    assert.ok(!shell.includes('localStorage'), 'preference ownership remains with the shared initializer');
});

test('navigation uses the actual admin Gate and gates the optional report on every page', () => {
    assert.ok(shell.includes('Gate::forUser($operator)'));
    for (const ability of ['view-dashboard', 'manage-catalog', 'manage-categories', 'manage-orders', 'manage-concierge', 'manage-subscribers', 'view-audit-log', 'view-reports']) {
        assert.ok(shell.includes(`$adminGate->allows('${ability}')`), ability);
    }
    assert.ok(shell.includes("enabled('admin_ops_summary'"));
    assert.ok(!shell.includes("in_array($activePage, ['auth-events', 'operations-summary']"), 'flagged reports also reachable from dashboard/profile');
    assert.ok(!/href="#"/.test(shell));
});

function navigation(mobile) {
    const script = shell.match(/<script data-admin-shell>([\s\S]*?)<\/script>/)?.[1];
    const disclosure = shell.match(/<details\b[^>]*data-admin-navigation[^>]*>[\s\S]*?<\/details>/)?.[0];
    assert.ok(script && disclosure, 'native details plus standalone progressive enhancement');
    const dom = new JSDOM(`${disclosure}<main id="main-content"><input id="draft" value="Unsaved"></main>`, { runScripts: 'outside-only' });
    let listener;
    dom.window.matchMedia = query => {
        assert.equal(query, '(max-width: 960px)');
        return { matches: mobile, addEventListener: (name, handler) => { assert.equal(name, 'change'); listener = handler; } };
    };
    dom.window.eval(script);
    return { dom, change: matches => listener({ matches }) };
}

test('mobile disclosure closes with Escape and restores focus without locking the page', () => {
    const { dom, change } = navigation(true);
    try {
        const { document, KeyboardEvent } = dom.window;
        const menu = document.querySelector('[data-admin-navigation]'), summary = menu.querySelector('summary');
        assert.equal(menu.open, false);
        summary.click(); assert.equal(menu.open, true);
        menu.querySelector('a').focus();
        menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        assert.equal(menu.open, false); assert.equal(document.activeElement, summary);
        assert.equal(document.querySelector('[inert], [aria-modal="true"]'), null);
        assert.equal(document.body.style.overflow, '');
        change(false); assert.equal(menu.open, true);
        assert.equal(document.activeElement, menu.querySelector('a'), 'focus must not remain on the now-hidden desktop summary');
        change(true); assert.equal(menu.open, false); assert.equal(document.activeElement, summary);
        const draft = document.getElementById('draft'); draft.focus();
        change(false); change(true);
        assert.equal(document.activeElement, draft, 'responsive navigation must not steal focus from work');
        assert.equal(draft.value, 'Unsaved');
    } finally { dom.window.close(); }
});

test('desktop navigation stays open and the disclosure also works without its script', () => {
    const { dom } = navigation(false);
    try {
        const menu = dom.window.document.querySelector('[data-admin-navigation]');
        assert.equal(menu.open, true);
        menu.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        assert.equal(menu.open, true);
    } finally { dom.window.close(); }
    const raw = shell.match(/<details\b[^>]*data-admin-navigation[^>]*>[\s\S]*?<\/details>/)?.[0];
    const fallback = new JSDOM(raw);
    try {
        const menu = fallback.window.document.querySelector('details');
        assert.equal(menu.open, true);
        menu.querySelector('summary').click(); assert.equal(menu.open, false);
        menu.querySelector('summary').click(); assert.equal(menu.open, true);
    } finally { fallback.window.close(); }
});

test('read-page styles inherit semantic tokens instead of a second fixed light palette', () => {
    const events = readFileSync('resources/views/admin/auth-events.blade.php', 'utf8');
    const operations = readFileSync('resources/views/admin/operations-summary.blade.php', 'utf8');
    assert.ok(!events.includes(':root {'));
    assert.ok(!/background:\s*#/.test(events));
    assert.ok(events.includes('var(--surface)'));
    assert.ok(events.includes('var(--on-accent)'));
    assert.ok(operations.includes('min-width: 0'));
});

test('admin theme and type contract uses semantic surfaces with a fixed-dark brand rail', () => {
    for (const token of ['surface', 'surface-alt', 'ink', 'muted', 'line', 'accent', 'on-accent', 'focus-ring']) assert.ok(css.includes(`var(--${token})`), token);
    assert.match(css, /\.admin-sidebar\s*\{[^}]*background:\s*var\(--sapphire-deep\)/);
    assert.match(css, /\.admin-brand-name\s*\{[^}]*font-family:\s*var\(--serif\)/);
    assert.match(css, /body\.admin-console\s*\{[^}]*font-family:\s*var\(--sans\)/);
    assert.ok(css.includes('font-variant-numeric: tabular-nums'));
    assert.ok(css.includes('minmax(0, 1fr)'));
    assert.ok(css.includes('@media (max-width: 960px)'));
    assert.ok(css.includes('body.admin-console :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible'));
    assert.ok(css.includes('min-height: 44px'));
    assert.ok(!/var\(--(?:white|ivory|sapphire)\)/.test(css + dashboardCss), 'theme-sensitive UI must not depend on primitive colors');
});
