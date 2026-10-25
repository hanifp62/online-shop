import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const locales = ['id', 'en', 'fr', 'it', 'zh'];
const pairs = source => [...source.matchAll(/'([^']+)'\s*=>\s*'((?:\\.|[^'])*)'/g)].map(match => [match[1], match[2]]);
function section(source, name) {
    const start = source.indexOf(`    '${name}' => [`);
    return source.slice(start, source.indexOf('\n    ],', start));
}

test('FR-PROFILE-20261025 new profile and account navigation copy has five-locale parity', () => {
    const adminKeys = pairs(read('lang/en/profile_ui.php')).map(([key]) => key);
    const accountKeys = pairs(section(read('lang/en/account_ui.php'), 'show')).map(([key]) => key);
    for (const locale of locales) {
        const admin = pairs(read(`lang/${locale}/profile_ui.php`));
        assert.deepEqual(admin.map(([key]) => key), adminKeys);
        assert.ok(admin.every(([, value]) => value.trim() !== ''));
        const show = pairs(section(read(`lang/${locale}/account_ui.php`), 'show'));
        assert.deepEqual(show.map(([key]) => key), accountKeys);
        for (const key of ['account_label', 'navigation', 'details', 'security', 'theme', 'theme_light', 'theme_dark', 'theme_system']) {
            assert.ok(new Map(show).get(key)?.trim(), `${locale}: ${key}`);
        }
        const history = new Map(pairs(section(read(`lang/${locale}/shop_ui.php`), 'welcome'))).get('profile_action_orders');
        assert.ok(history?.trim());
        assert.doesNotMatch(history, /orders|pesanan|commandes|ordini|订单/i);
    }
});

test('profile route is additive GET-only in the existing admin guard/TOTP/permission group', () => {
    const routes = read('routes/web.php');
    const protectedGroup = routes.slice(routes.indexOf("Route::middleware([NoStore::class, 'auth:admin', EnsureAdminTotp::class])"));
    assert.match(protectedGroup, /Route::get\('\/admin\/profil', \[AdminProfileController::class, 'show'\]\)->middleware\('admin\.can:view-dashboard'\)->name\('admin\.profile\.show'\)/);
    assert.equal([...routes.matchAll(/Route::\w+\('\/admin\/profil'/g)].length, 1);
    assert.match(protectedGroup, /Route::patch\('\/admin\/password', \[AdminDashboardController::class, 'updatePassword'\]\)->middleware\('admin\.can:view-dashboard'\)/);
    assert.ok(read('config/frontend.php').includes("'Admin/Profile'"));
    const controller = read('app/Http/Controllers/AdminProfileController.php');
    assert.match(controller, /\$request->user\('admin'\)/);
    assert.match(controller, /FrontendPage::render\('Admin\/Profile', 'admin\.profile'/);
});

test('profile DTO and native form cannot serialize models, old input or credential form state', () => {
    const dto = read('app/Support/AdminProfilePageProps.php');
    assert.doesNotMatch(dto, /->toArray\(|->getOldInput\(|->all\(|'password'\s*=>\s*\$admin|'totp_secret'\s*=>|remember_token/);
    assert.match(dto, /'totpEnrol' => \$admin->getRawOriginal\('totp_secret'\) === null \? route\('admin\.totp\.enrol'\) : null/);
    assert.match(dto, /Gate::forUser\(\$admin\)->allows\('view-audit-log'\)/);
    const native = read('resources/js/pages/Admin/Profile.jsx');
    assert.match(native, /import AuthForm, \{ AuthFeedback, AuthField \}/);
    assert.match(native, /<input type="hidden" name="_method" value="PATCH"/);
    assert.doesNotMatch(native, /useForm|useRemember|localStorage|sessionStorage|router\.|fetch\(|defaultValue=\{.*password/);
    const blade = read('resources/views/admin/profile.blade.php');
    assert.ok(blade.includes("@extends('admin.layouts.app'"));
    assert.ok(blade.includes("@method('PATCH')"));
    assert.doesNotMatch(blade, /old\(|value="\{\{.*password/);
});

test('customer Blade keeps real profile sections and existing native security and privacy paths', () => {
    const blade = read('resources/views/account/show.blade.php');
    for (const id of ['account-details', 'account-security', 'account-privacy', 'account-history']) {
        assert.ok(blade.includes(`href="#${id}"`));
        assert.ok(blade.includes(`id="${id}" aria-labelledby="${id}-heading"`));
        assert.ok(blade.includes(`<h2 id="${id}-heading">`));
    }
    for (const route of ['account.update', 'account.totp.destroy', 'account.totp.enrol', 'account.export', 'account.destroy', 'social.unlink', 'social.link', 'logout']) {
        assert.ok(blade.includes(`route('${route}'`));
    }
    assert.ok(blade.includes('<x-private-page-lifecycle />'));
    assert.ok(blade.includes('<x-react-root :page="$page ?? null">'));
    assert.ok(blade.includes('data-theme-select'));
    assert.doesNotMatch(blade, /old\('(password|confirmation|disable_code)'/);
});

test('NFR-THEME-20261025 profile CSS uses semantic pairs, readable controls and bounded reflow', () => {
    const css = read('resources/css/profile.css');
    for (const token of ['surface', 'surface-alt', 'ink', 'muted', 'line', 'accent', 'on-accent']) assert.ok(css.includes(`var(--${token})`));
    assert.doesNotMatch(css, /#[\da-f]{3,8}\b|rgba?\(|hsla?\(/i);
    assert.ok(css.includes('min-height: 44px'));
    assert.ok(css.includes('minmax(0, 1fr)'));
    assert.ok(css.includes('overflow-wrap: anywhere'));
    assert.match(css, /:focus-visible\s*\{[^}]*outline: 2px solid var\(--ink\)/);
    assert.doesNotMatch(css, /animation:|@keyframes|outline:\s*(none|0)/);
});
