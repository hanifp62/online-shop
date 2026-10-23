// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const blade = readFileSync('resources/views/shop/index.blade.php', 'utf8');
const inline = blade.match(/<script>\n([\s\S]*?)<\/script>/)[1];
const ownership = inline.slice(0, inline.indexOf('    const api = window.shopRequest;')) + 'window.__fallbackStarts();\n})();';
const flush = async () => { for (let i = 0; i < 5; i++) await Promise.resolve(); };
let starts;
function boot(state = 'pending') {
    document.body.innerHTML = `<div id="app" data-react-boot="${state}"><input value=""><button>Checkout</button></div>`;
    window.eval(ownership);
    return document.getElementById('app');
}
beforeEach(() => { vi.useFakeTimers(); starts = vi.fn(); window.__fallbackStarts = starts; });
afterEach(() => { vi.clearAllTimers(); vi.useRealTimers(); document.body.innerHTML = ''; });
it('pending entry and chunks do not start a second cart owner; successful claim cancels fallback deadline', async () => {
    const root = boot();
    expect(starts).not.toHaveBeenCalled();
    root.dispatchEvent(new Event('zafira:react-claim'));
    root.dataset.reactBoot = 'ready';
    await vi.advanceTimersByTimeAsync(11000); await flush();
    expect(starts).not.toHaveBeenCalled();
});
it('dirty form or failed entry starts the real Blade owner exactly once without erasing drafts', async () => {
    const root = boot();
    root.querySelector('input').value = 'Private draft';
    root.dataset.reactBoot = 'fallback'; await flush();
    expect(starts).toHaveBeenCalledTimes(1);
    expect(root.querySelector('input').value).toBe('Private draft');
    await vi.advanceTimersByTimeAsync(11000);
    expect(starts).toHaveBeenCalledTimes(1);
});
it('stalled entry or lazy chunk yields untouched root to Blade after ten seconds', async () => {
    const root = boot(); await vi.advanceTimersByTimeAsync(10001); await flush();
    expect(root.dataset.reactBoot).toBe('fallback');
    expect(starts).toHaveBeenCalledTimes(1);
});
it('late fallback code does not touch a ready or failed React tree; rollback starts immediately', () => {
    boot('ready'); boot('failed'); expect(starts).not.toHaveBeenCalled();
    boot('fallback'); expect(starts).toHaveBeenCalledTimes(1);
});
it('a root without lifecycle markers does not suppress the native fallback', () => {
    document.body.innerHTML = '<div id="app"><input value="draft"></div>';
    window.eval(ownership);
    expect(starts).toHaveBeenCalledTimes(1);
    expect(document.querySelector('input').value).toBe('draft');
});
it('fallback markup and native component remain separate with no HTML injection bridge or storage', () => {
    expect(blade).toContain('<x-react-root :page="$page ?? null">');
    expect(blade).toContain("@vite('resources/js/inertia.jsx')");
    const jsx = readFileSync('resources/js/pages/Shop/Index.jsx', 'utf8');
    expect(jsx).not.toMatch(/dangerouslySetInnerHTML|innerHTML|@inertiajs\/react|localStorage|sessionStorage/);
    expect(jsx).toContain('formNoValidate={Boolean(pending)}');
});
