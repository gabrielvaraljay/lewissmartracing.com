// Single source of truth for site content.
// Anything marked `null` or TBC is still to be confirmed with Lewis. Do not invent it.

export const site = {
  name: 'Lewis Smart',
  number: '32',
  tagline: 'From Freshman to Factory Triumph',
  description:
    'Lewis Smart #32, British motorcycle road racer. From a Honda CB500 in the Freshman Championship to racing with Triumph. Follow the story and partner with the team.',
  email: null as string | null, // TBC
  instagram: 'https://www.instagram.com/lewis.smart32/',
  facebook: 'https://www.facebook.com/people/Lewis-Smart-32/100076010736822/',
  sponsorDeck: null as string | null, // e.g. '/lewis-smart-32-sponsorship.pdf'
  formEndpoint: null as string | null, // e.g. Formspree / Web3Forms URL
};

export const nav = [
  { href: '/', label: 'Home' },
  { href: '/my-story/', label: 'My Story' },
  { href: '/results/', label: 'Results' },
  { href: '/calendar/', label: 'Calendar' },
  { href: '/bike-and-team/', label: 'Bike & Team' },
  { href: '/gallery/', label: 'Gallery' },
  { href: '/sponsors/', label: 'Sponsors' },
  { href: '/contact/', label: 'Contact' },
];

// Hero flip-book: number of frames in /public/frames (frame-01.webp ...).
// 0 = no frames yet, the hero shows the static fallback.
export const heroFrameCount = 0;

// Placeholder values until confirmed by Lewis.
export const stats = [
  { label: 'Seasons raced', value: '06', note: '2020 to 2025' },
  { label: 'Race wins', value: null, note: 'TBC' },
  { label: 'Podiums', value: null, note: 'TBC' },
  { label: 'Titles', value: '01', note: '2022 Sportsman' },
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
    series: 'Sportsman Championship',
    result: 'Champion',
    body:
      'Lewis won the Sportsman Championship. Alongside the title campaign he raced in Junior Supersport, finishing in the 20s and learning fast against the best young riders in the country.',
  },
  {
    year: '2023',
    title: 'Breakthrough',
    series: 'Junior Supersport',
    result: 'First podium + a win',
    body:
      'His first Junior Supersport podium came mid-season, and he ended the year the best way possible: with a race win.',
  },
  {
    year: '2024',
    title: 'Front runner',
    bike: 'Kawasaki ZX-4RR',
    series: 'Junior Supersport',
    result: '3rd in the championship',
    body:
      'On the Kawasaki ZX-4RR Lewis delivered consistent wins and podiums to finish 3rd in the championship. That form caught the attention of Triumph.',
  },
  {
    year: '2025',
    title: 'Racing with Triumph',
    bike: 'Triumph',
    series: 'TBC',
    result: 'Targeting the title',
    body:
      'A new chapter with Triumph. The goal is simple: consistent podiums and a serious run at the championship.',
  },
];

export const milestones = [
  { year: '2020', tag: 'Rookie year', title: 'First wins', body: 'Several race wins and 3rd overall in the Freshman Championship on a Honda CB500.' },
  { year: '2022', tag: 'Title', title: 'Sportsman Champion', body: 'Crowned Sportsman Champion while gaining Junior Supersport experience at national level.' },
  { year: '2025', tag: 'Present', title: 'Racing with Triumph', body: 'After 3rd in the 2024 championship, Lewis joins forces with Triumph to chase the title.' },
];

// Fill in when the calendar is confirmed. ISO date of race day.
export const nextRace: { round: string; circuit: string; series: string; date: string } | null = null;

export const sponsors: { name: string; logo: string; url?: string; tier: 'title' | 'partner' | 'supporter' }[] = [];
