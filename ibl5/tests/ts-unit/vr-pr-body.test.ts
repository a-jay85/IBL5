import { describe, it, expect } from 'vitest';
import {
  PR_BODY_MARKER_BEGIN,
  PR_BODY_MARKER_END,
  newScreenUrl,
  buildCopyPlan,
  buildNewScreensSection,
  normalizeBody,
  spliceBody,
  type LeanCell,
} from '../e2e/vr-pr-body';

const PAGES_URL = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review/';

describe('newScreenUrl', () => {
  it('1a: appends new-screens/<title>.png under the pages URL', () => {
    expect(newScreenUrl(PAGES_URL, 'standings')).toBe(`${PAGES_URL}new-screens/standings.png`);
  });

  it('1b: adds a trailing slash when pagesUrl lacks one', () => {
    const noSlash = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review';
    expect(newScreenUrl(noSlash, 'standings')).toBe(`${noSlash}/new-screens/standings.png`);
  });

  it('1c: encodeURIComponent is applied to the title', () => {
    expect(newScreenUrl(PAGES_URL, 'standings mobile')).toBe(
      `${PAGES_URL}new-screens/${encodeURIComponent('standings mobile')}.png`
    );
  });
});

describe('buildCopyPlan', () => {
  it('2a: maps each cell to {src: <renders>/<title>.after.png, dest: <dest>/<title>.png}', () => {
    const cells: LeanCell[] = [{ module: 'standings', viewport: 'desktop', title: 'standings' }];
    expect(buildCopyPlan(cells, 'ibl5/vr-gallery', 'ibl5/vr-gallery/new-screens')).toEqual([
      { src: 'ibl5/vr-gallery/standings.after.png', dest: 'ibl5/vr-gallery/new-screens/standings.png' },
    ]);
  });

  it('2b: empty newCells -> [] (boundary)', () => {
    expect(buildCopyPlan([], 'ibl5/vr-gallery', 'ibl5/vr-gallery/new-screens')).toEqual([]);
  });
});

describe('buildNewScreensSection', () => {
  it('3a: empty newCells -> "" (boundary/negative)', () => {
    expect(buildNewScreensSection([], PAGES_URL)).toBe('');
  });

  it('3b: non-empty section starts with BEGIN and ends with END markers', () => {
    const cells: LeanCell[] = [{ module: 'standings', viewport: 'desktop', title: 'standings' }];
    const section = buildNewScreensSection(cells, PAGES_URL);
    expect(section.startsWith(PR_BODY_MARKER_BEGIN)).toBe(true);
    expect(section.endsWith(PR_BODY_MARKER_END)).toBe(true);
  });

  it('3c: contains one image per cell, linking via newScreenUrl', () => {
    const cells: LeanCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
      { module: 'standings', viewport: 'mobile', title: 'standings-mobile' },
    ];
    const section = buildNewScreensSection(cells, PAGES_URL);
    expect(section).toContain(`![standings — desktop](${newScreenUrl(PAGES_URL, 'standings')})`);
    expect(section).toContain(
      `![standings-mobile — mobile](${newScreenUrl(PAGES_URL, 'standings-mobile')})`
    );
  });

  it('3d: modules are ordered by localeCompare', () => {
    const cells: LeanCell[] = [
      { module: 'zebra', viewport: 'desktop', title: 'zebra' },
      { module: 'alpha', viewport: 'desktop', title: 'alpha' },
    ];
    const section = buildNewScreensSection(cells, PAGES_URL);
    expect(section.indexOf('alpha')).toBeLessThan(section.indexOf('zebra'));
  });
});

describe('normalizeBody', () => {
  it("4a: 'null' -> ''", () => {
    expect(normalizeBody('null')).toBe('');
  });

  it("4b: strips trailing newlines: 'x\\n\\n' -> 'x'", () => {
    expect(normalizeBody('x\n\n')).toBe('x');
  });

  it("4c: '' -> ''", () => {
    expect(normalizeBody('')).toBe('');
  });

  it('4d: null -> "" (negative)', () => {
    expect(normalizeBody(null)).toBe('');
    expect(normalizeBody(undefined)).toBe('');
  });
});

describe('spliceBody', () => {
  it('5a: empty body + section -> section only, no leading blank', () => {
    const section = buildNewScreensSection(
      [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      PAGES_URL
    );
    expect(spliceBody('', section)).toBe(section);
  });

  it('5b: human-prose body (no marker) + section -> section\\n\\nprose, prose intact', () => {
    const section = buildNewScreensSection(
      [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      PAGES_URL
    );
    const prose = 'Human prose here.';
    expect(spliceBody(prose, section)).toBe(`${section}\n\n${prose}`);
  });

  it('5c: a well-formed block at offset 0 is replaced, prose preserved, and the result is idempotent', () => {
    const cellsA: LeanCell[] = [{ module: 'standings', viewport: 'desktop', title: 'standings' }];
    const cellsB: LeanCell[] = [{ module: 'roster', viewport: 'desktop', title: 'roster' }];
    const sectionA = buildNewScreensSection(cellsA, PAGES_URL);
    const sectionB = buildNewScreensSection(cellsB, PAGES_URL);
    const prose = 'Human prose here.';

    const once = spliceBody(spliceBody(prose, sectionA), sectionB);
    expect(once).toBe(`${sectionB}\n\n${prose}`);
    expect(once).not.toContain('standings');

    const twice = spliceBody(once, sectionB);
    expect(twice).toBe(once);
  });

  it('5d: a BEGIN marker mid-body (not offset 0) is NOT stripped and is preserved verbatim after prepend', () => {
    const section = buildNewScreensSection(
      [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      PAGES_URL
    );
    const midBodyBegin = `Some prose.\n\n${PR_BODY_MARKER_BEGIN}\nnot really a managed block\n${PR_BODY_MARKER_END}`;
    const result = spliceBody(midBodyBegin, section);
    expect(result).toBe(`${section}\n\n${midBodyBegin}`);
  });

  it('5e: strip case — block at offset 0 + section === "" removes the block, prose preserved', () => {
    const section = buildNewScreensSection(
      [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      PAGES_URL
    );
    const prose = 'Human prose here.';
    const withBlock = spliceBody(prose, section);
    expect(spliceBody(withBlock, '')).toBe(prose);
  });
});
