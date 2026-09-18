/**
 * Victorious Market POS - Service Worker
 * Version: 1.0.0
 */

const CACHE_NAME = 'vmpos-cache-v1';
const STATIC_ASSETS = [
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/apple-touch-icon.png',
    '/icons/maskable-icon.png'
];

// Install Event - Pre-cache static shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event - Clean up stale cache versions
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event
// Strategy: Network-first for dynamic navigation and APIs; Cache-first for images, fonts, and icons
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip Chrome extension requests or cross-origin analytics
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // Static Assets & Media (Cache-First)
    if (
        request.destination === 'image' ||
        request.destination === 'font' ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.woff2')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                }).catch(() => {
                    // Fail silently or return fallback
                });
            })
        );
        return;
    }

    // HTML Pages & Navigation (Network-First, with cache fallback for resilience)
    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match(request);
                })
        );
        return;
    }
});
