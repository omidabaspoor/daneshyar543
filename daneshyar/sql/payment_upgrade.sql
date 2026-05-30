-- =========================================================
-- دانش‌یار - ارتقاء دیتابیس برای سیستم پرداخت
-- =========================================================

-- جدول رسیدهای کارت به کارت
CREATE TABLE IF NOT EXISTS `card_receipts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_code` VARCHAR(50) NOT NULL,
  `plan_title` VARCHAR(80) NOT NULL DEFAULT '',
  `amount` INT NOT NULL,
  `receipt_image` VARCHAR(255) NOT NULL,
  `activate_at` DATETIME DEFAULT NULL COMMENT 'زمان دلخواه فعال‌سازی',
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`status`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تغییر subscription_type به VARCHAR برای انعطاف بیشتر
ALTER TABLE `users`
  MODIFY COLUMN `subscription_type` VARCHAR(50) NOT NULL DEFAULT 'none';

-- اضافه کردن ستون activate_at به transactions
ALTER TABLE `transactions`
  ADD COLUMN IF NOT EXISTS `activate_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `payment_method` ENUM('card','online','manual') DEFAULT 'manual',
  ADD COLUMN IF NOT EXISTS `note` TEXT DEFAULT NULL;

-- تنظیم session طولانی‌مدت (ذخیره در DB)
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` VARCHAR(128) PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `last_active` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
