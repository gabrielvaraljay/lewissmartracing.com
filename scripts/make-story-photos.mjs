// Turns a folder of photos into the My Story timeline photos: WebPs + src/data/story-photos.json.
// Usage: npm run photos -- [input-folder=photos-src/story]
// Each photo goes on the timeline under the year its file name starts with.
// File names become the captions/alt text ("2024 Endurance.jpg" -> "2024 Endurance"),
// so name them "<year> <what>". Newest first. Every photo gets the site address
// (scripts/assets/watermark.svg) in the bottom-right corner, and becomes:
//   public/images/story/<slug>.webp      full size for the lightbox (max 2400 px)
//   public/images/story/<slug>-sm.webp   timeline thumbnail, cropped to 3:2 (960x640)
import { readdir, readFile, mkdir, rm, writeFile } from 'node:fs/promises';
import { join, extname, basename } from 'node:path';
import sharp from 'sharp';

const [input = 'photos-src/story'] = process.argv.slice(2);
const out = 'public/images/story';
const exts = ['.jpg', '.jpeg', '.png', '.webp', '.heic', '.avif'];
const files = (await readdir(input))
  .filter((f) => exts.includes(extname(f).toLowerCase()))
  .sort((a, b) => b.localeCompare(a, undefined, { numeric: true }));
if (!files.length) throw new Error(`No images in ${input}`);
// "LEWISSMARTRACING.COM" in the bottom-right corner of every photo, sized to the photo.
const mark = await readFile('scripts/assets/watermark.svg');
async function watermarked(img) {
  const { data, info } = await img.toBuffer({ resolveWithObject: true });
  const side = Math.min(info.width, info.height);
  const w = Math.round(Math.min(info.width * 0.3, side * 0.55));
  const m = Math.round(side * 0.025);
  const wm = await sharp(mark, { density: 300 }).resize(w).png().toBuffer({ resolveWithObject: true });
  return sharp(data).composite([{ input: wm.data, left: info.width - wm.info.width - m, top: info.height - wm.info.height - m }]);
}
const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });
const photos = [];
for (const f of files) {
  const name = basename(f, extname(f));
  const s = slug(name);
  const src = sharp(join(input, f)).rotate();
  const full = await watermarked(src.clone().resize(2400, 2400, { fit: 'inside', withoutEnlargement: true }))
    .then((img) => img.webp({ quality: 82, smartSubsample: true }).toFile(join(out, `${s}.webp`)));
  const sm = await watermarked(src.clone().resize(960, 640, { fit: 'cover', position: 'attention' }))
    .then((img) => img.webp({ quality: 76, smartSubsample: true }).toFile(join(out, `${s}-sm.webp`)));
  photos.push({ year: name.slice(0, 4), src: `/images/story/${s}-sm.webp`, full: `/images/story/${s}.webp`, alt: `Lewis Smart #32, ${name}`, w: sm.width, h: sm.height });
  console.log(`${f} -> ${s}.webp ${full.width}x${full.height} ${(full.size / 1024).toFixed(0)} KB, -sm ${(sm.size / 1024).toFixed(0)} KB`);
}
await writeFile('src/data/story-photos.json', JSON.stringify(photos, null, 2) + '\n');

console.log(`\n${photos.length} photos written, list in src/data/story-photos.json`);
