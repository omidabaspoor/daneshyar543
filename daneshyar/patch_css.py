import re

with open('assets/css/chat.css', 'r', encoding='utf-8') as f:
    css = f.read()

# Add width: 100% to composer-row if not present
if 'width: 100%; max-width: 820px;' in css:
    pass # already replaced by sed
else:
    css = css.replace('max-width: 820px;', 'width: 100%; max-width: 820px;')

# Ensure composer-hint has width: 100%
css = css.replace('.composer-hint {', '.composer-hint {\n  width: 100%;')

with open('assets/css/chat.css', 'w', encoding='utf-8') as f:
    f.write(css)

