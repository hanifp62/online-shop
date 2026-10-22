export function buildCatalogFilterUrl(action, entries) {
    const query = new URLSearchParams();
    for (const [name, value] of entries) {
        if (typeof value === 'string' && value !== '' && name !== 'page') {
            // Literal repeated kategori keys are the server's public URL contract.
            query.append(name, value);
        }
    }
    const path = action.split(/[?#]/, 1)[0];
    return query.size ? `${path}?${query}` : path;
}

export function createSerialQueue() {
    let tail = Promise.resolve();
    return (task) => {
        const result = tail.then(task);
        tail = result.catch(() => {});
        return result;
    };
}

export function createCatalogActions({ post, feedback, copy, queue = createSerialQueue() }) {
    let active = true;
    let version = 0;

    async function report(operation, success) {
        if (!active) return false;
        const intent = ++version;
        feedback(copy.processing);
        try {
            await operation();
            if (active && intent === version) feedback(success);
            return true;
        } catch (error) {
            if (active && intent === version) feedback(error.message || copy.failed);
            return false;
        }
    }

    return {
        setQuantity(url, fields) {
            const quantity = Math.min(99, Math.max(1, parseInt(fields.quantity, 10) || 1));
            const payload = { product_id: Number(fields.product_id), quantity };
            // Capture intent before enqueueing; reading the form later would lose SET ordering.
            return report(
                () => queue(() => post(url, payload)),
                copy.updated.replace(':qty', String(quantity)),
            );
        },
        saveFavorite(url, productId) {
            const payload = { product_id: Number(productId) };
            return report(() => post(url, payload), copy.saved);
        },
        activate() {
            active = true;
        },
        dispose() {
            active = false;
            version += 1;
        },
    };
}

export async function postCatalogJson(url, payload, copy, {
    fetchImpl = globalThis.fetch,
    document = globalThis.document,
    location = globalThis.location,
} = {}) {
    const destination = new URL(url, location.href);
    if (destination.origin !== location.origin || destination.username || destination.password) {
        throw new Error(copy.failed);
    }
    // Read at execution time, including when a queued request starts after token rotation.
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) throw new Error(copy.failed);

    let response;
    try {
        response = await fetchImpl(destination.href, {
            method: 'POST',
            credentials: 'same-origin',
            mode: 'same-origin',
            redirect: 'error',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });
    } catch {
        // A failed transport may already have committed. Never replay a mutation automatically.
        throw new Error(copy.network);
    }

    if (response.redirected) throw new Error(copy.failed);
    if (response.status === 401) throw new Error(copy.login);
    if (response.status === 419 || response.status >= 500) throw new Error(copy.failed);
    if (!/\bapplication\/(?:[\w.-]+\+)?json\b/i.test(response.headers.get('Content-Type') || '')) {
        throw new Error(copy.failed);
    }

    let data;
    try {
        data = await response.json();
    } catch {
        throw new Error(copy.failed);
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error(copy.failed);
    if (!response.ok) {
        const validation = response.status === 422
            ? (typeof data.message === 'string' && data.message) || Object.values(data.errors || {})
                .flat().filter((value) => typeof value === 'string').join(' ')
            : '';
        throw new Error(validation || copy.failed);
    }
    return data;
}

export function watchCatalogFilters(details, media, activeCount) {
    const sync = () => { details.open = !media.matches || activeCount > 0; };
    sync();
    media.addEventListener('change', sync);
    return () => media.removeEventListener('change', sync);
}
