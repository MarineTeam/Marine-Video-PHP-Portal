const CACHE = 'mvp-static-v1';
const ASSETS = [
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'manifest.json',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

// Only serve cached responses for the known static assets above — never
// intercept API calls, authed pages, or video streams.
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (ASSETS.some((a) => url.pathname.endsWith(a))) {
    event.respondWith(caches.match(event.request).then((res) => res || fetch(event.request)));
  }
});
