import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
import { act, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { StorefrontProvider, useStorefront } from '../../resources/js/components/storefront/StorefrontProvider.jsx';

// NFR-THEME-20261025: head ownership and bundle-only fallback have the same observable contract.
const head = readFileSync('resources/views/components/theme-preference.blade.php', 'utf8').match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const props = { locale: 'id', links: { cart: '/cart' }, copy: { cart: { loading: 'Memuat' }, app_js: { bag_empty: 'Kosong' } } };
let dom; let root; let media; let listeners; let rendered;
function Probe() {
    const { theme, setTheme } = useStorefront(); rendered.push(theme);
    return <><output>{theme}</output><select className="zafira-theme-select" value={theme} onChange={event => setTheme(event.target.value)}>{['system', 'light', 'dark'].map(mode => <option key={mode} value={mode}>{mode}</option>)}</select></>;
}
const mount = async () => { await act(async () => root.render(<StrictMode><StorefrontProvider {...props}><Probe /></StorefrontProvider></StrictMode>)); };
const change = async value => { await act(async () => { const select = document.querySelector('select'); select.value = value; select.dispatchEvent(new window.Event('change', { bubbles: true })); }); };
const expectTheme = (setting, resolved) => {
    expect(document.querySelector('output').textContent).toBe(setting);
    expect(document.documentElement.dataset.theme).toBe(resolved);
    expect(document.documentElement.dataset.themeSetting).toBe(setting);
    expect(document.documentElement.dataset.themePreference).toBe(setting);
    expect(document.documentElement.style.colorScheme).toBe(resolved);
};
beforeEach(() => {
    dom = new JSDOM('<!doctype html><html><head></head><body><div id="app"></div></body></html>', { url: 'https://shop.test/', runScripts: 'outside-only' });
    vi.stubGlobal('window', dom.window); vi.stubGlobal('document', dom.window.document); vi.stubGlobal('localStorage', dom.window.localStorage);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ items: [], subtotal: 0 }))));
    listeners = new Set(); rendered = [];
    media = { matches: false, addEventListener: (_, fn) => listeners.add(fn), removeEventListener: (_, fn) => listeners.delete(fn) };
    window.matchMedia = () => media;
    root = createRoot(document.getElementById('app'));
});
afterEach(async () => { await act(async () => root.unmount()); dom.window.close(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

it('never renders system or overwrites a saved dark during bundle-only mount, even under StrictMode', async () => {
    localStorage.setItem('zafira_theme', 'dark');
    const writes = vi.spyOn(window.Storage.prototype, 'setItem');
    await mount(); expectTheme('dark', 'dark'); expect(rendered.every(value => value === 'dark')).toBe(true);
    expect(writes.mock.calls.filter(([key]) => key === 'zafira_theme')).toEqual([]);
    await change('light'); expectTheme('light', 'light'); expect(localStorage.getItem('zafira_theme')).toBe('light');
});
it('fallback migrates legacy dark even when canonical persistence is unavailable', async () => {
    localStorage.setItem('zafira-theme', 'dark');
    vi.spyOn(window.Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('quota'); });
    await mount(); expectTheme('dark', 'dark');
    await change('light'); expectTheme('light', 'light');
});
it('fallback works with blocked storage and cleans OS listeners on unmount', async () => {
    Object.defineProperty(window, 'localStorage', { configurable: true, get() { throw new Error('blocked'); } });
    media.matches = true;
    await mount(); expectTheme('system', 'dark'); expect(listeners.size).toBe(1);
    await change('light'); expectTheme('light', 'light');
    await act(async () => root.unmount()); expect(listeners.size).toBe(0);
    root = createRoot(document.getElementById('app'));
});
it('fallback follows OS, canonical storage, reset and legacy updates without changing explicit preferences', async () => {
    await mount(); expectTheme('system', 'light');
    await act(async () => { media.matches = true; listeners.forEach(fn => fn()); }); expectTheme('system', 'dark');
    await change('light');
    await act(async () => listeners.forEach(fn => fn())); expectTheme('light', 'light');
    await act(async () => {
        localStorage.setItem('zafira_theme', 'dark');
        window.dispatchEvent(new window.StorageEvent('storage', { key: 'zafira_theme', newValue: 'dark', storageArea: localStorage }));
    }); expectTheme('dark', 'dark');
    await act(async () => {
        localStorage.clear();
        window.dispatchEvent(new window.StorageEvent('storage', { key: null, storageArea: localStorage }));
    }); expectTheme('system', 'dark');
    await act(async () => {
        localStorage.setItem('zafira-theme', 'light');
        window.dispatchEvent(new window.StorageEvent('storage', { key: 'zafira-theme', newValue: 'light', storageArea: localStorage }));
    }); expectTheme('light', 'light');
});
it('keeps native account/profile selects synchronized with the persistent Blade shell across page replacements', async () => {
    localStorage.setItem('zafira_theme', 'dark'); window.eval(head);
    const markup = '<select data-theme-select><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>';
    const shell = document.createElement('header'); shell.innerHTML = markup; document.body.prepend(shell);
    const ProfilePreference = ({ page }) => <section key={page}><select data-theme-select defaultValue={document.documentElement.dataset.themePreference || 'system'}>{['system', 'light', 'dark'].map(mode => <option key={mode} value={mode}>{mode}</option>)}</select></section>;
    await act(async () => root.render(<ProfilePreference page="account" />));
    const checkControls = expected => {
        for (const control of document.querySelectorAll('[data-theme-select]')) {
            expect(control.value).toBe(expected);
            for (const option of control.options) expect(option.defaultSelected).toBe(option.selected);
        }
    };
    checkControls('dark');
    await act(async () => {
        const control = shell.querySelector('select'); control.value = 'light';
        control.dispatchEvent(new window.Event('change', { bubbles: true }));
    }); checkControls('light');
    await act(async () => root.render(<ProfilePreference page="admin-profile" />)); checkControls('light');
    await act(async () => {
        const control = document.querySelector('#app select'); control.value = 'system';
        control.dispatchEvent(new window.Event('change', { bubbles: true }));
    }); checkControls('system'); expect(localStorage.getItem('zafira_theme')).toBe('system');
    expect(listeners.size).toBe(1);
});
it('delegates to the head owner, syncs external controls and survives React remount without extra OS listeners or writes', async () => {
    localStorage.setItem('zafira_theme', 'dark'); window.eval(head);
    const api = window.ZafiraTheme; const set = vi.spyOn(api, 'set'); const writes = vi.spyOn(window.Storage.prototype, 'setItem');
    await mount(); expectTheme('dark', 'dark'); expect(rendered.every(value => value === 'dark')).toBe(true);
    expect(listeners.size).toBe(1); expect(set).not.toHaveBeenCalled();
    expect(writes.mock.calls.filter(([key]) => key === 'zafira_theme')).toEqual([]);
    await change('light'); expectTheme('light', 'light'); expect(api.get()).toBe('light');
    await act(async () => api.set('system')); expectTheme('system', 'light');
    await act(async () => { media.matches = true; listeners.forEach(fn => fn()); }); expectTheme('system', 'dark');
    await act(async () => {
        localStorage.setItem('zafira_theme', 'dark');
        window.dispatchEvent(new window.StorageEvent('storage', { key: 'zafira_theme', newValue: 'dark', storageArea: localStorage }));
    }); expectTheme('dark', 'dark');
    await act(async () => root.unmount());
    api.set('light');
    root = createRoot(document.getElementById('app')); await mount(); expectTheme('light', 'light');
    expect(window.ZafiraTheme).toBe(api); expect(listeners.size).toBe(1);
});
