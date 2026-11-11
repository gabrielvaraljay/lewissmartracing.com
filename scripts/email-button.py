#!/usr/bin/env python3
"""Render an email button as a PNG in the site's .btn--primary style (neon #d6f20a, Barlow Condensed 800 italic,
chamfered top-right corner). Images are not recoloured by Gmail's dark mode, so the button keeps its colours.
Usage: python3 scripts/email-button.py "SEE THE BOARD" email/buttons/see-the-board.png   (2x: shown at half size)"""
import sys
from PIL import Image, ImageDraw, ImageFont

text, out = sys.argv[1], sys.argv[2]
S = 2                                   # retina
H, PADX, SIZE, TRACK, CHAMFER = 48 * S, 26 * S, 21 * S, 0.06, 12 * S
font = ImageFont.truetype('node_modules/@fontsource/barlow-condensed/files/barlow-condensed-latin-800-italic.woff2', SIZE)
adv = [font.getlength(c) + (SIZE * TRACK if i < len(text) - 1 else 0) for i, c in enumerate(text)]
W = round(sum(adv) + 2 * PADX)
im = Image.new('RGBA', (W, H), (0, 0, 0, 0))
d = ImageDraw.Draw(im)
d.polygon([(0, 0), (W - CHAMFER, 0), (W, CHAMFER), (W, H), (0, H)], fill='#d6f20a')
asc, desc = font.getmetrics()
x, y = PADX, (H - (asc + desc)) / 2 + 1 * S
for c, a in zip(text, adv):
    d.text((x, y), c, font=font, fill='#0b0b0d')
    x += a
im.save(out, optimize=True)
print(out, W // S, 'x', H // S)
