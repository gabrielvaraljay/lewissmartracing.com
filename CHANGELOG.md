# Changelog

All notable changes to lewissmartracing.com are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Professional README covering the stack, setup, scripts, project structure, content editing, the design system, hero frames and deployment.
- This changelog.
- GitHub Actions deploy workflow: builds the site and rsyncs it to the Hetzner server on every push to `main`, using a dedicated deploy key jailed to the site directory.

## [0.1.0] - 2026-11-08

First version of the site.

### Added

- Static Astro site with eight pages: Home, My Story, Results, Calendar, Bike & Team, Gallery, Sponsors and Contact.
- Scroll-driven canvas hero on the Home page, with an optional photo flip-book. It falls back to a static image on small screens and when reduced motion is requested.
- My Story career timeline, oldest season first.
- `src/data/site.ts` as the single source of truth for all copy and data.
- `npm run frames` script (`scripts/make-frames.mjs`) that turns a folder of photos into 16:9 WebP hero frames.
- SEO and social meta: canonical URL, description, Open Graph and Twitter card.
- Skip-to-content link and scroll reveal animations.
- Self-hosted fonts (Barlow Condensed, Inter Variable) and a Material Symbols icon font cut down to only the glyphs the site uses.
- First real photos: hero, portrait, bike livery and the grid shot.

### Changed

- Home and My Story are now ported 1:1 from the Google Stitch layouts. Tailwind is compiled at build time, and the Stitch "Apex Precision" tokens are in `tailwind.config.mjs`.
- Header and footer follow the Stitch design on every page.
- The red accent is replaced with the #32 livery colours: a fluorescent yellow-green primary and a purple secondary.
- Heading classes use the display font (Barlow Condensed), and the footer brand block is tighter.
- My Story timeline labels are uppercase.
- The profile image uses the new portrait photo.

### Removed

- Day-job references on the Home page, in the My Story facts and in the footer, to keep the copy focused on racing.
- Invented stats, telemetry figures and the unconfirmed series chip on My Story. They are replaced by confirmed facts or "TBC".

[Unreleased]: https://github.com/gabrielvaraljay/lewissmartracing.com/compare/6117591...HEAD
[0.1.0]: https://github.com/gabrielvaraljay/lewissmartracing.com/compare/a2919a6...6117591
