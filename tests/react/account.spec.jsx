// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Show from '../../resources/js/pages/Account/Show';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));
const props = {
    locale: 'fr', assets: { hero: '/prototype/assets/hero_couture.jpg' },
    copy: {
        common: { brand_world: 'Brand world', editorial: 'Editorial ZAFIRA', home: 'Home' },
        show: { title: 'Account — ZAFIRA', salon: 'Salon', intro: 'Private salon', back: 'Back', logout: 'Logout', contact_intro: 'Contact details', resend: 'Resend' },
        profile: { name: 'Name', first_name: 'First name', last_name: 'Last name', mobile: 'Mobile', mobile_placeholder: '08…', address1: 'Address', address2: 'City', email: 'Email', reminders: 'Reminders', save: 'Save' },
        social: { heading: 'Connected accounts', unlink: 'Unlink' },
        totp: { heading: 'Security', intro: 'Enable security', enable: 'Enable', disable_heading: 'Disable security', code: 'Code', disable: 'Disable' },
        privacy: { heading: 'Privacy', intro: 'Your data', export: 'Export', delete: 'Delete account', password: 'Password', confirm: 'Confirm deletion?', submit: 'Delete' },
        history: { heading: 'History', empty: 'No history' },
        greeting: 'Bonjour Ada', unverified: 'Verify ada@example.test', deletePhrase: 'Type HAPUS AKUN SAYA',
    },
    urls: { home: '/', logout: '/logout', update: '/akun', resend: '/email/verifikasi-ulang', export: '/akun/ekspor', destroy: '/akun', totpEnrol: '/akun/totp', totpDestroy: '/akun/totp' },
    profile: { name: 'Ada', first_name: 'Ada', last_name: 'Lovelace', mobile: '08123', address1: 'Linen', address2: 'Paris', email: 'ada@example.test', cart_reminder_opt_in: true },
    verified: false, hasPassword: true, providers: [], totp: { enabled: false }, history: [], errors: {}, status: null,
};
let root;
beforeEach(() => { vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true); document.head.innerHTML = '<meta name="csrf-token" content="dom-token">'; document.body.innerHTML = ''; });
afterEach(async () => { if (root) await act(async () => root.unmount()); root = null; vi.restoreAllMocks(); vi.unstubAllGlobals(); });
function markup(extra = {}) { const div = document.createElement('div'); div.innerHTML = renderToStaticMarkup(<Show {...props} {...extra} />); return div; }
async function mount(extra = {}) { const div = document.createElement('div'); document.body.append(div); root = createRoot(div); await act(async () => root.render(<Show {...props} {...extra} />)); return div; }
const formAt = (dom, action, method) => [...dom.querySelectorAll('form')].find((form) => form.getAttribute('action') === action && (form.querySelector('[name="_method"]')?.value ?? 'POST') === method);

it('FR-FE-001/002 renders the original brand, editorial shell, profile and full-document links', () => {
    const dom = markup();
    expect(dom.querySelector('h1').textContent).toBe('Bonjour Ada');
    expect(dom.querySelector('.auth-card').style.maxWidth).toBe('580px');
    const image = dom.querySelector('.auth-visual img');
    expect([image.getAttribute('src'), image.width, image.height, image.getAttribute('fetchPriority')]).toEqual([props.assets.hero, 1400, 1750, 'high']);
    const brand = readFileSync('resources/views/components/brand-mark.blade.php', 'utf8');
    for (const shape of dom.querySelectorAll('.brand-emblem polygon, .brand-emblem path')) expect(brand).toContain(shape.getAttribute('points') ?? shape.getAttribute('d'));
    const form = formAt(dom, '/akun', 'PATCH');
    expect(form.method).toBe('post');
    expect(form.noValidate).toBe(true);
    for (const [name, value] of Object.entries(props.profile)) {
        const input = dom.querySelector(`#${name}`);
        if (name === 'cart_reminder_opt_in') expect(input.checked).toBe(true);
        else expect(input.value).toBe(value);
    }
    expect(dom.querySelector('#email').disabled).toBe(true);
    expect(dom.querySelector('#email').hasAttribute('name')).toBe(false);
    expect([...form.querySelectorAll('[name="cart_reminder_opt_in"]')].map((el) => el.value)).toEqual(['0', '1']);
    expect([...form.querySelectorAll('[autocomplete]')].map((el) => el.autocomplete)).toEqual(['name', 'given-name', 'family-name', 'tel', 'street-address', 'address-level2']);
    for (const url of ['/', '/akun/ekspor', '/akun/totp']) expect(dom.querySelector(`a[href="${url}"]`)).not.toBeNull();
    expect(dom.querySelector('[data-inertia], [data-prefetch]')).toBeNull();
    expect(formAt(dom, '/logout', 'POST')).not.toBeUndefined();
    expect(formAt(dom, '/email/verifikasi-ulang', 'POST')).not.toBeUndefined();
});

it('renders linked Apple/Google, optional Google link, TOTP count and disable form without credential values', () => {
    const dom = markup({ verified: true, providers: [
        { id: 'apple', linked: true, label: 'Apple linked', confirm: 'Unlink Apple?', url: '/social/apple/unlink' },
        { id: 'google', linked: false, label: 'Link Google', url: '/social/google/link' },
    ], totp: { enabled: true, active: 'Active 22 Oct 2026 · 8 codes' }, errors: { disable_code: 'Invalid code' }, old: { disable_code: '123456' } });
    expect(formAt(dom, '/email/verifikasi-ulang', 'POST')).toBeUndefined();
    expect(formAt(dom, '/social/apple/unlink', 'DELETE')).not.toBeUndefined();
    expect(dom.querySelector('a[href="/social/google/link"]').textContent).toBe('Link Google');
    const input = dom.querySelector('#disable_code');
    expect([input.value, input.pattern, input.maxLength, input.autocomplete]).toEqual(['', '\\d{6}', 6, 'one-time-code']);
    expect(input.closest('details').open).toBe(true);
    expect(dom.textContent).toContain('Active 22 Oct 2026 · 8 codes');
    expect(dom.querySelector('a[href="/akun/totp"]')).toBeNull();
});

it.each([true, false])('keeps deletion credentials DOM-only, confirmation cancellable, hasPassword=%s', async (hasPassword) => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    const dom = await mount({ hasPassword, old: { password: 'secret-marker', confirmation: 'secret-marker' } });
    const form = formAt(dom, '/akun', 'DELETE');
    const input = form.querySelector('[name="password"], [name="confirmation"]');
    expect(input.value).toBe('');
    expect(input.name).toBe(hasPassword ? 'password' : 'confirmation');
    const canceled = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(canceled));
    expect(canceled.defaultPrevented).toBe(true);
    expect(confirm).toHaveBeenCalledWith('Confirm deletion?');
    confirm.mockReturnValue(true);
    document.querySelector('meta').content = 'fresh-token';
    const allowed = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(allowed));
    expect(allowed.defaultPrevented).toBe(false);
    expect(form.elements.namedItem('_token').value).toBe('fresh-token');
});

it('focuses escaped errors, reveals failed deletion, retains old profile input and locale', async () => {
    const unsafe = '<img src=x onerror=alert(1)>';
    const dom = await mount({ profile: { ...props.profile, name: unsafe }, errors: { name: unsafe, password: 'Wrong password' }, status: unsafe });
    expect(document.activeElement).toBe(dom.querySelector('#auth-error-summary'));
    expect(document.documentElement.lang).toBe('fr');
    expect(dom.querySelector('#name').value).toBe(unsafe);
    expect(dom.querySelector('#name').getAttribute('aria-describedby')).toBe('name-error');
    expect(dom.querySelector('#delete_password').closest('details').open).toBe(true);
    expect(dom.querySelector('[onerror]')).toBeNull();
    expect(dom.querySelector('#delete_password').value).toBe('');
});

it('posts native PATCH with fresh DOM CSRF, no XHR/history/storage or credential prop', async () => {
    const fetch = vi.spyOn(window, 'fetch');
    const storage = vi.spyOn(Storage.prototype, 'setItem');
    const push = vi.spyOn(window.history, 'pushState');
    const dom = await mount({ csrfToken: 'prop-token' });
    const form = formAt(dom, '/akun', 'PATCH');
    document.querySelector('meta').content = 'rotated-token';
    const event = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(event));
    expect(event.defaultPrevented).toBe(false);
    expect(form.elements.namedItem('_token').value).toBe('rotated-token');
    expect(form.querySelector('[type="submit"]').disabled).toBe(true);
    const duplicate = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(duplicate));
    expect(duplicate.defaultPrevented).toBe(true);
    await act(async () => window.dispatchEvent(new Event('pageshow')));
    expect(form.querySelector('[type="submit"]').disabled).toBe(false);
    const retry = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(retry));
    expect(retry.defaultPrevented).toBe(false);
    expect(dom.innerHTML).not.toContain('prop-token');
    expect(fetch).not.toHaveBeenCalled(); expect(storage).not.toHaveBeenCalled(); expect(push).not.toHaveBeenCalled();
});

it('escapes multiline concierge history and renders localized status/schedule and empty state', () => {
    expect(markup().textContent).toContain('No history');
    const dom = markup({ history: [{ service: '<Couture>', status: 'confirmed', statusLabel: 'Confirmé', schedule: '22 Oct 2026 10:00', submitted: 'Submitted today', notes: '<script>bad</script>\nSecond line' }] });
    expect(dom.querySelector('.badge-confirmed').textContent).toBe('Confirmé');
    expect(dom.textContent).toContain('22 Oct 2026 10:00 · Submitted today');
    expect(dom.textContent).toContain('<script>bad</script>\nSecond line');
    expect(dom.querySelector('script')).toBeNull();
    expect(dom.querySelector('[style*="white-space"]').style.whiteSpace).toBe('pre-wrap');
});

it.each(['input', 'submit'])('account fallback guardian retains ownership after %s before takeover', (type) => {
    const fallback = markup();
    fallback.id = 'app';
    document.body.append(fallback);
    const guardian = readFileSync('resources/views/components/react-root.blade.php', 'utf8').match(/<script data-frontend-handoff>([\s\S]*?)<\/script>/)[1];
    window.eval(guardian);
    const form = formAt(fallback, '/akun', 'PATCH');
    const field = fallback.querySelector('#name');
    if (type === 'input') field.value = 'Unsubmitted profile';
    (type === 'input' ? field : form).dispatchEvent(new Event(type, { bubbles: true, cancelable: true }));
    expect(fallback.dataset.reactBoot).toBe('fallback');
    expect(field.value).toBe(type === 'input' ? 'Unsubmitted profile' : 'Ada');
    // Abort the inline listeners exactly as a successful ownership claim does.
    fallback.dispatchEvent(new Event('zafira:react-claim'));
});

it('keeps the real Blade fallback guarded and whole-document private lifecycle in both modes', () => {
    const blade = readFileSync('resources/views/account/show.blade.php', 'utf8');
    expect(blade).toContain('<x-private-page-lifecycle />');
    expect(blade).toContain('<x-react-root :page="$page ?? null">');
    expect(blade.indexOf('<x-react-root')).toBeLessThan(blade.indexOf('<main'));
    expect(blade.indexOf('</x-react-root>')).toBeGreaterThan(blade.indexOf('class="toast"'));
    expect(blade).toContain('@isset($page)');
    expect(blade).toContain("'resources/js/inertia.jsx'");
    expect(blade).toContain("'resources/js/app.js'");
    for (const name of ['name', 'first_name', 'last_name', 'mobile', 'address1', 'address2']) expect(blade).toContain(`old('${name}', $user->${name})`);
    expect(blade).not.toContain("old('password'");
    expect(blade).not.toContain("old('disable_code'");
});
