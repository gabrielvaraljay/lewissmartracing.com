// Runs after `astro build`. On a production build it removes the pages that are only on staging
// (see `stagingOnly` in src/data/site.ts) and fails if any remaining page still links to one.
// On a staging build (PUBLIC_SITE_ENV=staging) it does nothing.
import { readdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';

const DIST = 'dist';
// Keep in sync with `stagingOnly` in src/data/site.ts.
const STAGING_ONLY = ['/bike-and-team/', '/gallery/', '/club32/'];

if (process.env.PUBLIC_SITE_ENV === 'staging') {
  console.log('[prune] staging build: keeping every page');
  process.exit(0);
}

for (const p of STAGING_ONLY) {
  rmSync(join(DIST, p), { recursive: true, force: true });
  console.log(`[prune] removed ${p}`);
}

const walk = (dir) =>
  readdirSync(dir).flatMap((f) => {
    const full = join(dir, f);
    return statSync(full).isDirectory() ? walk(full) : full.endsWith('.html') ? [full] : [];
  });

const bad = [];
for (const file of walk(DIST)) {
  const html = readFileSync(file, 'utf8');
  for (const p of STAGING_ONLY) {
    if (html.includes(`href="${p}`) || html.includes(`href="${p.slice(0, -1)}"`)) bad.push(`${file} -> ${p}`);
  }
}
if (bad.length) {
  console.error('[prune] production pages still link to staging-only pages:\n  ' + bad.join('\n  '));
  process.exit(1);
}
console.log('[prune] production build OK: no links to staging-only pages');
