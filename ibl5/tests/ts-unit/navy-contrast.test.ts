import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ibl5Root = fileURLToPath(new URL('../../', import.meta.url));

const inputCss = readFileSync(`${ibl5Root}design/input.css`, 'utf8');

function parseTokens(css: string): Map<string, string> {
  const tokens = new Map<string, string>();
  const re = /^\s*--color-(navy|gray|accent)-(\d{3}):\s*(#[0-9a-fA-F]{6})\s*;/gm;
  for (const m of css.matchAll(re)) {
    tokens.set(`${m[1]}-${m[2]}`, m[3]);
  }
  return tokens;
}

const tokens = parseTokens(inputCss);

/** Resolve a token key (e.g. `navy-800`) to hex; a missing key throws. */
function tok(key: string): string {
  const hex = tokens.get(key);
  if (hex === undefined) {
    throw new Error(`Token ${key} not found in design/input.css`);
  }
  return hex;
}

type Rgb = [number, number, number];
type Color = string | Rgb;

function toChannels(c: Color): Rgb {
  if (typeof c !== 'string') return c;
  const h = c.replace('#', '');
  return [
    parseInt(h.slice(0, 2), 16),
    parseInt(h.slice(2, 4), 16),
    parseInt(h.slice(4, 6), 16),
  ];
}

function luminance(c: Color): number {
  const [r, g, b] = toChannels(c).map((v) => {
    const s = v / 255;
    return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  }) as Rgb;
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Per-channel alpha*fg + (1-alpha)*bg in sRGB space, unrounded. */
function composite(fg: Color, alpha: number, bg: Color): Rgb {
  const f = toChannels(fg);
  const g = toChannels(bg);
  return f.map((v, i) => alpha * v + (1 - alpha) * (g[i] as number)) as Rgb;
}

function contrast(a: Color, b: Color): number {
  const la = luminance(a);
  const lb = luminance(b);
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

// --- Source file set (tracked plus untracked-not-ignored) ---

const SCAN_DIRS = ['design/', 'classes/', 'modules/', 'includes/'];
const SCAN_EXTS = ['.css', '.php', '.js', '.ts', '.html'];

function scanFiles(): string[] {
  const out = execFileSync('git', ['ls-files', '-co', '--exclude-standard'], {
    cwd: ibl5Root,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });
  return out
    .split('\n')
    .filter((p) => p.length > 0)
    .filter(
      (p) => SCAN_DIRS.some((d) => p.startsWith(d)) || p === 'themes/IBL/theme.php',
    )
    .filter((p) => SCAN_EXTS.some((e) => p.endsWith(e)));
}

function readSource(rel: string): string | null {
  try {
    return readFileSync(`${ibl5Root}${rel}`, 'utf8');
  } catch {
    return null; // listed by git but deleted in the working tree
  }
}

function ruleBody(css: string, selector: string): string {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const m = new RegExp(`${escaped}\\s*\\{([^}]*)\\}`).exec(css);
  if (m === null) {
    throw new Error(`Rule ${selector} not found`);
  }
  return m[1] as string;
}

// --- Tests ---

describe('navy contrast helpers', () => {
  it('contrast helper reproduces WCAG reference ratios', () => {
    expect(contrast('#ffffff', '#000000')).toBeCloseTo(21, 2);
    const mid = contrast('#777777', '#ffffff');
    expect(mid).toBeLessThan(4.5);
    expect(mid).toBeGreaterThan(4.4);
  });
});

describe('navy tokens', () => {
  it('parses the four navy tokens from input.css', () => {
    const navyKeys = [...tokens.keys()].filter((k) => k.startsWith('navy-')).sort();
    expect(navyKeys).toEqual(['navy-600', 'navy-700', 'navy-800', 'navy-900']);
  });

  it('navy-800 is #1e293b', () => {
    expect(tok('navy-800').toLowerCase()).toBe('#1e293b');
  });

  it('navy ramp luminance strictly increases from 900 to 600', () => {
    const l900 = luminance(tok('navy-900'));
    const l800 = luminance(tok('navy-800'));
    const l700 = luminance(tok('navy-700'));
    const l600 = luminance(tok('navy-600'));
    expect(l900).toBeLessThan(l800);
    expect(l800).toBeLessThan(l700);
    expect(l700).toBeLessThan(l600);
  });
});

interface Pair {
  site: string;
  fgLabel: string;
  bgLabel: string;
  fg: () => Color;
  bg: () => Color;
  threshold: number;
}

const white = '#ffffff';
const black = '#000000';
const n800 = (): string => tok('navy-800');

function pair(
  site: string,
  fgLabel: string,
  fg: () => Color,
  bgLabel: string,
  bg: () => Color,
  threshold = 4.5,
): Pair {
  return { site, fgLabel, bgLabel, fg, bg, threshold };
}

const tokFn = (key: string) => (): string => tok(key);

// Excluded pairs (not asserted):
// - `.ibl-btn:disabled` opacity .6: WCAG 1.4.3 exempts inactive controls.
// - `.last-sim-recap__record-sep` alpha .3: decorative separator.
// - `game-boxscore.css` scoreboard `__meta` / `__at`: background is a DB team colour; its
//   `var(--navy-*)` fallback is already covered by the white-on-navy-900/800 rows.
const pairs: Pair[] = [
  pair('.ibl-tooltip::after, h2h series tooltip', 'white', () => white, 'navy-900', tokFn('navy-900')),
  pair('news meta over gradient dark end', 'gray-300', tokFn('gray-300'), 'navy-900', tokFn('navy-900')),
  pair('.sco-log__message', 'gray-400', tokFn('gray-400'), 'navy-900', tokFn('navy-900')),
  pair('news links over gradient dark end', 'accent-300', tokFn('accent-300'), 'navy-900', tokFn('navy-900')),
  pair('news links over gradient dark end', 'accent-400', tokFn('accent-400'), 'navy-900', tokFn('navy-900')),
  pair('.dc-card__pos-badge, __pos-chip, table headers', 'white', () => white, 'navy-800', n800),
  pair('nav menu links', 'gray-200', tokFn('gray-200'), 'navy-800', n800),
  pair('.nav-trigger, news meta', 'gray-300', tokFn('gray-300'), 'navy-800', n800),
  pair('.ibl-tab, nav League/username labels', 'gray-400', tokFn('gray-400'), 'navy-800', n800),
  pair('visited nav links, topic hover', 'accent-300', tokFn('accent-300'), 'navy-800', n800),
  pair('news header links', 'accent-400', tokFn('accent-400'), 'navy-800', n800),
  pair('Sim League label', 'accent-500', tokFn('accent-500'), 'navy-800', n800),
  pair('.ibl-card__subtitle', 'white@.7/navy-800', () => composite(white, 0.7, n800()), 'navy-800', n800),
  pair('.topic-card stats, .last-sim-recap__meta', 'white@.6/navy-800', () => composite(white, 0.6, n800()), 'navy-800', n800),
  pair('.last-sim-recap__sub', 'white@.55/navy-800', () => composite(white, 0.55, n800()), 'navy-800', n800),
  pair('.nav-login-input::placeholder', 'gray-400', tokFn('gray-400'), 'white@.05/navy-800', () => composite(white, 0.05, n800())),
  pair('.nav-select, .nav-logout-btn', 'white', () => white, 'white@.10/navy-800', () => composite(white, 0.1, n800())),
  pair('news header .ibl-badge', 'accent-300', tokFn('accent-300'), 'white@.15/navy-800', () => composite(white, 0.15, n800())),
  pair('thead th.h2h-col-hover', 'white', () => white, 'white@.18/navy-800', () => composite(white, 0.18, n800())),
  pair('mobile League strip (bg-black/20)', 'gray-400', tokFn('gray-400'), 'black@.20/navy-800', () => composite(black, 0.2, n800())),
  pair('forms.css primary hover gradient, demo-403.php hover', 'white', () => white, 'navy-700', tokFn('navy-700')),
  pair('leaderboards.css th.sorted-col', 'white', () => white, 'navy-600', tokFn('navy-600')),
  pair('last-sim-recap __mom-bar-shape--neg, __inj-dot--opp (non-text graphic)', 'navy-700', tokFn('navy-700'), 'white', () => white, 3.0),
  pair('free-agency.css sort icons (non-text icon)', 'white@.7/navy-800', () => composite(white, 0.7, n800()), 'navy-800', n800, 3.0),
];

describe('text-on-navy pairs meet WCAG AA', () => {
  it.each(pairs.map((p) => [`${p.site}: ${p.fgLabel} on ${p.bgLabel}`, p] as const))(
    '%s',
    (_name, p) => {
      const ratio = contrast(p.fg(), p.bg());
      expect(
        ratio,
        `contrast ${ratio.toFixed(2)} < ${p.threshold} for ${p.site}`,
      ).toBeGreaterThanOrEqual(p.threshold);
    },
  );
});

describe('navy-as-text on light surfaces meets WCAG AA', () => {
  const textKeys = ['navy-900', 'navy-800', 'navy-700', 'navy-600'];
  const surfaces: Array<[string, () => string]> = [
    ['#ffffff', () => white],
    ['gray-100', tokFn('gray-100')],
    ['#eeeeee', () => '#eeeeee'],
  ];
  const cases = textKeys.flatMap((k) =>
    surfaces.map(([label, hex]) => [`${k} text on ${label}`, k, hex] as const),
  );
  it.each(cases)('%s', (_name, key, surface) => {
    const ratio = contrast(tok(key), surface());
    expect(ratio, `contrast ${ratio.toFixed(2)} < 4.5`).toBeGreaterThanOrEqual(4.5);
  });

  // Mutation proof: a navy-600 lightened to #94a3b8 must fail on every light surface.
  it('mutant navy-600 #94a3b8 fails on all three light surfaces', () => {
    for (const [label, hex] of surfaces) {
      const ratio = contrast('#94a3b8', hex());
      expect(ratio, `#94a3b8 on ${label} unexpectedly ${ratio.toFixed(2)}`).toBeLessThan(4.5);
    }
  });
});

describe('axe colour-contrast ratchet stays closed', () => {
  // Mutation proof for the axe rows: white header text on a navy-800 of #94a3b8 is ~2.6:1.
  it('mutant navy-800 #94a3b8 drops white header text below AA', () => {
    const ratio = contrast(white, '#94a3b8');
    expect(ratio).toBeGreaterThan(2.5);
    expect(ratio).toBeLessThan(2.7);
  });

  // Pages whose navy table headers currently pass axe must never be allowlisted to hide a
  // navy regression. The spec file itself is owned elsewhere and is read, never edited.
  it("KNOWN_FAILING['color-contrast'] excludes navy-header pages", () => {
    const spec = readFileSync(`${ibl5Root}tests/e2e/smoke/accessibility.spec.ts`, 'utf8');
    const block = spec.match(/'color-contrast':\s*new Set\(\[([\s\S]*?)\]\)/);
    expect(block, "KNOWN_FAILING['color-contrast'] block not found").not.toBeNull();
    const entries = (block?.[1] ?? '')
      .split('\n')
      .filter((line) => !line.trim().startsWith('//'))
      .flatMap((line) => [...line.matchAll(/'([^']+)'/g)].map((m) => m[1]));
    expect(entries.length).toBeGreaterThan(0);
    expect(entries).not.toContain('standings');
    expect(entries).not.toContain('career leaderboards');
  });
});

describe('navy source hygiene', () => {
  it('no stale navy hex in source', () => {
    const stale = ['#0a0f1a', '#1a2234', '#243044'];
    const offenders: string[] = [];
    for (const rel of scanFiles()) {
      const text = readSource(rel);
      if (text === null) continue;
      const lower = text.toLowerCase();
      for (const hex of stale) {
        if (lower.includes(hex)) offenders.push(`${rel}: ${hex}`);
      }
    }
    expect(offenders).toEqual([]);
  });

  it('#111827 appears only on the --color-gray-900 line', () => {
    const hits: string[] = [];
    for (const rel of scanFiles()) {
      const text = readSource(rel);
      if (text === null) continue;
      text.split('\n').forEach((line, i) => {
        if (/#111827/i.test(line)) hits.push(`${rel}:${i + 1}:${line.trim()}`);
      });
    }
    expect(hits).toHaveLength(1);
    const [hit] = hits;
    expect(hit).toMatch(/^design\/input\.css:\d+:/);
    expect(hit?.replace(/^design\/input\.css:\d+:/, '')).toMatch(
      /^--color-gray-900:\s*#111827;/,
    );
  });

  it('navy Navigation views carry no text-gray-500', () => {
    const views = [
      'classes/Navigation/Views/MobileNavView.php',
      'classes/Navigation/Views/DesktopNavView.php',
      'classes/Navigation/Views/TeamsDropdownView.php',
      'classes/Navigation/Views/LoginFormView.php',
    ];
    const offenders = views.filter((v) =>
      readFileSync(`${ibl5Root}${v}`, 'utf8').includes('text-gray-500'),
    );
    expect(offenders).toEqual([]);
  });

  it('on-navy CSS sites use the token-following values', () => {
    const nav = readFileSync(`${ibl5Root}design/components/navigation.css`, 'utf8');
    const news = readFileSync(`${ibl5Root}design/components/news.css`, 'utf8');

    const placeholder = ruleBody(nav, '.nav-login-input::placeholder');
    expect(placeholder).toContain('var(--gray-400)');
    expect(placeholder).not.toContain('gray-500');

    const card = ruleBody(nav, '.nav-dropdown__card');
    expect(card).toContain('color-mix(in srgb, var(--navy-800) 95%, transparent)');
    expect(card).not.toContain('rgba(30, 41, 59');

    const badge = ruleBody(news, '.news-article__header .ibl-badge');
    expect(badge).toContain('color: var(--accent-300)');
  });
});
