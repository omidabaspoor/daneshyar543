import sys

# We need to create a table for user-specific plan discounts
# Columns: id, user_id, plan_code, discount_percent, created_at
sql = """
CREATE TABLE IF NOT EXISTS user_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_code VARCHAR(50) NOT NULL,
    discount_percent TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_plan (user_id, plan_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

# We need to run this on the DB. Since we are in an agent mode, I'll create a PHP script to run it.
php_script = f"""
<?php
require_once 'includes/db.php';
try {{
    $pdo = db();
    $pdo->exec("{sql}");
    echo "Table user_discounts created successfully.";
}} catch (Exception $e) {{
    echo "Error: " . $e->getMessage();
    exit(1);
}}
"""
with open('update_discounts_db.php', 'w', encoding='utf-8') as f:
    f.write(php_script)
