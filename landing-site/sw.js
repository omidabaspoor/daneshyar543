// Service Worker برای صفحه نصب دانش‌یار
const CACHE_NAME = 'daneshyar-install-v1';

// نصب
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// فعال‌سازی
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// رهگیری درخواست‌ها - فقط کش صفحه نصب
self.addEventListener('fetch', (event) => {
    // درخواست‌های اصلی سایت رو مستقیم بفرست
    if (event.request.url.includes('daneshyar.ir')) {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                return response || fetch(event.request);
            })
            .catch(() => {
                // اگر آفلاین بود
                if (event.request.headers.get('accept')?.includes('text/html')) {
                    return new Response(`
                        <!DOCTYPE html>
                        <html dir="rtl">
                        <head><title>آفلاین</title></head>
                        <body style="background:#0f1115;color:#fff;font-family:sans-serif;text-align:center;padding:50px;">
                            <h1>اینترنت متصل نیست</h1>
                            <p>لطفاً اتصال خود را بررسی کنید</p>
                        </body>
                        </html>
                    `, { headers: { 'Content-Type': 'text/html' } });
                }
            })
    );
});
