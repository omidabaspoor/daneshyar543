<?php
$adminPage = 'books';
$pageTitle = 'مدیریت کتاب‌ها';
include __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/book_chunker.php';
ensure_book_chunks_schema();
require_once __DIR__ . '/../includes/icons.php';

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $r = db()->prepare("SELECT file_name FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row) {
            @unlink(BOOKS_PATH . '/' . $row['file_name']);
            try { db()->prepare("DELETE FROM book_chunks WHERE book_id=?")->execute([$id]); } catch (Throwable $e) {}
            db()->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
            $msg = '✓ کتاب حذف شد.';
        }

    } elseif (isset($_POST['rechunk_id'])) {
        $id = (int)$_POST['rechunk_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row && mb_strlen($row['cached_text'] ?? '', 'UTF-8') > 100) {
            $cnt = save_book_chunks($id, $row['cached_text']);
            $msg = '✓ «' . e($row['title']) . '» → ' . num_fa($cnt) . ' بخش.';
        } else { $msg = '⚠ ابتدا متن را استخراج کنید.'; $msgType = 'info'; }

    } elseif (isset($_POST['rechunk_all'])) {
        $all = db()->query("SELECT id, cached_text FROM books WHERE LENGTH(COALESCE(cached_text,'')) > 100")->fetchAll();
        $t = 0; foreach ($all as $a) $t += save_book_chunks((int)$a['id'], $a['cached_text']);
        $msg = '✓ ' . num_fa(count($all)) . ' کتاب → ' . num_fa($t) . ' بخش.';

    } elseif (isset($_POST['ai_extract_id'])) {
        $id = (int)$_POST['ai_extract_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row) {
            $pdf = BOOKS_PATH . '/' . $row['file_name'];
            if (!is_file($pdf)) { $msg = '❌ فایل PDF یافت نشد.'; $msgType = 'error'; }
            else {
                $res = extract_book_content_with_ai($pdf);
                if ($res['ok']) {
                    $txt = $res['text'];
                    if (function_exists('sanitize_utf8')) $txt = sanitize_utf8($txt);
                    db()->prepare("UPDATE books SET cached_text=? WHERE id=?")->execute([$txt, $id]);
                    $cnt = save_book_chunks($id, $txt);
                    $msg = '✓ «' . e($row['title']) . '»: ' . num_fa(number_format(mb_strlen($txt,'UTF-8'))) . ' کاراکتر → ' . num_fa($cnt) . ' بخش.';
                } else { $msg = '❌ ' . e($res['error']); $msgType = 'error'; }
            }
        }

    } else {
        $title   = trim($_POST['title'] ?? '');
        $grade   = (int)($_POST['grade'] ?? 0);
        $major   = normalize_major($_POST['major'] ?? 'all', true);
        $subject = trim($_POST['subject'] ?? '');

        if (!$title || !$grade || !$subject || empty($_FILES['file']['tmp_name'])) {
            $msg = '❌ همه فیلدها الزامی.'; $msgType = 'error';
        } elseif ($_FILES['file']['size'] > 30 * 1024 * 1024) {
            $msg = '❌ حجم بیش از ۳۰ مگ.'; $msgType = 'error';
        } elseif (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            $msg = '❌ فقط PDF.'; $msgType = 'error';
        } else {
            if (!is_dir(BOOKS_PATH)) mkdir(BOOKS_PATH, 0755, true);
            $fn = 'book_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
            $dest = BOOKS_PATH . '/' . $fn;
            move_uploaded_file($_FILES['file']['tmp_name'], $dest);

            // استخراج با AI
            $res = extract_book_content_with_ai($dest);
            $cached = $res['ok'] ? $res['text'] : '';
            if (function_exists('sanitize_utf8') && $cached) $cached = sanitize_utf8($cached);

            db()->prepare("INSERT INTO books (title,grade,subject,major,file_name,cached_text) VALUES (?,?,?,?,?,?)")
                ->execute([$title, $grade, $subject, $major, $fn, $cached]);
            $bid = (int)db()->lastInsertId();

            $cc = 0;
            if ($res['ok'] && mb_strlen($cached, 'UTF-8') >= 100) $cc = save_book_chunks($bid, $cached);

            $tl = mb_strlen($cached, 'UTF-8');
            if ($res['ok'] && $cc > 0) {
                $msg = '✓ کتاب اضافه شد! ' . num_fa(number_format($tl)) . ' کاراکتر → ' . num_fa($cc) . ' بخش.';
            } elseif ($res['ok']) {
                $msg = '✓ کتاب اضافه شد (' . num_fa(number_format($tl)) . ' کاراکتر). بخش: ۰'; $msgType = 'info';
            } else {
                $msg = '⚠ کتاب ذخیره شد ولی استخراج ناموفق: ' . e($res['error']) . ' — بعداً دکمه AI رو بزنید.'; $msgType = 'info';
            }
        }
    }
}

$books = db()->query("SELECT id, title, grade, subject, major, file_name, LENGTH(COALESCE(cached_text,'')) as text_len, COALESCE(chunks_count,0) as chunks_count, created_at FROM books ORDER BY grade, major, subject")->fetchAll();
?>
<h2 style="margin-bottom:14px"><?= icon('book') ?> مدیریت کتاب‌ها</h2>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType==='error'?'error':($msgType==='info'?'info':'success') ?>"><?= $msg ?></div>
<?php endif; ?>

<div class="glass" style="padding:20px; margin-bottom:20px">
  <h3 style="color:var(--orange); margin-bottom:14px"><?= icon('plus') ?> افزودن کتاب جدید</h3>
  <form method="post" enctype="multipart/form-data" class="book-form" id="bf">
    <div class="form-group" style="margin:0"><label class="form-label">عنوان</label><input class="input" name="title" required placeholder="مثلاً: ریاضی دهم"></div>
    <div class="form-group" style="margin:0"><label class="form-label">پایه</label><select class="select" name="grade" required><?php for($g=7;$g<=12;$g++):?><option value="<?=$g?>">پایه <?=num_fa($g)?></option><?php endfor;?></select></div>
    <div class="form-group" style="margin:0"><label class="form-label">رشته</label><select class="select" name="major" required><?php foreach(book_major_options() as $c=>$l):?><option value="<?=e($c)?>"><?=e($l)?></option><?php endforeach;?></select></div>
    <div class="form-group" style="margin:0"><label class="form-label">درس</label><input class="input" name="subject" required placeholder="مثلاً: ریاضی"></div>
    <div class="form-group" style="margin:0"><label class="form-label">PDF</label><input class="input" name="file" type="file" accept=".pdf" required></div>
    <button class="btn btn-primary" type="submit" id="ub"><?= icon('upload') ?> آپلود</button>
  </form>
  <p style="margin-top:10px;font-size:12px;color:var(--text-dim)"><?= icon('sparkle') ?> PDF مستقیم به AI فرستاده می‌شه. ممکنه ۱-۴ دقیقه طول بکشه.</p>
</div>
<style>.book-form{display:grid;grid-template-columns:1.6fr .8fr 1.1fr 1fr 1.4fr auto;gap:10px;align-items:end}@media(max-width:1000px){.book-form{grid-template-columns:1fr}}</style>

<div style="display:flex;justify-content:flex-end;margin-bottom:10px">
  <form method="post"><input type="hidden" name="rechunk_all" value="1"><button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی همه؟')"><?= icon('refresh') ?> بازسازی بخش‌ها</button></form>
</div>

<div style="overflow-x:auto"><table class="admin-table">
<thead><tr><th>#</th><th>عنوان</th><th>پایه</th><th>رشته</th><th>درس</th><th>متن</th><th>بخش</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach($books as $b): $tl=(int)$b['text_len']; $cc=(int)($b['chunks_count']??0); ?>
<tr>
  <td><?=num_fa($b['id'])?></td>
  <td><b><?=e($b['title'])?></b></td>
  <td>پایه <?=num_fa($b['grade'])?></td>
  <td><?=e(major_label($b['major']??'all'))?></td>
  <td><?=e($b['subject'])?></td>
  <td><?php if($tl>100):?><span style="color:var(--success)">✓ <?=num_fa(number_format($tl))?></span><?php else:?><span style="color:var(--danger)">✗</span><?php endif;?></td>
  <td><?php if($cc>0):?><span style="color:var(--success)"><?=num_fa($cc)?></span><?php else:?><span style="color:var(--text-muted)">—</span><?php endif;?></td>
  <td><div style="display:flex;gap:3px;flex-wrap:wrap">
    <form method="post" style="display:inline" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='⏳'"><input type="hidden" name="ai_extract_id" value="<?=$b['id']?>"><button class="btn btn-sm" onclick="return confirm('استخراج AI؟ (۱-۴ دقیقه)')" style="background:rgba(75,171,247,.15);color:#4dabf7;border:1px solid rgba(75,171,247,.3);font-size:11px">AI</button></form>
    <form method="post" style="display:inline"><input type="hidden" name="rechunk_id" value="<?=$b['id']?>"><button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی؟')" style="font-size:11px" title="بازسازی بخش‌ها"><?=icon('refresh')?></button></form>
    <a href="<?=BASE_URL?>/books/<?=e($b['file_name'])?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:11px"><?=icon('pdf')?></a>
    <form method="post" style="display:inline"><input type="hidden" name="delete_id" value="<?=$b['id']?>"><button class="btn btn-danger btn-sm" onclick="return confirm('حذف؟')" style="font-size:11px"><?=icon('trash')?></button></form>
  </div></td>
</tr>
<?php endforeach; if(!$books):?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-dim)">هنوز کتابی اضافه نشده.</td></tr><?php endif;?>
</tbody></table></div>
<script>document.getElementById('bf')?.addEventListener('submit',function(){var b=document.getElementById('ub');if(b){b.disabled=true;b.textContent='⏳ در حال پردازش...';}});</script>
<?php include __DIR__ . '/_footer.php'; ?>
