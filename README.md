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
- [Club32 and admin](#club32-and-admin)
- [Deployment](#deployment)
- [Still to confirm](#still-to-confirm)
- [Changelog](#changelog)

---

## Features

- **Nine pages:** Home, My Story, Results, Calendar, Bike & Team, Gallery, Sponsors, Club32 and Contact. Live: Home, My Story, Results, Calendar, Contact and Sponsors (via the "Sponsor Lewis" button). The rest are on staging only.
- **Club32 and admin:** a number board with bank-transfer reservations, and a private admin panel (see [below](#club32-and-admin)).
- **Home hero:** a canvas "flip-book" driven by scrolling. It falls back to a static image on small screens and when `prefers-reduced-motion` is set.
- **My Story:** the career timeline, newest season first (2026 at the top), with the long-range goal above it and the story going back in time as the visitor scrolls.
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

## Club32 and admin

Club32 is the supporters' draw: 100 numbers at £32, each valid for the whole season and in the draw before every BSB race weekend.

**How a purchase works**

1. The visitor picks free numbers on `/club32/` and leaves their name and email.
2. The numbers are held for 24 hours (configurable). BlueFox emails the bank details and a personal payment reference such as `C32-K7F3Q`. Lewis gets an alert email.
3. A reminder goes out 6 hours before the hold ends. If nothing is confirmed by then, the numbers are released and the buyer is told.
4. When the transfer arrives, Lewis (or whoever has a login) presses **Payment received** in the admin. The buyer gets a confirmation and their first name appears on the board. Surnames are never shown publicly.

Last season's members can be imported on the admin **Presale** page and emailed a personal link that holds their old number until the presale end date.

**Parts**

| Path | What it is |
| --- | --- |
| `src/pages/club32.astro`, `src/pages/club32/reserved.astro` | Public board and the "how to pay" page |
| `public/api/club32/*.php` | `numbers.php` (board), `reserve.php` (new hold), `order.php` (payment details by reference) |
| `public/api/_lib/club32.php` | Shared code: database, rules, BlueFox emails |
| `public/api/hit.php` | Cookie-free visit counter (daily totals, no IPs stored) |
| `public/admin/index.php` | The admin panel |
| `public/admin/cli.php` | Command line only: `add-user`, `invite`, `set-role`, `set-email`, `set-password`, `list-users`, `sweep` |

**Data and secrets.** The SQLite database is `/var/lib/lewissmartracing/club32.sqlite`, outside the web root, so deploys never touch it. The BlueFox API key stays in `/etc/lewissmartracing/bluefox.env`. The BlueFox email ids are in `C32_MAIL_IDS` in `club32.php` (a line in `/etc/lewissmartracing/club32.env` overrides one). Bank details, price, hold time and prizes are set in the admin **Settings** page.

**Server setup (once).** After the first deploy with these files, run `bash scripts/setup-club32.sh`. It creates the data folder, writes the email ids, adds the cron job (`cli.php sweep` every 10 minutes) and creates the first admin user. nginx needs `index.php` in its `index` list for `/admin/` to load.

**Admin users.** Admins sign in with their email and password, and can reset a forgotten password by email (a one-time link, valid 1 hour). There are two roles: **Admin** runs Club32, draws, visitors and settings; **Developer** can also add, remove and change admins on the **Admins** page (new admins get an invite email to set their own password, valid 7 days). There is always at least one developer. Accounts made before email sign-in keep working with their username until an email is added on their **Account** page. The first developer is made on the server:

```bash
sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php add-user you@example.com developer "Your name"
```

**Admin security.** Every admin page needs a login (hashed passwords, a changed password or removed user ends their other sessions, 5 failed attempts per 15 minutes, `SameSite=Strict` session cookie, CSRF tokens). The panel is not linked from the site and sends `noindex`. It is deliberately *not* listed in `robots.txt`, because that file is public.

**Local development**

```bash
mkdir -p /tmp/lsr/etc /tmp/lsr/data
export LSR_ETC=/tmp/lsr/etc LSR_DATA=/tmp/lsr/data LSR_MAIL_LOG=/tmp/lsr/mail.log
LSR_PASSWORD=devpassword123 php public/admin/cli.php add-user dev@example.com developer Dev
php -S localhost:8080 -t public   # PHP endpoints and /admin/
npm run dev                       # proxies /api and /admin to :8080
```

With `LSR_MAIL_LOG` set, emails are written to that file instead of being sent.

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

### Staging

<https://staging.lewissmartracing.com> has the full site, including pages that are not live yet. It is password protected (HTTP basic auth), every response carries `X-Robots-Tag: noindex, nofollow`, every page has a `noindex` meta tag, and a "Staging" badge sits in the corner. Nothing about staging is listed in a robots.txt or sitemap.

- `npm run build` is the production build. `scripts/prune-staging-only.mjs` removes the pages listed in `stagingOnly` (`src/data/site.ts`: Bike & Team, Gallery, Club32) and fails the build if a live page still links to one. Sponsors stays live for the "Sponsor Lewis" button, but is only in the menu on staging.
- `npm run build:staging` (`PUBLIC_SITE_ENV=staging`) builds every page.
- To put a page live, remove it from `stagingOnly` in both `src/data/site.ts` and `scripts/prune-staging-only.mjs`.

The Deploy workflow deploys both: production first, then staging with a second `lsr-deploy` key jailed to `/var/www/staging.lewissmartracing.com` (secret `DEPLOY_STAGING_SSH_KEY`). Staging PHP uses its own config and database (`/etc/lewissmartracing-staging`, `/var/lib/lewissmartracing-staging`), so it never touches live Club32 data.

**Server setup (once).** Add a DNS A record `staging` → `78.47.73.104` in GoDaddy, then run `bash scripts/setup-staging.sh`. It asks for the staging username, password and first admin email, sets up nginx, TLS, the deploy key and the GitHub secret, and runs a deploy.

The production URL is set by `site` in `astro.config.mjs`, and the canonical and Open Graph URLs are built from it.

## Still to confirm

These items still need confirming with Lewis: 2025/2026 results, current class, stats, calendar, bike spec, team, sponsors and logos, contact email, form endpoint, sponsorship deck PDF, and more photos.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

© Lewis Smart Racing. All rights reserved. The photos and branding are not licensed for reuse.
