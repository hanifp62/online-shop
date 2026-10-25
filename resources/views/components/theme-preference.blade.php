@once
<script data-theme-preference-runtime>
    (() => {
        if (window.ZafiraTheme) { window.ZafiraTheme.sync(); return; }
        const root = document.documentElement;
        const key = 'zafira_theme';
        const legacy = 'zafira-theme';
        const valid = value => ['light', 'dark', 'system'].includes(value);
        const media = window.matchMedia?.('(prefers-color-scheme: dark)');
        const save = value => {
            try { localStorage.setItem(key, value); localStorage.removeItem(legacy); } catch { /* Device preferences are optional. */ }
        };
        const read = (fallback = 'system') => {
            try {
                const current = localStorage.getItem(key);
                if (current !== null) return valid(current) ? current : 'system';
                const old = localStorage.getItem(legacy);
                if (valid(old)) { save(old); return old; }
                return 'system';
            } catch { return fallback; }
        };
        let setting = read();
        const sync = () => {
            document.querySelectorAll('select[data-theme-select], select.zafira-theme-select').forEach(select => {
                // Programmatic preference sync is not an edited Blade form during React handoff.
                for (const option of select.options) {
                    const selected = option.value === setting;
                    if (option.defaultSelected !== selected) option.defaultSelected = selected;
                }
                if (select.value !== setting) select.value = setting;
            });
            document.querySelectorAll('[data-set-theme]').forEach(button => {
                const active = button.dataset.setTheme === setting;
                button.classList.toggle('active', active);
                button.setAttribute(button.getAttribute('role') === 'radio' ? 'aria-checked' : 'aria-pressed', String(active));
            });
        };
        const apply = () => {
            const resolved = setting === 'system' ? media?.matches ? 'dark' : 'light' : setting;
            const changed = root.dataset.theme !== resolved || root.dataset.themePreference !== setting;
            root.dataset.theme = resolved;
            root.dataset.themeSetting = setting;
            root.dataset.themePreference = setting;
            root.style.colorScheme = resolved;
            sync();
            if (changed) window.dispatchEvent(new CustomEvent('zafira:theme-changed', { detail: { theme: setting, effectiveTheme: resolved } }));
        };
        const set = value => {
            if (!valid(value)) return;
            if (setting === value) { sync(); return; }
            setting = value;
            save(setting);
            apply();
        };
        window.ZafiraTheme = { get: () => setting, set, sync };
        apply();
        // Run before fallback loaders can intercept a click while their bundle is unavailable.
        document.addEventListener('change', event => {
            if (event.target.matches?.('select[data-theme-select], select.zafira-theme-select')) set(event.target.value);
        }, true);
        document.addEventListener('click', event => {
            const button = event.target.closest?.('[data-set-theme]');
            if (button) { event.preventDefault(); set(button.dataset.setTheme); }
        }, true);
        const systemChanged = () => { if (setting === 'system') apply(); };
        if (media?.addEventListener) media.addEventListener('change', systemChanged);
        else media?.addListener?.(systemChanged);
        window.addEventListener('storage', event => {
            if (event.key !== null && event.key !== key && event.key !== legacy) return;
            try { if (event.storageArea && event.storageArea !== localStorage) return; } catch { return; }
            if (event.key === legacy) setting = read(setting);
            else {
                setting = valid(event.newValue) ? event.newValue : 'system';
                // A reset must not resurrect an obsolete legacy value on the next navigation.
                if (event.newValue === null) { try { localStorage.removeItem(legacy); } catch {} }
            }
            apply();
        });
        window.addEventListener('pageshow', () => { setting = read(setting); apply(); });
        document.addEventListener('DOMContentLoaded', sync, { once: true });
        // Includes parser-created controls and replacements from native Inertia page mounts.
        if (window.MutationObserver) new MutationObserver(records => {
            if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1))) sync();
        }).observe(root, { childList: true, subtree: true });
    })();
</script>
@endonce
