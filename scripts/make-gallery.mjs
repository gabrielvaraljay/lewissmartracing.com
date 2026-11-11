// Turns a folder of photos into gallery WebPs + src/data/gallery.json.
// Usage: npm run gallery -- [input-folder=photos-src/gallery] [og-photo-file-name]
// File names become the captions/alt text ("2024 Endurance.jpg" -> "2024 Endurance"),
// so name them "<year> <what>". Newest first. Each photo gets:
//   public/images/gallery/<slug>.webp      full size for the lightbox (max 2400 px)
//   public/images/gallery/<slug>-sm.webp   grid thumbnail (960 px wide)
// The optional og photo is cropped to 1200x630 JPG (public/images/og-gallery.jpg):
// JPG because not every link preview (LinkedIn, older WhatsApp) shows WebP.
import { readdir, mkdir, rm, writeFile } from 'node:fs/promises';
import { join, extname, basename } from 'node:path';
import sharp from 'sharp';

const [input = 'photos-src/gallery', og] = process.argv.slice(2);
const out = 'public/images/gallery';
const exts = ['.jpg', '.jpeg', '.png', '.webp', '.heic', '.avif'];
const files = (await readdir(input))
  .filter((f) => exts.includes(extname(f).toLowerCase()))
  .sort((a, b) => b.localeCompare(a, undefined, { numeric: true }));
if (!files.length) throw new Error(`No images in ${input}`);
const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });
const photos = [];
for (const f of files) {
  const name = basename(f, extname(f));
  const s = slug(name);
  const src = sharp(join(input, f)).rotate();
  const full = await src.clone().resize(2400, 2400, { fit: 'inside', withoutEnlargement: true })
    .webp({ quality: 82, smartSubsample: true }).toFile(join(out, `${s}.webp`));
  const sm = await src.clone().resize(960, null, { withoutEnlargement: true })
    .webp({ quality: 76, smartSubsample: true }).toFile(join(out, `${s}-sm.webp`));
  photos.push({ src: `/images/gallery/${s}-sm.webp`, full: `/images/gallery/${s}.webp`, alt: `Lewis Smart #32, ${name}`, w: sm.width, h: sm.height });
  console.log(`${f} -> ${s}.webp ${full.width}x${full.height} ${(full.size / 1024).toFixed(0)} KB, -sm ${(sm.size / 1024).toFixed(0)} KB`);
}
await writeFile('src/data/gallery.json', JSON.stringify(photos, null, 2) + '\n');

if (og) {
  await sharp(join(input, og)).rotate().resize(1200, 630, { fit: 'cover', position: 'attention' })
    .jpeg({ quality: 84, mozjpeg: true }).toFile('public/images/og-gallery.jpg');
  console.log(`OG: ${og} -> public/images/og-gallery.jpg`);
}
console.log(`\n${photos.length} photos written, list in src/data/gallery.json`);
