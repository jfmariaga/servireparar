// Service worker mínimo (PWA "básico", spec: pulido de producto): cache-first
// SOLO de los assets estáticos compilados por Vite (/build/...), para que la
// app cargue más rápido y sea instalable. No cachea páginas HTML ni datos —
// las OT/inventario siguen siempre viniendo del servidor, sin edición offline.
const CACHE = 'serviops-static-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((nombres) => Promise.all(
            nombres.filter((n) => n !== CACHE).map((n) => caches.delete(n)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || !url.pathname.startsWith('/build/')) {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then((cache) => cache.match(event.request).then((cacheado) => {
            if (cacheado) {
                return cacheado;
            }

            return fetch(event.request).then((respuesta) => {
                cache.put(event.request, respuesta.clone());

                return respuesta;
            });
        })),
    );
});
