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

const inputCss = readFileSync(`${ibl5Root}design/input.css`, 'utf8');

// token: --color-txn-fa-fg: #b84300;  ->  'fa-fg' => '#b84300'
const TXN_TOKEN_RE = /--color-txn-([a-z]+)-(fg|bg)\s*:\s*(#[0-9a-fA-F]{6})\s*;/g;

function parseTxnTokens(css: string): Map<string, string> {
  const map = new Map<string, string>();
  for (const m of css.matchAll(TXN_TOKEN_RE)) {
    map.set(`${m[1] as string}-${m[2] as string}`, m[3] as string);
  }
  return map;
}

const PAIRS = ['trade', 'fa', 'waiver', 'extension', 'draft', 'release'] as const;

const tokens = parseTxnTokens(inputCss);

function token(name: string): string {
  const v = tokens.get(name);
  if (v === undefined) {
    throw new Error(`token --color-txn-${name} not found`);
  }
  return v;
}

describe('transaction badge contrast', () => {
  it('parses all twelve txn badge tokens', () => {
    expect(tokens.size).toBe(12);
  });

  it.each(PAIRS)('every txn badge pair meets WCAG AA 4.5:1 (%s)', (name) => {
    expect(contrast(token(`${name}-fg`), token(`${name}-bg`))).toBeGreaterThanOrEqual(4.5);
  });

  it('uses the darkened fa and release foregrounds', () => {
    expect(token('fa-fg').toLowerCase()).toBe('#b84300');
    expect(token('release-fg').toLowerCase()).toBe('#00707a');
  });

  it('keeps the four untouched pairs at their pinned hex values', () => {
    const pinned: Record<string, string> = {
      'trade-fg': '#1565c0',
      'trade-bg': '#e3f2fd',
      'waiver-fg': '#2e7d32',
      'waiver-bg': '#e8f5e9',
      'extension-fg': '#c2185b',
      'extension-bg': '#fce4ec',
      'draft-fg': '#7b1fa2',
      'draft-bg': '#f3e5f5',
      'fa-bg': '#fff3e0',
      'release-bg': '#e0f7fa',
    };
    for (const [name, hex] of Object.entries(pinned)) {
      expect(token(name).toLowerCase(), name).toBe(hex);
    }
  });

  it('parser ignores near-miss names and short hex', () => {
    const parsed = parseTxnTokens('--color-txn-fa-fgx: #123456; --color-txn-fa-fg: #abc;');
    expect(parsed.size).toBe(0);
  });
});
