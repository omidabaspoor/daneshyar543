# 🛠 یادداشت فیکس‌ها — آپلود عکس/PDF و garbage هوش مصنوعی

## مشکل‌هایی که فیکس شدن

### 1) خرابی ارسال عکس HEIC ("هزار حرف انگلیسی")
**ریشه:** مدل `gemini-2.5-flash` از طریق GapGPT فرمت HEIC رو نمی‌فهمه، ولی کد قبلی base64 خام HEIC رو با `data:image/heic;base64,…` به‌عنوان `image_url` می‌فرستاد. مدل اون رو به‌عنوان توکن‌های نامعتبر می‌خوند و خروجی garbage تولید می‌کرد (loop کلمات لاتین تکراری).

**راه‌حل:**
- فایل جدید `includes/image_helper.php` که با Imagick (اگه باشه) HEIC رو به JPEG تبدیل می‌کنه.
- اگه Imagick روی هاست نباشه، آپلود HEIC با پیام واضح فارسی reject می‌شه (نه garbage).
- در `includes/ai.php` فیلتر اضافه شد که فقط `image/jpeg | png | webp | gif` به مدل پاس بشه.

### 2) خرابی تصاویر خیلی بزرگ
**ریشه:** عکس 10MB → 13MB base64 → مدل timeout می‌خورد یا توکنیزر خراب می‌کرد.

**راه‌حل:** در `image_helper.php` تابع `normalize_image_for_ai` با GD، عکس‌های بالاتر از 2000px یا 3MB رو resize/compress می‌کنه.

### 3) garbage خروجی AI حتی بدون عکس
**ریشه:**
- نبود `frequency_penalty` → مدل گاهی repetition loop داشت
- truncate تاریخچه با `mb_substr(.., 5000)` بدون چارست → mojibake
- PDF خام به‌جای متن استخراج‌شده base64 می‌شد

**راه‌حل:**
- `api/chat.php` بازنویسی شد: تشخیص garbage tail (loop کاراکترها، 250+ کاراکتر بدون فارسی) و قطع استریم با پیام مناسب.
- `includes/ai.php` پارامترهای `frequency_penalty=0.3` و `presence_penalty=0.1` اضافه شد.
- پرامپت سیستمی صریح‌تر شد: «همیشه فارسی، هرگز پاسخ طولانی فقط با حروف لاتین».
- در صورت خطای استریم، quota refund می‌شه (تابع `refund_message_quota` که قبلاً تعریف بود ولی استفاده نمی‌شد).

### 4) سازگاری با هاست‌های اشتراکی ضعیف
- `@set_time_limit` و `@ini_set` با `@` (در shared hosting بدون disable_functions کار می‌کنه).
- `ignore_user_abort(true)` و چک `connection_aborted()` در curl callback.
- timeout آپلود سمت JS به 120 ثانیه.
- PDF parser محدود به 12MB می‌خونه و در حافظه `unset` می‌کنه.
- header `X-Accel-Buffering: no` برای Nginx + `Connection: keep-alive`.
- در صورت نبود `imagewebp`/`imagecreatefromwebp` graceful fallback.

### 5) امنیت ضمیمه
- در `api/chat.php` چک path traversal اضافه شد (فقط مسیرهای زیر `uploads/` قبول می‌شن).
- HEIC با magic-bytes شناسایی می‌شه (نه فقط با extension).

---

## فایل‌هایی که تغییر کردن یا اضافه شدن

| فایل | وضعیت |
|------|--------|
| `includes/image_helper.php` | 🆕 جدید |
| `api/upload.php` | ✏️ بازنویسی کامل |
| `api/chat.php` | ✏️ بازنویسی کامل |
| `includes/ai.php` | ✏️ بازنویسی کامل |
| `includes/functions.php` | ✏️ پچ: `safe_image_ext` (HEIC فقط با Imagick) + `extract_pdf_text_simple` (محافظت حافظه) |
| `assets/js/chat.js` | ✏️ پچ: `handleFile` و `uploadFile` (پیام HEIC، timeout، preview صحیح) |
| `chat.php` | ✏️ پچ: `baseUrl` به `window.DANESHYAR` اضافه شد + `chat.js?v=19` |

---

## نکات نصب روی هاست

### اگه HEIC پشتیبانی می‌خوای (پیشنهاد می‌شه):
در cPanel → **Select PHP Version** → **Extensions** → فعال کن: **imagick**

اگه `imagick` نباشه، آپلود HEIC کاربر با پیام دوستانه reject می‌شه (به‌جای garbage):
> «فرمت HEIC روی این سرور پشتیبانی نمی‌شود. لطفاً عکس را به JPG یا PNG تبدیل کن یا از گوشی یه اسکرین‌شات بگیر و بفرست.»

### پسوندهای PHP لازم
- `gd` ✅ (تقریباً همیشه روی اشتراکی هست) — برای resize
- `curl` ✅ — برای AI
- `mbstring` ✅ — برای UTF-8 truncate
- `fileinfo` ✅ — برای تشخیص mime
- `imagick` ⭕ اختیاری ولی توصیه می‌شه (برای HEIC)

### تنظیمات `php.ini` پیشنهادی
```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 300
```

اگه دسترسی به php.ini نداری، یه `.htaccess` در روت سایت بساز:
```apache
php_value upload_max_filesize 20M
php_value post_max_size 25M
php_value memory_limit 256M
php_value max_execution_time 300
```

### پس از آپلود فایل‌ها روی هاست
1. کش مرورگر رو پاک کن (Ctrl+F5) — چون `chat.js?v=19` تغییر کرده.
2. تست کن:
   - 🟢 عکس JPG → باید کار کنه
   - 🟢 عکس PNG → باید کار کنه
   - 🟡 عکس HEIC از آیفون → اگه Imagick هست تبدیل می‌شه، اگه نه پیام راهنما می‌بینی
   - 🟢 PDF درسی → متنش استخراج می‌شه و به AI می‌ره
   - 🟢 اگه AI loop کرد یا garbage داد → سرور خودش قطع می‌کنه و quota refund می‌شه

---

## فایل‌هایی که باید روی هاست آپلود کنی

این ۶ فایل کافیه:

```
includes/image_helper.php         (جدید - باید آپلود بشه)
api/upload.php                    (بازنویسی شده)
api/chat.php                      (بازنویسی شده)
includes/ai.php                   (بازنویسی شده)
includes/functions.php            (پچ شده)
assets/js/chat.js                 (پچ شده)
chat.php                          (پچ شده - فقط ۲ خط)
```
