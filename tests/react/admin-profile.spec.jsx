// @vitest-environment jsdom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Profile from '../../resources/js/pages/Admin/Profile.jsx';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));
const props = {
    locale: 'id',
    copy: { title: 'Profil admin', navigation: 'Menu profil', identity: 'Detail akun', readonly: 'Identitas hanya dapat dibaca.', name: 'Nama', email: 'Email', role: 'Peran', security: 'Keamanan', totp_active: 'Verifikasi dua langkah aktif', totp_inactive: 'Verifikasi dua langkah belum aktif', totp_enrol: 'Aktifkan verifikasi dua langkah', auth_events: 'Log autentikasi', password_heading: 'Ubah kata sandi', password_hint: 'Minimal 8 karakter.', password_effect: 'Sesi akan dirotasi.', current_password: 'Kata sandi saat ini', new_password: 'Kata sandi baru', confirm_password: 'Konfirmasi', password_submit: 'Simpan kata sandi', theme: 'Tampilan', theme_light: 'Terang', theme_dark: 'Gelap', theme_system: 'Otomatis' },
    identity: { name: 'Nadia Putri', email: 'nadia@example.test', initials: 'NP', role: 'Staf' },
    security: { totpEnabled: false },
    urls: { password: '/admin/password', totpEnrol: '/admin/totp/enrol', authEvents: null },
    errors: {}, status: null,
};
let root; let host;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    document.head.innerHTML = '<meta name="csrf-token" content="fixture-token">';
    document.body.innerHTML = '<div id="app"></div>';
    host = document.getElementById('app'); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); vi.restoreAllMocks(); vi.unstubAllGlobals(); });
async function mount(extra = {}) { await act(async () => root.render(<Profile {...props} {...extra} />)); }

it('FR-PROFILE-20261025 shows read-only identity, real section links and permission-filtered security links', async () => {
    await mount();
    expect(host.querySelector('.profile-initials').textContent).toBe('NP');
    for (const value of Object.values(props.identity)) expect(host.textContent).toContain(value);
    expect(host.querySelector('[name="name"], [name="email"], [name="role"], img')).toBeNull();
    for (const link of host.querySelectorAll('nav a')) expect(host.querySelector(link.getAttribute('href'))).not.toBeNull();
    expect(host.querySelector('a[href="/admin/auth-events"]')).toBeNull();
    expect(host.querySelector('a[href="/admin/totp/enrol"]')).not.toBeNull();
    expect([...host.querySelector('[data-theme-select]').options].map(option => option.value)).toEqual(['system', 'light', 'dark']);
    await mount({ security: { totpEnabled: true }, urls: { ...props.urls, totpEnrol: null, authEvents: '/admin/auth-events' } });
    expect(host.textContent).toContain(props.copy.totp_active);
    expect(host.querySelector('a[href="/admin/totp/enrol"]')).toBeNull();
    expect(host.querySelector('a[href="/admin/auth-events"]')).not.toBeNull();
});

it('NFR-SAFE-20261025 submits only the existing native PATCH with fresh CSRF and DOM-only passwords', async () => {
    const fetch = vi.spyOn(window, 'fetch'); const storage = vi.spyOn(Storage.prototype, 'setItem');
    const push = vi.spyOn(history, 'pushState'); const replace = vi.spyOn(history, 'replaceState');
    await mount({ old: { password: 'never-render-this' }, csrfToken: 'never-render-this' });
    const form = host.querySelector('form');
    expect(form.getAttribute('action')).toBe('/admin/password'); expect(form.method).toBe('post');
    expect(form.elements.namedItem('_method').value).toBe('PATCH');
    const passwords = [...form.querySelectorAll('input[type="password"]')];
    expect(passwords.map(input => input.name)).toEqual(['current_password', 'password', 'password_confirmation']);
    expect(passwords.map(input => input.autocomplete)).toEqual(['current-password', 'new-password', 'new-password']);
    for (const input of passwords) { expect(input.value).toBe(''); input.value = 'DOM-only-value'; }
    document.querySelector('meta').content = 'rotated-token';
    const submit = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(submit));
    expect(submit.defaultPrevented).toBe(false); expect(form.elements.namedItem('_token').value).toBe('rotated-token');
    expect(form.querySelector('button[type="submit"]').disabled).toBe(true);
    const duplicate = new Event('submit', { bubbles: true, cancelable: true });
    await act(async () => form.dispatchEvent(duplicate)); expect(duplicate.defaultPrevented).toBe(true);
    await act(async () => window.dispatchEvent(new Event('pageshow')));
    expect(form.querySelector('button[type="submit"]').disabled).toBe(false);
    expect(host.innerHTML).not.toContain('DOM-only-value'); expect(host.innerHTML).not.toContain('never-render-this');
    for (const spy of [fetch, storage, push, replace]) expect(spy).not.toHaveBeenCalled();
});

it('escapes identity and focuses server validation errors without restoring credentials', async () => {
    const unsafe = '<img src=x onerror=bad()>';
    await mount({ identity: { ...props.identity, name: unsafe }, errors: { current_password: 'Password mismatch' }, status: unsafe });
    expect(host.textContent).toContain(unsafe); expect(host.querySelector('[onerror]')).toBeNull();
    expect(document.activeElement.id).toBe('auth-error-summary');
    expect(host.querySelector('[name="current_password"]').getAttribute('aria-describedby')).toBe('current-password-error');
    expect(host.querySelector('[name="current_password"]').value).toBe('');
});
