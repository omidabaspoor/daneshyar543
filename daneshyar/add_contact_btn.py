import re

with open('assets/css/chat.css', 'r', encoding='utf-8') as f:
    css = f.read()

new_css = """
.contact-btn-header {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 12px;
  background: rgba(235,124,42,0.05);
  border: 1px solid var(--border-orange);
  border-radius: 12px;
  text-decoration: none;
  flex-shrink: 0;
  transition: .15s;
}
.contact-btn-header:hover { background: rgba(235,124,42,0.1); }
.contact-btn-header .ico { width: 18px; height: 18px; color: var(--orange); }
.contact-btn-meta { display: flex; flex-direction: column; gap: 1px; text-align: right; }
.contact-btn-meta small { color: var(--orange); font-size: 10px; font-weight: 800; line-height: 1.2; }
.contact-btn-meta b { color: var(--orange); font-size: 13px; font-weight: 700; line-height: 1.5; }

@media (max-width: 600px) {
  .contact-btn-meta { display: none; }
  .contact-btn-header { padding: 9px; }
}
"""

if '.contact-btn-header' not in css:
    css = css.replace('.book-picker-meta {', new_css + '\n.book-picker-meta {')

with open('assets/css/chat.css', 'w', encoding='utf-8') as f:
    f.write(css)

with open('chat.php', 'r', encoding='utf-8') as f:
    php = f.read()

# Remove old sidebar contacts
php = re.sub(
    r'<div class="sidebar-section"( style="margin-bottom:12px")?>\s*<a href="<\?= BASE_URL \?>/contact\.php".*?</a>\s*</div>',
    '',
    php,
    flags=re.DOTALL
)
php = re.sub(
    r'<div class="sidebar-section contact-links".*?</a>\s*</div>',
    '',
    php,
    flags=re.DOTALL
)

# Insert new button in header
header_insert = """      </button>
      <input type="hidden" id="bookSelect" value="<?= e($activeBookId) ?>">

      <a href="<?= BASE_URL ?>/contact.php" class="contact-btn-header" title="ارتباط با ما">
        <span class="contact-btn-meta">
          <small>درخواست کتاب اختصاصی</small>
          <b>ارتباط با ما</b>
        </span>
        <?= icon('mail') ?>
      </a>"""

php = php.replace("""      </button>\n      <input type="hidden" id="bookSelect" value="<?= e($activeBookId) ?>">""", header_insert)

with open('chat.php', 'w', encoding='utf-8') as f:
    f.write(php)

