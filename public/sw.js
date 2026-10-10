const CACHE_NAME = 'zafira-cache-v2';
const CORE_ASSETS = [
    '/favicon.svg',
    '/manifest.webmanifest',
    '/robots.txt',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith('zafira-cache-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // Navigasi HTML: selalu network-first agar tidak menyajikan state terotentikasi,
    // CSRF token kadaluarsa, atau keranjang lama dari cache.
    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(
            fetch(request).catch(
                () => new Response('Koneksi terputus. Silakan coba kembali saat terhubung ke jaringan.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                })
            )
        );
        return;
    }

    // Aset build hasil kompilasi Vite (immutable / ber-hash)
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
    }
});