// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const paths = ['resources/views/welcome.blade.php', 'resources/views/products/show.blade.php'];
let imports;
function boot(path, state = 'pending') {
    document.body.innerHTML = `<script type="application/json" data-page="app">{}</script><div id="app" data-react-boot="${state}"><button type="button">Cart</button><input value=""></div>`;
    const root = document.getElementById('app');
    // Execute the actual page-local ownership gate, substituting only the network loader and Blade URLs.
    let script = readFileSync(path, 'utf8').match(/<script type="module" data-storefront-fallback-loader>([\s\S]*?)<\/script>/)[1];
    script = script.replace(/await new Promise\(\(resolve, reject\) => \{[\s\S]*?document.head.appendChild\(script\);\n            \}\)\.catch\(\(\) => \{\}\);/, 'await window.__core();');
    script = script.replace(/await import\(@json\([^\n]+\)\);/, 'await window.__fallbackImport();');
    script = script.replace(/@json\([^\n]+\)/g, '"fixture"');
    window.eval(`{ ${script} }`);
    return root;
}
const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
beforeEach(() => {
    vi.useFakeTimers(); imports = vi.fn(async () => {}); window.__fallbackImport = imports; window.__core = vi.fn(async () => {});
});
afterEach(() => { vi.clearAllTimers(); vi.useRealTimers(); document.body.innerHTML = ''; });
for (const path of paths) {
    it(`${path}: a claimed root never loads the legacy DOM or translation engine`, async () => {
        const root = boot(path); root.dispatchEvent(new Event('zafira:react-claim'));
        root.dataset.reactBoot = 'ready'; await vi.advanceTimersByTimeAsync(11000);
        root.querySelector('button').click(); await flush();
        expect(imports).not.toHaveBeenCalled(); expect(window.__core).not.toHaveBeenCalled();
    });
    it(`${path}: dirty fallback loads once and retains entered values`, async () => {
        const root = boot(path); root.querySelector('input').value = 'late edit';
        root.dataset.reactBoot = 'fallback'; await flush();
        expect(imports).toHaveBeenCalledTimes(1);
        expect(root.querySelector('input').value).toBe('late edit');
        root.querySelector('button').click(); await flush(); expect(imports).toHaveBeenCalledTimes(1);
    });
    it(`${path}: stalled chunks hand the untouched root to fallback`, async () => {
        const root = boot(path); await vi.advanceTimersByTimeAsync(10001); await flush();
        expect(root.dataset.reactBoot).toBe('fallback'); expect(imports).toHaveBeenCalledTimes(1);
    });
    it(`${path}: R6-4 pointerdown starts loading before the first click, then replays that intent exactly once`, async () => {
        const root = boot(path); const button = root.querySelector('button');
        let finish; const handled = vi.fn();
        imports.mockImplementation(() => new Promise(resolve => { finish = () => { button.addEventListener('click', handled); resolve(); }; }));
        root.addEventListener('pointerdown', () => { root.dataset.reactBoot = 'fallback'; }, { capture: true });
        button.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true })); await flush();
        expect(imports).toHaveBeenCalledTimes(1);
        button.click(); button.click(); await flush(); expect(handled).not.toHaveBeenCalled();
        finish(); await flush(); expect(handled).toHaveBeenCalledTimes(1);
        button.click(); expect(handled).toHaveBeenCalledTimes(2);
    });
    it(`${path}: R6-4 inline and submit controls are never replayed`, async () => {
        const root = boot(path); const inline = root.querySelector('button'); inline.setAttribute('onclick', '');
        const inlineAction = vi.fn(); inline.addEventListener('click', inlineAction);
        const form = document.createElement('form'); const submit = document.createElement('button'); submit.type = 'submit'; form.appendChild(submit); root.appendChild(form);
        const nativeSubmit = vi.fn(event => event.preventDefault()); form.addEventListener('submit', nativeSubmit);
        let finish; imports.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
        root.dataset.reactBoot = 'fallback'; await flush(); inline.click(); submit.click(); finish(); await flush();
        expect(inlineAction).toHaveBeenCalledTimes(1); expect(nativeSubmit).toHaveBeenCalledTimes(1);
    });
    it(`${path}: loader arriving after React committed cannot intercept native clicks`, async () => {
        const root = boot(path, 'ready'); const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        root.querySelector('button').dispatchEvent(event); await flush();
        expect(event.defaultPrevented).toBe(false); expect(imports).not.toHaveBeenCalled();
    });
}
it('templates retain fallback, metadata and branch-specific scripts without an HTML bridge', () => {
    for (const path of paths) {
        const blade = readFileSync(path, 'utf8');
        expect(blade).toContain('<x-react-root :page="$page ?? null">');
        expect(blade).toContain('application/ld+json'); expect(blade).toContain('rel="canonical"');
        expect(blade).toContain("@vite('resources/js/inertia.jsx')");
    }
    const native = ['Layout', 'Chrome', 'Products', 'Forms', 'Primitives', 'StorefrontProvider', 'CartDrawer'].map(file => readFileSync(`resources/js/components/storefront/${file}.jsx`, 'utf8')).join('\n');
    expect(native).not.toMatch(/dangerouslySetInnerHTML|innerHTML|MutationObserver|@inertiajs\/react|initAdminTables/);
    expect(native).not.toMatch(/\brawHtml\b/);
});
