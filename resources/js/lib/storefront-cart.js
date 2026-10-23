export const formatCopy = (value = '', params = {}) => String(value).replace(/:([a-z_]+)/g, (match, key) => Object.hasOwn(params, key) ? String(params[key]) : match);
export const rupiah = value => 'Rp' + Number(value || 0).toLocaleString('id-ID');

export async function storefrontRequest(url, method = 'GET', payload, options = {}) {
    const { fetchImpl = globalThis.fetch, timeout = 10000 } = options;
    const controller = new AbortController();
    const error = (kind, status, errors) => Object.assign(new Error(kind), { kind, status, errors });
    let timer;
    const deadline = new Promise((_, reject) => {
        timer = setTimeout(() => { reject(error('timeout')); controller.abort(); }, timeout);
    });
    const operation = async () => {
        let response;
        try {
            response = await fetchImpl(url, {
                method, credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': globalThis.document?.querySelector('meta[name="csrf-token"]')?.content || '' },
                ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
            });
        } catch { throw error('network'); }
        const data = response.status === 204 ? null : await response.json().catch(() => null);
        if (!response.ok) {
            const kind = response.status === 419 || response.status === 401 ? 'session' : response.status === 429 ? 'rate' : response.status >= 500 ? 'service' : response.status === 422 ? 'validation' : 'bag';
            throw error(kind, response.status, data?.errors);
        }
        if (response.status !== 204 && !data) throw error('invalid');
        return data;
    };
    try { return await Promise.race([operation(), deadline]); }
    finally { clearTimeout(timer); }
}

export function createStorefrontCart({ request = storefrontRequest, publish = () => {}, url = '/cart' } = {}) {
    let queue = Promise.resolve();
    let snapshot = { items: [], subtotal: 0 };
    const accept = data => {
        const validLine = item => item && Number.isInteger(item.product_id) && item.product_id > 0
            && typeof item.product_title === 'string' && Number.isInteger(item.quantity) && item.quantity >= 1 && item.quantity <= 99
            && Number.isInteger(item.unit_price) && Number.isInteger(item.subtotal) && typeof item.available === 'boolean';
        if (!Array.isArray(data?.items) || !data.items.every(validLine) || !Number.isInteger(data.subtotal)) throw Object.assign(new Error('invalid'), { kind: 'invalid' });
        snapshot = data; publish(data); return data;
    };
    const serial = task => { const result = queue.catch(() => {}).then(task); queue = result; return result; };
    const fresh = async () => accept(await request(url));
    return {
        refresh: () => serial(fresh),
        adjust: (productId, delta) => serial(async () => {
            await fresh();
            if (delta < 0 && !snapshot.items.some(item => Number(item.product_id) === Number(productId))) return null;
            return accept(await request(`${url}/items/${Number(productId)}/delta`, 'POST', { delta }));
        }),
        remove: productId => serial(async () => {
            await fresh();
            if (!snapshot.items.some(item => Number(item.product_id) === Number(productId))) return null;
            return accept(await request(`${url}/items/${Number(productId)}`, 'DELETE'));
        }),
    };
}

export function readLegacyCart(storage) {
    const lines = [];
    for (const key of ['zafira-cart:v1', 'zafira-cart']) {
        try {
            const items = JSON.parse(storage?.getItem(key) || '[]');
            if (Array.isArray(items)) lines.push(...items.filter(item => item && typeof item === 'object'));
        } catch { /* Unreadable legacy data stays untouched. */ }
    }
    return lines;
}
