export const money = value => 'Rp' + Number(value).toLocaleString('id-ID');
export function shopText(copy, key, params = {}) {
    return String(copy.js?.[key] ?? copy[key] ?? key).replace(/:([a-z_]+)/g, (match, name) => Object.hasOwn(params, name) ? String(params[name]) : match);
}

export function createShopRequest(copy = {}, options = {}) {
    return async (url, method = 'GET', payload) => {
        const controller = new AbortController();
        let timer;
        let timedOut = false;
        const timeout = () => Object.assign(new Error(copy.timeout || copy.generic || 'Request timed out'), { timeout: true });
        const deadline = new Promise((_, reject) => {
            timer = setTimeout(() => { timedOut = true; reject(timeout()); controller.abort(); }, options.timeoutMs ?? 10000);
        });
        const operation = async () => {
            let response;
            try {
                response = await (options.fetch ?? globalThis.fetch)(url, {
                    method, credentials: 'same-origin', signal: controller.signal,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': options.csrf ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
                });
            } catch {
                if (timedOut) throw timeout();
                throw new Error(copy.network || copy.generic || 'Network unavailable');
            }
            const data = await response.json().catch(() => null);
            if (timedOut) throw timeout();
            if (!response.ok || !data || typeof data !== 'object') {
                const validation = response.status === 422 && data?.errors && typeof data.errors === 'object'
                    ? Object.values(data.errors).flat().filter(value => typeof value === 'string').join(' ') : '';
                const error = new Error(copy[response.status] || (response.status >= 500 ? copy.server : validation) || copy.generic || 'Request failed');
                if (!response.ok) error.status = response.status;
                throw error;
            }
            return data;
        };
        try { return await Promise.race([operation(), deadline]); }
        finally { clearTimeout(timer); }
    };
}

const guestFields = ['customer_name', 'customer_email', 'customer_phone', 'label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code'];
export function checkoutPayload(fields, loggedIn, key) {
    if (loggedIn) return Object.freeze({ address_id: Number(fields.get('address_id')), voucher_code: String(fields.get('voucher_code') || '').trim() || null, idempotency_key: key });
    return Object.freeze({ ...Object.fromEntries(guestFields.map(name => [name, String(fields.get(name) ?? '')])), idempotency_key: key });
}
export function validCart(cart) {
    return cart && Array.isArray(cart.items) && Number.isInteger(cart.subtotal) && cart.items.every(item => Number.isInteger(item.product_id) && Number.isInteger(item.quantity) && item.quantity > 0 && Number.isInteger(item.subtotal) && Number.isInteger(item.unit_price) && typeof item.available === 'boolean');
}
export function validOrder(result) {
    const order = result?.order;
    return order && Number.isInteger(order.id) && order.id > 0 && Number.isInteger(order.total) && typeof order.status === 'string' && Array.isArray(order.items)
        && order.items.every(item => item !== null && typeof item === 'object' && typeof item.product_title === 'string' && Number.isInteger(item.quantity) && item.quantity > 0 && Number.isInteger(item.subtotal));
}

export function validGuestCheckout(result) {
    return validOrder(result) && (typeof result.tracking_token === 'string' && result.tracking_token.length === 64 && /^[a-f0-9]{64}$/.test(result.tracking_token)
        || result.tracking_token === null && result.tracking_recovery === 'unavailable');
}

// This client owns one full document. Uncertain writes never consult edited forms or a new cart.
export function createCheckoutClient({ api, urls, loggedIn, loadCart, text, uuid = () => crypto.randomUUID(), onPending = () => {} }) {
    let pending = null;
    let busy = false;
    return {
        get pending() { return pending; },
        async submit(fields) {
            if (busy) return null;
            busy = true;
            try {
                if (!pending) {
                    if (loggedIn && !Number(fields.get('address_id'))) throw new Error(text('pick_address'));
                    const cart = await loadCart();
                    if (!cart.items.length || cart.items.some(item => !item.available)) throw new Error(text('check_cart'));
                    pending = checkoutPayload(fields, loggedIn, uuid());
                    onPending(pending);
                }
                let result;
                try {
                    result = await api(urls.checkout, 'POST', pending);
                    if (!(loggedIn ? validOrder(result) : validGuestCheckout(result))) throw new Error(text('checkout_pending'));
                } catch (error) {
                    if ([409, 422].includes(error.status)) { pending = null; onPending(null); }
                    throw error;
                }
                pending = null;
                onPending(null);
                return result;
            } finally { busy = false; }
        },
    };
}
