// Service Worker for Hekmat Charity (v5 - High Reliability & Static Asset Acceleration)
const STATIC_CACHE = 'hekmat-static-v5';

const STATIC_ASSETS = [
  '/logo.png',
  '/manifest.json',
  '/assets/tailwind.min.css',
  '/assets/alpine.min.js'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== STATIC_CACHE).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Only intercept static assets: local fonts, styles, scripts and images
  const isStatic = STATIC_ASSETS.some(asset => url.pathname === asset) ||
                   url.pathname.startsWith('/assets/') ||
                   url.pathname.endsWith('.woff2') ||
                   url.pathname.endsWith('.png') ||
                   url.pathname.endsWith('.webp') ||
                   url.pathname.endsWith('.ico');

  if (isStatic) {
    event.respondWith(
      caches.match(req).then(cached => {
        if (cached) return cached;
        return fetch(req).then(res => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(STATIC_CACHE).then(c => c.put(req, copy)).catch(() => {});
          }
          return res;
        }).catch(() => caches.match(req));
      })
    );
    return;
  }

  // All HTML navigations and dynamic PHP requests pass through natively to browser
  // This ensures zero interference with Safari/Chrome on mobile networks
});
