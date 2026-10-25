export const locales = ['id', 'en', 'fr', 'it', 'zh'];
export const themes = ['light', 'dark', 'system'];
export const sanitizeMonogram = value => String(value).replace(/[^a-zA-Z]/g, '').slice(0, 3).toUpperCase();
export function readPreference(storage, key, legacy, allowed, fallback) {
    try {
        const current = storage.getItem(key);
        if (current !== null) return allowed.includes(current) ? current : fallback;
        const old = storage.getItem(legacy);
        if (allowed.includes(old)) { storage.setItem(key, old); return old; }
    } catch { /* Preferences cannot block the storefront. */ }
    return fallback;
}
export function readThemePreference(win = globalThis.window, fallback = 'system') {
    if (win?.ZafiraTheme) return win.ZafiraTheme.get();
    try {
        const current = win.localStorage.getItem('zafira_theme');
        if (current !== null) return themes.includes(current) ? current : 'system';
        const legacy = win.localStorage.getItem('zafira-theme');
        if (themes.includes(legacy)) {
            try { win.localStorage.setItem('zafira_theme', legacy); win.localStorage.removeItem('zafira-theme'); } catch { /* Still use a readable legacy preference if writes fail. */ }
            return legacy;
        }
        return 'system';
    } catch { return fallback; }
}
export function connectThemePreference(onChange, win = window) {
    let setting = readThemePreference(win);
    const changed = event => {
        if (themes.includes(event.detail?.theme)) { setting = event.detail.theme; onChange(setting); }
    };
    win.addEventListener('zafira:theme-changed', changed);
    const disconnect = () => win.removeEventListener('zafira:theme-changed', changed);
    if (win.ZafiraTheme) {
        onChange(win.ZafiraTheme.get());
        return { set: value => win.ZafiraTheme.set(value), dispose: disconnect };
    }
    // Bundle-only consumers have no Blade head. Never persist a default during mount.
    const media = win.matchMedia?.('(prefers-color-scheme: dark)');
    const apply = () => {
        const resolved = setting === 'system' ? media?.matches ? 'dark' : 'light' : setting;
        const root = win.document.documentElement;
        root.dataset.theme = resolved; root.dataset.themeSetting = setting; root.dataset.themePreference = setting;
        root.style.colorScheme = resolved;
        win.dispatchEvent(new win.CustomEvent('zafira:theme-changed', { detail: { theme: setting, effectiveTheme: resolved } }));
    };
    const systemChanged = () => { if (setting === 'system') apply(); };
    const storageChanged = event => {
        if (event.key !== null && event.key !== 'zafira_theme' && event.key !== 'zafira-theme') return;
        try { if (event.storageArea && event.storageArea !== win.localStorage) return; } catch { return; }
        if (event.key === 'zafira-theme') setting = readThemePreference(win, setting);
        else {
            setting = themes.includes(event.newValue) ? event.newValue : 'system';
            if (event.newValue === null) { try { win.localStorage.removeItem('zafira-theme'); } catch {} }
        }
        apply();
    };
    const restored = () => { setting = readThemePreference(win, setting); apply(); };
    if (media?.addEventListener) media.addEventListener('change', systemChanged);
    else media?.addListener?.(systemChanged);
    win.addEventListener('storage', storageChanged);
    win.addEventListener('pageshow', restored);
    apply();
    return {
        set(value) {
            if (!themes.includes(value)) return;
            setting = value;
            try { win.localStorage.setItem('zafira_theme', value); win.localStorage.removeItem('zafira-theme'); } catch { /* Optional device preference. */ }
            apply();
        },
        dispose() {
            disconnect();
            if (media?.removeEventListener) media.removeEventListener('change', systemChanged);
            else media?.removeListener?.(systemChanged);
            win.removeEventListener('storage', storageChanged);
            win.removeEventListener('pageshow', restored);
        },
    };
}
// Aborting a locale fetch does not cancel its server-side session/profile write.
export async function postLocale(url, locale) {
    const response = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({ locale }),
    });
    if (!response.ok) throw new Error('Locale save failed');
}
export function createLocaleWriter({ initial, post, accepted, failed, reload }) {
    let pending = null;
    let running = null;
    let confirmed = initial;
    return function choose(locale) {
        if (!locales.includes(locale)) return Promise.resolve();
        pending = locale;
        if (running) return running;
        running = (async () => {
            let saved = false;
            while (pending) {
                const next = pending; pending = null;
                try { await post(next); confirmed = next; saved = true; accepted(next); }
                catch { if (!pending) failed(confirmed); }
            }
            if (saved) reload();
        })().finally(() => { running = null; });
        return running;
    };
}
