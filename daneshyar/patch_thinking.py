import re

with open('assets/css/chat.css', 'r', encoding='utf-8') as f:
    css = f.read()

# Make thinking-steps horizontal and compact
css = re.sub(r'\.thinking-steps\s*\{[^}]*\}', r'.thinking-steps {\n  display: flex;\n  flex-direction: row;\n  flex-wrap: wrap;\n  gap: 8px;\n  margin-top: 8px;\n  padding-top: 8px;\n  border-top: 1px solid rgba(255,255,255,.06);\n  justify-content: flex-start;\n}', css)

css = css.replace('.thinking-bubble {\n  min-height: 80px;\n}', '.thinking-bubble {\n  min-height: 40px;\n}')

# Keep t-step very small
css = re.sub(r'\.t-step\s*\{[^}]*\}', r'.t-step {\n  display: flex;\n  align-items: center;\n  gap: 4px;\n  font-size: 11px;\n  color: var(--text-muted);\n  transition: color .4s;\n  background: rgba(255,255,255,.03);\n  padding: 4px 8px;\n  border-radius: 8px;\n}', css)

# Make the thinking header inline
css = re.sub(r'\.ai-thinking\s*\{[^}]*\}', r'.ai-thinking { display:inline-flex; align-items:center; gap:5px; margin-left: 10px; }', css)

with open('assets/css/chat.css', 'w', encoding='utf-8') as f:
    f.write(css)

with open('assets/js/chat.js', 'r', encoding='utf-8') as f:
    js = f.read()

js = js.replace(
    "'<div class=\"ai-thinking\"><span></span><span></span><span></span></div>' +\n        `<div class=\"thinking-text\">${stepLabel}</div>` +",
    "'<div style=\"display:flex; align-items:center;\"><div class=\"ai-thinking\"><span></span><span></span><span></span></div>' +\n        `<div class=\"thinking-text\">${stepLabel}</div></div>` +"
)

with open('assets/js/chat.js', 'w', encoding='utf-8') as f:
    f.write(js)

