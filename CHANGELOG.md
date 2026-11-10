# Changelog

All notable changes to lewissmartracing.com are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Production now shows only the finished pages: Home, My Story, Results, Calendar and Contact in the menu, plus Sponsors through the "Sponsor Lewis" button. Bike & Team, Gallery and Club32 are on staging only.

### Added

- **Club32** (`/club32/`): a 1-100 number board for the supporters' draw. Visitors pick numbers, reserve them for 24 hours and pay by bank transfer with their own reference (`C32-XXXXX`). Only first names are shown. Returning members get a personal presale link that holds their old number.
- **Admin panel** (`/admin/`, login required, not indexed): confirm payments, extend or cancel holds, add cash sales, member board and CSV export, presale invitations, random or manual draws with a reveal screen for the live videos, winner emails, settings (bank details, price, hold time, sales on/off) and an activity log.
- Cookie-free visit counter (`/api/hit.php`) with a Visitors page in the admin.
- Club32 emails through BlueFox: payment details, reminder, expiry, confirmation, cancellation, new-reservation alert to Lewis, presale invitation and winner.
- `scripts/setup-club32.sh` for the one-off server setup (database folder, email ids, cron, first admin user).
- Admin sign-in by email and password, with "Forgot password?" (one-time reset link by email) and an Account page for your own name, email and password.
- Admin and Developer roles: developers add admins on the Admins page (invite email to set a password), change roles and remove admins.
- Club32 members' newsletter: paid members are added to the BlueFox "Club32 Members" list unless they tick "No thanks" on the form; the confirmation email mentions it.
- Privacy notice (`/privacy/`, linked from the footer, the contact form and the newsletter form) and Club32 terms (`/club32/terms/`). Drafts: confirm the prize-claim and refund rules, and whether the draw needs a small society lottery registration with the local council (Gambling Act 2005), before going public.
- Professional README covering the stack, setup, scripts, project structure, content editing, the design system, hero frames and deployment.
- This changelog.
- Home hero: "Big Announcement Coming Soon" panel with an email-only newsletter signup (`NewsletterSignup` component). Sign-ups go to the BlueFox "Website Newsletter" form and the "Newsletter" subscriber list.
- Newsletter double opt-in: after signing up, visitors land on `/newsletter/check-your-inbox/` (confirm, save 32@lewissmartracing.com, check spam). The confirmation email comes from 32@lewissmartracing.com, and the link leads to `/newsletter/confirmed/`.
- 2027 race calendar from the official British Superbike Championship calendar: two official tests and every round except Round 9 at Assen (Lewis is not racing there), with ticket and event links. The home page and Calendar countdowns now run to the next round (Round 1, Oulton Park, 1 May 2027).
- Staging site (staging.lewissmartracing.com): full site behind a password and noindex. `npm run build:staging`, a second jailed deploy key, and `scripts/setup-staging.sh` for the one-off server setup.
- Sponsor logos on the Sponsors page (Official partners): BAS Group, Amethyst, Strive Performance, Green Power Motorsports, Arai, R&G and Uggly & Co, each on its own brand colour. Each logo links to the sponsor's website (Instagram for Strive Performance and Green Power Motorsports). "What sponsors get" now includes newsletter features.
- Results page: 165 races from 2021 to 2026 from the official TSL Timing classifications (BSB Junior Supersport, Superteens, National and British Sportbike, plus No Limits Racing sprint and endurance). Season tabs with races, wins, podiums and best BSB finish, class positions for mixed-class and endurance races, best laps, and a link to each source PDF. `scripts/results/` refreshes the data.
- GitHub Actions deploy workflow: builds the site and rsyncs it to the Hetzner server on every push to `main`, using a dedicated deploy key jailed to the site directory.

### Changed

- My Story timeline is now newest first: 2026 is at the top (below the British Superbikes goal) and the story goes back in time to 2020.

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
