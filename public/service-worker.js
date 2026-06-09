const CACHE_VERSION = 'spacechip-pwa-v1';
const OFFLINE_URL = '/offline.html';

const CORE_ASSETS = [
  '/',
  '/manifest.json',
  '/favicon.svg',
  OFFLINE_URL,
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(CORE_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k === CACHE_VERSION ? Promise.resolve() : caches.delete(k))))
    ).then(() => self.clients.claim())
  );
});

const isNavigationRequest = (request) =>
  request.mode === 'navigate' ||
  (request.method === 'GET' && request.headers.get('accept') && request.headers.get('accept').includes('text/html'));

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') return;

  if (isNavigationRequest(request)) {
    event.respondWith(
      fetch(request)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy)).catch(() => {});
          return res;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          if (cached) return cached;
          const offline = await caches.match(OFFLINE_URL);
          return offline || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
        })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy)).catch(() => {});
          return res;
        })
        .catch(() => cached);
      return cached || fetchPromise;
    })
  );
});

