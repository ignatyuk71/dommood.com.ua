// Старий service worker міг кешувати HTML зі старими Vite assets.
// Цей файл очищає кеші, знімає service worker з домену і віддає керування браузеру.
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const cacheNames = await caches.keys();

    await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
    await self.registration.unregister();

    const clientsList = await self.clients.matchAll({
      includeUncontrolled: true,
      type: 'window',
    });

    await Promise.all(
      clientsList.map((client) => client.navigate(client.url)),
    );
  })());
});
