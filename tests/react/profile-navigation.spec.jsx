// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Account from '../../resources/js/pages/Account/Show.jsx';
import { Header, Profile } from '../../resources/js/components/storefront/Chrome.jsx';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));
let storefront;
vi.mock('../../resources/js/components/storefront/StorefrontProvider.jsx', () => ({ useStorefront: () => storefront }));
function section(source, name) {
    const start = source.indexOf(`    '${name}' => [`);
    const end = source.indexOf('\n    ],', start);
    return Object.fromEntries([...source.slice(start, end).matchAll(/'([^']+)'\s*=>\s*'((?:\\.|[^'])*)'/g)].map(match => [match[1], match[2].replace(/\\'/g, "'")]));
}
function accountCopy(locale) {
    const source = readFileSync(`lang/${locale}/account_ui.php`, 'utf8');
    return { ...Object.fromEntries(['common', 'show', 'profile', 'social', 'totp', 'privacy', 'history'].map(name => [name, section(source, name)])), greeting: 'Bonjour, Nadia.', unverified: 'Verify email', deletePhrase: 'HAPUS AKUN SAYA' };
}
const urls = { home: '/', logout: '/logout', update: '/akun', resend: '/email/verifikasi-ulang', export: '/akun/ekspor', destroy: '/akun', totpEnrol: '/akun/verifikasi-dua-langkah', totpDestroy: '/akun/verifikasi-dua-langkah' };
let root; let host;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    document.head.innerHTML = '<meta name="csrf-token" content="fixture-token">'; document.body.innerHTML = '<div id="app"></div>';
    host = document.getElementById('app'); root = createRoot(host);
    const shop = readFileSync('lang/en/shop_ui.php', 'utf8');
    storefront = { copy: { welcome: section(shop, 'welcome'), header: section(shop, 'header') }, user: null,
        links: { account: '/akun', login: '/login', logout: '/logout', home: '/', locales: [] }, dialog: { type: 'profile' },
        open: vi.fn(), close: vi.fn(), themes: ['light', 'dark', 'system'], theme: 'system', setTheme: vi.fn(),
        locale: 'en', locales: ['en'], chooseLocale: vi.fn(), cart: { items: [] }, wishlist: [], showFavorites: vi.fn() };
});
afterEach(async () => { await act(async () => root.unmount()); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

it.each(['id', 'en', 'fr', 'it', 'zh'])('FR-PROFILE-20261025 customer section navigation and theme choices are localized (%s)', async locale => {
    const copy = accountCopy(locale);
    await act(async () => root.render(<Account locale={locale} copy={copy} assets={{ hero: '/hero.jpg' }} urls={urls}
        profile={{ name: 'Nadia Putri', email: 'nadia@example.test' }} verified hasPassword providers={[]} totp={{ enabled: false }} />));
    expect(host.querySelector('.profile-initials').textContent).toBe('NP');
    expect(host.querySelector('.profile-identity').textContent).toContain('nadia@example.test');
    const nav = host.querySelector('nav.profile-section-nav');
    expect(nav.getAttribute('aria-label')).toBe(copy.show.navigation);
    expect([...nav.querySelectorAll('a')].map(link => link.getAttribute('href'))).toEqual(['#account-details', '#account-security', '#account-privacy', '#account-history']);
    for (const link of nav.querySelectorAll('a')) {
        expect(link.textContent.length).toBeGreaterThan(0);
        const target = host.querySelector(link.getAttribute('href'));
        expect(target).not.toBeNull(); expect(target.querySelector('h2').textContent.length).toBeGreaterThan(0);
    }
    expect(host.querySelector('a[href="/akun/ekspor"]')).not.toBeNull();
    expect(host.querySelector('[data-theme-select]').labels[0].textContent).toBe(copy.show.theme);
    expect(host.querySelector('#account-history').textContent).toContain(copy.history.empty);
    expect(host.querySelector('#account-details form').getAttribute('action')).toBe('/akun');
});

it('guests see login, not an authenticated profile or invented order history', async () => {
    await act(async () => root.render(<Profile />));
    expect(host.querySelector('a[href="/login"]').textContent).toBe(storefront.copy.header.login_aria);
    expect(host.querySelector('a[href^="/akun"]')).toBeNull();
    expect(host.querySelector('img')).toBeNull();
});

it('signed-in drawer leads with identity and real account/concierge destinations, preserves provider actions', async () => {
    storefront.user = { name: '  Nadia  Putri  ', email: 'nadia@example.test' };
    await act(async () => root.render(<Profile />));
    expect(host.querySelector('.profile-vic-avatar').textContent).toBe('NP');
    expect(host.querySelector('a[href="/akun"]').textContent).toBe(storefront.copy.header.auth_profile);
    const history = host.querySelector('a[href="/akun#account-history"]');
    expect(history.textContent).toBe('Concierge & appointment history');
    const fitting = [...host.querySelectorAll('button')].find(button => button.textContent === storefront.copy.welcome.profile_action_fitting);
    await act(async () => fitting.click()); expect(storefront.open).toHaveBeenCalledWith('concierge');
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    expect(storefront.close).toHaveBeenCalled();
});

it('drawer settings keep the existing provider and logout submits natively with fresh CSRF', async () => {
    storefront.user = { name: 'Nadia Putri', email: 'nadia@example.test' };
    await act(async () => root.render(<Profile />));
    const dark = host.querySelector(`button[aria-label="${storefront.copy.welcome.theme_dark}"]`);
    await act(async () => dark.click()); expect(storefront.setTheme).toHaveBeenCalledWith('dark');
    expect(dark.textContent).toBe(storefront.copy.welcome.theme_dark);
    const logout = host.querySelector('form[action="/logout"]');
    expect(logout.method).toBe('post');
    document.querySelector('meta').content = 'rotated-token';
    const submit = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => logout.dispatchEvent(submit));
    expect(submit.defaultPrevented).toBe(false); expect(logout.elements.namedItem('_token').value).toBe('rotated-token');
});

it('home header distinguishes guest login from signed-in profile access', async () => {
    await act(async () => root.render(<Header home />));
    expect(host.querySelector('button[aria-label="Sign in to your account"]')).not.toBeNull();
    storefront.user = { name: 'Nadia Putri' };
    await act(async () => root.render(<Header home />));
    const profile = host.querySelector(`button[aria-label="${storefront.copy.header.auth_account_aria.replace(':name', 'Nadia Putri')}"]`);
    expect(profile).not.toBeNull();
    await act(async () => profile.click()); expect(storefront.open).toHaveBeenCalledWith('profile');
});
