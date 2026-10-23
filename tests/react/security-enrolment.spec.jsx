// @vitest-environment jsdom
// FE-COMP-20261023 SECURITY: FR-FE-001/002, NFR-FE-001/002.
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import CustomerEnrol from '../../resources/js/pages/Account/TotpEnrol';
import AdminEnrol from '../../resources/js/pages/Admin/TotpEnrol';
import CustomerRecovery from '../../resources/js/pages/Account/TotpRecovery';
import AdminRecovery from '../../resources/js/pages/Admin/TotpRecovery';
import Unsubscribe from '../../resources/js/pages/Account/CartReminderUnsubscribe';

const context = vi.hoisted(() => ({ flash: {} }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, usePage: () => context }));
const props = {
    locale: 'id', copy: { page: { title: 'Security', heading: 'Security', intro: 'Instructions', submit: 'Confirm', cancel: 'Cancel', code: 'Code', qr_alt: 'QR', setup_key: 'Key', uri: 'URI', manual: 'Manual', saved: 'Saved', codes: 'Codes', shop: 'Shop' }, common: { admin_access: 'Account', logout: 'Logout', home: 'Home' } },
    urls: { submit: '/submit?signature=test', exit: '/exit', logout: '/logout', home: '/', shop: '/shop' },
    errors: {}, old: { code: 'must-not-restore', secret: 'must-not-restore' },
};
let root;
function mount(Component, extra = {}) {
    act(() => root.render(<Component {...props} {...extra} />));
}
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    document.head.innerHTML = '<meta name="csrf-token" content="test-csrf">';
    document.body.innerHTML = '<div id="root"></div>';
    root = createRoot(document.getElementById('root'));
    context.flash = { totpMaterial: { secret: 'test-only-key', uri: 'otpauth://test-only', qr: 'data:image/svg+xml;base64,PHN2Zy8+', codes: ['test-only-recovery'] } };
});
afterEach(() => { act(() => root.unmount()); vi.unstubAllGlobals(); });
it.each([CustomerEnrol, AdminEnrol])('keeps native six-digit enrol POST, DOM CSRF, and no old credentials', (Component) => {
    mount(Component);
    const field = document.querySelector('[name="code"]');
    expect(field.value === '').toBe(true);
    expect(field.pattern).toBe('\\d{6}');
    expect(field.maxLength).toBe(6);
    expect(field.inputMode).toBe('numeric');
    expect(document.querySelector('label[for="code"]') !== null).toBe(true);
    field.value = '001234';
    document.querySelector('meta').content = 'rotated-test-csrf';
    const event = new Event('submit', { bubbles: true, cancelable: true });
    act(() => field.form.dispatchEvent(event));
    expect(event.defaultPrevented).toBe(false);
    expect(field.form.method).toBe('post');
    expect(new FormData(field.form).get('_token') === 'rotated-test-csrf').toBe(true);
    expect(new FormData(field.form).get('code') === '001234').toBe(true);
    expect(document.body.textContent.includes('must-not-restore')).toBe(false);
    expect(document.querySelector('img').getAttribute('src').startsWith('data:image/svg+xml;base64,')).toBe(true);
});
it.each([CustomerEnrol, AdminEnrol, CustomerRecovery, AdminRecovery])('does not recover material from props/history and conceals on leave', (Component) => {
    context.flash = {};
    mount(Component, { secret: 'not-flash', codes: ['not-flash'] });
    expect(document.body.textContent.includes('not-flash')).toBe(false);
    expect(document.querySelector('img, ul')).toBeNull();
    context.flash = { totpMaterial: { secret: 'test-only-key', qr: 'data:image/svg+xml;base64,PHN2Zy8+', codes: ['test-only-recovery'] } };
    mount(Component);
    expect(document.querySelector('img, ul') !== null).toBe(true);
    act(() => window.dispatchEvent(new Event('zafira:private-leave')));
    expect(document.querySelector('img, ul')).toBeNull();
});
it('preserves admin manual URI and logout, customer cancel, recovery exit and signed unsubscribe', () => {
    mount(AdminEnrol);
    expect(document.querySelector('#setup_uri').readOnly).toBe(true);
    expect(document.querySelector('form[action="/logout"]').method).toBe('post');
    mount(CustomerEnrol);
    expect(document.querySelector('a').getAttribute('href')).toBe('/exit');
    mount(AdminRecovery);
    expect(document.querySelector('a').textContent).toBe('Saved');
    mount(Unsubscribe);
    expect(document.querySelector('form').getAttribute('action')).toBe('/submit?signature=test');
    expect(document.querySelectorAll('h1')).toHaveLength(1);
    expect(document.querySelector('a[href="/shop"]') !== null).toBe(true);
    expect(document.querySelector('[name="_token"]').value === 'test-csrf').toBe(true);
});
it('renders POST completion with status feedback and no repeat submission form', () => {
    mount(Unsubscribe, { done: true, copy: { ...props.copy, page: { ...props.copy.page, heading: 'Unsubscribed', intro: 'Reminders stopped' } }, urls: { ...props.urls, submit: null } });
    expect(document.querySelector('h1').textContent).toBe('Unsubscribed');
    expect(document.querySelector('[role="status"]').textContent).toBe('Reminders stopped');
    expect(document.querySelector('form')).toBeNull();
    expect(document.querySelector('a[href="/shop"]') !== null).toBe(true);
    expect(document.querySelector('a[href="/"]') !== null).toBe(true);
});
it('connects validation feedback and refuses non-data QR sources', () => {
    context.flash.totpMaterial.qr = 'https://example.test/untrusted.svg';
    mount(CustomerEnrol, { errors: { code: 'Invalid code' } });
    expect(document.querySelector('img')).toBeNull();
    expect(document.querySelector('[role="alert"]').textContent).toBe('Invalid code');
});
