#!/usr/bin/env python3
"""Build the HTML emails in email/*.html into email/dist/ for pasting into BlueFox.
  <!-- @darkmode -->  -> the Gmail dark-mode fix (style block below)
  <!-- @sponsors -->  -> email/sponsor-footer.html
Gmail's apps recolour dark emails in dark mode (black turns light grey, #d6f20a turns dark green). The fix:
backgrounds also set as a linear-gradient image (Gmail leaves images alone), and text wrapped in
<div class="gb-s"><div class="gb-d">..</div></div>: Gmail inverts these black backgrounds to white and the two
blend modes invert the text back. `u + .body` only matches inside Gmail, so other clients ignore it. Buttons are images."""
import pathlib, re
root = pathlib.Path(__file__).resolve().parent.parent / 'email'
strip = lambda s: re.sub(r'<!--.*?-->\s*', '', s, flags=re.S).strip()
DARK = ('<style>:root{color-scheme:dark;supported-color-schemes:dark;}'
        'u + .body .gb-s{background:#000;mix-blend-mode:screen;}'
        'u + .body .gb-d{background:#000;mix-blend-mode:difference;}</style>')
parts = {'darkmode': DARK, 'sponsors': strip((root / 'sponsor-footer.html').read_text())}
(root / 'dist').mkdir(exist_ok=True)
for f in sorted(root.glob('*.html')):
    if f.name == 'sponsor-footer.html': continue
    html = re.sub(r'<!-- @(\w+) -->', lambda m: parts[m.group(1)], f.read_text())
    (root / 'dist' / f.name).write_text(html)
    print('email/dist/' + f.name, len(html))
