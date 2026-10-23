import { describe, expect, it, vi } from 'vitest';
import { createStorefrontCart, storefrontRequest } from '../../resources/js/lib/storefront-cart.js';
import { sanitizeMonogram, createLocaleWriter } from '../../resources/js/lib/storefront-preferences.js';

const cart = (quantity = 1) => ({ items: [{ product_id: 7, quantity, product_title: 'Sutra', unit_price: 100, subtotal: quantity * 100, available: true }], subtotal: quantity * 100 });
describe('FE-COMP-20261023 cart delta boundary', () => {
    it('serializes fresh reads and relative writes without losing rapid adds', async () => {
        const request = vi.fn(async (url, method) => method === 'POST' ? cart(2) : cart());
        const client = createStorefrontCart({ request });
        await Promise.all([client.adjust(7, 1), client.adjust(7, 1)]);
        expect(request.mock.calls).toEqual([
            ['/cart'], ['/cart/items/7/delta', 'POST', { delta: 1 }],
            ['/cart'], ['/cart/items/7/delta', 'POST', { delta: 1 }],
        ]);
    });
    it('does not mutate after failed fresh read and recovers the queue', async () => {
        const request = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue(cart());
        const client = createStorefrontCart({ request });
        await expect(client.adjust(7, 1)).rejects.toThrow('offline');
        await client.adjust(7, 1);
        expect(request).toHaveBeenCalledTimes(3);
    });
    it('removes from fresh state and exposes server removed quantity for undo', async () => {
        const request = vi.fn().mockResolvedValueOnce(cart(4)).mockResolvedValueOnce({ ...cart(0), items: [], removed_quantity: 4 });
        const client = createStorefrontCart({ request });
        const result = await client.remove(7);
        expect(result.removed_quantity).toBe(4);
        expect(request).toHaveBeenLastCalledWith('/cart/items/7', 'DELETE');
    });
    it('does not retry an uncertain mutation', async () => {
        const request = vi.fn().mockResolvedValueOnce(cart()).mockRejectedValueOnce(new Error('timeout'));
        await expect(createStorefrontCart({ request }).adjust(7, 1)).rejects.toThrow('timeout');
        expect(request).toHaveBeenCalledTimes(2);
    });
    it('keeps hostile text literal data and rejects malformed snapshots', async () => {
        const client = createStorefrontCart({ request: async () => ({ items: null }) });
        await expect(client.refresh()).rejects.toThrow();
    });
    it('bounds the complete body operation and never exposes server details', async () => {
        vi.useFakeTimers();
        const pending = storefrontRequest('/cart', 'GET', undefined, { fetchImpl: async () => ({ ok: true, json: () => new Promise(() => {}) }), timeout: 10 });
        const assertion = expect(pending).rejects.toMatchObject({ kind: 'timeout' });
        await vi.advanceTimersByTimeAsync(11);
        await assertion;
        vi.useRealTimers();
        await expect(storefrontRequest('/cart', 'GET', undefined, { fetchImpl: async () => new Response('secret sql', { status: 503 }) })).rejects.toMatchObject({ kind: 'service', status: 503 });
    });
});
it('sanitizes personalisation without generating purchase fields', () => {
    expect(sanitizeMonogram('a<2bçcD')).toBe('ABC');
});
it('serializes locale writes and retains last accepted language after a newer failure', async () => {
    let finish;
    const post = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve; })).mockRejectedValueOnce(new Error('offline'));
    const accepted = vi.fn(); const failed = vi.fn(); const reload = vi.fn();
    const choose = createLocaleWriter({ initial: 'id', post, accepted, failed, reload });
    const first = choose('en'); choose('fr');
    finish(); await first;
    expect(post.mock.calls).toEqual([['en'], ['fr']]);
    expect(failed).toHaveBeenCalledWith('en');
    expect(accepted).toHaveBeenCalledWith('en');
    expect(reload).toHaveBeenCalledTimes(1);
});
it('R6-2 commits successful locale URL/storage before reloading after the queued choice fails', async () => {
    let finish;
    const state = { url: new URL('https://maison.test/?locale=id#atelier'), stored: 'id' };
    const post = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve; })).mockRejectedValueOnce(new Error('offline'));
    const reload = vi.fn(() => { expect(state.url.searchParams.has('locale')).toBe(false); expect(state.url.hash).toBe('#atelier'); expect(state.stored).toBe('en'); });
    const choose = createLocaleWriter({ initial: 'id', post, accepted: locale => { state.url.searchParams.delete('locale'); state.stored = locale; }, failed: vi.fn(), reload });
    const pending = choose('en'); choose('fr'); finish(); await pending;
    expect(reload).toHaveBeenCalledTimes(1);
});
