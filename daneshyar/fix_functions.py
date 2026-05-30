import re

with open('includes/functions.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_reset = """function reset_daily_if_needed($user) {
    $today = date('Y-m-d');
    if ($user['last_reset_date'] !== $today) {
        db()->prepare("UPDATE users SET messages_used_today=0, free_used_today=0, last_reset_date=? WHERE id=?")
            ->execute([$today, $user['id']]);
        $user['messages_used_today'] = 0;
        $user['free_used_today']     = 0;
        $user['last_reset_date']     = $today;
    }
    return $user;
}"""

new_reset = """function reset_daily_if_needed($user) {
    $today = date('Y-m-d');
    if ($user['last_reset_date'] !== $today) {
        db()->prepare("UPDATE users SET messages_used_today=0, free_used_today=0, last_reset_date=? WHERE id=? AND (last_reset_date IS NULL OR last_reset_date != ?)")
            ->execute([$today, $user['id'], $today]);
        $stmt = db()->prepare("SELECT messages_used_today, free_used_today, last_reset_date FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $updated = $stmt->fetch();
        if ($updated) {
            $user['messages_used_today'] = $updated['messages_used_today'];
            $user['free_used_today']     = $updated['free_used_today'];
            $user['last_reset_date']     = $updated['last_reset_date'];
        }
    }
    return $user;
}"""

content = content.replace(old_reset, new_reset)

with open('includes/functions.php', 'w', encoding='utf-8') as f:
    f.write(content)
