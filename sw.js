const CACHE_NAME = 'solar-tracker-v2';
const ASSETS = [
    'index.html',
    'assets/js/common.js',
    'assets/js/map.js',
    'assets/img/icon-192.png'
];

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', (e) => {
    // skip caching for API calls
    if (e.request.url.includes('/api/')) {
        return;
    }
    e.respondWith(
        caches.match(e.request).then((res) => res || fetch(e.request))
    );
});
