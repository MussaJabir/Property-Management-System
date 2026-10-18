/* PMS service worker {{ $version }} — see App\Http\Controllers\PwaController. */
const CACHE = 'pms-{{ $version }}';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/images/pwa/icon-192.png', '/images/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('pms-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

/** Built assets have hashed names, so a cached copy never goes stale. */
const isStatic = (url) => url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/images/pwa/')
    || url.pathname.startsWith('/fonts/');

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Pages: always fresh from the network; the offline page when there is no connection.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (isStatic(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
    // Everything else (Livewire, data, files, PDFs) goes straight to the network.
});
