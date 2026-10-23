// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Shop from '../../resources/js/pages/Shop/Index.jsx';

const item = { product_id: 7, product_title: '<img onerror=alert(1)> Sutra', unit_price: 100, quantity: 1, subtotal: 100, available: true };
const cart = { items: [item], subtotal: 100 };
const address = { id: 3, label: 'Rumah', recipient_name: 'Ada', phone: '0812', line1: 'Jalan Sutra', city: 'Jakarta', province: 'DKI', postal_code: '12345', is_default: true };
const order = { id: 9, total: 100, status: 'pending', items: [item] };
const urls = Object.fromEntries(['home', 'catalog', 'account', 'login', 'cart', 'cartStore', 'cartItems', 'cartClear', 'addresses', 'addressStore', 'checkout', 'quote', 'orders', 'track', 'reviews', 'favorites', 'products'].map(key => [key, '/' + key]));
const copy = { loading: 'Loading', heading: 'Belanja', clear_bag: 'Clear bag', add_address: 'Add address', label: 'Label', recipient: 'Recipient', recipient_phone: 'Phone', phone: 'Phone', street: 'Street', detail: 'Detail', city: 'City', province: 'Province', postal: 'Postal', saved_address: 'Address', voucher: 'Voucher', full_name: 'Name', email: 'Email', guest_button: 'Guest checkout', confirm_order: 'Checkout', clear_token: 'Clear token', track_label: 'Token', track_button: 'Track', js: { shipping: 'Shipping :amount', estimate_total: 'Estimated :amount', checkout_pending: 'Uncertain checkout', order_title: 'Order #:id', order_line: ':status :amount', order_created: 'Created #:id :amount', update: 'Update', remove: 'Remove', retry: 'Retry', cart_failed: 'Cart failed', cart_empty: 'Cart empty', remove_address: 'Delete address', send_review: 'Send review', cancel_order: 'Cancel', remove_favorite: 'Remove favorite', next: 'Next', prev: 'Previous', review_sent: 'Review sent', rating: 'Rating', review: 'Review', qty_aria: 'Quantity :title' } };
let root;
let host;
let calls;
let implementation;
let cartValue;
let orderPage;
const flush = async () => { await act(async () => { await new Promise(resolve => setTimeout(resolve, 0)); }); };
const submit = async selector => { await act(async () => { host.querySelector(selector).dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); }); await flush(); };
const click = async element => { await act(async () => element.click()); await flush(); };
const set = (name, value, form = '#guest-checkout-form') => { host.querySelector(`${form} [name="${name}"]`).value = value; };
async function mount(loggedIn = false) {
    await act(async () => root.render(<Shop {...{ loggedIn, copy, urls }} requestCopy={{ generic: 'Failure' }} statuses={{ pending: 'Pending', unknown: 'Unknown' }} year="2026" request={async (url, method = 'GET', body) => {
        calls.push({ url, method, body });
        if (implementation) { const result = await implementation(url, method, body); if (result !== undefined) return result; }
        if (url === urls.cart) return cartValue;
        if (url === urls.addresses) return { addresses: [address] };
        if (url.startsWith(urls.orders + '?')) return orderPage;
        if (url === urls.favorites) return { favorites: [] };
        return {};
    }} />));
    await flush();
}
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
    calls = []; implementation = null; cartValue = cart;
    orderPage = { data: [], current_page: 1, last_page: 1, total: 0 };
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); });

for (const loggedIn of [false, true]) describe(loggedIn ? 'account' : 'guest', () => {
    it('captures form before disable; retries original frozen payload despite invalid edits and empty cart', async () => {
        let attempts = 0;
        implementation = async url => { if (url === urls.checkout) { if (++attempts === 1) throw new Error('Lost'); return { order, tracking_token: 'a'.repeat(64) }; } };
        await mount(loggedIn);
        const form = loggedIn ? '#checkout-form' : '#guest-checkout-form';
        if (!loggedIn) { set('customer_name', 'Élodie'); set('customer_email', 'private@example.test'); set('line1', 'Original'); }
        else set('voucher_code', ' SAVE ', form);
        await submit(form);
        expect(host.querySelector(form + ' button').formNoValidate).toBe(true);
        expect(host.querySelector('[name="quantity"]').disabled).toBe(true);
        expect([...host.querySelectorAll('#alamat button')].find(node => node.textContent === 'Delete address')?.disabled ?? true).toBe(true);
        const first = calls.find(call => call.url === urls.checkout).body;
        if (loggedIn) { expect(first).toMatchObject({ address_id: 3, voucher_code: 'SAVE' }); set('voucher_code', 'CHANGED', form); }
        else { expect(first.customer_name).toBe('Élodie'); expect(first.line1).toBe('Original'); set('customer_email', 'not-email'); set('customer_name', ''); }
        cartValue = { items: [], subtotal: 0 };
        const reads = calls.filter(call => call.url === urls.cart).length;
        await submit(form);
        const posts = calls.filter(call => call.url === urls.checkout);
        expect(posts).toHaveLength(2);
        expect(posts[1].body).toBe(first);
        expect(calls.filter(call => call.url === urls.cart)).toHaveLength(reads + 1); // success refresh only
        expect(host.querySelector(form + ' button').formNoValidate).toBe(false);
        expect(host.textContent).toContain('Created #9');
        if (!loggedIn) {
            expect(host.querySelector('code').textContent).toBe('a'.repeat(64));
            const input = host.querySelector('#guest-track-form input');
            await act(async () => {
                Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, 'b'.repeat(64));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            expect(host.querySelector('code').textContent).toBe('a'.repeat(64));
            await click([...host.querySelectorAll('button')].find(node => node.textContent === 'Clear token'));
            expect(host.querySelector('code')).toBeNull();
            expect(input.value).toBe('');
        }
        expect(host.querySelector('img')).toBeNull();
        expect(localStorage.length).toBe(0);
    });
    it('success with refresh failure is terminal, not a pending retry', async () => {
        let committed = false;
        implementation = async url => {
            if (url === urls.checkout) { committed = true; return { order, tracking_token: null, tracking_recovery: 'unavailable' }; }
            if (url === urls.cart && committed) throw new Error('Refresh failed');
        };
        await mount(loggedIn);
        const form = loggedIn ? '#checkout-form' : '#guest-checkout-form';
        await submit(form);
        expect(host.textContent).toContain('Created #9');
        expect(host.querySelector(form + ' button').formNoValidate).toBe(false);
        expect(host.querySelector(form + ' button').disabled).toBe(true);
        expect(calls.filter(call => call.url === urls.checkout)).toHaveLength(1);
    });
    it('definitive rejection restores validation; unavailable fresh cart never posts', async () => {
        implementation = async url => { if (url === urls.checkout) throw Object.assign(new Error('Rejected'), { status: 422 }); };
        await mount(loggedIn);
        const form = loggedIn ? '#checkout-form' : '#guest-checkout-form';
        await submit(form);
        expect(host.querySelector(form + ' button').formNoValidate).toBe(false);
        cartValue = { ...cart, items: [{ ...item, available: false }] };
        await submit(form);
        expect(calls.filter(call => call.url === urls.checkout)).toHaveLength(1);
        expect(host.querySelector(form + ' button').disabled).toBe(true);
    });
});
it('guest token tracking is POST-only, token can be cleared, null-token recovery stays available', async () => {
    implementation = async url => { if (url === urls.checkout) return { order, tracking_token: null, tracking_recovery: 'unavailable', message: 'Recovery unavailable' }; if (url === urls.track) return { order }; };
    await mount(); await submit('#guest-checkout-form');
    expect(host.textContent).toContain('Recovery unavailable');
    expect(host.querySelector('code')).toBeNull();
    const tokenInput = host.querySelector('#guest-track-form input');
    tokenInput.value = 'A'.repeat(64); await submit('#guest-track-form');
    expect(calls.filter(call => call.url === urls.track)).toHaveLength(0);
    tokenInput.value = 'b'.repeat(64); await submit('#guest-track-form');
    expect(calls.find(call => call.url === urls.track)).toMatchObject({ method: 'POST', body: { token: 'b'.repeat(64) } });
    await click([...host.querySelectorAll('button')].find(node => node.textContent === 'Clear token'));
    expect(tokenInput.value).toBe('');
    expect(sessionStorage.length).toBe(0);
});
it('account lists recover independently after initial cart failure, then SET/delete/clear use independent endpoints', async () => {
    let failCart = true;
    implementation = async url => { if (url === urls.cart && failCart) throw new Error('Offline'); };
    await mount(true);
    expect(host.textContent).toContain('Rumah');
    expect(host.querySelector('#checkout-form button').disabled).toBe(true);
    failCart = false;
    await click(host.querySelector('#tas button'));
    set('quantity', '2', '#cart-list form'); await submit('#cart-list form');
    expect(calls.find(call => call.url === urls.cartStore).body).toEqual({ product_id: 7, quantity: 2 });
    await click([...host.querySelectorAll('#tas button')].find(node => node.textContent === 'Remove'));
    expect(calls.find(call => call.url === urls.cartItems + '/7').method).toBe('DELETE');
    await click([...host.querySelectorAll('#tas button')].find(node => node.textContent === 'Clear bag'));
    expect(calls.find(call => call.url === urls.cartClear).method).toBe('DELETE');
    expect(calls.some(call => call.url.includes('/delta'))).toBe(false);
});
it('account exposes pending-only cancellation, completed reviews, 15-item server pagination and native favorite links', async () => {
    orderPage = { data: [{ ...order, status: 'pending' }, { ...order, id: 10, status: 'paid' }, { ...order, id: 11, status: 'completed' }], current_page: 1, last_page: 2, total: 16, per_page: 15 };
    implementation = async url => { if (url === urls.favorites) return { favorites: [{ product_id: 7, product: { product_title: 'Sutra', product_slug: 'sutra' } }] }; };
    await mount(true);
    expect([...host.querySelectorAll('#pesanan button')].filter(node => node.textContent === 'Cancel')).toHaveLength(1);
    expect(host.querySelector('#favorit a[href="/products/sutra"]')).not.toBeNull();
    expect(host.querySelectorAll('#pesanan form')).toHaveLength(1);
    set('rating', '5', '#pesanan form'); set('body', 'Très belle', '#pesanan form'); await submit('#pesanan form');
    expect(calls.find(call => call.url === urls.reviews).body).toMatchObject({ order_id: 11, product_id: 7, rating: 5, body: 'Très belle' });
    await click([...host.querySelectorAll('#pesanan button')].find(node => node.textContent === 'Cancel'));
    expect(calls.find(call => call.url === '/orders/9/cancel').method).toBe('POST');
    await click([...host.querySelectorAll('#favorit button')].find(node => node.textContent === 'Remove favorite'));
    expect(calls.find(call => call.url === '/favorites/7').method).toBe('DELETE');
    await click([...host.querySelectorAll('#pesanan button')].find(node => node.textContent === 'Next'));
    expect(calls.some(call => call.url === '/orders?page=2')).toBe(true);
    expect(host.querySelector('nav a[href="/catalog"]')).not.toBeNull();
});
it('multiple completed items expose product-named forms and submit the selected product only', async () => {
    const second = { ...item, product_id: 8, product_title: 'Tas Kulit' };
    orderPage = { data: [{ ...order, status: 'completed', items: [item, second] }], current_page: 1, last_page: 1, total: 1 };
    await mount(true);
    const forms = [...host.querySelectorAll('#pesanan form')];
    expect(forms.map(form => form.getAttribute('aria-label'))).toEqual([`Review: ${item.product_title}`, 'Review: Tas Kulit']);
    expect(forms.map(form => form.querySelector('legend').textContent)).toEqual([item.product_title, 'Tas Kulit']);
    const form = forms.find(form => form.getAttribute('aria-label') === 'Review: Tas Kulit');
    form.querySelector('[name="rating"]').value = '4';
    form.querySelector('[name="body"]').value = 'For the bag';
    await act(async () => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    await flush();
    expect(calls.filter(call => call.url === urls.reviews)).toHaveLength(1);
    expect(calls.find(call => call.url === urls.reviews).body).toEqual({ order_id: 9, product_id: 8, rating: 4, body: 'For the bag' });
    expect(forms[0].isConnected).toBe(true);
    expect(host.textContent).toContain('Review sent');
});
for (const malformed of [{}, { tracking_token: 'INVALID' }, { tracking_token: null }]) it(`guest malformed token shape ${JSON.stringify(malformed)} keeps replay available`, async () => {
    let attempts = 0;
    implementation = async url => { if (url === urls.checkout) return ++attempts === 1 ? { order, ...malformed } : { order, tracking_token: null, tracking_recovery: 'unavailable' }; };
    await mount();
    await submit('#guest-checkout-form');
    expect(host.querySelector('#guest-checkout-button').formNoValidate).toBe(true);
    expect(host.querySelector('[name="quantity"]').disabled).toBe(true);
    const first = calls.find(call => call.url === urls.checkout).body;
    cartValue = { items: [], subtotal: 0 };
    await submit('#guest-checkout-form');
    expect(calls.filter(call => call.url === urls.checkout)[1].body).toBe(first);
    expect(host.querySelector('#guest-checkout-button').formNoValidate).toBe(false);
    expect(host.textContent).toContain('Created #9');
});
it('saved address create/default/delete and voucher quote use captured native field values and server totals', async () => {
    implementation = async (url, method, body) => {
        if (url === urls.quote) return { ...cart, shipping_fee: 50, discount: 10, tax: 0, total: 140, voucher_code: body.voucher_code };
    };
    await mount(true);
    for (const [name, value] of Object.entries({ label: 'Atelier', recipient_name: 'Ada', phone: '0812', line1: 'Jalan 1', line2: 'Floor 2', city: 'Jakarta', province: 'DKI', postal_code: '12345' })) set(name, value, '#address-form');
    host.querySelector('#address-form [name="is_default"]').checked = true;
    await submit('#address-form');
    expect(calls.find(call => call.url === urls.addressStore).body).toMatchObject({ label: 'Atelier', line1: 'Jalan 1', is_default: '1' });
    expect(host.querySelector('#address-form [name="label"]').value).toBe('');
    await click([...host.querySelectorAll('#alamat button')].find(node => node.textContent === 'Delete address'));
    expect(calls.find(call => call.url === '/addresses/3').method).toBe('DELETE');
    const voucher = host.querySelector('[name="voucher_code"]');
    voucher.value = ' SAVE ';
    await act(async () => voucher.dispatchEvent(new FocusEvent('focusout', { bubbles: true })));
    await flush();
    expect(calls.find(call => call.url === urls.quote).body).toEqual({ voucher_code: 'SAVE' });
    expect(host.querySelector('#checkout-summary').textContent).toContain('Estimated Rp140');
});
