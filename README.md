# lewissmartracing.com

Official website of **Lewis Smart #32**, British motorcycle road racer.

A fast, fully static site built with [Astro](https://astro.build) and [Tailwind CSS](https://tailwindcss.com). The CSS is compiled at build time (no CDN), so the output is plain HTML, CSS and images that any static host can serve.

![Astro](https://img.shields.io/badge/Astro-5.18-BC52EE?logo=astro&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-38B2AC?logo=tailwindcss&logoColor=white)
![Node](https://img.shields.io/badge/Node-%E2%89%A518.20-339933?logo=node.js&logoColor=white)

---

## Contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Scripts](#scripts)
- [Project structure](#project-structure)
- [Editing content](#editing-content)
- [Design system](#design-system)
- [Hero flip-book frames](#hero-flip-book-frames)
- [Deployment](#deployment)
- [Still to confirm](#still-to-confirm)
- [Changelog](#changelog)

---

## Features

- **Eight pages:** Home, My Story, Results, Calendar, Bike & Team, Gallery, Sponsors and Contact.
- **Home hero:** a canvas "flip-book" driven by scrolling. It falls back to a static image on small screens and when `prefers-reduced-motion` is set.
- **My Story:** the career timeline, oldest season first, so the story builds up as the visitor scrolls.
- **One content file:** all copy and data live in `src/data/site.ts`.
- **Facts only:** anything not yet confirmed shows as "TBC" or a placeholder. Nothing is invented.
- **SEO and sharing:** each page has a canonical URL, a meta description, Open Graph tags and a Twitter card.
- **Accessibility:** a skip-to-content link, the reduced-motion fallback and semantic landmarks.
- **Performance:** fonts are self-hosted through Fontsource, the icon font is cut down to only the glyphs the site uses, images are WebP and the HTML is compressed.

## Tech stack

| Layer | Choice |
| --- | --- |
| Framework | Astro 5 (static output) |
| Styling | Tailwind CSS 3 + PostCSS, plus a little custom CSS |
| Fonts | Barlow Condensed (display), Inter Variable (body), Material Symbols (icons) |
| Image tooling | `sharp` (dev only, for the hero frames) |

## Getting started

**Requirements:** Node.js 18.20.8, 20.3+ or 22+ (developed on Node 22), and npm.

```bash
git clone https://github.com/gabrielvaraljay/lewissmartracing.com.git
cd lewissmartracing.com
npm install
npm run dev
```

The dev server runs at <http://localhost:4321> and reloads when files change.

## Scripts

| Command | What it does |
| --- | --- |
| `npm run dev` | Starts the local dev server on port 4321 |
| `npm run build` | Builds the static site into `dist/` |
| `npm run preview` | Serves the built `dist/` locally, to check it before deploying |
| `npm run frames -- <folder> [width] [quality]` | Turns a folder of photos into hero frames (see [below](#hero-flip-book-frames)) |

## Project structure

```text
.
├── public/
│   ├── favicon.svg
│   ├── frames/            # Hero flip-book frames (frame-01.webp …)
│   └── images/            # Portrait, bike livery, hero fallback, grid shot
├── scripts/
│   └── make-frames.mjs    # Photo → 16:9 WebP frame converter
├── src/
│   ├── components/        # Header, footer, page header, next race, enquiry form, icons …
│   ├── data/site.ts       # All copy and data: the one place to edit content
│   ├── layouts/Base.astro # <head>, SEO meta, fonts, header/footer shell
│   ├── pages/             # One .astro file per route
│   └── styles/            # tailwind.css entry + global.css extras
├── astro.config.mjs
├── tailwind.config.mjs    # Design tokens (colours, type scale, spacing)
└── postcss.config.cjs
```

## Editing content

Almost every content change happens in **`src/data/site.ts`**:

| What | Where in `site.ts` |
| --- | --- |
| Name, number, tagline, meta description | `site` |
| Contact email, socials, sponsor deck PDF, form endpoint | `site` (`null` = not set yet) |
| Navigation | `nav` |
| Headline stats | `stats` (`value: null` shows as TBC) |
| Career timeline | `timeline` (oldest first) |
| Hero frame count | `heroFrameCount` |

**Rule:** if a fact is not confirmed by Lewis, leave it `null` or "TBC". Do not invent results, stats or sponsors.

To add a page, create `src/pages/<slug>.astro`, wrap it in the `Base` layout and add it to `nav`.

## Design system

The Home and My Story pages are ported 1:1 from the Google Stitch layouts. The Stitch "Apex Precision" tokens live in `tailwind.config.mjs`. Their red palette is swapped for the #32 livery:

- **Primary:** fluorescent yellow-green (`primary-container`), the colour of the bike and the leathers.
- **Secondary:** purple.
- **Surfaces:** a dark theme built on near-black (`#0e0e10`).

Extra CSS for the simpler pages is in `src/styles/global.css`.

## Hero flip-book frames

1. Put 24–30 landscape photos in `photos-src/hero/`, named in the order they should play. This folder is not committed.
2. Run:

   ```bash
   npm run frames -- photos-src/hero
   ```

   The script crops each photo to 16:9 (centred on the most interesting area), resizes it to 1920 px wide and writes `public/frames/frame-01.webp` and onwards. You can pass a width and a WebP quality as extra arguments, e.g. `npm run frames -- photos-src/hero 1600 70`.
3. Set `heroFrameCount` in `src/data/site.ts` to the number the script prints.

The frames play only on screens 768 px and wider, and only when reduced motion is not requested. Everywhere else, frame 1 (or the static hero image) is shown.

## Deployment

The site runs on the Hetzner server (nginx), served from `/var/www/lewissmartracing.com`.

Deploys are automatic. A push to `main` (or a manual run of the **Deploy** workflow) builds the site in GitHub Actions and rsyncs `dist/` to the server. See [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

**Deploy access is locked down.** Actions logs in as the dedicated `lsr-deploy` user with its own SSH key. On the server that key is limited to `restrict,command="/usr/bin/rrsync /var/www/lewissmartracing.com"`, which means:

- no shell and no port, agent or X11 forwarding;
- rsync only, jailed to the site directory, so the key cannot write anywhere else on the server.

The repository secrets it needs are:

| Secret | Value |
| --- | --- |
| `DEPLOY_SSH_KEY` | Private key of `lsr-deploy` |
| `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan -t ed25519 78.47.73.104` |

The production URL is set by `site` in `astro.config.mjs`, and the canonical and Open Graph URLs are built from it.

## Still to confirm

These items still need confirming with Lewis: 2025/2026 results, current class, stats, calendar, bike spec, team, sponsors and logos, contact email, form endpoint, sponsorship deck PDF, and more photos.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

© Lewis Smart Racing. All rights reserved. The photos and branding are not licensed for reuse.
