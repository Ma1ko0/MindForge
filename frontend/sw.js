// MindForge Service Worker — Phase 1: installable PWA + offline shell
// Strategy:
//   - Precache the static app shell on install
//   - Cache-first for static assets (components, views, css, js, icons)
//   - Network-first with a polite offline fallback for dynamic requests
//   - Skip non-GET requests (PWAs don't try to cache POST/PUT/DELETE)

const CACHE = 'mindforge-shell-v2';

const SHELL = [
    '/',
    '/index.html',
    '/manifest.json',
    '/icon.svg',
    '/customstyles.css',
    '/htmx.min.js',
    '/components/Sidebar.html',
    '/components/Sidebar.css',
    '/node_modules/bootstrap/dist/css/bootstrap.min.css',
    '/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(SHELL).catch((err) => {
                // If a single asset 404s we don't want to abort the whole install
                console.warn('[SW] Some shell assets failed to cache', err);
            }))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

function isStaticPath(url) {
    const p = url.pathname;
    return (
        p === '/' ||
        p === '/index.html' ||
        p === '/manifest.json' ||
        p === '/icon.svg' ||
        p.startsWith('/components/') ||
        p.startsWith('/views/') ||
        p.startsWith('/node_modules/') ||
        p.endsWith('.css') ||
        p.endsWith('.js') ||
        p.endsWith('.svg') ||
        p.endsWith('.png') ||
        p.endsWith('.woff2') ||
        p.endsWith('.woff')
    );
}

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Don't touch non-GET requests
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Only handle same-origin — let the browser do CDN (marked.js, font-awesome)
    if (url.origin !== self.location.origin) return;

    // Never cache the SW itself
    if (url.pathname === '/sw.js') return;

    // Network-first for everything same-origin: always try the live server,
    // fall back to cache only when offline. This avoids stale-content issues
    // during active development while still keeping an offline app shell.
    event.respondWith(
        fetch(req)
            .then((resp) => {
                if (resp && resp.ok && isStaticPath(url)) {
                    const clone = resp.clone();
                    caches.open(CACHE).then((c) => c.put(req, clone));
                }
                return resp;
            })
            .catch(() => caches.match(req).then((cached) => cached || offlineFallback(req)))
    );
});

function offlineFallback(req) {
    const accept = req.headers.get('accept') || '';
    if (accept.includes('text/html')) {
        return new Response(
            '<div class="alert alert-warning m-4">' +
            '<i class="fa-solid fa-cloud-arrow-down me-2"></i>' +
            'Offline — der Server ist gerade nicht erreichbar.' +
            '</div>',
            { headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
        );
    }
    return new Response('', { status: 503, statusText: 'Offline' });
}
