import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const ibl5Root = fileURLToPath(new URL('../../', import.meta.url));

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

function contrast(a: Color, b: Color): number {
  const la = luminance(a);
  const lb = luminance(b);
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

function ruleBody(css: string, selector: string): string {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const m = new RegExp(`${escaped}\\s*\\{([^}]*)\\}`).exec(css);
  if (m === null) {
    throw new Error(`Rule ${selector} not found`);
  }
  return m[1] as string;
}

const inputCss = readFileSync(`${ibl5Root}design/input.css`, 'utf8');
const tokensCss = readFileSync(`${ibl5Root}design/tokens/tokens.css`, 'utf8');
const cardsCss = readFileSync(`${ibl5Root}design/components/cards.css`, 'utf8');

// palette: --color-success-700: #15803d;  ->  'success-700' => '#15803d'
const PALETTE_RE = /--color-((?:success|warning)-\d+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/g;
// alias:   --warning-700: var(--color-warning-700);  ->  'warning-700' => 'warning-700'
const ALIAS_RE = /--((?:success|warning)-\d+)\s*:\s*var\(--color-((?:success|warning)-\d+)\)\s*;/g;
// declaration inside a rule body; the lead class excludes background-color / border-color
const COLOR_DECL_RE = /(?:^|[;{\s])color\s*:\s*var\(--((?:success|warning)-\d+)\)/;
const BG_DECL_RE = /(?:^|[;{\s])background\s*:\s*var\(--((?:success|warning)-\d+)\)/;

function parseMap(css: string, re: RegExp): Map<string, string> {
  const map = new Map<string, string>();
  for (const m of css.matchAll(re)) {
    map.set(m[1] as string, m[2] as string);
  }
  return map;
}

const palette = parseMap(inputCss, PALETTE_RE);
const aliases = parseMap(`${inputCss}\n${tokensCss}`, ALIAS_RE);

function resolve(name: string): string {
  const paletteName = aliases.get(name);
  if (paletteName === undefined) {
    throw new Error('unresolved token --' + name);
  }
  const hex = palette.get(paletteName);
  if (hex === undefined) {
    throw new Error('unresolved token --' + name);
  }
  return hex;
}

function alertPair(selector: string): { fg: string; bg: string } {
  const body = ruleBody(cardsCss, selector);
  const fg = COLOR_DECL_RE.exec(body);
  const bg = BG_DECL_RE.exec(body);
  if (fg === null || bg === null) {
    throw new Error('no text color declaration in ' + selector);
  }
  return { fg: resolve(fg[1] as string), bg: resolve(bg[1] as string) };
}

describe('alert contrast', () => {
  it('contrast helper returns 21 for black on white', () => {
    expect(Math.abs(contrast('#ffffff', '#000000') - 21)).toBeLessThan(0.01);
  });

  it('success alert text meets 4.5:1 on its background', () => {
    const { fg, bg } = alertPair('.ibl-alert--success');
    expect(contrast(fg, bg)).toBeGreaterThanOrEqual(4.5);
  });

  it('warning alert text meets 4.5:1 on its background', () => {
    const { fg, bg } = alertPair('.ibl-alert--warning');
    expect(contrast(fg, bg)).toBeGreaterThanOrEqual(4.5);
  });
});
