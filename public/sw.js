/**
 * Service worker for the Karyakarta PWA surface only (/app and the
 * enrolment wizard it links into under /admin/enrolments/*).
 *
 * Scope is deliberately narrow: this caches the app shell (CSS, icons)
 * for fast repeat loads and shows a friendly offline page when there's
 * no connection at all. It does NOT queue or replay enrolment/OTP/
 * payment submissions offline — those involve live OTP verification and
 * real money movement through Cashfree, so silently queuing them for
 * later would risk duplicate charges or stale-data mistakes. Every POST
 * request is left untouched and always goes straight to the network.
 */

const CACHE_VERSION = 'hithachintak-v1';
const APP_SHELL = [
  '/offline.html',
  '/assets/icons/icon-192.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only ever intercept safe, idempotent GETs — enrolment/OTP/payment
  // actions are all POST and must always hit the network live.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Page navigations: always prefer the network (data changes constantly),
  // falling back to the offline page only when there's truly no connection.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Static assets (CSS, icons, manifest): cache-first for speed, and
  // refresh the cache in the background whenever the network succeeds.
  if (/\.(?:css|png|svg|ico|webmanifest)(?:\?.*)?$/.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const network = fetch(request)
          .then((response) => {
            // Clone synchronously, before any async step — once the
            // response body starts streaming to the page (which can
            // happen as soon as this .then() returns), clone() throws
            // "Response body is already used".
            if (response.ok) {
              const copy = response.clone();
              caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
            }
            return response;
          })
          .catch(() => cached);

        return cached || network;
      })
    );
  }
});
