import re

with open('includes/functions.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_res = """function reserve_message_quota($user, $mode) {
    if ($mode === 'free') {
        $stmt = db()->prepare("UPDATE users SET free_used_today = free_used_today + 1 WHERE id=? AND free_used_today < ?");
        $stmt->execute([(int)$user['id'], FREE_DAILY_LIMIT]);
        return $stmt->rowCount() === 1;
    }

    $sub = subscription_status($user);
    if (!$sub['active']) return false;
    $plan = $sub['plan'];
    $conds = ["id=?", "subscription_end > NOW()"];
    $params = [(int)$user['id']];
    if ((int)$plan['daily_limit'] > 0) $conds[] = "messages_used_today < " . (int)$plan['daily_limit'];
    if ((int)$plan['total_limit'] > 0) $conds[] = "messages_used_total < " . (int)$plan['total_limit'];

    $stmt = db()->prepare("UPDATE users SET messages_used_today=messages_used_today+1, messages_used_total=messages_used_total+1 WHERE " . implode(' AND ', $conds));
    $stmt->execute($params);
    return $stmt->rowCount() === 1;
}"""

new_res = """function reserve_message_quota($user, $mode) {
    // Reset limits first if the date has changed to prevent false blocks
    $user = reset_daily_if_needed($user);

    if ($mode === 'free') {
        $stmt = db()->prepare("UPDATE users SET free_used_today = free_used_today + 1 WHERE id=? AND free_used_today < ?");
        $stmt->execute([(int)$user['id'], FREE_DAILY_LIMIT]);
        return $stmt->rowCount() === 1;
    }

    $sub = subscription_status($user);
    if (!$sub['active']) return false;
    $plan = $sub['plan'];
    $conds = ["id=?", "subscription_end > NOW()"];
    $params = [(int)$user['id']];
    if ((int)$plan['daily_limit'] > 0) $conds[] = "messages_used_today < " . (int)$plan['daily_limit'];
    if ((int)$plan['total_limit'] > 0) $conds[] = "messages_used_total < " . (int)$plan['total_limit'];

    $stmt = db()->prepare("UPDATE users SET messages_used_today=messages_used_today+1, messages_used_total=messages_used_total+1 WHERE " . implode(' AND ', $conds));
    $stmt->execute($params);
    return $stmt->rowCount() === 1;
}"""

content = content.replace(old_res, new_res)

with open('includes/functions.php', 'w', encoding='utf-8') as f:
    f.write(content)
