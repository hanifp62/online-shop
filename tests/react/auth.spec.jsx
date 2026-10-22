// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/pages/Auth/Login';
import Register from '../../resources/js/pages/Auth/Register';
import ForgotPassword from '../../resources/js/pages/Auth/ForgotPassword';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));

const common = {
    email: 'Email address', password: 'Password', optional: 'Optional',
    show_password: 'Show password', hide_password: 'Hide password',
    show_password_confirmation: 'Show password confirmation', hide_password_confirmation: 'Hide password confirmation',
    show: 'Show', hide: 'Hide', back_home: 'Back to the Maison', home_label: 'ZAFIRA home',
    visual_label: 'The world of ZAFIRA', image_alt: 'ZAFIRA Haute Couture editorial',
};
const pages = [
    { name: 'Login', Component: Login, action: '/login', blade: 'login', passwords: 1,
        page: { title: 'Sign In — ZAFIRA', heading: 'Welcome back.', intro: 'Sign in to continue.',
            eyebrow: 'Mon Compte', quote_eyebrow: 'Votre Monde Privé', quote_heading: 'Timeless elegance.', quote_body: 'Personal service.',
            submit: 'Sign in to your account', remember: 'Remember me', forgot: 'Forgot your password?', new: 'New to ZAFIRA?', register: 'Create a personal account' } },
    { name: 'Register', Component: Register, action: '/register', blade: 'register', passwords: 2,
        page: { title: 'Create Account — ZAFIRA', heading: 'Join the Maison.', intro: 'Create your personal space.',
            eyebrow: 'Créer un Compte', quote_eyebrow: 'Le Cercle ZAFIRA', quote_heading: 'Haute couture.', quote_body: 'Discover personal selections.',
            full_name: 'Full name', first_name: 'First name', last_name: 'Last name', mobile: 'Mobile number', mobile_placeholder: '08…', address: 'Address line 1',
            password_hint: 'At least 8 characters combining uppercase letters, lowercase letters, numbers, and symbols.', confirm_password: 'Confirm password',
            submit: 'Create personal account', existing: 'Already part of ZAFIRA?', login: 'Sign in here' } },
    { name: 'ForgotPassword', Component: ForgotPassword, action: '/lupa-kata-sandi', blade: 'password-email', passwords: 0,
        page: { title: 'Forgot Password — ZAFIRA', heading: 'Forgot password', intro: 'Enter your registered email.',
            quote_heading: 'Private Access Recovery', quote_body: 'We will send a recovery link.', back: 'Back to Sign In', submit: 'Send recovery link' } },
];

function propsFor(spec, extra = {}) {
    return {
        locale: 'en', copy: { common, page: spec.page, social: { group: 'Sign in with a social account' } },
        assets: { hero: '/prototype/assets/hero_couture.jpg', heroSrcSet: '/prototype/assets/hero_couture-600.webp 600w, /prototype/assets/hero_couture.webp 896w' },
        urls: { home: '/', submit: spec.action, login: '/login', register: '/register', forgotPassword: '/lupa-kata-sandi' },
        old: { email: 'client@example.test', name: 'Ada', first_name: 'Ada', last_name: 'Lovelace', mobile: '0812340000', address1: 'Linen Street' },
        providers: [], errors: {}, status: null, ...extra,
    };
}

function markup(spec, extra = {}) {
    const container = document.createElement('div');
    container.innerHTML = renderToStaticMarkup(<spec.Component {...propsFor(spec, extra)} />);
    return container;
}

let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    document.head.innerHTML = '';
    document.body.innerHTML = '';
});
afterEach(async () => {
    if (root) await act(async () => root.unmount());
    root = null;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function mount(spec, extra = {}) {
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    await act(async () => root.render(<spec.Component {...propsFor(spec, extra)} />));
    return container;
}

function metaToken(value) {
    let meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'csrf-token';
        document.head.append(meta);
    }
    meta.content = value;
}

describe('FR-FE-001/002: native auth markup, not a JSON or HTML bridge', () => {
    it.each(pages)('$name renders the actual editorial shell and ordinary POST form', (spec) => {
        const dom = markup(spec);
        expect(dom.querySelectorAll('main.auth-page')).toHaveLength(1);
        expect(dom.querySelectorAll('h1')).toHaveLength(1);
        expect(dom.querySelector('h1').textContent).toBe(spec.page.heading);
        expect(dom.querySelector('.auth-quote h2').textContent).toBe(spec.page.quote_heading);
        expect(dom.querySelector('.brand-name').textContent).toBe('ZAFIRA');
        expect(dom.querySelector('.brand').getAttribute('href')).toBe('/');
        expect(dom.querySelector('.auth-visual > img').getAttribute('alt')).toBe(common.image_alt);
        const form = dom.querySelector('form');
        expect(form.getAttribute('method')).toBe('POST');
        expect(form.getAttribute('action')).toBe(spec.action);
        expect(form.noValidate).toBe(true);
        expect(form.querySelector('[name="_token"]').type).toBe('hidden');
        expect(form.querySelector('[type="submit"]').textContent).toBe(spec.page.submit);
        expect(dom.querySelectorAll('script, iframe')).toHaveLength(0);
        for (const field of dom.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"])')) {
            expect(dom.querySelector(`label[for="${field.id}"]`)).not.toBeNull();
        }
    });

    it.each(pages.slice(0, 2))('$name keeps responsive image attributes and original brand geometry', (spec) => {
        const dom = markup(spec);
        const image = dom.querySelector('.auth-visual > img');
        expect(image.getAttribute('srcset')).toContain('hero_couture-600.webp 600w');
        expect(image.getAttribute('sizes')).toBe('(max-width: 900px) 100vw, 50vw');
        expect(image.getAttribute('width')).toBe('1400');
        expect(image.getAttribute('height')).toBe('1750');
        const original = document.createElement('div');
        original.innerHTML = readFileSync('resources/views/components/brand-mark.blade.php', 'utf8');
        const geometry = (node) => [...node.querySelectorAll('.brand-emblem polygon, .brand-emblem path')].map((shape) => shape.getAttribute('points') ?? shape.getAttribute('d'));
        expect(geometry(dom)).toEqual(geometry(original));
    });

    it('keeps the registration field, autocomplete, optional and password-strength contracts', () => {
        const dom = markup(pages[1]);
        const names = [...dom.querySelectorAll('input')].map((input) => input.name);
        expect(names).toEqual(['_token', 'name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'password', 'password_confirmation']);
        for (const [name, autocomplete] of Object.entries({ name: 'name', first_name: 'given-name', last_name: 'family-name', email: 'email', mobile: 'tel', address1: 'street-address', password: 'new-password', password_confirmation: 'new-password' })) {
            expect(dom.querySelector(`[name="${name}"]`).getAttribute('autocomplete')).toBe(autocomplete);
        }
        for (const name of ['first_name', 'last_name', 'mobile', 'address1']) {
            expect(dom.querySelector(`[name="${name}"]`).required).toBe(false);
            expect(dom.querySelector(`label[for="${name}"] span`).textContent).toBe('Optional');
        }
        for (const name of ['password', 'password_confirmation']) {
            expect(dom.querySelector(`[name="${name}"]`).minLength).toBe(8);
            expect(dom.querySelector(`[name="${name}"]`).required).toBe(true);
        }
        expect(dom.querySelector('#password-hint').textContent).toBe(pages[1].page.password_hint);
    });

    it('keeps login remember-me unchecked, password autocomplete, and full-document navigation', () => {
        const dom = markup(pages[0], { old: { email: 'client@example.test', remember: true } });
        expect(dom.querySelector('[name="remember"]').checked).toBe(false);
        expect(dom.querySelector('[name="remember"]').value).toBe('1');
        expect(dom.querySelector('[name="password"]').getAttribute('autocomplete')).toBe('current-password');
        expect(dom.querySelector('.auth-options a').getAttribute('href')).toBe('/lupa-kata-sandi');
        expect(dom.querySelector('.auth-switch a').getAttribute('href')).toBe('/register');
        expect(markup(pages[1]).querySelector('.auth-switch a').getAttribute('href')).toBe('/login');
        expect(markup(pages[2]).querySelector('.auth-back').getAttribute('href')).toBe('/login');
    });

    it('renders only supplied social links, with localized labels and unchanged provider icons', () => {
        const providers = [
            { id: 'google', url: '/auth/google/redirect', label: 'Continuer avec Google' },
            { id: 'apple', url: '/auth/apple/redirect', label: 'Continuer avec Apple' },
        ];
        const paths = [...readFileSync('resources/views/auth/_social.blade.php', 'utf8').matchAll(/<path d="([^"]+)"/g)].map((match) => match[1]);
        for (const spec of pages.slice(0, 2)) {
            expect(markup(spec).querySelector('.social-auth')).toBeNull();
            const dom = markup(spec, { providers });
            expect(dom.querySelectorAll('.social-auth a')).toHaveLength(2);
            providers.forEach((provider, index) => {
                const link = dom.querySelector(`[data-social-provider="${provider.id}"]`);
                expect(link.getAttribute('href')).toBe(provider.url);
                expect(link.textContent).toBe(provider.label);
                expect(link.querySelector('path').getAttribute('d')).toBe(paths[index]);
            });
        }
        expect(markup(pages[2], { providers }).querySelector('.social-auth')).toBeNull();
    });

    it.each(pages)('$name escapes old input, errors and status without repopulating credentials', (spec) => {
        const unsafe = '"><img src=x onerror="alert(1)">';
        const dom = markup(spec, {
            old: { email: unsafe, name: unsafe, password: 'synthetic-password', password_confirmation: 'synthetic-confirmation', token: 'synthetic-reset-token' },
            errors: { email: unsafe, password: 'Invalid password' }, status: '<script>unsafe</script>',
        });
        expect(dom.querySelector('[name="email"]').value).toBe(unsafe);
        expect(dom.querySelector('#email-error').textContent).toBe(unsafe);
        expect(dom.querySelector('[role="alert"]').textContent).toContain('Invalid password');
        expect(dom.querySelector('[role="status"]').textContent).toBe('<script>unsafe</script>');
        expect(dom.querySelector('script, img[src="x"]')).toBeNull();
        expect(dom.innerHTML).not.toContain('synthetic-password');
        expect(dom.innerHTML).not.toContain('synthetic-confirmation');
        expect(dom.innerHTML).not.toContain('synthetic-reset-token');
        for (const field of dom.querySelectorAll('input[type="password"]')) {
            expect(field.hasAttribute('value')).toBe(false);
        }
        expect(dom.querySelector('[name="email"]').getAttribute('aria-invalid')).toBe('true');
        expect(dom.querySelector('[name="email"]').getAttribute('aria-describedby')).toBe('email-error');
    });

    it('does not mark clean fields invalid or render an empty summary', () => {
        const dom = markup(pages[0]);
        expect(dom.querySelector('[role="alert"]')).toBeNull();
        expect(dom.querySelector('[name="email"]').hasAttribute('aria-invalid')).toBe(false);
        expect(dom.querySelector('[name="password"]').hasAttribute('aria-describedby')).toBe(false);
    });

    it.each(pages)('$name retains a usable Blade fallback and isolates the legacy entry', (spec) => {
        const source = readFileSync(`resources/views/auth/${spec.blade}.blade.php`, 'utf8');
        expect(source).toMatch(/@isset\(\$page\)\s*@viteReactRefresh\s*@vite\(\['resources\/css\/app.css', 'resources\/js\/inertia.jsx'\]\)\s*@else/);
        expect(source).toMatch(/@else[\s\S]*@vite\(\['resources\/css\/app.css', 'resources\/js\/app.js'\]\)\s*@endisset/);
        expect(source.indexOf('<x-react-root')).toBeGreaterThan(source.indexOf('<body>'));
        expect(source.indexOf('</x-react-root>')).toBeLessThan(source.indexOf('</body>'));
        expect(source).toContain('@csrf');
        expect(source).toContain('<meta name="csrf-token"');
        const passwords = source.match(/<input\b[^>]*type="password"[^>]*>/g) ?? [];
        expect(passwords).toHaveLength(spec.passwords);
        passwords.forEach((input) => expect(input).not.toMatch(/\bvalue=/));
    });
});

describe('NFR-FE-001/002: local DOM interactions, no remembered form or XHR auth', () => {
    it.each(pages)('$name focuses errors rather than the success status and applies locale', async (spec) => {
        const dom = await mount(spec, { locale: 'fr', errors: { email: 'Email invalide' }, status: 'Message de statut' });
        expect(document.documentElement.lang).toBe('fr');
        expect(document.activeElement).toBe(dom.querySelector('#auth-error-summary'));
        expect(document.activeElement.tabIndex).toBe(-1);
        expect(dom.querySelector('#email-error').textContent).toBe('Email invalide');
    });

    it('toggles both passwords independently without changing or storing their values', async () => {
        const store = vi.spyOn(Storage.prototype, 'setItem');
        const push = vi.spyOn(history, 'pushState');
        const replace = vi.spyOn(history, 'replaceState');
        const dom = await mount(pages[1]);
        const password = dom.querySelector('#password');
        const confirmation = dom.querySelector('#password_confirmation');
        password.value = 'synthetic-local-password';
        confirmation.value = 'synthetic-local-confirmation';
        const toggles = dom.querySelectorAll('.password-toggle');
        await act(async () => toggles[0].click());
        expect(password.type).toBe('text');
        expect(confirmation.type).toBe('password');
        expect(toggles[0].getAttribute('aria-label')).toBe('Hide password');
        expect(toggles[0].getAttribute('aria-pressed')).toBe('true');
        await act(async () => toggles[1].click());
        expect(toggles[1].getAttribute('aria-label')).toBe('Hide password confirmation');
        await act(async () => toggles[0].click());
        expect(password.type).toBe('password');
        expect(toggles[0].getAttribute('aria-pressed')).toBe('false');
        expect(toggles[0].textContent).toBe('Show');
        expect(password.value).toBe('synthetic-local-password');
        expect(confirmation.value).toBe('synthetic-local-confirmation');
        expect(store).not.toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
        expect(replace).not.toHaveBeenCalled();
    });

    it.each(pages)('$name reads fresh CSRF from meta, keeps native submit and restores busy on Back', async (spec) => {
        metaToken('synthetic-dom-token');
        const fetch = vi.fn();
        vi.stubGlobal('fetch', fetch);
        const dom = await mount(spec, { csrfToken: 'must-not-use-props-token' });
        const form = dom.querySelector('form');
        expect(form.elements.namedItem('_token').value).toBe('synthetic-dom-token');
        metaToken('synthetic-rotated-token');
        const submission = new Event('submit', { bubbles: true, cancelable: true });
        await act(async () => form.dispatchEvent(submission));
        expect(submission.defaultPrevented).toBe(false);
        expect(new FormData(form).get('_token')).toBe('synthetic-rotated-token');
        expect(new FormData(form).get('email')).toBe('client@example.test');
        const button = form.querySelector('[type="submit"]');
        expect(button.disabled).toBe(true);
        expect(button.getAttribute('aria-busy')).toBe('true');
        expect(button.textContent).toBe(spec.page.submit);
        const duplicate = new Event('submit', { bubbles: true, cancelable: true });
        await act(async () => form.dispatchEvent(duplicate));
        expect(duplicate.defaultPrevented).toBe(true);
        expect(fetch).not.toHaveBeenCalled();
        await act(async () => window.dispatchEvent(new Event('pageshow')));
        expect(button.disabled).toBe(false);
        expect(button.hasAttribute('aria-busy')).toBe(false);
        const retry = new Event('submit', { bubbles: true, cancelable: true });
        await act(async () => form.dispatchEvent(retry));
        expect(retry.defaultPrevented).toBe(false);
    });

    it('can render static markup without a document and never accepts a CSRF prop', () => {
        vi.stubGlobal('document', undefined);
        const html = renderToStaticMarkup(<Login {...propsFor(pages[0], { csrfToken: 'synthetic-prop-token' })} />);
        expect(html).toContain('name="_token" value=""');
        expect(html).not.toContain('synthetic-prop-token');
    });
});
