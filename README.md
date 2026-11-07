# lewissmartracing.com

Official website of Lewis Smart #32, British motorcycle road racer.
Static site built with [Astro](https://astro.build): no client framework, a few tiny inline scripts, self-hosted fonts.

## Run

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # static output in dist/
```

## Where things live

| What | Where |
| --- | --- |
| Colours, fonts, spacing (design tokens) | `src/styles/global.css` (`--accent` = the one accent colour) |
| All copy and data (timeline, stats, socials, sponsors, next race) | `src/data/site.ts` |
| Pages | `src/pages/*.astro` |
| Hero flip-book frames | `public/frames/frame-01.webp` ... |

## Hero frames

1. Put 24-30 landscape photos in `photos-src/hero/` (not committed), named in the order they should play.
2. `npm run frames -- photos-src/hero`
3. Set `heroFrameCount` in `src/data/site.ts` to the number printed.

Frames only play on screens 768px+ without `prefers-reduced-motion`; everywhere else frame 1 is shown as a static image.

## Still to confirm with Lewis

Results 2025/2026, current class, stats, calendar, bike spec, team, sponsors and logos, contact email, form endpoint, sponsorship deck PDF, photos.
Everything not yet known shows as "TBC" or a placeholder; nothing is invented.
