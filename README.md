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
- [My Story photos and Gallery reels](#my-story-photos-and-gallery-reels)
- [Tracking (Tragly) and cookie banner](#tracking-tragly-and-cookie-banner)
- [SEO and link previews](#seo-and-link-previews)
- [Club32 and admin](#club32-and-admin)
- [Sponsor logos in emails](#sponsor-logos-in-emails)
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
| `npm run photos -- [folder]` | Turns a folder of photos into My Story timeline WebPs (see [below](#my-story-photos-and-gallery-reels)) |
| `npm run og` | Builds the 1200x630 link preview image of every page (see [below](#seo-and-link-previews)) |

## Project structure

```text
.
├── public/
│   ├── favicon.svg
│   ├── frames/            # Hero flip-book frames (frame-01.webp …)
│   └── images/            # Portrait, bike livery, hero fallback, grid shot
├── scripts/
│   ├── make-frames.mjs    # Photo → 16:9 WebP frame converter
│   └── make-gallery.mjs   # Photo → Gallery WebPs + src/data/gallery.json
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

## My Story photos and Gallery reels

Claude does this when Louis or Lewis send new photos or reels.

**Photos** go on the My Story timeline, under their year.

1. The photos go in `photos-src/story/` (not committed). The file name starts with the year and becomes the caption and alt text, e.g. `2024 Superteens on track.jpg`.
2. `npm run photos` adds "LEWISSMARTRACING.COM" (`scripts/assets/watermark.svg`) to the bottom-right corner of every photo and writes two WebPs to `public/images/story/`: the full photo for the viewer (max 2400 px, quality 82) and a 3:2 thumbnail (960x640). The list goes to `src/data/story-photos.json`.
3. On the timeline, one photo per year is the big one (`lead` in `src/pages/my-story.astro`), the others sit small under it. Clicking any opens the viewer (`src/components/Lightbox.astro`), which pages through all of them in a loop (arrows, keyboard, swipe).

The Gallery page has the reels only.

**Reels** are self-hosted, not Instagram embeds, so the page loads no Instagram scripts or cookies. Each one is a 720p H.264 MP4 with a WebP poster in `public/reels/`, listed newest first in `src/data/reels.json`. To make one from an Instagram link: download it with a current `yt-dlp`, then `ffmpeg -i in.mp4 -vf scale=720:-2 -c:v libx264 -preset slow -crf 26 -maxrate 2500k -bufsize 5000k -pix_fmt yuv420p -c:a aac -b:a 96k -movflags +faststart public/reels/<name>.mp4`, and use a frame at 1 s as the poster.

## SEO and link previews

- **Link previews (Open Graph).** Every page has its own 1200x630 card in `public/images/og/`: the page title in Barlow Condensed on the dark background, with a photo on the right. `npm run og` rebuilds them from the list in `scripts/make-og.mjs`. The text is drawn from glyph outlines (`scripts/assets/barlow-glyphs.json`), so no font needs installing. Pages pass `image="/images/og/<name>.jpg"` to `<Base>`; pages without one use the home card. They are JPG because not every preview (LinkedIn, older WhatsApp) shows WebP.
- **Meta tags.** `src/layouts/Base.astro` sets the title, description, canonical URL, `robots`, Open Graph (with image size and alt) and Twitter card tags. Thank-you and confirmation pages pass `noindex`.
- **Structured data.** Every page carries schema.org JSON-LD: the WebSite, Lewis as a Person (with Instagram and Facebook), the page itself and a breadcrumb.
- **Sitemap and robots.txt.** `scripts/seo-files.mjs` runs after the production build. It writes `sitemap.xml` (every live page that is not noindex, with the Gallery photos and reels as image and video entries) and `robots.txt`. Staging gets neither.
- **Icons.** `favicon.svg`, plus PNGs (`favicon-32.png`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`) and `site.webmanifest`.

**Google Search Console.** Not set up yet. Add a *Domain* property for `lewissmartracing.com`, put the TXT record it shows into the GoDaddy DNS, verify, then submit `https://lewissmartracing.com/sitemap.xml`. Google cannot see anything while the password gate is on, so do this when the gate comes off.

## Tracking (Tragly) and cookie banner

Measurement runs through Tragly (server-side Google Tag Manager), tenant `TRG-6mzqy8`: web container `GTM-5WC2G3W7`, server container `GTM-P98Z3P9F`, first-party host `0vl1ndql.sgtm.tragly.com`, GA4 `G-YS289LQJEK`. Everything (GA4, later Meta etc.) is managed in Tragly and the containers, not in the site code.

- `src/components/Tracking.astro` holds the snippets, copied verbatim from Tragly's signed installation: the ConsentKit cookie banner first, then the Consent Mode v2 default (all denied), then the GTM loader, plus the `<noscript>` in `<body>`. The `tagproof` markers are signed, so do not edit them by hand; get a new snippet from Tragly instead.
- Production only. Staging is never measured.
- Nothing is measured until the visitor accepts analytics cookies. The [privacy notice](/privacy/) lists Google Analytics, ConsentKit and the cookies.
- The banner text comes from the Tragly/ConsentKit template and has not been legally reviewed.

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

**Members' newsletter.** Club32 membership includes a members-only newsletter. When a payment is confirmed (or a paid member is added by hand), the member is added to the private BlueFox list **Club32 Members** (double opt-in off; the list id is looked up by name once, or set `BLUEFOX_C32_LIST_ID`). Buyers can tick "No thanks" on the form; that choice is stored on the order (`orders.newsletter`) and shown in the members CSV. Send the newsletter as a BlueFox campaign to that list.

**Legal pages.** `/privacy/` (whole site) and `/club32/terms/` describe what the code does. They are drafts to be checked before Club32 goes public: the prize-claim deadline, the refund rule, the retention periods, and whether the draw needs a lottery registration (see the note in the CHANGELOG).

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

## Sponsor logos in emails

Emails carry a clickable "Proudly supported by" row of sponsor logos. Each logo opens the sponsor's site in a new window. The block is [`email/sponsor-footer.html`](email/sponsor-footer.html). The logos are 256x128 PNG tiles in the BlueFox gallery folder "Sponsor logos (email)", because email clients cannot show WebP and the site is behind a password while in preview.

| With logos | Without logos (the reader must not be distracted) |
| --- | --- |
| Newsletter templates (LSR - Newsletter, LSR - Club32 race weekend update, LSR - Sponsors monthly update) and every campaign made from them | Newsletter - Confirm subscription |
| Club32 - Payment confirmed, Club32 - Winner, Club32 - Reservation cancelled | Club32 - Reserved (payment details), Payment reminder, Reservation expired, Returning member presale |
| | Admin - Password reset, Admin - Invite, Website enquiry notification and New reservation (internal) |

### Email sources and Gmail dark mode

Every BlueFox transactional email has its source in `email/*.html`. Run `python3 scripts/email-build.py` and paste the result from `email/dist/` (git-ignored) into BlueFox. The build fills in two placeholders: `<!-- @darkmode -->` and `<!-- @sponsors -->`.

The Gmail apps recolour dark emails in dark mode: black turns light grey and #d6f20a turns dark green. The emails keep their look in three ways:

- Every background colour is also set as a `linear-gradient` image.
- Text is wrapped in `<div class="gb-s"><div class="gb-d">`, and only Gmail applies the blend-mode rules to it.
- Buttons are PNG images in the site's button style. To make a new one, run `python3 scripts/email-button.py "LABEL" email/buttons/x.png` and upload it to the "Sponsor logos (email)" gallery folder.

The 3 newsletter templates are built in the visual editor. They have the logo grid, but not the dark-mode fix.

When a sponsor is added or removed:

1. Update `sponsors` in `src/data/site.ts` and the list in `scripts/email-sponsor-logos.py`.
2. Run `python3 scripts/email-sponsor-logos.py <folder>` and upload the new PNGs to the gallery folder.
3. Update `email/sponsor-footer.html`, then paste the new block into every email and template in the "With logos" column.

## Results

The Results page reads `src/data/results.json`, built from the official TSL Timing classifications (<https://www.tsl-timing.com/Results>) for British Superbikes and No Limits Racing. Every row links to its source PDF. To refresh after a race weekend (needs Python 3 and `pdftotext` from poppler-utils):

```bash
python3 scripts/results/crawl.py /tmp/tsl bsb,nolimits 2021,2022,2023,2024,2025,2026
python3 scripts/results/parse.py /tmp/tsl
python3 scripts/results/build.py /tmp/tsl/results.json
```

`crawl.py` downloads every result PDF of every event (cached, so later runs only fetch new ones) and keeps the ones with "Lewis SMART". `parse.py` reads his row: position, class position in mixed-class and endurance races, best lap and endurance team-mates. `build.py` names the championships and writes the JSON. 2020 (Freshman Championship) is not on TSL, so that season shows the summary from the timeline.

## Deployment

The site runs on the Hetzner server (nginx), served from `/var/www/lewissmartracing.com`.

Deploys run from the devbox with [`deploy/deploy.sh`](deploy/deploy.sh). GitHub Actions is switched off (since 13 November 2026, after a stolen GitHub token pushed a workflow that read repository secrets), and GitHub holds no secrets: the deploy key lives only on the devbox.

```bash
deploy/deploy.sh --dry-run
```

```bash
deploy/deploy.sh
```

- It builds the **last commit** (`git archive HEAD`, then `npm ci` and the build in a temporary folder), never uncommitted files, and stops if there are uncommitted changes, if you are not on `main`, or if GitHub has commits on `main` that are not on the devbox.
- With no argument it deploys live and staging, like the old workflow. `deploy/deploy.sh live` or `deploy/deploy.sh staging` does only one. Live needs the confirmation word `deploy-live`.
- It first shows what would be uploaded and deleted (full lists in `~/.cache/lewissmartracing-deploy/`). `--dry-run` stops there.
- It logs in once as `devbox` on 78.47.73.104: the key's passphrase, then the `devbox` user's server password, both typed by Gabriel. Every later step reuses that one connection.
- Checks after the upload: live `/` answers 200 (or 302 while the password gate is on), staging `/` redirects (302) to `/__gate.html`.

Settings, outside the repository, in `~/.config/lewissmartracing/deploy.env`: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY` (the same `devbox` key as trendfingers.com, which lives on the same server).

**Server setup (once).** Run [`deploy/server-setup.sh`](deploy/server-setup.sh) on the server with `sudo`. It gives `/var/www/lewissmartracing.com` and `/var/www/staging.lewissmartracing.com` to the `devbox` user and locks the old GitHub Actions user `lsr-deploy` (its keys were repository secrets).

### Staging

<https://staging.lewissmartracing.com> has the full site, including pages that are not live yet. It sits behind the same password gate as the live site (same gate page and password, its own 30-day cookie), every response carries `X-Robots-Tag: noindex, nofollow`, every page has a `noindex` meta tag, and a "Staging" badge sits in the corner. Nothing about staging is listed in a robots.txt or sitemap.

- `npm run build` is the production build. `scripts/prune-staging-only.mjs` removes the pages listed in `stagingOnly` (`src/data/site.ts`: Bike & Team, Gallery, Club32) and fails the build if a live page still links to one. Sponsors stays live for the "Sponsor Lewis" button, but is only in the menu on staging.
- `npm run build:staging` (`PUBLIC_SITE_ENV=staging`) builds every page.
- To put a page live, remove it from `stagingOnly` in both `src/data/site.ts` and `scripts/prune-staging-only.mjs`.

`deploy/deploy.sh` deploys both: production first, then staging. Staging PHP uses its own config and database (`/etc/lewissmartracing-staging`, `/var/lib/lewissmartracing-staging`), so it never touches live Club32 data.

**Server setup (once).** Add a DNS A record `staging` → `78.47.73.104` in GoDaddy, then run `LSR_SSH_KEY=~/.ssh/hetzner.tf bash scripts/setup-staging.sh` on the devbox. It was run once (it asked for the gate password, not stored in the repo), set up nginx and TLS, and copied the live admin users (same email and password) into the staging database. No orders, members or stats were copied. Its deploy-key and GitHub-secret steps belong to the old Actions deploy, so the script now stops before doing anything.

The production URL is set by `site` in `astro.config.mjs`, and the canonical and Open Graph URLs are built from it.

## Still to confirm

These items still need confirming with Lewis: 2025/2026 results, current class, stats, calendar, bike spec, team, sponsors and logos, contact email, form endpoint, sponsorship deck PDF, and more photos.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

© Lewis Smart Racing. All rights reserved. The photos and branding are not licensed for reuse.
