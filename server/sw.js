// سرویس ورکر دانش‌یار - نسخه 1.0.0
const CACHE_NAME = 'daneshyar-v1';
const OFFLINE_URL = '/offline.html';

// فایل‌هایی که باید کش بشن
const PRECACHE_URLS = [
    '/',
    '/offline.html',
    '/assets/css/style.css',
    '/assets/css/chat.css',
    '/assets/js/chat.js',
    '/assets/img/logo.png',
    '/assets/vendor/fonts/Vazirmatn-Regular.woff2',
    '/assets/vendor/fonts/Vazirmatn-Medium.woff2',
    '/assets/vendor/fonts/Vazirmatn-Bold.woff2',
    '/assets/vendor/fonts/vazirmatn.css'
];

// نصب سرویس ورکر
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] کش کردن فایل‌های استاتیک');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// فعال‌سازی
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] حذف کش قدیمی:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// رهگیری درخواست‌ها
self.addEventListener('fetch', (event) => {
    // فقط درخواست‌های GET رو مدیریت کن
    if (event.request.method !== 'GET') return;

    // درخواست‌های API رو مستقیم بفرست
    if (event.request.url.includes('/api/')) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // برگردوندن از کش و آپدیت در پس‌زمینه
                    fetch(event.request).then((response) => {
                        if (response && response.status === 200) {
                            const responseClone = response.clone();
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(event.request, responseClone);
                            });
                        }
                    }).catch(() => {});
                    
                    return cachedResponse;
                }

                // اگر در کش نبود، از شبکه بگیر
                return fetch(event.request)
                    .then((response) => {
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });

                        return response;
                    })
                    .catch(() => {
                        // اگر آفلاین بود و صفحه HTML خواست
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match(OFFLINE_URL);
                        }
                    });
            })
    );
});
