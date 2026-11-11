#!/usr/bin/env python3
"""Make the email sponsor logo tiles (256x128 PNG, shown at 124x62) from public/images/sponsors/*.webp.
Usage: python3 scripts/email-sponsor-logos.py <out-dir>
Upload the PNGs to the BlueFox gallery folder "Sponsor logos (email)" and put the new URLs in email/sponsor-footer.html.
Keep the list in step with `sponsors` in src/data/site.ts (name -> file, brand background)."""
import sys
from PIL import Image

SPONSORS = [('bas-group', '#ffffff'), ('amethyst', '#ffffff'), ('strive-performance', '#231f20'),
            ('green-power-motorsports', '#18423e'), ('arai', '#ffffff'), ('rg', '#ffffff'), ('uggly-and-co', '#ffffff')]
W, H = 256, 128
out = sys.argv[1] if len(sys.argv) > 1 else '.'
for name, bg in SPONSORS:
    im = Image.open(f'public/images/sponsors/{name}.webp').convert('RGBA')
    if (box := im.getbbox()): im = im.crop(box)
    s = min(W * 0.78 / im.width, H * 0.70 / im.height)
    im = im.resize((max(1, round(im.width * s)), max(1, round(im.height * s))), Image.LANCZOS)
    tile = Image.new('RGBA', (W, H), bg)
    tile.alpha_composite(im, ((W - im.width) // 2, (H - im.height) // 2))
    tile.convert('RGB').save(f'{out}/lsr-sponsor-{name}.png', optimize=True)
    print(f'{out}/lsr-sponsor-{name}.png')
