// @vitest-environment jsdom
// FR-FE-COMP-20261023: execute the actual after-content ownership/replay gate.
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { initAdminTables } from '../../resources/js/admin-table';

const blade = readFileSync('resources/views/admin/dashboard.blade.php', 'utf8');
const loader = blade.match(/<script type="module" data-admin-dashboard-fallback-loader>([\s\S]*?)<\/script>/)[1];
const handoff = readFileSync('resources/views/components/react-root.blade.php', 'utf8').match(/<script data-frontend-handoff>([\s\S]*?)<\/script>/)[1];
let imports, cleanups;
const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
function boot({ state = 'pending', rootPresent = true, pagePresent = true, runLoader = true } = {}) {
    document.body.innerHTML = `${pagePresent ? '<script type="application/json" data-page="app">{}</script>' : ''}
        <div ${rootPresent ? 'id="app" data-react-fallback' : ''}>
          <section id="panel-user" data-admin-table="user" aria-busy="false"><h2 tabindex="-1">Users</h2>
            <form data-admin-table-form="user" action="/admin#panel-user" method="GET"><input name="uq" value=""><button type="submit">Search</button></form>
            <p data-table-error role="alert" hidden></p>
            <div hidden data-table-enhancement><button type="button" data-table-density="user" aria-pressed="false">Compact rows</button></div>
            <table data-table-body="user" data-density="compact"><tbody><tr><td>Client</td></tr></tbody></table>
          </section>
          <form action="/admin/categories" method="POST"><input name="_token" value="csrf"><input name="cat_title" value=""><button type="submit">Create</button></form>
          <form action="/admin/orders/3" method="POST"><input name="_method" value="PATCH"><button type="submit">Save status</button></form>
        </div>
        <div class="toast" data-flash="Category saved" role="status" aria-live="polite"><p data-toast-message></p></div>`;
    const root = document.getElementById('app');
    if (root) { window.eval(handoff); root.dataset.reactBoot = state; }
    if (runLoader) {
        // Substitute only the dynamic import network boundary and Blade-generated copy.
        const executable = loader.replace(/await import\(@json\([^\n]+\)\);/, 'await window.__adminFallbackImport();')
            .replace(/@json\([^\n]+\)/g, '"Unable to load table enhancements"');
        window.eval(executable);
    }
    return root;
}
function submit(form) {
    return form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: form.querySelector('button[type="submit"]') }));
}
function enhancedSubmits(root) {
    const handled = vi.fn();
    const listener = event => { if (event.target.matches('[data-admin-table-form]')) { event.preventDefault(); handled(event.target); } };
    root.addEventListener('submit', listener);
    cleanups.push(() => root.removeEventListener('submit', listener));
    return handled;
}
beforeEach(() => {
    vi.useFakeTimers(); cleanups = []; window.history.replaceState({}, '', '/admin');
    imports = vi.fn(async () => {}); window.__adminFallbackImport = imports;
});
afterEach(() => {
    cleanups.forEach(cleanup => cleanup()); vi.clearAllTimers(); vi.useRealTimers();
    document.body.innerHTML = ''; delete window.__adminFallbackImport;
});

it('dirty ownership loads once, preserves the input and shows fallback flash', async () => {
    const root = boot(), field = root.querySelector('[name="uq"]'); field.value = 'Unsaved filter';
    field.dispatchEvent(new Event('input', { bubbles: true })); await flush();
    expect(root.dataset.reactBoot).toBe('fallback'); expect(imports).toHaveBeenCalledTimes(1);
    expect(field.value).toBe('Unsaved filter'); expect(document.querySelector('.toast').classList.contains('show')).toBe(true);
    expect(document.querySelector('[data-toast-message]').textContent).toBe('Category saved');
    root.dataset.reactBoot = 'fallback'; await vi.advanceTimersByTimeAsync(11000); await flush(); expect(imports).toHaveBeenCalledTimes(1);
});
it('failed page chunk setting fallback loads legacy without waiting for the deadline', async () => {
    const root = boot(); root.dataset.reactBoot = 'fallback'; await flush();
    expect(imports).toHaveBeenCalledTimes(1); expect(root.querySelector('[data-admin-table-form]').method).toBe('get');
});
it('stalled untouched React chunk becomes permanently fallback at ten seconds', async () => {
    const root = boot(); await vi.advanceTimersByTimeAsync(9999); expect(imports).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(1); await flush();
    expect(root.dataset.reactBoot).toBe('fallback'); expect(imports).toHaveBeenCalledTimes(1);
});
it('claim before the deadline permanently prevents import, replay and duplicate flash', async () => {
    const root = boot(); root.dispatchEvent(new Event('zafira:react-claim')); root.dataset.reactBoot = 'ready';
    const event = new SubmitEvent('submit', { bubbles: true, cancelable: true }); root.querySelector('[data-admin-table-form]').dispatchEvent(event);
    await vi.advanceTimersByTimeAsync(11000); await flush();
    expect(imports).not.toHaveBeenCalled(); expect(event.defaultPrevented).toBe(false); expect(document.querySelector('.toast.show')).toBeNull();
});
it.each(['ready', 'failed'])('late loader on %s root never imports legacy', async state => {
    boot({ state }); await vi.advanceTimersByTimeAsync(11000); expect(imports).not.toHaveBeenCalled(); expect(document.querySelector('.toast.show')).toBeNull();
});
it('removed initial-page data also signifies React ownership', async () => {
    boot({ pagePresent: false }); await vi.advanceTimersByTimeAsync(11000); expect(imports).not.toHaveBeenCalled();
});
it('pending first filter submission is captured and replayed exactly once after enhancement ready', async () => {
    let finish, handled; imports.mockImplementation(() => new Promise(resolve => { finish = () => { handled = enhancedSubmits(document.getElementById('app')); resolve(); }; }));
    const root = boot(), form = root.querySelector('[data-admin-table-form]'); form.querySelector('input').value = 'Silk';
    expect(submit(form)).toBe(false); submit(form); await flush();
    expect(imports).toHaveBeenCalledTimes(1); expect(handled).toBeUndefined();
    finish(); await flush(); expect(handled).toHaveBeenCalledTimes(1); expect(form.querySelector('input').value).toBe('Silk');
    submit(form); expect(handled).toHaveBeenCalledTimes(2); expect(imports).toHaveBeenCalledTimes(1);
});
it('density intent before ready replays once through the actual existing admin-table enhancement', async () => {
    let finish; const root = boot(), button = root.querySelector('[data-table-density]');
    imports.mockImplementation(() => new Promise(resolve => { finish = () => { initAdminTables({ document: root, window }); resolve(); }; }));
    root.dispatchEvent(new Event('pointerdown', { bubbles: true })); await flush();
    button.click(); button.click(); expect(button.getAttribute('aria-pressed')).toBe('false');
    finish(); await flush(); expect(button.getAttribute('aria-pressed')).toBe('false');
    expect(root.querySelector('[data-table-body]').getAttribute('data-density')).toBe('comfortable');
    button.click(); expect(button.getAttribute('aria-pressed')).toBe('true'); expect(imports).toHaveBeenCalledTimes(1);
});
it('mutation submits are never captured or replayed while legacy loads', async () => {
    let finish; imports.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const root = boot(), forms = [...root.querySelectorAll('form[method="POST"]')], submissions = [];
    forms.forEach(form => { const fn = vi.fn(); form.addEventListener('submit', fn); submissions.push(fn); });
    forms.forEach(form => expect(submit(form)).toBe(true)); await flush();
    expect(imports).toHaveBeenCalledTimes(1); finish(); await flush();
    submissions.forEach(fn => expect(fn).toHaveBeenCalledTimes(1));
});
it('failed legacy import releases captured GET natively once and leaves future forms native', async () => {
    let fail; imports.mockImplementation(() => new Promise((_, reject) => { fail = reject; }));
    const root = boot(), form = root.querySelector('[data-admin-table-form]'), native = enhancedSubmits(root);
    submit(form); await flush(); expect(native).not.toHaveBeenCalled(); fail(new Error('chunk unavailable')); await flush();
    expect(native).toHaveBeenCalledTimes(1); expect(root.querySelector('[data-table-error]').hidden).toBe(false);
    submit(form); expect(native).toHaveBeenCalledTimes(2); expect(imports).toHaveBeenCalledTimes(1);
});
it('a detached captured form is not replayed', async () => {
    let finish; imports.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const root = boot(), form = root.querySelector('[data-admin-table-form]'), request = vi.spyOn(form, 'requestSubmit');
    submit(form); await flush(); form.remove(); finish(); await flush(); expect(request).not.toHaveBeenCalled(); request.mockRestore();
});
it('a claim arriving before the import microtask prevents legacy start and queued replay', async () => {
    const root = boot(), form = root.querySelector('[data-admin-table-form]'), request = vi.spyOn(form, 'requestSubmit');
    submit(form); root.dispatchEvent(new Event('zafira:react-claim')); root.dataset.reactBoot = 'ready'; await flush();
    expect(imports).not.toHaveBeenCalled(); expect(request).not.toHaveBeenCalled(); expect(document.querySelector('.toast.show')).toBeNull(); request.mockRestore();
});
it('Blade without a React root never loads an extra app module', async () => {
    boot({ rootPresent: false, pagePresent: false }); await vi.advanceTimersByTimeAsync(11000); expect(imports).not.toHaveBeenCalled();
});
it('without JavaScript the fallback GET and mutation form defaults remain usable', () => {
    const root = boot({ runLoader: false });
    const filter = root.querySelector('[data-admin-table-form]'); expect(filter.method).toBe('get'); expect(filter.getAttribute('action')).toBe('/admin#panel-user');
    expect(submit(filter)).toBe(true); root.querySelectorAll('form[method="POST"]').forEach(form => expect(submit(form)).toBe(true)); expect(imports).not.toHaveBeenCalled();
    expect(blade).toContain('method="GET" action="{{ route(\'admin.dashboard\') }}#panel-user"');
    expect(blade).toContain('method="POST" action="{{ route(\'admin.password.update\') }}"');
});
it('loader is confined to after-content and the Inertia-page branch, retaining the existing flash source', () => {
    const afterContent = blade.slice(blade.indexOf("@section('after-content')"));
    expect(afterContent).toContain('@isset($page)'); expect(afterContent).toContain(loader);
    expect(afterContent).toContain('data-flash="{{ session(\'success\') }}"');
    expect(loader).toContain("Vite::asset('resources/js/app.js')"); expect(loader).not.toContain('initAdminTables(');
});
