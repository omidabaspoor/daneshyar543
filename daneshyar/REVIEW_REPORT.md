# 📋 گزارش بررسی کامل پروژه دانش‌یار (daneshyar45)

## 🔎 خلاصه پروژه

**دانش‌یار** یک سیستم آموزشی هوشمند مبتنی بر وب است که با **PHP خام** (بدون فریم‌ورک) و **MySQL** نوشته شده و از **هوش مصنوعی (GapGPT API → Gemini 2.5 Flash)** برای پاسخ‌دهی به سوالات درسی دانش‌آموزان پایه ۷ تا ۱۲ استفاده می‌کند.

**سازنده:** امید عباسپور  
**زبان:** PHP + JavaScript (وانیلا) + CSS  
**دیتابیس:** MySQL (PDO)  
**مدل AI:** `gemini-2.5-flash` از طریق `api.gapgpt.app`  
**فونت:** Vazirmatn (لوکال)  
**کتابخانه‌های کلاینت:** KaTeX (فرمول ریاضی) + Marked.js (Markdown) – همه لوکال  
**تعداد فایل‌ها:** 107 فایل | **حجم:** ~14 مگابایت  

---

## 📁 ساختار پوشه‌ها

```
daneshyar45/
├── admin/                    ← پنل مدیریت (9 فایل PHP)
│   ├── _header.php, _footer.php
│   ├── index.php (داشبورد)
│   ├── login.php, logout.php
│   ├── books.php, users.php, user.php
│   ├── receipts.php, pricing.php
│   ├── transactions.php, messages.php
│
├── api/                      ← API endpoints
│   ├── chat.php              ← SSE streaming endpoint اصلی چت AI
│   ├── chats.php             ← CRUD عملیات چت (pin/rename/delete)
│   ├── upload.php            ← آپلود تصویر/PDF
│   ├── scheduler.php         ← فعال‌سازی خودکار اشتراک‌ها (cron)
│
├── assets/
│   ├── css/style.css (1436 خط)  ← استایل اصلی
│   ├── css/chat.css (1687 خط)   ← استایل چت
│   ├── js/chat.js (606 خط)      ← جاوااسکریپت چت (SSE client)
│   ├── img/ (logo.png, logo.svg)
│   └── vendor/               ← کتابخانه‌های لوکال
│       ├── fonts/ (Vazirmatn woff2)
│       ├── katex/ (KaTeX + فونت‌ها)
│       └── marked.min.js
│
├── includes/                 ← هسته منطقی
│   ├── config.php            ← تنظیمات اصلی + API key
│   ├── db.php                ← اتصال PDO singleton
│   ├── functions.php         ← توابع کمکی (session, auth, subscription, ...)
│   ├── ai.php                ← کلاس DaneshyarAI (system prompt + streaming)
│   ├── icons.php             ← آیکون‌های SVG inline
│   ├── image_helper.php      ← پردازش تصویر (HEIC→JPG, resize)
│   ├── header.php, footer.php
│
├── sql/
│   ├── install.sql           ← اسکیمای اصلی دیتابیس
│   ├── payment_upgrade.sql
│   ├── add_status_and_scheduler.sql
│
├── uploads/                  ← آپلودهای کاربران
│   ├── receipts/             ← رسیدهای پرداخت
│
├── صفحات اصلی:
│   ├── index.php             ← صفحه اصلی (landing page)
│   ├── login.php, register.php, logout.php
│   ├── chat.php              ← صفحه چت (اپلیکیشن اصلی)
│   ├── profile.php, pricing.php, payment.php
│   ├── contact.php           ← ارتباط با ما + درخواست کتاب
│   ├── install.php           ← نصب‌کننده دیتابیس
│   ├── migrate.php
│
├── فایل‌های تعمیر/پچ (ابزار توسعه):
│   ├── fix_*.py, patch_*.py  ← اسکریپت‌های پایتون برای تعمیر
│   ├── fix_functions.php, patch_api.php
│
├── preview*.html             ← پیش‌نمایش‌های طراحی
├── robots.txt, sitemap.xml   ← SEO
└── README.md, FIX_NOTES*.md, SCHEDULER_SETUP.md
```

---

## 🏗️ معماری و جریان داده

### جریان اصلی چت:
```
کاربر → chat.php (UI)
        ↓
    chat.js (JavaScript)
        ↓ POST (SSE)
    api/chat.php
        ↓
    DaneshyarAI::streamChat()
        ↓ cURL streaming
    GapGPT API (Gemini 2.5 Flash)
        ↓ SSE chunks
    chat.js → DOM rendering (Marked + KaTeX)
```

### جریان پرداخت:
```
pricing.php → payment.php (کارت به کارت + آپلود رسید)
        ↓
    admin/receipts.php (تایید ادمین)
        ↓
    activate_subscription() یا approved_pending (زمان‌بندی)
        ↓
    api/scheduler.php (cron → فعال‌سازی خودکار)
```

---

## 📊 دیتابیس (8 جدول اصلی + 2 کمکی)

| جدول | توضیح |
|-------|--------|
| `users` | کاربران (اشتراک، محدودیت‌ها، پایه/رشته، مسدودسازی) |
| `books` | کتاب‌های PDF (با متن کش‌شده) |
| `pricing` | پلن‌های اشتراک (3 ساعته / هفتگی / ماهانه) |
| `chats` | گفت‌وگوها (عنوان، کتاب مرجع، سنجاق) |
| `chat_history` | تاریخچه پیام‌ها (user/assistant + پیوست) |
| `transactions` | تراکنش‌ها |
| `card_receipts` | رسیدهای کارت‌به‌کارت (pending/approved_pending/approved/rejected) |
| `user_sessions` | نشست‌های طولانی‌مدت (Remember Me 30 روز) |
| `user_discounts` | تخفیف‌های اختصاصی هر کاربر |
| `book_requests` | درخواست اضافه‌کردن کتاب |
| `messages` | پیام‌های تماس با ما |

---

## ✅ نقاط قوت

### 1. **معماری تمیز و خوانا**
- کد PHP خوب سازماندهی شده
- جداسازی مناسب لایه‌ها (config / db / functions / ai / views)
- استفاده از PDO با prepared statements
- تابع `e()` برای escape خروجی HTML

### 2. **سیستم اشتراک پیشرفته**
- سه پلن: ساعتی / هفتگی / ماهانه
- سهمیه روزانه + کلی
- سهمیه رایگان روزانه (3 پیام)
- ریست خودکار روزانه
- زمان‌بندی فعال‌سازی (approved_pending)
- تخفیف اختصاصی برای هر کاربر روی هر پلن

### 3. **مدیریت هوشمند AI**
- System prompt بسیار دقیق و حرفه‌ای
- فرمول‌نویسی LaTeX اجباری در prompt
- تنظیم سطح پاسخ بر اساس پایه/رشته دانش‌آموز
- کتاب مرجع با متن کش‌شده
- تشخیص garbage output و قطع خودکار
- محافظت در برابر loop بی‌نهایت مدل (12000 کاراکتر)
- Refund سهمیه در صورت خطا یا قطع ارتباط

### 4. **UX مناسب**
- طراحی Glassmorphism تیره با لهجه نارنجی
- ریسپانسیو و Mobile-First
- Sidebar drawer برای موبایل
- انتخاب کتاب با مودال + جستجو
- Drag & Drop و Paste برای آپلود تصویر
- انیمیشن تایپینگ هوش مصنوعی

### 5. **سازگاری با ایران**
- تمام vendor‌ها لوکال (بدون CDN خارجی)
- فونت Vazirmatn لوکال
- KaTeX و Marked لوکال
- تبدیل تاریخ شمسی ↔ میلادی
- تبدیل ارقام فارسی ↔ انگلیسی

### 6. **پنل ادمین کامل**
- داشبورد با آمار
- مدیریت کاربران (مسدودسازی، فعال‌سازی دستی، ریست)
- مدیریت کتاب‌ها (آپلود PDF + کش متن)
- رسیدهای پرداخت (تایید/رد)
- مدیریت قیمت‌ها
- تراکنش‌ها و درآمد
- پیام‌ها و درخواست‌های کتاب

### 7. **SEO**
- تگ‌های Open Graph و Twitter Card
- Schema.org (FAQPage + SoftwareApplication)
- Canonical URLs
- robots.txt و sitemap.xml

---

## ⚠️ مشکلات امنیتی (بحرانی)

### 🔴 1. **API Key در plaintext داخل سورس‌کد**
```php
// includes/config.php
define('AI_API_KEY', 'sk-kc61Y9n64u02EslIQ7swPTnrXpLMaHFNub3UbksE702rLP2X');
```
**ریسک:** هر کسی که به ریپو دسترسی دارد، API key را می‌بیند و می‌تواند سوءاستفاده کند.
**راه‌حل:** از متغیرهای محیطی (`$_ENV` / `.env` فایل) استفاده شود یا از `.gitignore` برای `config.php` استفاده شود.

### 🔴 2. **رمز ادمین hardcode شده**
```php
define('ADMIN_USER', 'webmania');
define('ADMIN_PASS', 'zamn1222');
```
**ریسک:** رمز ادمین هم در کد و هم در README قابل مشاهده است.
**راه‌حل:** رمز ادمین باید hash شده در دیتابیس ذخیره شود، نه در فایل config.

### 🔴 3. **اطلاعات کارت بانکی در سورس‌کد**
```php
define('CARD_NUMBER', '6037 9982 5668 6014');
define('CARD_HOLDER', 'امید عباسپور');
```
**ریسک:** اطلاعات مالی شخصی در ریپوزیتوری عمومی.

### 🔴 4. **DEV_MODE فعال در production**
```php
define('DEV_MODE', true);
```
**ریسک:** نمایش خطاهای PHP کامل به کاربران، که می‌تواند اطلاعات حساس سرور را فاش کند.

### 🟡 5. **SSL Verification غیرفعال**
```php
CURLOPT_SSL_VERIFYPEER => false,
CURLOPT_SSL_VERIFYHOST => 0,
```
**ریسک:** آسیب‌پذیری Man-in-the-Middle.

### 🟡 6. **ورود ادمین plaintext**
در `admin/login.php` رمز ادمین مستقیماً با `===` مقایسه می‌شود (نه hash).

### 🟡 7. **CSRF Token ثابت در session**
توکن CSRF یکبار ساخته می‌شود و تا پایان session تغییر نمی‌کند. بهتر است per-request باشد.

### 🟡 8. **شماره موبایل/تلفن شخصی در کد**
```php
define('CONTACT_PHONE', '09921627009');
define('CONTACT_TELEGRAM', 'webmania_admin');
```

---

## ⚠️ مشکلات فنی و باگ‌ها

### 🟠 1. **Auto-migration در هر درخواست**
```php
ensure_education_schema();
ensure_discounts_schema();
ensure_payment_schema();
```
این توابع در **هر page load** اجرا می‌شوند و ALTER TABLE/CREATE TABLE اجرا می‌کنند. این کار:
- کندی ایجاد می‌کند
- در صورت ترافیک بالا می‌تواند database lock ایجاد کند
**راه‌حل:** مهاجرت فقط در install/update اجرا شود.

### 🟠 2. **Rate Limiting ناکافی**
محدودیت فقط بر اساس `messages_used_today` و `free_used_today` است. هیچ rate limit واقعی (مثلاً بر اساس IP یا زمان بین درخواست‌ها) وجود ندارد.

### 🟠 3. **PDF Parser ابتدایی**
استخراج متن PDF با regex روی streamهای raw انجام می‌شود:
```php
preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $m)
```
این روش برای PDF‌های فارسی و اسکن‌شده عملاً کار نمی‌کند.

### 🟡 4. **فایل‌های fix/patch در production**
فایل‌های مثل `fix_api.py`, `patch_css.py`, `patch_thinking.py` و... نباید در production وجود داشته باشند.

### 🟡 5. **عدم Rate Limit روی API آپلود**
`api/upload.php` فقط CSRF و login چک می‌کند ولی rate limit ندارد. کاربر مخرب می‌تواند هزاران فایل آپلود کند.

### 🟡 6. **install.php قابل دسترسی**
اگر `install.php` حذف نشود، هر کسی می‌تواند دیتابیس را re-initialize کند.

### 🟡 7. **عدم Foreign Key در SQL**
جداول بدون `FOREIGN KEY` تعریف شده‌اند (بجز `user_discounts`). این یعنی orphan records ممکن است باقی بمانند.

---

## 📈 پیشنهادات بهبود

### معماری و کد
1. **استفاده از .env:** اطلاعات حساس (API key, DB credentials, admin password) از فایل `.env` خوانده شوند
2. **Composer Autoloading:** ساختار PSR-4 و autoloading خودکار
3. **Router:** استفاده از یک router ساده به‌جای فایل‌های مجزا
4. **Migration System:** سیستم migration مجزا (بجای auto-migrate در هر درخواست)
5. **Error Logging:** لاگ خطاها در فایل بجای نمایش به کاربر

### امنیت
6. **رمز ادمین hash شده** در دیتابیس ذخیره شود
7. **Rate Limiting** بر اساس IP و user
8. **SSL Verification** فعال شود
9. **CSRF per-request** شود
10. **Content Security Policy** header اضافه شود
11. **حذف install.php** بعد از نصب (یا قفل خودکار)
12. **`.gitignore`** برای `config.php` و `uploads/`

### عملکرد
13. **Caching:** کش نتایج DB برای صفحات پرترافیک
14. **CDN:** استفاده از CDN برای فایل‌های استاتیک
15. **Database Indexing:** بهینه‌سازی ایندکس‌ها
16. **Lazy Loading:** بارگذاری تنبل تصاویر

### قابلیت‌ها
17. **درگاه پرداخت آنلاین** (زرین‌پال / IDPay)
18. **WebSocket** بجای SSE برای چت real-time
19. **PWA** برای نصب روی موبایل
20. **نوتیفیکیشن** پوش برای کاربران
21. **گزارش‌گیری** پیشرفته در پنل ادمین (نمودار)
22. **OCR** بهتر برای PDF‌های اسکن‌شده

---

## 📊 آمار کد

| فایل/بخش | خطوط تقریبی |
|-----------|-------------|
| `includes/functions.php` | ~650 |
| `includes/ai.php` | ~180 |
| `includes/image_helper.php` | ~230 |
| `api/chat.php` | ~350 |
| `api/upload.php` | ~200 |
| `chat.php` | ~300 |
| `payment.php` | ~500+ |
| `admin/users.php` | ~300 |
| `admin/books.php` | ~150 |
| `admin/receipts.php` | ~200 |
| `assets/js/chat.js` | 606 |
| `assets/css/style.css` | 1436 |
| `assets/css/chat.css` | 1687 |
| **جمع کل PHP/JS/CSS** | **~7000+** |

---

## 🎯 نتیجه‌گیری

**دانش‌یار** یک پروژه **فول‌استک PHP** نسبتاً کامل و حرفه‌ای است که:

### ✅ خوب است:
- **قابلیت‌های متنوع:** چت AI، مدیریت اشتراک، پرداخت، مدیریت کتاب، پنل ادمین
- **UX خوب:** طراحی مدرن، ریسپانسیو، سازگار با ایران
- **System Prompt هوشمند:** پرامپت AI بسیار دقیق و آموزشی
- **بدون وابستگی خارجی:** همه چیز لوکال
- **کد خوانا:** naming مناسب، کامنت‌های فارسی

### ⛔ نیاز به بهبود فوری دارد:
- **امنیت:** API key، رمز ادمین و اطلاعات بانکی در سورس کد عمومی
- **DEV_MODE:** باید در production غیرفعال شود
- **Auto-migration:** باید حذف شود
- **فایل‌های اضافی:** fix/patch فایل‌ها باید حذف شوند
- **SSL Verification:** باید فعال شود

### 💡 سطح پروژه:
یک پروژه **سطح متوسط رو به حرفه‌ای** که نشان‌دهنده درک خوب از PHP، MySQL، SSE/streaming و طراحی UI است. با رفع مشکلات امنیتی و چند بهبود معماری، می‌تواند یک محصول قابل ارائه باشد.
