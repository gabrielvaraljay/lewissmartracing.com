// Builds the 1200x630 Open Graph (link preview) image for every page: public/images/og/<name>.jpg.
// Usage: npm run og
// Each card is the page title in the site's Barlow Condensed on the dark background, with a photo on the right.
// Text is drawn from glyph outlines in scripts/assets/barlow-glyphs.json, so no font has to be installed.
// JPG (not WebP) because not every link preview (LinkedIn, older WhatsApp) shows WebP.
// Add a page: add a line to `cards`, run `npm run og`, and pass image="/images/og/<name>.jpg" to <Base>.
import { mkdir, readFile } from 'node:fs/promises';
import sharp from 'sharp';

const G = 'photos-src/gallery/'; // originals (not committed); falls back to the site WebP if missing
const cards = [
  { name: 'home', title: 'Lewis Smart #32', sub: 'British motorcycle road racer', photo: 'public/images/hero-static.webp' },
  { name: 'my-story', title: 'My Story', sub: 'From the Freshman Championship to racing with Triumph', photo: 'public/images/lewis-portrait.webp', pos: 'north' },
  { name: 'results', title: 'Results', sub: 'Race by race, season by season, from the official timing sheets', photo: `${G}2023 Endurance Champion.jpg`, fallback: 'public/images/gallery/2023-endurance-champion.webp' },
  { name: 'calendar', title: 'Calendar', sub: 'Every round and race weekend of the season', photo: `${G}2026 Smart Racing Triumph.jpg`, fallback: 'public/images/gallery/2026-smart-racing-triumph.webp' },
  { name: 'bike-and-team', title: 'Bike & Team', sub: 'The bike and the people behind #32', photo: 'public/images/bike-livery.webp' },
  { name: 'gallery', title: 'Gallery', sub: 'Photos and reels, from 2020 to today', photo: `${G}2025 Triumph Factory Team on track.jpg`, fallback: 'public/images/gallery/2025-triumph-factory-team-on-track.webp' },
  { name: 'sponsors', title: 'Sponsor Lewis', sub: '2027 partnership opportunities', photo: `${G}2025 Triumph Factory Team launch.jpg`, fallback: 'public/images/gallery/2025-triumph-factory-team-launch.webp' },
  { name: 'contact', title: 'Contact', sub: 'Sponsorship, media and general enquiries', photo: 'public/images/lewis-grid.webp', pos: 'north' },
  { name: 'club32', title: 'Club32', sub: 'Pick your lucky number and win BSB race weekend tickets', photo: `${G}2024 Superteens pit lane.jpg`, fallback: 'public/images/gallery/2024-superteens-pit-lane.webp' },
];

const W = 1200, H = 630, PHOTO_W = 600, PAD = 72, TEXT_W = 560;
const C = { bg: '#0b0b0d', accent: '#d6f20a', purple: '#6d4bc9', text: '#ffffff', text2: '#9ca3af' };
const fonts = JSON.parse(await readFile('scripts/assets/barlow-glyphs.json', 'utf8'));

// Width of a string in px at font size `size` (glyph advances; no kerning), plus letter spacing.
const measure = (font, s, size, track = 0) =>
  [...s].reduce((w, ch) => w + ((font.glyphs[ch]?.[0] ?? font.upm / 3) * size) / font.upm + track, 0) - track;
// SVG paths for `s` with its baseline at (x, y).
function text(font, s, x, y, size, fill, track = 0) {
  const k = size / font.upm;
  let out = '', cx = x;
  for (const ch of s) {
    const g = font.glyphs[ch];
    if (!g) { cx += (font.upm / 3) * k + track; continue; }
    if (g[1]) out += `<path transform="translate(${cx.toFixed(1)} ${y}) scale(${k.toFixed(5)} ${(-k).toFixed(5)})" d="${g[1]}" fill="${fill}"/>`;
    cx += g[0] * k + track;
  }
  return out;
}
// Greedy word wrap to `max` px.
function wrap(font, s, size, max, track = 0) {
  const lines = [];
  for (const word of s.split(' ')) {
    const last = lines.at(-1);
    if (last !== undefined && measure(font, `${last} ${word}`, size, track) <= max) lines[lines.length - 1] = `${last} ${word}`;
    else lines.push(word);
  }
  return lines;
}

async function card({ name, title, sub, photo, fallback, pos = 'attention' }) {
  const src = await readFile(photo).catch(() => readFile(fallback));
  const pic = await sharp(src).rotate().resize(PHOTO_W, H, { fit: 'cover', position: pos }).toBuffer();
  const D = fonts.display, L = fonts.label;
  const T = title.toUpperCase();
  // Title: as big as fits, up to 2 lines.
  let size = 132, lines = [T];
  for (; size > 60; size -= 4) {
    lines = wrap(D, T, size, TEXT_W);
    if (lines.length <= 2 && lines.every((l) => measure(D, l, size) <= TEXT_W)) break;
  }
  const lh = size * 0.92;
  const subLines = wrap(L, sub, 32, TEXT_W - 20).slice(0, 3);
  const block = 40 + lines.length * lh + 24 + subLines.length * 40;
  let y = Math.max(150, (H - block) / 2 + 10);
  let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
<defs><linearGradient id="fade" x1="0" x2="1"><stop offset="0" stop-color="${C.bg}"/><stop offset=".45" stop-color="${C.bg}" stop-opacity=".55"/><stop offset="1" stop-color="${C.bg}" stop-opacity="0"/></linearGradient></defs>
<rect x="${W - PHOTO_W}" width="260" height="${H}" fill="url(#fade)"/>
<g transform="translate(${PAD} ${y}) skewX(-20)"><rect width="34" height="5" fill="${C.accent}"/><rect x="34" width="14" height="5" fill="${C.purple}"/></g>`;
  svg += text(L, 'LEWIS SMART #32', PAD + 60, y + 8, 26, C.accent, 4);
  y += 40;
  for (const l of lines) { y += lh; svg += text(D, l, PAD - 4, y, size, C.text); }
  y += 24;
  for (const l of subLines) { y += 40; svg += text(L, l, PAD, y, 32, C.text2); }
  svg += text(L, 'LEWISSMARTRACING.COM', PAD, H - 52, 22, C.text, 3);
  svg += `<rect y="${H - 8}" width="${W * 0.7}" height="8" fill="${C.accent}"/><rect x="${W * 0.7}" y="${H - 8}" width="${W * 0.3}" height="8" fill="${C.purple}"/></svg>`;
  await sharp({ create: { width: W, height: H, channels: 3, background: C.bg } })
    .composite([{ input: pic, left: W - PHOTO_W, top: 0 }, { input: Buffer.from(svg), left: 0, top: 0 }])
    .jpeg({ quality: 86, mozjpeg: true })
    .toFile(`public/images/og/${name}.jpg`);
  console.log(`og/${name}.jpg`);
}

await mkdir('public/images/og', { recursive: true });
for (const c of cards) await card(c);
