// @vitest-environment jsdom
// NFR-FE-001: BFCache must not reveal an old privileged document after logout.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { expect, it, vi } from 'vitest';

it('conceals the entire private document and requests history invalidation before leaving', () => {
    const source = readFileSync('resources/views/components/private-page-lifecycle.blade.php', 'utf8');
    const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];
    const listeners = {};
    const document = { documentElement: { style: {} } };
    const dispatchEvent = vi.fn();
    const reload = vi.fn();
    vm.runInNewContext(script, { document, Event, window: { addEventListener: (name, fn) => { listeners[name] = fn; }, dispatchEvent, location: { reload } } });
    listeners.pageshow({ persisted: false });
    expect(reload).not.toHaveBeenCalled();
    listeners.pagehide();
    expect(document.documentElement.style.visibility).toBe('hidden');
    expect(dispatchEvent.mock.calls[0][0].type).toBe('zafira:private-leave');
    listeners.pageshow({ persisted: true });
    expect(reload).toHaveBeenCalledTimes(1);
    expect(document.documentElement.style.visibility).toBe('hidden');
});
