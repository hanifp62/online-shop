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
