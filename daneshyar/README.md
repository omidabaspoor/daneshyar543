# 📚 دانش‌یار (نسخه ۲ - ریدیزاین حرفه‌ای)

سیستم آموزشی هوشمند با چت‌بات AI برای دانش‌آموزان پایه ۷ تا ۱۲.

## ✨ تغییرات مهم نسخه جدید

- ✅ **ریدیزاین کامل صفحه چت** با Mobile-First (sidebar drawer در موبایل)
- ✅ **آیکون‌های SVG اختصاصی** (بدون ایموجی)
- ✅ **KaTeX و Marked به صورت لوکال** (سازگار با نت ملی ایران - بدون CDN)
- ✅ **فونت Vazirmatn لوکال** (بدون نیاز به CDN)
- ✅ **رفع باگ کتاب‌ها** (همه کتاب‌ها در سایت دیده می‌شوند، گروه‌بندی شده بر اساس پایه)
- ✅ **کش متن کتاب** هنگام آپلود (پاسخ‌های سریع‌تر)
- ✅ **بهبود ظاهر کلی** با المان‌های شیشه‌ای حرفه‌ای، گرادینت‌های نارنجی، سایه‌های نرم
- ✅ **حذف امضای پایین صفحه** (به پرامپت سیستمی منتقل شد)
- ✅ **پرامپت سیستمی هوشمندتر** برای مخفی‌سازی فناوری و معرفی امید عباسپور

---

## 🚀 نصب روی XAMPP

1. پوشه `daneshyar` رو در `C:\xampp\htdocs\` کپی کن.
2. Apache + MySQL را روشن کن.
3. **API Key** را در `includes/config.php` خط `AI_API_KEY` بگذار.
4. در مرورگر برو به: `http://localhost/daneshyar/install.php`
5. بعد از نصب، فایل `install.php` را حذف کن.
6. ورود: `http://localhost/daneshyar/`
7. ادمین: `http://localhost/daneshyar/admin/login.php`
   - یوزر: `webmania`
   - پسورد: `zamn1222`

## 🌐 نصب روی هاست cPanel

1. کل پوشه را در `public_html` آپلود کن.
2. در cPanel دیتابیس MySQL بساز.
3. در `includes/config.php` این مقادیر را ویرایش کن:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_daneshyar');
define('DB_USER', 'cpaneluser_daneshyar');
define('DB_PASS', 'your-password');
define('AI_API_KEY', 'sk-...');
define('DEV_MODE', false);
```
4. `install.php` را اجرا کن و سپس حذفش کن.
5. مطمئن شو `books/` و `uploads/` قابل نوشتن هستند (chmod 755).

---

## 📁 ساختار

```
daneshyar/
├── admin/              پنل مدیریت
├── api/chat.php        endpoint چت AI
├── assets/
│   ├── css/            استایل‌ها
│   ├── js/             JS کلاینت
│   ├── img/            تصاویر
│   └── vendor/         کتابخانه‌های لوکال
│       ├── katex/      KaTeX برای فرمول
│       ├── marked.min.js
│       └── fonts/      Vazirmatn (woff2)
├── books/              کتاب‌های PDF
├── includes/
│   ├── config.php      ⚠ API key اینجاست
│   ├── db.php
│   ├── functions.php
│   ├── ai.php          کلاس AI با پرامپت سیستمی
│   ├── icons.php       کتابخانه SVG اختصاصی
│   ├── header.php
│   └── footer.php
├── sql/install.sql
├── index.php, login.php, register.php
├── chat.php, profile.php, pricing.php
├── install.php
└── preview.html        پیش‌نمایش طراحی
```

## 🎨 تم

- Glassmorphism تیره با لهجه نارنجی `#eb7c2a`
- فونت Vazirmatn (لوکال)
- ریسپانسیو Mobile-First
- آیکون‌های SVG اختصاصی
- سازگار با نت ملی ایران (بدون CDN خارجی)

---

اطلاعات بیشتر: امید عباسپور
