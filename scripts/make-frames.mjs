// Turns a folder of photos into identical 16:9 WebP hero frames.
// Usage: npm run frames -- <input-folder> [width=1920] [quality=72]
// Photos are sorted by file name, centre-cropped to 16:9 and written to
// public/frames/frame-01.webp ... Then set heroFrameCount in src/data/site.ts.
import { readdir, mkdir, rm } from 'node:fs/promises';
import { join, extname } from 'node:path';
import sharp from 'sharp';

const [input = 'photos-src/hero', w = '1920', q = '72'] = process.argv.slice(2);
const width = Number(w), height = Math.round((width * 9) / 16);
const out = 'public/frames';
const files = (await readdir(input))
  .filter((f) => ['.jpg', '.jpeg', '.png', '.webp', '.heic', '.avif'].includes(extname(f).toLowerCase()))
  .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
if (!files.length) throw new Error(`No images in ${input}`);
await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });
let i = 0;
for (const f of files) {
  i++;
  const name = `frame-${String(i).padStart(2, '0')}.webp`;
  await sharp(join(input, f)).rotate().resize(width, height, { fit: 'cover', position: 'attention' }).webp({ quality: Number(q) }).toFile(join(out, name));
  console.log(`${f} -> ${name}`);
}
console.log(`\n${i} frames written. Set heroFrameCount = ${i} in src/data/site.ts`);
