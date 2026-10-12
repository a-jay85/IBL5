import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, it, expect } from 'vitest';
import { checkCorpus, classifyCss, scanDir } from './component-css-hex-scan';
import type { Allowlist } from './component-css-hex-scan';

const ROOT = fileURLToPath(new URL('../../design/components', import.meta.url));
const IBL5 = fileURLToPath(new URL('../../', import.meta.url));
const COLOR_WHITE_PIN = /--color-white:\s*#ffffff\s*;/i;

// Every remaining raw declaration / definition hex under design/components/**.
// Keyed by (file, hex, category, count), never by line number. Deleting an
// entry after a retoken is the intended way to shrink this list.
const BOOTSTRAP = 'Bootstrap button palette; no exact token exists, a near-match swap would shift color';
const ALLOWLIST: Allowlist = {
  'block-fa-admin.css': [
    { hex: '#dc3545', category: 'raw', count: 3, reason: BOOTSTRAP },
    { hex: '#007bff', category: 'raw', count: 2, reason: BOOTSTRAP },
    { hex: '#0056b3', category: 'raw', count: 1, reason: BOOTSTRAP },
    { hex: '#c82333', category: 'raw', count: 1, reason: BOOTSTRAP },
  ],
  'navigation.css': [
    { hex: '#0ea5e9', category: 'raw', count: 1, reason: 'sky accent with no token; file held by open PR #2515' },
  ],
  'debug.css': [
    { hex: '#1a1a2e', category: 'definition', count: 1, reason: 'component-local debug palette, defined once' },
    { hex: '#16213e', category: 'definition', count: 1, reason: 'component-local debug palette, defined once' },
    { hex: '#a5d8ff', category: 'definition', count: 1, reason: 'component-local debug palette, defined once' },
  ],
  'depth-chart.css': [
    '#fff1e0',
    '#f0ad6e',
    '#ffe4c4',
    '#e8943e',
    '#ffd6a5',
    '#e07d20',
    '#ffc580',
    '#d96c0c',
    '#ffb35c',
    '#c05600',
  ].map((hex) => ({
    hex,
    category: 'definition' as const,
    count: 1,
    reason: 'component-local orange glow ramp, defined once',
  })),
  'head-to-head-records.css': ['#88ff88', '#ff8888', '#bbbbbb'].map((hex) => ({
    hex,
    category: 'definition' as const,
    count: 1,
    reason: 'component-local record-tier palette, defined once',
  })),
  'player-views.css': ['#0000cc', '#ff0000', '#ffbb00'].map((hex) => ({
    hex,
    category: 'definition' as const,
    count: 1,
    reason: '--player-legacy-* palette kept for legacy player-view colors',
  })),
};

describe('classifyCss / checkCorpus fixtures', () => {
  it('1: flags a new raw hex', () => {
    const hits = classifyCss('.a{color:#123456;}');
    expect(hits).toHaveLength(1);
    expect(hits[0].category).toBe('raw');
    const v = checkCorpus({ 'x.css': hits }, {});
    expect(v).toHaveLength(1);
    expect(v[0]).toMatch(/^NEW raw hex #123456/);
  });

  it('2: var() fallback hex is not flagged', () => {
    const hits = classifyCss('.a{color:var(--x, #123456);}');
    expect(hits.map((h) => h.category)).toEqual(['fallback']);
    expect(checkCorpus({ 'x.css': hits }, {})).toEqual([]);
  });

  it('3: nested var() fallback hex is a fallback', () => {
    const hits = classifyCss('.a{color:var(--a, var(--b, #123));}');
    expect(hits.map((h) => h.category)).toEqual(['fallback']);
  });

  it('4: comment hex is not flagged', () => {
    const hits = classifyCss('/* color: #123456; */ .a{color:var(--white);}');
    expect(hits.map((h) => h.category)).toEqual(['comment']);
    expect(checkCorpus({ 'x.css': hits }, {})).toEqual([]);
  });

  it('5: flags a new definition, passes when allowlisted', () => {
    const hits = classifyCss('.a{--x:#123456;}');
    expect(hits.map((h) => h.category)).toEqual(['definition']);
    const v = checkCorpus({ 'x.css': hits }, {});
    expect(v).toHaveLength(1);
    expect(v[0]).toMatch(/^NEW definition hex #123456/);
    const allow: Allowlist = { 'x.css': [{ hex: '#123456', category: 'definition', count: 1, reason: 'r' }] };
    expect(checkCorpus({ 'x.css': hits }, allow)).toEqual([]);
  });

  it('6: flags a stale allowlist entry', () => {
    const hits = classifyCss('.a{color:var(--white);}');
    const allow: Allowlist = { 'x.css': [{ hex: '#abcdef', category: 'raw', count: 1, reason: 'r' }] };
    const v = checkCorpus({ 'x.css': hits }, allow);
    expect(v).toHaveLength(1);
    expect(v[0]).toMatch(/^STALE allowlist entry #abcdef\/raw/);
  });

  it('7: pure white/black is flagged even when allowlisted, but not as a fallback', () => {
    const allowFff: Allowlist = { 'x.css': [{ hex: '#fff', category: 'raw', count: 1, reason: 'r' }] };
    const v1 = checkCorpus({ 'x.css': classifyCss('.a{color:#FFF;}') }, allowFff);
    expect(v1.some((m) => m.startsWith('PURE white/black #fff'))).toBe(true);
    const v2 = checkCorpus({ 'x.css': classifyCss('.a{background:#000000;}') }, {});
    expect(v2.some((m) => m.startsWith('PURE white/black #000000'))).toBe(true);
    const v3 = checkCorpus({ 'x.css': classifyCss('.a{--k:#000;}') }, {});
    expect(v3.some((m) => m.startsWith('PURE white/black #000'))).toBe(true);
    expect(checkCorpus({ 'x.css': classifyCss('.a{color:var(--x, #fff);}') }, {})).toEqual([]);
  });

  it('8: ID and pseudo-class selectors yield no hits', () => {
    expect(classifyCss('#add { color: var(--white); } a:hover { color: var(--white); }')).toEqual([]);
  });

  it('9: reports the line of a hex in a multi-line value', () => {
    const hits = classifyCss('.a{\n  box-shadow: 0 0 1px\n    #123456;\n}');
    expect(hits).toHaveLength(1);
    expect(hits[0].category).toBe('raw');
    expect(hits[0].line).toBe(3);
  });

  it('10: flags an allowlist entry with an empty reason', () => {
    const hits = classifyCss('.a{color:#123456;}');
    const allow: Allowlist = { 'x.css': [{ hex: '#123456', category: 'raw', count: 1, reason: '' }] };
    const v = checkCorpus({ 'x.css': hits }, allow);
    expect(v.some((m) => m.startsWith('BAD allowlist entry'))).toBe(true);
  });
});

describe('ibl5/design/components corpus', () => {
  const hitsByFile = scanDir(ROOT);

  it('every raw declaration and definition hex is allowlisted', () => {
    expect(checkCorpus(hitsByFile, ALLOWLIST)).toEqual([]);
  });

  it('walks subdirectories', () => {
    expect(Object.keys(hitsByFile).some((k) => k.includes('/'))).toBe(true);
  });
});

describe('--white token', () => {
  it('tokens.css aliases --white to --color-white', () => {
    const css = readFileSync(`${IBL5}design/tokens/tokens.css`, 'utf8');
    expect(css).toMatch(/--white:\s*var\(--color-white\)\s*;/);
  });

  it('input.css defines --color-white as #ffffff', () => {
    const css = readFileSync(`${IBL5}design/input.css`, 'utf8');
    expect(css).toMatch(COLOR_WHITE_PIN);
  });

  it('the --color-white pin rejects a near-white such as #fefefe', () => {
    expect('--color-white: #fefefe;').not.toMatch(COLOR_WHITE_PIN);
    expect('--color-white: #ffffff;').toMatch(COLOR_WHITE_PIN);
  });

  it('the head-to-head smoke baselines exist, so the swap is covered by visual regression', () => {
    const dir = `${IBL5}tests/e2e/smoke/visual-regression.spec.ts-snapshots`;
    for (const name of ['head-to-head-records.png', 'head-to-head-records-mobile.png']) {
      expect(existsSync(`${dir}/${name}`), `missing baseline ${name}`).toBe(true);
    }
  });
});
