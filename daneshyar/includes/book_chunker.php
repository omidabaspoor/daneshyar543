<?php
/**
 * ============================================================
 *  دانش‌یار – سیستم استخراج محتوای کتاب + Chunking + RAG
 *
 *  روش کار:
 *   1. ادمین PDF آپلود می‌کنه
 *   2. PDF مستقیم (base64) به Gemini فرستاده می‌شه
 *      (همون روشی که چت کار می‌کنه – پس 100% کار می‌کنه)
 *   3. از Gemini خواسته می‌شه یک خلاصه ساختاریافته بده:
 *      فصل‌ها، مفاهیم کلیدی، فرمول‌ها، تعاریف
 *   4. خلاصه ذخیره و chunk می‌شه
 *   5. وقتی دانش‌آموز سوال می‌پرسه، chunk‌های مرتبط پیدا
 *      و به system prompt اضافه می‌شن
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ============================================================
//  Schema
// ============================================================

function ensure_book_chunks_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `book_chunks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `book_id` INT NOT NULL,
            `chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `title` VARCHAR(200) NOT NULL DEFAULT '',
            `content` MEDIUMTEXT NOT NULL,
            `keywords` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_chunk_book` (`book_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try { db()->exec("ALTER TABLE `books` ADD COLUMN `chunks_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `book_chunks` ADD FULLTEXT INDEX `ft_chunk_content` (`content`, `keywords`)"); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
}

// ============================================================
//  استخراج محتوای کتاب با AI
//  (دقیقاً همون روشی که چت PDF می‌فرسته – پس کار می‌کنه)
// ============================================================

/**
 * PDF رو مستقیم به Gemini می‌فرسته و خلاصه ساختاریافته می‌گیره.
 *
 * @param string $pdfPath مسیر فایل PDF
 * @return array ['ok'=>bool, 'text'=>string, 'error'=>?string]
 */
function extract_book_content_with_ai($pdfPath) {
    if (!is_file($pdfPath)) {
        return ['ok' => false, 'text' => '', 'error' => 'فایل PDF یافت نشد.'];
    }

    $fileSize = filesize($pdfPath);
    if ($fileSize > 20 * 1024 * 1024) {
        return ['ok' => false, 'text' => '', 'error' => 'حجم PDF بیشتر از ۲۰ مگابایت.'];
    }
    if ($fileSize < 1000) {
        return ['ok' => false, 'text' => '', 'error' => 'فایل PDF خیلی کوچک است.'];
    }

    $raw = @file_get_contents($pdfPath);
    if ($raw === false || strlen($raw) === 0) {
        return ['ok' => false, 'text' => '', 'error' => 'خواندن فایل ناموفق.'];
    }

    $pdfBase64 = base64_encode($raw);
    unset($raw);

    // prompt مخصوص استخراج محتوای آموزشی
    $prompt = <<<'PROMPT'
تو یک سیستم استخراج محتوای آموزشی هستی. این PDF یک کتاب درسی ایرانی است.

وظیفه تو: یک خلاصه ساختاریافته و کامل از محتوای آموزشی این کتاب بنویس.

قوانین:
1. برای هر فصل/بخش یک عنوان با ## بنویس
2. مفاهیم کلیدی هر بخش را لیست کن
3. تمام فرمول‌ها را به LaTeX بنویس (مثل $F=ma$ یا $$x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}$$)
4. تعریف‌ها و قوانین مهم را عیناً بنویس
5. مثال‌های حل‌شده مهم را خلاصه کن
6. نکات کنکوری و نکات مهم را مشخص کن
7. جدول‌ها و داده‌های عددی مهم را حفظ کن

مهم:
- خلاصه نکن به جایی که اطلاعات مهم حذف بشه
- فرمول‌ها خیلی مهمن – همه رو بنویس
- تعریف‌ها عیناً نوشته بشن
- خروجی فقط فارسی باشه (اصطلاحات انگلیسی داخل پرانتز)
- حداقل 3000 کلمه خروجی بده (اگه کتاب بزرگه بیشتر)
PROMPT;

    // ساخت payload – دقیقاً مثل چت
    $messages = [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => [
                    'url' => 'data:application/pdf;base64,' . $pdfBase64,
                ]],
            ],
        ],
    ];

    $payload = [
        'model'       => AI_MODEL,
        'messages'    => $messages,
        'temperature' => 0.1,
        'max_tokens'  => 16000,
        'stream'      => false,
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    unset($pdfBase64, $messages, $payload);

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payloadJson,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);
    unset($payloadJson);

    if ($response === false) {
        return ['ok' => false, 'text' => '', 'error' => 'خطای شبکه: ' . $curlErr . ' (#' . $curlNo . ')'];
    }

    if ($httpCode !== 200) {
        $errMsg = 'خطای API (HTTP ' . $httpCode . ')';
        $d = @json_decode($response, true);
        if (isset($d['error']['message'])) {
            $errMsg .= ': ' . $d['error']['message'];
        } elseif (isset($d['error']) && is_string($d['error'])) {
            $errMsg .= ': ' . $d['error'];
        }
        return ['ok' => false, 'text' => '', 'error' => $errMsg];
    }

    $d = @json_decode($response, true);
    if (!is_array($d)) {
        return ['ok' => false, 'text' => '', 'error' => 'پاسخ API قابل پردازش نبود.'];
    }

    $text = trim($d['choices'][0]['message']['content'] ?? '');

    if (mb_strlen($text, 'UTF-8') < 200) {
        return ['ok' => false, 'text' => $text, 'error' => 'محتوای استخراج‌شده خیلی کم بود (' . mb_strlen($text, 'UTF-8') . ' کاراکتر). PDF ممکنه اسکنی باشه.'];
    }

    // پاکسازی
    $text = preg_replace('/\r\n?/', "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    return ['ok' => true, 'text' => $text, 'error' => null];
}

// ============================================================
//  Chunking
// ============================================================

function chunk_book_text($fullText, $chunkSize = 1500, $overlap = 200) {
    $fullText = trim((string)$fullText);
    if ($fullText === '') return [];

    $words = preg_split('/\s+/u', $fullText, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($words);
    if ($total === 0) return [];

    if ($total <= $chunkSize + $overlap) {
        $c = implode(' ', $words);
        return [['title' => make_chunk_title($c), 'content' => $c, 'keywords' => extract_kw($c)]];
    }

    $chunks = [];
    $pos = 0;
    while ($pos < $total) {
        $end = min($pos + $chunkSize, $total);
        $c = implode(' ', array_slice($words, $pos, $end - $pos));
        $c = trim_sentence($c);
        if (mb_strlen($c, 'UTF-8') > 50) {
            $chunks[] = ['title' => make_chunk_title($c), 'content' => $c, 'keywords' => extract_kw($c)];
        }
        $next = $end - $overlap;
        if ($next <= $pos) $next = $pos + 1;
        $pos = $next;
        if ($pos >= $total) break;
    }
    return $chunks;
}

function trim_sentence($t) {
    $l = mb_strlen($t, 'UTF-8');
    if ($l < 200) return $t;
    $tail = mb_substr($t, $l - 150, 150, 'UTF-8');
    $best = 0;
    foreach (['.','؟','!','؛',"\n\n"] as $s) {
        $p = mb_strrpos($tail, $s);
        if ($p !== false && $p > $best) $best = $p;
    }
    return $best > 10 ? mb_substr($t, 0, $l - 150 + $best + 1, 'UTF-8') : $t;
}

function make_chunk_title($c) {
    if (preg_match('/^##\s*(.{5,})/mu', $c, $m)) return mb_substr(trim($m[1]), 0, 150, 'UTF-8');
    $w = preg_split('/\s+/u', preg_replace('/[\$\\\\{}\[\]#]/u', '', trim($c)), 12, PREG_SPLIT_NO_EMPTY);
    return mb_substr(implode(' ', array_slice($w, 0, 10)) ?: 'بخش کتاب', 0, 150, 'UTF-8');
}

function extract_kw($c) {
    $kw = [];
    if (preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]{3,}/u', $c, $m)) $kw = array_merge($kw, $m[0]);
    if (preg_match_all('/[a-zA-Z]{2,}/u', $c, $m)) $kw = array_merge($kw, $m[0]);
    if (preg_match_all('/\\\\([a-zA-Z]{3,})/u', $c, $m)) $kw = array_merge($kw, $m[1]);
    $stop = ['از','به','در','با','که','این','آن','است','بود','شد','می','و','یا','هم','را','تا','برای','یک','هر','شده','های','ها','ای','کنید','باشد','دارد','کند',
             'the','and','for','is','are','was','not','text','frac','cdot','left','right','begin','end'];
    $kw = array_filter(array_unique($kw), fn($w) => !in_array(mb_strtolower($w,'UTF-8'), $stop, true));
    return implode(' ', array_slice(array_values($kw), 0, 120));
}

// ============================================================
//  ذخیره chunk‌ها
// ============================================================

function save_book_chunks($bookId, $fullText) {
    ensure_book_chunks_schema();
    $bookId = (int)$bookId;
    if ($bookId <= 0) return 0;

    try { db()->prepare("DELETE FROM book_chunks WHERE book_id=?")->execute([$bookId]); } catch (Throwable $e) {}

    $chunks = chunk_book_text($fullText);
    if (empty($chunks)) {
        try { db()->prepare("UPDATE books SET chunks_count=0 WHERE id=?")->execute([$bookId]); } catch (Throwable $e) {}
        return 0;
    }

    $stmt = db()->prepare("INSERT INTO book_chunks (book_id, chunk_index, title, content, keywords) VALUES (?,?,?,?,?)");
    foreach ($chunks as $i => $c) {
        $stmt->execute([$bookId, $i, cln($c['title']), cln($c['content']), cln($c['keywords'])]);
    }

    $n = count($chunks);
    db()->prepare("UPDATE books SET chunks_count=? WHERE id=?")->execute([$n, $bookId]);
    return $n;
}

function cln($t) {
    if (!is_string($t)) return '';
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
    if (function_exists('mb_convert_encoding')) $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    return $t;
}

// ============================================================
//  جستجوی chunk‌ها (RAG)
// ============================================================

function find_relevant_chunks($bookId, $question, $maxChunks = 3, $maxChars = 12000) {
    ensure_book_chunks_schema();
    $bookId = (int)$bookId;
    if ($bookId <= 0 || trim($question) === '') return [];

    $stmt = db()->prepare("SELECT COUNT(*) FROM book_chunks WHERE book_id=?");
    $stmt->execute([$bookId]);
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt === 0) return [];

    if ($cnt <= $maxChunks) {
        $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC");
        $stmt->execute([$bookId]);
        return cap($stmt->fetchAll(), $maxChars);
    }

    $terms = search_terms($question);
    if (empty($terms)) {
        $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC LIMIT ?");
        $stmt->execute([$bookId, $maxChunks]);
        return $stmt->fetchAll();
    }

    // FULLTEXT
    $r = ft_search($bookId, $terms, $maxChunks);
    if (!empty($r)) return cap($r, $maxChars);

    // Keyword score
    $r = kw_search($bookId, $terms, $maxChunks);
    if (!empty($r)) return cap($r, $maxChars);

    // Fallback
    $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC LIMIT 2");
    $stmt->execute([$bookId]);
    return $stmt->fetchAll();
}

function search_terms($q) {
    $t = [];
    if (preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}]{3,}/u', $q, $m)) $t = array_merge($t, $m[0]);
    if (preg_match_all('/[a-zA-Z]{2,}/', $q, $m)) $t = array_merge($t, $m[0]);
    if (preg_match_all('/\d+/', $q, $m)) $t = array_merge($t, $m[0]);
    $stop = ['از','به','در','با','که','این','آن','است','بود','شد','می','و','یا','هم','را','تا','برای','یک','هر',
             'چرا','چی','چه','سوال','جواب','حل','توضیح','بده','بگو','لطفا','مسئله','تمرین','صفحه','کتاب','درس'];
    return array_values(array_unique(array_filter($t, fn($w) => !in_array(mb_strtolower($w,'UTF-8'), $stop, true) && mb_strlen($w,'UTF-8') >= 2)));
}

function ft_search($bid, $terms, $lim) {
    try {
        $s = implode(' ', array_slice($terms, 0, 15));
        $st = db()->prepare("SELECT *, MATCH(content,keywords) AGAINST(? IN NATURAL LANGUAGE MODE) AS sc FROM book_chunks WHERE book_id=? AND MATCH(content,keywords) AGAINST(? IN NATURAL LANGUAGE MODE) ORDER BY sc DESC LIMIT ?");
        $st->execute([$s, $bid, $s, $lim]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

function kw_search($bid, $terms, $lim) {
    try {
        $st = db()->prepare("SELECT * FROM book_chunks WHERE book_id=?"); $st->execute([$bid]);
        $all = $st->fetchAll(); $scored = [];
        foreach ($all as $c) {
            $sc = 0;
            $h = mb_strtolower($c['content'] . ' ' . ($c['keywords'] ?? ''), 'UTF-8');
            foreach ($terms as $t) {
                $tl = mb_strtolower($t, 'UTF-8');
                $n = mb_substr_count($h, $tl);
                if ($n > 0) { $sc += $n; if (mb_strpos(mb_strtolower($c['keywords'] ?? '','UTF-8'), $tl) !== false) $sc += 3; }
            }
            if ($sc > 0) { $c['_s'] = $sc; $scored[] = $c; }
        }
        usort($scored, fn($a,$b) => $b['_s'] <=> $a['_s']);
        return array_slice($scored, 0, $lim);
    } catch (Throwable $e) { return []; }
}

function cap($chunks, $max = 12000) {
    $r = []; $t = 0;
    foreach ($chunks as $c) {
        $l = mb_strlen($c['content'], 'UTF-8');
        if ($t + $l > $max && !empty($r)) break;
        $r[] = $c; $t += $l;
    }
    return $r;
}

// ============================================================
//  ساخت context برای prompt
// ============================================================

function build_book_context($chunks, $book) {
    if (empty($chunks)) return '';
    $ctx = "\n═══ محتوای مرجع از کتاب «" . ($book['title'] ?? '') . "» ═══\n";
    $ctx .= "بخش‌های زیر خلاصه ساختاریافته‌ای از محتوای واقعی کتاب هستند.\n\n";
    foreach ($chunks as $i => $c) {
        $n = $i + 1;
        $t = trim($c['title'] ?? '');
        $ctx .= "--- بخش {$n}" . ($t ? ": {$t}" : '') . " ---\n" . trim($c['content']) . "\n\n";
    }
    $ctx .= "═══ پایان ═══\n";
    return $ctx;
}
