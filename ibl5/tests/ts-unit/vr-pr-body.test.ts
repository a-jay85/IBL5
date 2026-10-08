import { describe, it, expect } from 'vitest';
import {
  PR_BODY_MARKER_BEGIN,
  PR_BODY_MARKER_END,
  newScreenUrl,
  buildCopyPlan,
  buildNewScreensSection,
  normalizeBody,
  spliceBody,
  findManagedBlocks,
  AGENT_SHOTS_BEGIN,
  AGENT_SHOTS_END,
  buildPrBodyBlock,
  extractAgentShots,
  sanitizeLabel,
  agentShotUrl,
  buildAgentShotEntry,
  upsertAgentShots,
  type ChangedSpot,
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
    expect(section).toContain(`![standings · desktop](${newScreenUrl(PAGES_URL, 'standings')})`);
    expect(section).toContain(
      `![standings-mobile · mobile](${newScreenUrl(PAGES_URL, 'standings-mobile')})`
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

  it('5d: marker mentions inline, in a code span, and inside a fence are human text and are preserved verbatim', () => {
    const section = buildNewScreensSection(
      [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      PAGES_URL
    );
    const body = [
      `Some prose mentioning \`${PR_BODY_MARKER_BEGIN}\` inline.`,
      '',
      '```',
      PR_BODY_MARKER_BEGIN,
      'example',
      PR_BODY_MARKER_END,
      '```',
    ].join('\n');
    expect(spliceBody(body, section)).toBe(`${section}\n\n${body}`);
    expect(spliceBody(body, '')).toBe(body);
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

  const sectionFor = (module: string) =>
    buildNewScreensSection([{ module, viewport: 'desktop', title: module }], PAGES_URL);

  it('5f: an own-line block mid-body is replaced in place', () => {
    const sectionA = sectionFor('standings');
    const sectionB = sectionFor('roster');
    const out = spliceBody(`Intro.\n\n${sectionA}\n\nOutro.`, sectionB);
    expect(out).toBe(`Intro.\n\n${sectionB}\n\nOutro.`);
    expect(out).not.toContain('standings');
  });

  it('5g: a stale second block is stripped and the result is idempotent', () => {
    const sectionA = sectionFor('standings');
    const sectionB = sectionFor('roster');
    const sectionStale = sectionFor('stale');
    const once = spliceBody(`${sectionA}\n\nmiddle\n\n${sectionStale}\n\nsummary`, sectionB);
    expect(once).toBe(`${sectionB}\n\nmiddle\n\nsummary`);
    expect(spliceBody(once, sectionB)).toBe(once);
  });

  it('5h: strip case removes every block', () => {
    const sectionA = sectionFor('standings');
    const sectionStale = sectionFor('stale');
    expect(spliceBody(`${sectionA}\n\nmiddle\n\n${sectionStale}\n\nsummary`, '')).toBe('middle\n\nsummary');
  });

  it('5i: unterminated BEGIN and stray END are human text', () => {
    const section = sectionFor('standings');
    const dangling = `prose\n\n${PR_BODY_MARKER_BEGIN}\ndangling`;
    const stray = `prose\n\n${PR_BODY_MARKER_END}`;
    for (const body of [dangling, stray]) {
      expect(spliceBody(body, section)).toBe(`${section}\n\n${body}`);
      expect(spliceBody(body, '')).toBe(body);
    }
  });
});

describe('findManagedBlocks', () => {
  const sectionFor = (module: string) =>
    buildNewScreensSection([{ module, viewport: 'desktop', title: module }], PAGES_URL);

  it('15a: no blocks in empty or prose bodies', () => {
    expect(findManagedBlocks('')).toEqual([]);
    expect(findManagedBlocks('Human prose.')).toEqual([]);
  });

  it('15b: one block at offset 0 spans the whole section', () => {
    const section = sectionFor('standings');
    expect(findManagedBlocks(section)).toEqual([{ start: 0, end: section.length }]);
  });

  it('15c: two blocks are returned in body order', () => {
    const one = sectionFor('standings');
    const two = sectionFor('roster');
    const body = `Intro.\n\n${one}\n\nmiddle\n\n${two}\n\nOutro.`;
    const blocks = findManagedBlocks(body);
    expect(blocks).toHaveLength(2);
    expect(body.slice(blocks[0].start, blocks[0].end)).toBe(one);
    expect(body.slice(blocks[1].start, blocks[1].end)).toBe(two);
  });

  it('15d: markers inside backtick and tilde fences are ignored', () => {
    const inner = `${PR_BODY_MARKER_BEGIN}\nx\n${PR_BODY_MARKER_END}`;
    expect(findManagedBlocks(`\`\`\`\n${inner}\n\`\`\``)).toEqual([]);
    expect(findManagedBlocks(`~~~\n${inner}\n~~~`)).toEqual([]);
  });

  it('15e: inline and unterminated markers are ignored', () => {
    expect(findManagedBlocks(`${PR_BODY_MARKER_BEGIN} trailing text\nx\n${PR_BODY_MARKER_END}`)).toEqual([]);
    expect(findManagedBlocks(`${PR_BODY_MARKER_BEGIN}\nno end`)).toEqual([]);
    expect(findManagedBlocks(PR_BODY_MARKER_END)).toEqual([]);
  });

  it('15f: a re-opened BEGIN restarts the block', () => {
    const body = `${PR_BODY_MARKER_BEGIN}\n${PR_BODY_MARKER_BEGIN}\nx\n${PR_BODY_MARKER_END}`;
    const blocks = findManagedBlocks(body);
    expect(blocks).toHaveLength(1);
    expect(blocks[0].start).toBe(PR_BODY_MARKER_BEGIN.length + 1);
    expect(blocks[0].end).toBe(body.length);
  });
});

function makeSpot(over: Partial<ChangedSpot> = {}): ChangedSpot {
  const title = over.title ?? 'standings';
  const index = over.index ?? 1;
  return {
    module: 'standings',
    viewport: 'desktop',
    title,
    index,
    count: 1,
    kind: 'spot',
    region: 'top, y 0-100 px',
    beforeFile: `changed/${title}.${index}.before.png`,
    afterFile: `changed/${title}.${index}.after.png`,
    ...over,
  };
}

const RUN_TIME = new Date('2026-10-06T20:23:45.000Z');
const SHA = 'abc1234def5678';

function block(over: Partial<Parameters<typeof buildPrBodyBlock>[0]> = {}): string {
  return buildPrBodyBlock({
    newCells: [],
    spots: [],
    pagesUrl: PAGES_URL,
    headSha: SHA,
    runTime: RUN_TIME,
    agentShots: '',
    ...over,
  });
}

describe('buildPrBodyBlock', () => {
  it('6a: emits one ### heading per spot', () => {
    const spots = [makeSpot({ title: 'a', index: 1 }), makeSpot({ title: 'b', index: 1 }), makeSpot({ title: 'b', index: 2 })];
    const headings = block({ spots }).split('\n').filter((l) => l.startsWith('### '));
    expect(headings).toHaveLength(3);
  });

  it('6b: **Before** precedes **After** within each spot', () => {
    const lines = block({ spots: [makeSpot({ title: 'a' }), makeSpot({ title: 'b' })] }).split('\n');
    const befores = lines.flatMap((l, i) => (l === '**Before**' ? [i] : []));
    const afters = lines.flatMap((l, i) => (l === '**After**' ? [i] : []));
    expect(befores).toHaveLength(2);
    expect(afters).toHaveLength(2);
    befores.forEach((b, i) => expect(b).toBeLessThan(afters[i]));
    expect(afters[0]).toBeLessThan(befores[1]);
  });

  it('6c: mobile spots sit inside <details>, desktop spots do not', () => {
    const out = block({
      spots: [
        makeSpot({ title: 'desk', viewport: 'desktop' }),
        makeSpot({ title: 'mob', viewport: 'mobile' }),
      ],
    });
    const open = out.indexOf('<details>');
    const close = out.indexOf('</details>');
    expect(open).toBeGreaterThan(-1);
    expect(close).toBeGreaterThan(open);
    expect(out).toContain('standings: mobile (1 spots)</summary>\n\n');
    const mobIdx = out.indexOf('· mob ·');
    const deskIdx = out.indexOf('· desk ·');
    expect(mobIdx).toBeGreaterThan(open);
    expect(mobIdx).toBeLessThan(close);
    expect(deskIdx).toBeLessThan(open);
  });

  it('6d: stamp holds the 7-char sha and UTC; non-hex sha renders unknown', () => {
    const out = block({ spots: [makeSpot()] });
    const stamp = out.split('\n').find((l) => l.startsWith('_VR screens for head'))!;
    expect(stamp).toContain('`abc1234`');
    expect(stamp).toContain('UTC');
    expect(stamp).toContain('2026-10-06 20:23');
    const bad = block({ spots: [makeSpot()], headSha: 'not-a-sha!' });
    expect(bad).toContain('`unknown`');
  });

  it('6e: all-empty input returns "" and splice strips the block leaving human text', () => {
    expect(block()).toBe('');
    const prose = 'Human prose here.';
    const withBlock = spliceBody(prose, block({ spots: [makeSpot()] }));
    expect(withBlock.startsWith(PR_BODY_MARKER_BEGIN)).toBe(true);
    expect(spliceBody(withBlock, block())).toBe(prose);
  });

  it('6f: 45 spots with the default cap emit 40 headings plus the overflow line and gallery link', () => {
    const spots = Array.from({ length: 45 }, (_, i) => makeSpot({ title: `t${String(i).padStart(2, '0')}` }));
    const out = block({ spots });
    const headings = out.split('\n').filter((l) => l.startsWith('### '));
    expect(headings).toHaveLength(40);
    expect(out).toContain('5 more changed spots');
    expect(out).toContain(`[Full gallery](${PAGES_URL})`);
  });

  it('6g: maxChars stops before the limit; length stays within it plus the overflow line', () => {
    const spots = Array.from({ length: 30 }, (_, i) => makeSpot({ title: `t${String(i).padStart(2, '0')}` }));
    const out = block({ spots, maxChars: 2000 });
    const overflow = out.split('\n').find((l) => l.includes('more changed spots'))!;
    expect(overflow).toBeDefined();
    expect(out.split('\n').filter((l) => l.startsWith('### ')).length).toBeLessThan(30);
    expect(out.length).toBeLessThanOrEqual(2000 + overflow.length);
  });

  it('6h: uncropped spot heading reads "full page (size changed)"', () => {
    const out = block({
      spots: [makeSpot({ kind: 'uncropped', reason: 'dimensions-changed', region: '' })],
    });
    const heading = out.split('\n').find((l) => l.startsWith('### '))!;
    expect(heading).toContain('full page (size changed)');
  });
});

describe('extractAgentShots', () => {
  const X = `${AGENT_SHOTS_BEGIN}\n![shot](https://example.test/x.png)\n${AGENT_SHOTS_END}`;

  it('7a: round-trips an agent-shot sub-block through buildPrBodyBlock', () => {
    const out = block({ spots: [makeSpot()], agentShots: X });
    expect(extractAgentShots(out)).toBe(X);
  });

  it('7b: sub-block markers outside the managed block return ""', () => {
    const managed = block({ spots: [makeSpot()] });
    const outside = `${managed}\n\n${X}`;
    expect(extractAgentShots(outside)).toBe('');
    expect(extractAgentShots(`Prose\n\n${X}`)).toBe('');
  });

  it('7c: reads the agent-shot sub-block from a block that is not at offset 0', () => {
    const withShots = block({ spots: [makeSpot()], agentShots: X });
    expect(extractAgentShots(`Intro.\n\n${withShots}`)).toBe(X);
  });

  it('7d: a sub-block only in a stale second block is not read', () => {
    const first = block({ spots: [makeSpot()] });
    const second = block({ spots: [makeSpot({ title: 'other' })], agentShots: X });
    expect(extractAgentShots(`${first}\n\nmiddle\n\n${second}`)).toBe('');
  });
});

describe('sanitizeLabel', () => {
  it('8a: neutralises markdown and html characters', () => {
    expect(sanitizeLabel('Hi ](javascript:x) <img>')).toBe('hi-javascript-x-img');
  });

  it('8c: a hostile agent-shot label becomes a safe gh-pages path', () => {
    const label = sanitizeLabel('Nav Bar ](x)');
    expect(label).toBe('nav-bar-x');
    expect(agentShotUrl('a'.repeat(40), label as string, 'after')).toBe(
      `https://a-jay85.github.io/IBL5/${'a'.repeat(40)}/visual-review/agent-shots/nav-bar-x.after.png`,
    );
  });

  it('8b: returns null when nothing survives', () => {
    expect(sanitizeLabel('!!!')).toBeNull();
  });
});

describe('upsertAgentShots', () => {
  const SHOT_SHA = 'a'.repeat(40);
  const entry = (label: string, hasBefore = true, sha = SHOT_SHA) => ({
    label,
    entry: buildAgentShotEntry(sha, label, hasBefore),
  });
  const markers = (body: string, label: string) =>
    body.split('\n').filter((l) => l === `<!-- vr-agent-shot:${label} -->`).length;

  it('10a: a body with no managed block gains one; human text survives below', () => {
    const out = upsertAgentShots('Human prose.', [entry('a')]);
    expect(out.startsWith(PR_BODY_MARKER_BEGIN)).toBe(true);
    expect(out.endsWith('Human prose.')).toBe(true);
    expect(out.indexOf(PR_BODY_MARKER_END)).toBeLessThan(out.indexOf('Human prose.'));
    expect(extractAgentShots(out)).toContain('<!-- vr-agent-shot:a -->');
  });

  it('10b: upserting the same label twice leaves one entry holding the second URLs', () => {
    const once = upsertAgentShots('Prose', [entry('a')]);
    const otherSha = 'b'.repeat(40);
    const twice = upsertAgentShots(once, [entry('a', true, otherSha)]);
    expect(markers(twice, 'a')).toBe(1);
    expect(twice).toContain(agentShotUrl(otherSha, 'a', 'after'));
    expect(twice).not.toContain(agentShotUrl(SHOT_SHA, 'a', 'after'));
  });

  it('10c: a second label appends after the first', () => {
    const out = upsertAgentShots(upsertAgentShots('Prose', [entry('a')]), [entry('b')]);
    expect(markers(out, 'a')).toBe(1);
    expect(markers(out, 'b')).toBe(1);
    expect(out.indexOf('<!-- vr-agent-shot:a -->')).toBeLessThan(out.indexOf('<!-- vr-agent-shot:b -->'));
  });

  it('10d: CI spot headings stay byte-identical after an upsert', () => {
    const body = spliceBody('Prose', block({ spots: [makeSpot({ title: 'x' }), makeSpot({ title: 'y' })] }));
    const ciHeadings = (b: string) =>
      b.split('\n').filter((l) => l.startsWith('### ') && !l.startsWith('### Agent shot'));
    const out = upsertAgentShots(body, [entry('a')]);
    expect(ciHeadings(out)).toEqual(ciHeadings(body));
    expect(ciHeadings(out)).toHaveLength(2);
    expect(out.endsWith('Prose')).toBe(true);
  });

  it('10e: a CI rebuild round-trip keeps the agent entry', () => {
    const withShot = upsertAgentShots('Prose', [entry('a')]);
    const rebuilt = spliceBody(withShot, block({ spots: [makeSpot()], agentShots: extractAgentShots(withShot) }));
    expect(markers(rebuilt, 'a')).toBe(1);
    expect(rebuilt).toContain(agentShotUrl(SHOT_SHA, 'a', 'after'));
    expect(rebuilt.endsWith('Prose')).toBe(true);
  });

  it('10f: hasBefore=false renders no **Before**', () => {
    const e = buildAgentShotEntry(SHOT_SHA, 'a', false);
    expect(e).not.toContain('**Before**');
    expect(e).toContain('**After**');
    expect(e).not.toContain(agentShotUrl(SHOT_SHA, 'a', 'before'));
  });

  it('10g: upsert into a block at offset > 0 edits that block and adds none', () => {
    const body = `Intro.\n\n${block({ spots: [makeSpot()] })}\n\nOutro.`;
    const out = upsertAgentShots(body, [entry('a')]);
    expect(findManagedBlocks(out)).toHaveLength(1);
    expect(out.startsWith('Intro.\n\n')).toBe(true);
    expect(out.endsWith('\n\nOutro.')).toBe(true);
    expect(extractAgentShots(out)).toContain('<!-- vr-agent-shot:a -->');
    expect(markers(out, 'a')).toBe(1);
  });

  it('10h: upsert with two blocks writes only the first', () => {
    const first = block({ spots: [makeSpot()] });
    const second = block({ spots: [makeSpot({ title: 'other' })] });
    const out = upsertAgentShots(`${first}\n\nmiddle\n\n${second}`, [entry('a')]);
    expect(out.endsWith(`\n\nmiddle\n\n${second}`)).toBe(true);
    expect(markers(out, 'a')).toBe(1);
    expect(findManagedBlocks(out)).toHaveLength(2);
    expect(extractAgentShots(out)).toContain('<!-- vr-agent-shot:a -->');
  });
});
