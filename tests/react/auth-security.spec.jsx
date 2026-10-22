// @vitest-environment jsdom
// FR-FE-001/002, NFR-FE-001/002: native forms, secret isolation, renderer parity.
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/pages/Admin/Login';
import AdminTotp from '../../resources/js/pages/Admin/TotpChallenge';
import CustomerTotp from '../../resources/js/pages/Auth/TotpChallenge';
import Reset from '../../resources/js/pages/Auth/ResetPassword';

const context = vi.hoisted(() => ({ flash: { resetToken: 'synthetic-flash-token' } }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, usePage: () => context }));
const copy = {
    common: { email: 'Email', password: 'Password', admin_access: 'Admin', logout: 'Logout', back_maison: 'Maison' },
    page: { title: 'ZAFIRA', heading: 'Verify', intro: 'Continue securely', eyebrow: 'Security', code: 'Code', submit: 'Continue',
        help: 'Use recovery', recovery_help: 'Use recovery', recovery_code: 'Recovery code', recovery_submit: 'Recover', back: 'Back to login',
        new_password: 'New password', confirm_password: 'Confirm password', visual_label: 'Maison', image_alt: 'Couture', home_label: 'Home' },
};
const props = { locale: 'id', copy, assets: { hero: '/hero.jpg' }, urls: { home: '/', submit: '/verify', recovery: '/recover', login: '/login', logout: '/logout' }, old: { email: 'client@example.test', password: 'must-not-render', code: '654321', recovery_code: 'never-render' }, errors: {} };
function markup(Component, extra = {}) {
    const el = document.createElement('div');
    el.innerHTML = renderToStaticMarkup(<Component {...props} {...extra} />);
    return el;
}
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    document.head.innerHTML = '<meta name="csrf-token" content="synthetic-csrf">';
    context.flash = { resetToken: 'synthetic-flash-token' };
});
afterEach(() => { if (root) act(() => root.unmount()); root = null; document.body.innerHTML = ''; vi.unstubAllGlobals(); });

describe('native auth security slice', () => {
    it.each([Login, AdminTotp, CustomerTotp, Reset])('uses document POST and CSRF without restoring secret input', (Component) => {
        const el = markup(Component);
        for (const form of el.querySelectorAll('form')) {
            expect(form.getAttribute('method')).toBe('POST');
            expect(form.querySelector('[name="_token"]').value).toBe('synthetic-csrf');
        }
        expect(el.innerHTML).not.toContain('must-not-render');
        expect(el.innerHTML).not.toContain('654321');
        expect(el.innerHTML).not.toContain('never-render');
        for (const field of el.querySelectorAll('input:not([type="hidden"])')) {
            expect(el.querySelector(`label[for="${field.id}"]`)).not.toBeNull();
        }
        expect(el.querySelectorAll('h1')).toHaveLength(1);
    });

    it.each([AdminTotp, CustomerTotp])('keeps six-digit strings and separate recovery controls', (Component) => {
        const el = markup(Component);
        const code = el.querySelector('[name="code"]');
        expect(code.type).toBe('text');
        expect(code.pattern).toBe('\\d{6}');
        expect(code.maxLength).toBe(6);
        expect(code.inputMode).toBe('numeric');
        expect(code.autocomplete).toBe('one-time-code');
        code.value = '001234';
        expect(new FormData(code.form).get('code')).toBe('001234');
        const recovery = el.querySelector('details [name="recovery_code"]');
        expect(recovery.autocomplete).toBe('off');
        expect(recovery.form.getAttribute('action')).toBe('/recover');
        expect(new FormData(recovery.form).has('code')).toBe(false);
    });

    it('keeps admin logout a native POST and customer exit a document link', () => {
        expect(markup(AdminTotp).querySelector('form[action="/logout"] button').textContent).toBe('Logout');
        const exit = markup(CustomerTotp).querySelector('a[href="/login"]');
        expect(exit.textContent).toBe('Back to login');
        expect(exit.hasAttribute('data-inertia')).toBe(false);
        expect(markup(Login).querySelector('[name="remember"]')).toBeNull();
    });

    it('reads reset token exclusively from flash, including absent flash history restore', () => {
        expect(markup(Reset, { resetToken: 'wrong-regular-prop' }).querySelector('[name="token"]').value).toBe('synthetic-flash-token');
        context.flash = {};
        expect(markup(Reset, { resetToken: 'wrong-regular-prop' }).querySelector('[name="token"]').value).toBe('');
        const el = markup(Reset);
        expect(el.querySelector('[name="email"]').value).toBe('client@example.test');
        expect([...el.querySelectorAll('[type="password"]')].map((field) => field.autocomplete)).toEqual(['new-password', 'new-password']);
    });

    it('does not cancel native submit, refreshes CSRF, blocks duplicate primary submits, and resets on pageshow', () => {
        document.body.innerHTML = '<div id="test-root"></div>';
        root = createRoot(document.getElementById('test-root'));
        act(() => root.render(<AdminTotp {...props} />));
        const form = document.querySelector('form');
        form.elements.code.value = '001234';
        document.querySelector('meta').content = 'rotated-csrf';
        const first = new Event('submit', { bubbles: true, cancelable: true });
        act(() => form.dispatchEvent(first));
        expect(first.defaultPrevented).toBe(false);
        expect(new FormData(form).get('code')).toBe('001234');
        expect(new FormData(form).get('_token')).toBe('rotated-csrf');
        const second = new Event('submit', { bubbles: true, cancelable: true });
        act(() => form.dispatchEvent(second));
        expect(second.defaultPrevented).toBe(true);
        act(() => window.dispatchEvent(new Event('pageshow')));
        expect(form.querySelector('button').disabled).toBe(false);
    });

    it('focuses escaped error feedback and associates field errors', () => {
        document.body.innerHTML = '<div id="test-root"></div>';
        root = createRoot(document.getElementById('test-root'));
        act(() => root.render(<CustomerTotp {...props} errors={{ code: '<unsafe>Invalid code</unsafe>' }} />));
        const summary = document.querySelector('[role="alert"]');
        expect(document.activeElement).toBe(summary);
        expect(summary.querySelector('unsafe')).toBeNull();
        const code = document.querySelector('[name="code"]');
        expect(code.getAttribute('aria-invalid')).toBe('true');
        expect(document.getElementById(code.getAttribute('aria-describedby')).textContent).toContain('Invalid code');
        expect(document.documentElement.lang).toBe('id');
    });

    it.each(['admin/login', 'admin/totp', 'auth/totp', 'auth/password-reset'])('keeps whole-document private lifecycle outside %s root', (name) => {
        const source = readFileSync(`resources/views/${name}.blade.php`, 'utf8');
        expect(source).toContain('<x-react-root :page="$page ?? null">');
        expect(source.indexOf('<x-private-page-lifecycle />')).toBeGreaterThan(source.indexOf('</x-react-root>'));
        expect(source).toContain('@viteReactRefresh');
        expect(source).toContain('<meta name="csrf-token"');
        expect(source).toContain('@csrf');
    });

    it('does not use remembered state, SPA submission, or prefetch in owned pages', () => {
        for (const path of ['Admin/Login', 'Admin/TotpChallenge', 'Auth/TotpChallenge', 'Auth/ResetPassword']) {
            const source = readFileSync(`resources/js/pages/${path}.jsx`, 'utf8');
            expect(source).not.toMatch(/useRemember|useForm|router\.|prefetch|<Link\b|dangerouslySetInnerHTML/);
        }
    });
});
