import re

with open('chat.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove from sidebar (both occurrences)
content = re.sub(
    r'<div class="sidebar-section"( style="margin-bottom:12px")?>\s*<a href="<\?= BASE_URL \?>/contact\.php".*?</a>\s*</div>',
    '',
    content,
    flags=re.DOTALL
)

content = re.sub(
    r'<div class="sidebar-section contact-links".*?</a>\s*</div>',
    '',
    content,
    flags=re.DOTALL
)

# Insert next to book picker
header_insert = """      </button>
      <input type="hidden" id="bookSelect" value="<?= e($activeBookId) ?>">

      <a href="<?= BASE_URL ?>/contact.php" class="book-picker-btn" style="flex: 0 1 auto; border-color:var(--orange); background:rgba(235,124,42,0.05); text-decoration:none; padding: 9px 10px; min-width: max-content;">
        <span class="book-picker-ico" style="color:var(--orange);"><?= icon('mail') ?></span>
        <span class="book-picker-meta" style="text-align:right;">
          <small style="color:var(--orange); font-size:9px;">درخواست کتاب اختصاصی</small>
          <b style="color:var(--orange); font-size:12px;">ارتباط با ما</b>
        </span>
      </a>"""

content = content.replace("""      </button>\n      <input type="hidden" id="bookSelect" value="<?= e($activeBookId) ?>">""", header_insert)

with open('chat.php', 'w', encoding='utf-8') as f:
    f.write(content)

