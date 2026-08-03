const CACHE = 'mtcms-static-v1';
const ASSETS = ['assets/icons/icon-192.png', 'assets/icons/icon-512.png', 'manifest.json'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (ASSETS.some((a) => url.pathname.endsWith(a))) {
    event.respondWith(caches.match(event.request).then((res) => res || fetch(event.request)));
  }
});

// Notifications plugin: display incoming Web Push messages.
self.addEventListener('push', (event) => {
  let data = { title: 'New notification', body: '', url: './index.php' };
  try { data = { ...data, ...event.data.json() }; } catch (e) { /* plain-text payload, use defaults */ }
  event.waitUntil(self.registration.showNotification(data.title, { body: data.body, data: { url: data.url } }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data && event.notification.data.url ? event.notification.data.url : './index.php'));
});
