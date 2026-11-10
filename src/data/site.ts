// Single source of truth for site content.
// Anything marked `null` or TBC is still to be confirmed with Lewis. Do not invent it.

export const site = {
  name: 'Lewis Smart',
  number: '32',
  tagline: 'From Freshman to Factory Triumph',
  description:
    'Lewis Smart #32, British motorcycle road racer from Sandy, Bedfordshire. From a Honda CB500 in the Freshman Championship to racing with Triumph. Follow the story and partner with the team.',
  email: null as string | null, // TBC
  instagram: 'https://www.instagram.com/lewis.smart32/',
  facebook: 'https://www.facebook.com/people/Lewis-Smart-32/100076010736822/',
  sponsorDeck: null as string | null, // e.g. '/lewis-smart-32-sponsorship.pdf'
  formEndpoint: '/api/enquiry.php' as string | null, // PHP handler on the server -> BlueFox (public/api/enquiry.php)
};

// Build target. `npm run build` makes the public site; `npm run build:staging` sets PUBLIC_SITE_ENV=staging
// and builds everything, including pages that are not finished yet (staging.lewissmartracing.com, password protected).
export const isStaging = import.meta.env.PUBLIC_SITE_ENV === 'staging';
// Pages only on staging until they are ready. scripts/prune-staging-only.mjs removes them from the production build
// and fails the build if a production page still links to one.
export const stagingOnly = ['/bike-and-team/', '/gallery/', '/club32/'];
// Sponsors stays live (the "Sponsor Lewis" button links to it) but is only in the menu on staging.
const stagingOnlyNav = [...stagingOnly, '/sponsors/'];
export const isLive = (href: string) => isStaging || !stagingOnly.some((p) => href.startsWith(p));

const allNav = [
  { href: '/', label: 'Home' },
  { href: '/my-story/', label: 'My Story' },
  { href: '/results/', label: 'Results' },
  { href: '/calendar/', label: 'Calendar' },
  { href: '/bike-and-team/', label: 'Bike & Team' },
  { href: '/gallery/', label: 'Gallery' },
  { href: '/sponsors/', label: 'Sponsors' },
  { href: '/club32/', label: 'Club32' },
  { href: '/contact/', label: 'Contact' },
];
export const nav = isStaging ? allNav : allNav.filter((n) => !stagingOnlyNav.includes(n.href));

// Club32: the supporters' number draw. Prices, prizes, bank details and dates are live data
// managed in the admin panel (/admin/); this is only the fixed explanatory copy.
// Facts below are from Lewis's own Club32 message. Anything else stays TBC.
export const club32 = {
  api: '/api/club32',
  numbers: 100,
  steps: [
    { title: 'Pick your numbers', body: 'Choose any free number from 1 to 100. Have as many as you like.' },
    { title: 'Reserve them', body: 'Leave your name and email. Your numbers are held for you while you pay.' },
    { title: 'Pay by bank transfer', body: 'We email you the bank details and your own payment reference. Pay within the hold time.' },
    { title: 'In every draw', body: 'Once the payment is confirmed, your numbers are in the draw before every British Superbikes race weekend, all season.' },
  ],
};

// Hero flip-book: number of frames in /public/frames (frame-01.webp ...).
// 0 = no frames yet, the hero shows the static fallback.
export const heroFrameCount = 0;

// Placeholder values until confirmed by Lewis.
export const stats = [
  { label: 'Seasons raced', value: '07', note: '2020 to 2026' },
  { label: 'Race wins', value: null, note: 'TBC' },
  { label: 'Podiums', value: null, note: 'TBC' },
  { label: 'Titles', value: '03', note: 'Sportsman 2022, Club 600 Endurance 2023, National 600 Endurance 2024' },
];

export type TimelineEntry = {
  year: string;
  title: string;
  bike?: string;
  series: string;
  result: string;
  body: string;
};

// Oldest first: the rise story unfolds while scrolling.
export const timeline: TimelineEntry[] = [
  {
    year: '2020',
    title: 'The first season',
    bike: 'Honda CB500',
    series: 'Freshman Championship',
    result: '3rd overall',
    body:
      'After studying as a race technician at Silverstone Motorsport College, and inspired by a racing classmate, Lewis lined up for his first season. The COVID-delayed calendar did not slow him down: several race wins and 3rd overall in the Freshman Championship.',
  },
  {
    year: '2021',
    title: 'Stepping up',
    series: 'Sportsman Championship',
    result: 'Podiums + BSB wildcard',
    body:
      'A move up to the Sportsman Championship brought regular podiums, and a late-season wildcard in British Superbikes Junior Supersport gave him his first taste of the national paddock.',
  },
  {
    year: '2022',
    title: 'Champion',
    bike: 'Honda CB500',
    series: 'CB500 Sportsman Championship',
    result: 'Champion',
    body:
      'Racing as #23, Lewis won the CB500 Sportsman Championship. Alongside the title campaign he raced in Junior Supersport, finishing in the 20s and learning fast against the best young riders in the country.',
  },
  {
    year: '2023',
    title: 'Breakthrough',
    series: 'Junior Supersport',
    result: 'First podium, a win + Club 600 Endurance title',
    body:
      'His first Junior Supersport podium came mid-season, and he ended the year the best way possible: with a race win. He also won the Club 600 Endurance championship.',
  },
  {
    year: '2024',
    title: 'Front runner',
    bike: 'Kawasaki ZX-4RR',
    series: 'BSB Kawasaki Superteens',
    result: '3rd in Superteens + National 600 Endurance champion',
    body:
      'On the Kawasaki ZX-4RR Lewis delivered consistent wins and podiums, including victory at the July round, to finish 3rd in the BSB Kawasaki Superteens championship. He also won the NLR National 600 Endurance series. That form caught the attention of Triumph.',
  },
  {
    year: '2025',
    title: 'Racing with Triumph',
    bike: 'Triumph Daytona 660',
    series: 'Pirelli National Sportbike',
    result: 'Season-best 5th at Brands Hatch',
    body:
      'Lewis joined the Macadam Triumph Factory Racing team in the Pirelli National Sportbike Championship on the Triumph Daytona 660, alongside Katie Hand, Jayden Martin and Brodie Gawith. He closed his debut season with a season-best 5th place at the Brands Hatch finale.',
  },
  {
    year: '2026',
    title: 'Top 10 regular',
    bike: 'Triumph',
    series: 'Pirelli British Sportbike',
    result: 'Consistent top 10s, best P4 at Oulton Park',
    body:
      'A season of consistent top 10 finishes in the Pirelli British Sportbike Championship at Bennetts British Superbikes, with a best of 4th at Oulton Park. The goal for 2027: turning that pace into podiums.',
  },
];

export const milestones = [
  { year: '2020', tag: 'Rookie year', title: 'First wins', body: 'Several race wins and 3rd overall in the Freshman Championship on a Honda CB500.' },
  { year: '2022', tag: 'Title', title: 'CB500 Sportsman Champion', body: 'Crowned CB500 Sportsman Champion, racing as #23, while gaining Junior Supersport experience at national level.' },
  { year: '2026', tag: 'Present', title: 'Chasing podiums', body: 'Consistent top 10 finishes in the Pirelli British Sportbike Championship, with a best of 4th at Oulton Park. 2027 target: the podium.' },
];

export type CalendarEvent = {
  kind: 'test' | 'round';
  round: string; // official BSB round label
  circuit: string;
  layout: string;
  start: string; // ISO date, first day of the event
  end: string; // ISO date, last day (race day)
  tickets?: string;
  info: string; // official event page
};

// 2027 British Superbike Championship calendar, from britishsuperbike.com/calendar (fetched 10 Nov 2026).
// Round 9 at Assen (NL, 17-19 Sep) is left out on purpose: Lewis is not racing there.
// Official round numbers are kept so they match the BSB timetable.
const BSB = 'https://www.britishsuperbike.com/calendar/2027';
export const calendarSeries = 'British Superbike Championship';
export const calendarYear = 2027;
export const calendar: CalendarEvent[] = [
  { kind: 'test', round: 'Official Test', circuit: 'Donington Park', layout: 'GP', start: '2027-03-26', end: '2027-03-27', info: `${BSB}/t1-donington-park` },
  { kind: 'test', round: 'Official Test', circuit: 'Oulton Park', layout: 'International', start: '2027-04-20', end: '2027-04-21', info: `${BSB}/t2-oulton-park` },
  { kind: 'round', round: 'Round 1', circuit: 'Oulton Park', layout: 'International', start: '2027-05-01', end: '2027-05-03', tickets: 'https://www.oultonpark.co.uk/2027/may/british-superbike-championship', info: `${BSB}/r1-oulton-park` },
  { kind: 'round', round: 'Round 2', circuit: 'Donington Park', layout: 'GP', start: '2027-05-21', end: '2027-05-23', tickets: 'https://www.donington-park.co.uk/2027/may/british-superbike-championship', info: `${BSB}/r2-donington-park` },
  { kind: 'round', round: 'Round 3', circuit: 'Knockhill', layout: 'Full', start: '2027-06-18', end: '2027-06-20', tickets: 'https://www.knockhill.com/events/British-Superbike-Championship', info: `${BSB}/r3-knockhill` },
  { kind: 'round', round: 'Round 4', circuit: 'Snetterton', layout: '300', start: '2027-07-02', end: '2027-07-04', tickets: 'https://www.snetterton.co.uk/2027/july/british-superbike-championship', info: `${BSB}/r4-snetterton` },
  { kind: 'round', round: 'Round 5', circuit: 'Brands Hatch', layout: 'GP', start: '2027-07-16', end: '2027-07-18', tickets: 'https://www.brandshatch.co.uk/2027/july/british-superbike-championship', info: `${BSB}/r5-brands-hatch` },
  { kind: 'round', round: 'Round 6', circuit: 'Oulton Park', layout: 'International', start: '2027-07-30', end: '2027-08-01', tickets: 'https://www.oultonpark.co.uk/2027/august/british-superbike-championship', info: `${BSB}/r6-oulton-park` },
  { kind: 'round', round: 'Round 7', circuit: 'Thruxton', layout: 'Full', start: '2027-08-06', end: '2027-08-08', tickets: 'https://thruxtonracing.co.uk/racing/bsb', info: `${BSB}/r7-thruxton` },
  { kind: 'round', round: 'Round 8', circuit: 'Cadwell Park', layout: 'Full', start: '2027-08-28', end: '2027-08-30', tickets: 'https://www.cadwellpark.co.uk/2027/august/british-superbike-championship', info: `${BSB}/r8-cadwell-park` },
  { kind: 'round', round: 'Round 10', circuit: 'Donington Park', layout: 'GP', start: '2027-10-01', end: '2027-10-03', tickets: 'https://www.donington-park.co.uk/2027/october/british-superbike-championship', info: `${BSB}/r10-donington-park` },
  { kind: 'round', round: 'Round 11', circuit: 'Brands Hatch', layout: 'GP', start: '2027-10-15', end: '2027-10-17', tickets: 'https://www.brandshatch.co.uk/2027/october/british-superbike-championship', info: `${BSB}/r11-brands-hatch` },
];

// Next race weekend, worked out at build time: the first round whose last day has not passed.
// `date` is the weekend's first day (08:00 UK time), which the countdowns run to.
const today = new Date().toISOString().slice(0, 10);
const upcoming = calendar.find((e) => e.kind === 'round' && e.end >= today);
export const nextRace: { round: string; circuit: string; series: string; date: string } | null = upcoming
  ? { round: upcoming.round, circuit: upcoming.circuit, series: calendarSeries, date: `${upcoming.start}T08:00:00+01:00` }
  : null;

export const sponsors: { name: string; logo: string; url?: string; tier: 'title' | 'partner' | 'supporter' }[] = [];
