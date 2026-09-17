/* Batang Surigaonon Scholar's App — safe static-only service worker */
const CACHE_VERSION = 'bssa-pwa-v5';
const STATIC_CACHE = `${CACHE_VERSION}-static`;

const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-512-maskable.png',
    '/icons/apple-touch-icon.png',
    '/images/bssa-logo.png',
];

const STATIC_FILE = /\.(?:css|js|woff2?|ttf|eot|png|jpg|jpeg|gif|svg|webp|ico|map)$/i;

function isSameOrigin(url) {
    return url.origin === self.location.origin;
}

function isPrivatePath(pathname) {
    return (
        pathname.startsWith('/user') ||
        pathname.startsWith('/staff') ||
        pathname.startsWith('/admin') ||
        pathname.startsWith('/storage') ||
        pathname.startsWith('/livewire') ||
        pathname.includes('/documents') ||
        pathname.includes('/attendances') ||
        pathname.includes('/notifications') ||
        pathname.includes('/reports') ||
        pathname === '/login' ||
        pathname.startsWith('/login/') ||
        pathname.startsWith('/register') ||
        pathname.startsWith('/logout') ||
        pathname.startsWith('/password') ||
        pathname.startsWith('/sanctum')
    );
}

function isStaticAsset(url) {
    if (!isSameOrigin(url)) {
        return url.hostname === 'fonts.gstatic.com' || url.hostname === 'fonts.googleapis.com';
    }

    return (
        STATIC_FILE.test(url.pathname) ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/js/') ||
        url.pathname.startsWith('/build/') ||
        url.pathname === '/manifest.json' ||
        url.pathname === '/offline.html' ||
        url.pathname === '/favicon.ico'
    );
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== STATIC_CACHE).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request).then((response) => {
        if (response && response.ok && request.method === 'GET') {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => cached);

    return cached || network;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.pathname === '/sw.js') {
        return;
    }

    const isNavigation = request.mode === 'navigate' ||
        (request.headers.get('accept') || '').includes('text/html');

    if (isNavigation) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    if (isPrivatePath(url.pathname) || (isSameOrigin(url) && !isStaticAsset(url))) {
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});
