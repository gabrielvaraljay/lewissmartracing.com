// Runs after the production build (and after prune-staging-only.mjs): writes dist/sitemap.xml and dist/robots.txt.
// The sitemap lists every page that is in the build and not marked noindex, with the My Story timeline photos
// and the Gallery reels as image/video entries so they can show up in Google Images and video results.
// On a staging build it writes nothing: staging must never be crawled or listed anywhere.
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

if (process.env.PUBLIC_SITE_ENV === 'staging') {
  console.log('[seo] staging build: no sitemap or robots.txt');
  process.exit(0);
}

const SITE = 'https://lewissmartracing.com';
const DIST = 'dist';
const today = new Date().toISOString().slice(0, 10);
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
// Home first, then the main pages; legal pages last and lower.
const priority = (path) => (path === '/' ? '1.0' : /^\/(privacy|club32\/terms)\//.test(path) ? '0.3' : '0.8');

const walk = (dir) =>
  readdirSync(dir).flatMap((f) => {
    const full = join(dir, f);
    return statSync(full).isDirectory() ? walk(full) : f === 'index.html' ? [full] : [];
  });

const entries = [];
for (const file of walk(DIST)) {
  const html = readFileSync(file, 'utf8');
  if (/<meta name="robots" content="noindex/.test(html)) continue;
  if (file.startsWith(join(DIST, 'admin'))) continue;
  const path = '/' + file.slice(DIST.length + 1).replace(/index\.html$/, '');
  let extra = '';
  if (path === '/my-story/') {
    const photos = JSON.parse(readFileSync('src/data/story-photos.json', 'utf8'));
    extra += photos.map((p) => `\n    <image:image><image:loc>${SITE}${p.full}</image:loc></image:image>`).join('');
  }
  if (path === '/gallery/') {
    const reels = JSON.parse(readFileSync('src/data/reels.json', 'utf8'));
    extra += reels.map((r) => `
    <video:video>
      <video:thumbnail_loc>${SITE}${r.poster}</video:thumbnail_loc>
      <video:title>${esc(r.title)}</video:title>
      <video:description>${esc(`${r.title}. Lewis Smart #32, British motorcycle road racer.`)}</video:description>
      <video:content_loc>${SITE}${r.src}</video:content_loc>
      <video:publication_date>${r.date}</video:publication_date>
      <video:family_friendly>yes</video:family_friendly>
    </video:video>`).join('');
  }
  entries.push({ path, xml: `  <url>\n    <loc>${SITE}${path}</loc>\n    <lastmod>${today}</lastmod>\n    <priority>${priority(path)}</priority>${extra}\n  </url>` });
}
entries.sort((a, b) => (a.path === '/' ? -1 : b.path === '/' ? 1 : a.path.localeCompare(b.path)));

writeFileSync(join(DIST, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
${entries.map((e) => e.xml).join('\n')}
</urlset>
`);
// /admin/ is deliberately not named here (this file is public); it sends noindex itself.
writeFileSync(join(DIST, 'robots.txt'), `User-agent: *
Allow: /
Disallow: /api/

Sitemap: ${SITE}/sitemap.xml
`);
console.log(`[seo] sitemap.xml (${entries.length} pages) and robots.txt written`);
