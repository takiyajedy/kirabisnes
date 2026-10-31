/*
 * The service worker exists so the app can be installed on a phone
 * (PLAN.md phase 6). It deliberately caches nothing.
 *
 * Inertia serves a versioned asset bundle and a page payload that reflects
 * the database as it is now. A cache that served a stale app shell would
 * show yesterday's balances with no sign that anything was wrong, which is
 * worse than a page that simply does not load. Offline support for a
 * bookkeeping app needs a sync design, not a fetch handler — so until there
 * is one, this passes everything through.
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Named so a browser counts the app as installable, and no more.
    event.respondWith(fetch(event.request));
});
