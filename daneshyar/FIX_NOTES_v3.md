# 🛠 فیکس‌های نسخه ۳ — فعال‌سازی اشتراک + کارت بانکی موبایلی

## ۱) 🔴 باگ بزرگ: اشتراک فعال نمی‌شد

### ریشه مشکل
در `activate_subscription` فقط `messages_used_total` صفر می‌شد:

```php
// قدیم
UPDATE users
   SET subscription_type=?, subscription_start=?, subscription_end=?,
       messages_used_total=0
 WHERE id=?
```

**ولی** در `reserve_message_quota` (که فیکس قبلی atomic کردیم) شرط این بود:

```sql
UPDATE users SET ...
WHERE id=? AND subscription_type=?
  AND subscription_end > NOW()
  AND messages_used_today < daily_limit  ← اینجا!
  AND messages_used_total < total_limit
```

پس اگر کاربر قبلاً:
- پلن قبلیش پر شده بود (`messages_used_today=50`)
- یا سهمیه رایگانش پر بود (`free_used_today=3`)

بعد از فعال‌سازی پلن جدید، `messages_used_today=50` همچنان می‌موند → شرط `< daily_limit` شکست می‌خورد → `rowCount=0` → کاربر **بلافاصله** پیام «سقف پر شد» می‌گرفت، انگار اشتراک فعال نشده.

### راه‌حل
حالا `activate_subscription` همه شمارنده‌ها رو با هم ریست می‌کنه:

```php
UPDATE users
   SET subscription_type   = ?,
       subscription_start  = ?,
       subscription_end    = ?,
       messages_used_total = 0,
       messages_used_today = 0,    ← اضافه شد
       free_used_today     = 0,    ← اضافه شد
       last_reset_date     = CURDATE()  ← اضافه شد
 WHERE id = ?
```

همین فیکس در `run_scheduler` هم اعمال شد (برای اشتراک‌های زمان‌بندی‌شده).

### تست
1. یه کاربر تستی که سهمیه‌اش پر شده درست کن:
   ```sql
   UPDATE users SET messages_used_today=50, free_used_today=3 WHERE mobile='09123456789';
   ```
2. از پنل ادمین (`admin/users.php` یا `admin/receipts.php`) براش پلن فعال کن
3. ✅ باید بلافاصله بتونه پیام بفرسته (در sidebar هم باید «۰/۵۰ پیام امروز» نشون بده)

---

## ۲) 🎨 کارت بانکی موبایلی - طراحی کاملاً جدید

### مشکلات نسخه قبلی
- شماره کارت با `letter-spacing: 4px` در موبایل **overflow** می‌کرد
- دکمه کپی کوچک و سخت برای لمس بود
- aspect ratio کارت اصلاً نسبت‌های کارت بانکی واقعی رو نداشت
- پیام "کپی شد!" زیر کارت می‌رفت و حواس‌پرت‌کن بود

### راه‌حل جدید

#### الف) شماره کارت با چهار بلوک flex جدا
به جای letter-spacing سنگین، شماره به ۴ گروه ۴ رقمی تقسیم می‌شه و هر کدوم `flex:1` می‌گیره — یعنی همیشه به‌طور یکنواخت در عرض کارت پخش می‌شه، حتی روی صفحه ۳۲۰ پیکسلی.

```html
<div class="bc-number" dir="ltr">
  <span class="bc-num-group">6037</span>
  <span class="bc-num-group">9982</span>
  <span class="bc-num-group">5668</span>
  <span class="bc-num-group">6014</span>
</div>
```

#### ب) اندازه فونت ریسپانسیو با `clamp()`
```css
font-size: clamp(16px, 5.2vw, 22px);
```
- موبایل کوچک: ۱۶px (همیشه خوانا)
- موبایل عادی: حدود ۱۸-۲۰px
- دسکتاپ: حداکثر ۲۲px

#### ج) نسبت دقیق کارت بانکی واقعی
```css
aspect-ratio: 1.586 / 1;  /* نسبت ISO/IEC 7810 ID-1 */
max-width: 420px;
```
حالا کارت در همه سایزها نسبت طبیعی داره.

#### د) دکمه کپی بزرگ زیر کارت
به‌جای دکمه کوچک داخل کارت:
- دکمه ۴۸px ارتفاع (راحت برای لمس)
- رنگ نارنجی برند → سبز موقع موفقیت
- ویبره ۴۰ms روی موبایل
- fallback برای مرورگرهای قدیمی (textarea + execCommand)

#### هـ) انیمیشن shimmer ظریف
یه نوار نور آرام روی کارت حرکت می‌کنه (هر ۶ ثانیه) — حس premium می‌ده بدون اینکه حواس‌پرت‌کن باشه.

#### و) ۳ سطح ریسپانسیو
- `> 480px`: نمایش کامل
- `≤ 480px`: padding کوچک‌تر، letter-spacing کم‌تر
- `≤ 360px`: padding خیلی کوچک، aspect-ratio کمی فشرده‌تر

### پیش‌نمایش
فایل `preview-card.html` رو در workspace باز کن — هم نمایش عادی و هم نمایش در عرض ۳۲۰px (شبیه‌ساز موبایل کوچک) رو می‌بینی.

---

## فایل‌های تغییریافته

| فایل | تغییر |
|------|--------|
| `includes/functions.php` | `activate_subscription` + `run_scheduler` ریست کامل سهمیه |
| `payment.php` | HTML کارت بازنویسی + CSS کامل جدید + JS کپی بهتر |
| `preview-card.html` | 🆕 برای دیدن طراحی جدید کارت |

---

## نکات نصب

1. فقط ۲ فایل تغییر کرده: `includes/functions.php` و `payment.php`. آپلودش کن.
2. **نیازی به migration DB نیست** — هیچ ستون جدیدی اضافه نشده.
3. Ctrl+F5 کن (cache CSS).
4. اگه کاربری از قبل پلن خریده ولی بلوک شده، یک بار **در پنل ادمین** اشتراکش رو reset کن یا یه پلن دیگه فعال/غیرفعال کن تا سهمیه‌هاش صفر بشه. (یا مستقیم SQL بزن: `UPDATE users SET messages_used_today=0, free_used_today=0 WHERE id=X`)

---

## ✅ بعد از این فیکس

- 🟢 اشتراک رایگان → خرید پلن → بلافاصله فعال و قابل استفاده
- 🟢 پلن قبلی پر → خرید پلن جدید → بلافاصله فعال (سهمیه قبلی پاک می‌شه)
- 🟢 اشتراک زمان‌بندی‌شده → وقتی scheduler اجرا می‌شه، درست فعال می‌شه
- 🟢 کارت بانکی در همه گوشی‌ها زیبا و خوانا
- 🟢 دکمه کپی بزرگ و راحت برای لمس
