// @vitest-environment jsdom
// NFR-FE-004: progressive enhancement must not erase an active fallback interaction.
import { readFileSync } from 'node:fs';
import { act } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const boot = vi.hoisted(() => ({ fail: false, setup: null }));
vi.mock('@inertiajs/react', () => ({
    createInertiaApp: vi.fn(async (options) => {
        if (boot.fail) throw new Error('Page chunk unavailable');
        boot.setup = options.setup;
    }),
}));

function fallback() {
    document.body.innerHTML = `<script type="application/json" data-page="app">{"props":{}}</script>
        <div id="app" data-react-fallback><form data-auth-form>
          <input name="email" value=""><div class="password-wrap"><input name="password" type="password">
          <button type="button" data-password-toggle data-i18n-show="Show" data-i18n-hide="Hide"
            data-i18n-aria-show="Show password" data-i18n-aria-hide="Hide password">Show</button></div>
          <button type="submit">Submit</button></form></div>`;
    const template = readFileSync('resources/views/components/react-root.blade.php', 'utf8');
    const script = template.match(/<script data-frontend-handoff>([\s\S]*?)<\/script>/)?.[1] ?? '';
    window.eval(script);
    return document.getElementById('app');
}

beforeEach(() => {
    vi.resetModules();
    boot.fail = false;
    boot.setup = null;
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
});
afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
});

it('keeps password controls working if the React page chunk fails', async () => {
    const root = fallback();
    boot.fail = true;
    await import('../../resources/js/inertia.jsx');
    await Promise.resolve();
    root.querySelector('[data-password-toggle]').click();
    expect(root.querySelector('[name="password"]').type).toBe('text');
    expect(root.querySelector('[data-password-toggle]').getAttribute('aria-pressed')).toBe('true');
});

it('keeps autofilled values AND fallback controls without taking over', async () => {
    const root = fallback();
    root.querySelector('[name="email"]').value = 'fixture@example.test';
    await import('../../resources/js/inertia.jsx');
    expect(boot.setup).toBeNull();
    root.querySelector('[data-password-toggle]').click();
    expect(root.querySelector('[name="email"]').value).toBe('fixture@example.test');
    expect(root.querySelector('[name="password"]').type).toBe('text');
});

it('does not replace a fallback after a click even without changed input', async () => {
    const root = fallback();
    root.querySelector('[data-password-toggle]').click();
    await import('../../resources/js/inertia.jsx');
    expect(boot.setup).toBeNull();
    expect(root.dataset.reactBoot).toBe('fallback');
});

it('checks again after resolution so a late edit cannot be erased', async () => {
    const root = fallback();
    await import('../../resources/js/inertia.jsx');
    root.querySelector('[name="email"]').value = 'late@example.test';
    boot.setup({ el: root, App: () => <p>React</p>, props: {} });
    expect(root.querySelector('[name="email"]').value).toBe('late@example.test');
    expect(root.dataset.reactBoot).toBe('fallback');
});

it('keeps keyboard focus on a fallback form while its chunk resolves', async () => {
    const root = fallback();
    await import('../../resources/js/inertia.jsx');
    const field = root.querySelector('[name="email"]');
    field.focus();
    boot.setup({ el: root, App: () => <p>React</p>, props: {} });
    expect(document.activeElement).toBe(field);
    expect(root.dataset.reactBoot).toBe('fallback');
});

it('claims untouched fallback once, detaches legacy ownership, marks ready only after mount', async () => {
    const root = fallback();
    const claim = vi.fn();
    root.addEventListener('zafira:react-claim', claim);
    await import('../../resources/js/inertia.jsx');
    expect(root.dataset.reactBoot).not.toBe('ready');
    let mounted;
    await act(async () => {
        mounted = boot.setup({ el: root, App: () => <button type="button">Native React</button>, props: {} });
    });
    expect(claim).toHaveBeenCalledTimes(1);
    expect(root.dataset.reactBoot).toBe('ready');
    expect(document.querySelector('script[data-page="app"]')).toBeNull();
    root.querySelector('button').click();
    expect(root.dataset.reactBoot).toBe('ready');
    if (mounted?.unmount) await act(async () => mounted.unmount());
});

it('can hand off server autofocus to the same native autofocus without treating it as user input', async () => {
    const root = fallback();
    const field = root.querySelector('[name="email"]');
    field.setAttribute('autofocus', '');
    field.focus();
    await import('../../resources/js/inertia.jsx');
    expect(boot.setup).not.toBeNull();
    let mounted;
    await act(async () => { mounted = boot.setup({ el: root, App: () => <input name="email" autoFocus />, props: {} }); });
    expect(root.dataset.reactBoot).toBe('ready');
    expect(document.activeElement).toBe(root.querySelector('input'));
    await act(async () => mounted.unmount());
});

it('never treats a user interaction with an autofocus field as an automatic focus handoff', async () => {
    const root = fallback();
    const field = root.querySelector('[name="email"]');
    field.setAttribute('autofocus', '');
    field.focus();
    field.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    await import('../../resources/js/inertia.jsx');
    expect(boot.setup).toBeNull();
    expect(document.activeElement).toBe(field);
});

it('malformed initial data leaves the usable fallback intact', async () => {
    const root = fallback();
    document.querySelector('script[data-page="app"]').textContent = 'invalid-json';
    await expect(import('../../resources/js/inertia.jsx')).resolves.toBeDefined();
    root.querySelector('[data-password-toggle]').click();
    expect(root.querySelector('[name="password"]').type).toBe('text');
    expect(root.dataset.reactBoot).toBe('fallback');
});
