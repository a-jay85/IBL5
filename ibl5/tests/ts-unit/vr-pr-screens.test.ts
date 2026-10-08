import { describe, it, expect } from 'vitest';
import {
  SCREENS_BEGIN,
  SCREENS_END,
  PAGES_BASE,
  parsePrNumber,
  isHeadSha,
  cellsHash,
  renderToken,
  planRender,
  screenUrl,
  galleryUrl,
  assembleMeta,
  buildScreensBlock,
  extractScreensBlock,
  stripScreensBlock,
  spliceScreensBlock,
  blockImageUrls,
  isNoop,
  spliceWithRetry,
  type Manifest,
  type ScreensRows,
} from '../e2e/vr-pr-screens';
import { normalizeBody } from '../e2e/vr-pr-body';
import type { ManualVrRow } from '../e2e/vr-manual-rows';

const PR = 42;
const SHA = 'a'.repeat(40);
const TOKEN = 'abcdef012345';
const RUN_URL = 'https://github.com/a-jay85/IBL5/actions/runs/123456';
const CELL = 'team-page-header';

function row(label: string, over: Partial<ManualVrRow> = {}): ManualVrRow {
  return {
    row: '1',
    label,
    role: 'anon',
    url: 'modules.php?name=Team',
    anchor: '.x',
    setup: [],
    ...over,
  };
}

function okStatus(): Record<'phone' | 'desktop', 'ok'> {
  return { phone: 'ok', desktop: 'ok' };
}

function rowsFor(labels: string[], status: 'ok' | 'failed' = 'ok'): ScreensRows {
  const side: ScreensRows['before'] = {};
  for (const l of labels) side[l] = { phone: status, desktop: status };
  return { cells: labels, before: { ...side }, after: { ...side } };
}

function build(
  rows: ScreensRows,
  over: Partial<{ galleryPresent: boolean; runUrl: string; headSha: string; token: string }> = {}
): string {
  return buildScreensBlock({
    pr: PR,
    headSha: SHA,
    token: TOKEN,
    rows,
    galleryPresent: true,
    runUrl: RUN_URL,
    ...over,
  });
}

const OTHER_BLOCKS = ['merge-digest', 'files-changed', 'tests-changed', 'gate-backtest', 'backlog-closes', 'manual-confirmation'].map(
  (n) => `<!-- ${n}:begin -->\ncontent of ${n}\n<!-- ${n}:end -->`
);

const FIXTURE = [
  '<!-- no-adr: fixture reason long enough -->',
  'Depends-on: #1',
  '',
  ...OTHER_BLOCKS.flatMap((b) => [b, '']),
  '## Manual Testing',
  '',
  '- [ ] check the header `vr: label=team-page-header; role=anon; url=modules.php?name=Team; anchor=.x`',
  '',
  '<!-- reviewer-verification:begin -->',
  'verified by reviewer',
  '<!-- reviewer-verification:end -->',
].join('\n');

const MANIFEST: Manifest = { schema: 1, cellsHash: 'h', beforeTree: 'b', afterTree: 'a' };

describe('vr-pr-screens', () => {
  it('screens-block-exact', () => {
    const block = build(rowsFor([CELL]));
    const img = (side: string, vp: string, w: string): string =>
      `<img src="${PAGES_BASE}pr/42/manual/${side}/${CELL}.${vp}.png?sha=${SHA}&amp;r=${TOKEN}"${w} alt="${CELL} ${vp} ${side}">`;
    const expected = [
      '<!-- vr-screens:begin -->',
      '<sub>Manual-row screenshots for <code>aaaaaaa</code>. Before is master, after is this PR.</sub>',
      '',
      `**${CELL}**`,
      '',
      `<table><tr><th>Phone before</th><th>Phone after</th></tr><tr><td>${img('before', 'phone', ' width="180"')}</td><td>${img('after', 'phone', ' width="180"')}</td></tr></table>`,
      '',
      `<b>Desktop before</b><br>${img('before', 'desktop', '')}`,
      '',
      `<b>Desktop after</b><br>${img('after', 'desktop', '')}`,
      '',
      `[Full visual-review gallery](${PAGES_BASE}pr/42/visual-review/)`,
      '<!-- vr-screens:end -->',
    ].join('\n');
    expect(block).toBe(expected);
  });

  it('screens-urls-cache-bust', () => {
    const block = build(rowsFor([CELL, 'roster-grid']), { token: '0123456789ab' });
    const urls = blockImageUrls(block);
    expect(urls).toHaveLength(8);
    const re = /^https:\/\/a-jay85\.github\.io\/IBL5\/pr\/42\/manual\/(before|after)\/(team-page-header|roster-grid)\.(phone|desktop)\.png\?sha=[0-9a-f]{40}&r=[0-9a-f]{12}$/;
    for (const u of urls) expect(u).toMatch(re);
    expect(screenUrl(42, 'before', CELL, 'phone', SHA, '0123456789ab')).toBe(
      `https://a-jay85.github.io/IBL5/pr/42/manual/before/${CELL}.phone.png?sha=${SHA}&r=0123456789ab`
    );
    expect(galleryUrl(42)).toBe('https://a-jay85.github.io/IBL5/pr/42/visual-review/');
  });

  it('screens-both-viewports-both-sides', () => {
    const block = build(rowsFor([CELL]));
    const alts = [...block.matchAll(/alt="([^"]*)"/g)].map((m) => m[1]).sort();
    expect(alts).toEqual(
      [`${CELL} desktop after`, `${CELL} desktop before`, `${CELL} phone after`, `${CELL} phone before`].sort()
    );
  });

  it('screens-block-forbidden-text', () => {
    const block = build(rowsFor([CELL, 'roster-grid'], 'failed'));
    const block2 = build(rowsFor([CELL]));
    for (const b of [block, block2]) {
      expect(b).not.toContain('- [ ]');
      expect(b).not.toMatch(/^## /m);
      expect(b.toLowerCase()).not.toContain('vr:');
    }
  });

  it('screens-failed-capture', () => {
    const rows = rowsFor([CELL]);
    rows.after[CELL] = { phone: 'failed', desktop: 'ok' };
    rows.before[CELL] = { phone: 'ok', desktop: 'missing' };
    const block = build(rows);
    expect(block).toContain('<i>capture failed</i> (<a href="' + RUN_URL + '">run log</a>)');
    expect(block).not.toContain(`alt="${CELL} phone after"`);
    expect(block).not.toContain(`alt="${CELL} desktop before"`);
    expect(block).toContain(`alt="${CELL} phone before"`);
    expect(blockImageUrls(block)).toHaveLength(2);

    const bad = build(rows, { runUrl: 'https://evil.example/x' });
    expect(bad).toContain('<i>capture failed</i>');
    expect(bad).not.toContain('<a ');
    const empty = build(rows, { runUrl: '' });
    expect(empty).not.toContain('<a ');
  });

  it('screens-invalid-label-dropped', () => {
    const rows = rowsFor([CELL, 'Bad Label', '<script>', 'a&b']);
    const block = build(rows);
    for (const bad of ['Bad Label', '<script>', 'a&b', '&lt;script&gt;', 'a&amp;b']) {
      expect(block).not.toContain(bad);
    }
    expect(block).toContain(`**${CELL}**`);
  });

  it('screens-overflow-cap', () => {
    const labels = Array.from({ length: 14 }, (_, i) => `cell-${i}`);
    const block = build(rowsFor(labels));
    expect((block.match(/^\*\*cell-\d+\*\*$/gm) ?? []).length).toBe(12);
    expect(block).toContain('<i>2 more manual rows were not inlined (12-row cap).</i>');
    expect(block).not.toContain('**cell-12**');
    const exact = build(rowsFor(labels.slice(0, 12)));
    expect(exact).not.toContain('not inlined');
  });

  it('screens-gallery-absent', () => {
    const block = build(rowsFor([CELL]), { galleryPresent: false });
    expect(block).toContain('<i>Full visual-review gallery not published for this PR yet.</i>');
    expect(block).not.toContain('[Full visual-review gallery]');
    expect(block).not.toContain('/visual-review/');
  });

  it('screens-zero-cells-empty', () => {
    expect(build({ cells: [], before: {}, after: {} })).toBe('');
    expect(build(rowsFor(['Bad Label', '<script>']))).toBe('');
  });

  it('splice-insert-before-manual-testing', () => {
    const block = build(rowsFor([CELL]));
    const { body, outcome } = spliceScreensBlock(FIXTURE, block);
    expect(outcome).toBe('inserted');
    expect(body.split('\n')[0]).toBe('<!-- no-adr: fixture reason long enough -->');
    expect(body).toContain(`${block}\n\n## Manual Testing`);
    expect(body.indexOf(block)).toBeGreaterThan(0);
  });

  it('splice-append-when-no-heading', () => {
    const block = build(rowsFor([CELL]));
    const { body, outcome } = spliceScreensBlock('Just some text\n\n', block);
    expect(outcome).toBe('appended');
    expect(body.startsWith('Just some text\n\n' + SCREENS_BEGIN)).toBe(true);
    expect(extractScreensBlock(body)).toBe(block);
    const empty = spliceScreensBlock('', block);
    expect(empty.outcome).toBe('appended');
    expect(extractScreensBlock(empty.body)).toBe(block);
  });

  it('splice-replace-in-place-idempotent', () => {
    const b1 = build(rowsFor([CELL]));
    const b2 = build(rowsFor([CELL, 'roster-grid']));
    const first = spliceScreensBlock(FIXTURE, b1);
    const second = spliceScreensBlock(first.body, b2);
    expect(second.outcome).toBe('replaced');
    expect(extractScreensBlock(second.body)).toBe(b2);
    expect(second.body.split(SCREENS_BEGIN)).toHaveLength(2);
    expect(second.body.indexOf(SCREENS_BEGIN)).toBe(first.body.indexOf(SCREENS_BEGIN));
    const again = spliceScreensBlock(second.body, b2);
    expect(again.outcome).toBe('unchanged');
    expect(again.body).toBe(second.body);
  });

  it('splice-other-blocks-byte-identical', () => {
    const block = build(rowsFor([CELL]));
    const { body } = spliceScreensBlock(FIXTURE, block);
    expect(stripScreensBlock(body)).toBe(normalizeBody(FIXTURE));
    for (const b of OTHER_BLOCKS) expect(body).toContain(b);
    expect(body).toContain('<!-- reviewer-verification:begin -->\nverified by reviewer\n<!-- reviewer-verification:end -->');
    const appended = spliceScreensBlock('plain body', block);
    expect(stripScreensBlock(appended.body)).toBe('plain body');
  });

  it('splice-remove-on-empty-block', () => {
    const block = build(rowsFor([CELL]));
    const withBlock = spliceScreensBlock(FIXTURE, block).body;
    const removed = spliceScreensBlock(withBlock, '');
    expect(removed.outcome).toBe('removed');
    expect(removed.body).toBe(normalizeBody(FIXTURE));
    expect(spliceScreensBlock(FIXTURE, '').outcome).toBe('unchanged');
  });

  it('splice-malformed-untouched', () => {
    const block = build(rowsFor([CELL]));
    const two = `x\n\n${SCREENS_BEGIN}\na\n${SCREENS_END}\n\n${SCREENS_BEGIN}\nb\n${SCREENS_END}`;
    const r1 = spliceScreensBlock(two, block);
    expect(r1.outcome).toBe('malformed');
    expect(r1.body).toBe(two);
    const reversed = `x\n${SCREENS_END}\ny\n${SCREENS_BEGIN}`;
    const r2 = spliceScreensBlock(reversed, block);
    expect(r2.outcome).toBe('malformed');
    expect(r2.body).toBe(reversed);
  });

  it('splice-refuses-over-60000', () => {
    const block = build(rowsFor([CELL]));
    const big = 'x'.repeat(59990);
    const r = spliceScreensBlock(big, block);
    expect(r.outcome).toBe('too-large');
    expect(r.body).toBe(big);
  });

  it('plan-render-sides', () => {
    const cur = { cellsHash: 'h', beforeTree: 'b', afterTree: 'a' };
    expect(planRender(null, cur)).toEqual(['before', 'after']);
    expect(planRender(MANIFEST, { ...cur, cellsHash: 'other' })).toEqual(['before', 'after']);
    expect(planRender(MANIFEST, { ...cur, beforeTree: 'b2' })).toEqual(['before']);
    expect(planRender(MANIFEST, { ...cur, afterTree: 'a2' })).toEqual(['after']);
    expect(planRender(MANIFEST, cur)).toEqual([]);
    expect(planRender({ ...MANIFEST, beforeTree: '' }, { ...cur, beforeTree: '' })).toEqual(['before']);
  });

  it('plan-render-memo-skip-still-current', () => {
    const cells = [row(CELL)];
    const cur = { cellsHash: cellsHash(cells), beforeTree: 'b', afterTree: 'a' };
    const prev: Manifest = { schema: 1, ...cur };
    const sides = planRender(prev, cur);
    expect(sides).toEqual([]);
    const prevRows = rowsFor([CELL]);
    const { rows, manifest } = assembleMeta({
      cells,
      sides,
      captured: { before: null, after: null },
      prevRows,
      prevManifest: prev,
      cur,
    });
    const oldSha = 'b'.repeat(40);
    const token = renderToken(manifest);
    const oldBlock = buildScreensBlock({ pr: PR, headSha: oldSha, token, rows, galleryPresent: true, runUrl: RUN_URL });
    const newBlock = buildScreensBlock({ pr: PR, headSha: SHA, token, rows, galleryPresent: true, runUrl: RUN_URL });
    expect(newBlock).toContain(`sha=${SHA}`);
    expect(newBlock).not.toContain(`sha=${oldSha}`);
    expect(isNoop({ sides, expectedBlock: newBlock, bodyBlock: oldBlock, prevBlock: oldBlock })).toBe(false);
  });

  it('noop-when-block-matches', () => {
    const a = build(rowsFor([CELL]));
    const b = build(rowsFor([CELL, 'roster-grid']));
    expect(isNoop({ sides: [], expectedBlock: a, bodyBlock: a, prevBlock: a })).toBe(true);
    expect(isNoop({ sides: ['after'], expectedBlock: a, bodyBlock: a, prevBlock: a })).toBe(false);
    expect(isNoop({ sides: [], expectedBlock: b, bodyBlock: a, prevBlock: a })).toBe(false);
    expect(isNoop({ sides: [], expectedBlock: a, bodyBlock: b, prevBlock: a })).toBe(false);
    expect(isNoop({ sides: [], expectedBlock: a, bodyBlock: a, prevBlock: b })).toBe(false);
  });

  it('render-token-changes', () => {
    const t = renderToken(MANIFEST);
    expect(t).toMatch(/^[0-9a-f]{12}$/);
    expect(renderToken({ ...MANIFEST })).toBe(t);
    expect(renderToken({ ...MANIFEST, cellsHash: 'h2' })).not.toBe(t);
    expect(renderToken({ ...MANIFEST, beforeTree: 'b2' })).not.toBe(t);
    expect(renderToken({ ...MANIFEST, afterTree: 'a2' })).not.toBe(t);
  });

  it('cells-hash-sensitive', () => {
    const base = cellsHash([row(CELL)]);
    expect(cellsHash([row(CELL)])).toBe(base);
    expect(base).toMatch(/^[0-9a-f]{64}$/);
    expect(cellsHash([row(CELL, { url: 'modules.php?name=Other' })])).not.toBe(base);
    expect(cellsHash([row(CELL, { role: 'admin' })])).not.toBe(base);
    expect(cellsHash([row(CELL, { anchor: '.y' })])).not.toBe(base);
    expect(cellsHash([row(CELL, { setup: [{ method: 'POST', path: 'test-state.php?action=x' }] })])).not.toBe(base);
  });

  it('parse-pr-number', () => {
    for (const bad of ['', '0', '-1', '12a', '1;rm -rf /', '01', '12345678']) {
      expect(parsePrNumber(bad)).toBeNull();
    }
    expect(parsePrNumber('42')).toBe(42);
  });

  it('head-sha-validation', () => {
    expect(isHeadSha(SHA)).toBe(true);
    expect(isHeadSha('a'.repeat(39))).toBe(false);
    expect(isHeadSha('A'.repeat(40))).toBe(false);
    expect(isHeadSha('g'.repeat(40))).toBe(false);
    expect(isHeadSha('a'.repeat(41))).toBe(false);
  });

  it('assemble-meta-sides', () => {
    const cells = [row(CELL)];
    const cur = { cellsHash: cellsHash(cells), beforeTree: 'b2', afterTree: 'a2' };
    const prevRows: ScreensRows = {
      cells: [CELL],
      before: { [CELL]: okStatus() },
      after: { [CELL]: { phone: 'failed', desktop: 'ok' } },
    };
    const prevManifest: Manifest = { schema: 1, cellsHash: cur.cellsHash, beforeTree: 'b1', afterTree: 'a1' };

    // before rendered but the job died; after not rendered and carried forward.
    const died = assembleMeta({
      cells,
      sides: ['before'],
      captured: { before: null, after: null },
      prevRows,
      prevManifest,
      cur,
    });
    expect(died.rows.before[CELL]).toEqual({ phone: 'missing', desktop: 'missing' });
    expect(died.manifest.beforeTree).toBe('');
    expect(died.rows.after[CELL]).toEqual({ phone: 'failed', desktop: 'ok' });
    expect(died.manifest.afterTree).toBe('a1');
    expect(died.manifest.cellsHash).toBe(cur.cellsHash);
    expect(died.manifest.schema).toBe(1);

    // rendered with rows present.
    const fresh = assembleMeta({
      cells: [row(CELL), row('roster-grid')],
      sides: ['after'],
      captured: { before: null, after: [{ label: CELL, viewports: { phone: 'ok', desktop: 'boom' } }] },
      prevRows,
      prevManifest,
      cur,
    });
    expect(fresh.rows.after[CELL]).toEqual({ phone: 'ok', desktop: 'failed' });
    expect(fresh.rows.after['roster-grid']).toEqual({ phone: 'missing', desktop: 'missing' });
    expect(fresh.manifest.afterTree).toBe('a2');
    expect(fresh.manifest.beforeTree).toBe('b1');
    expect(fresh.rows.cells).toEqual([CELL, 'roster-grid']);

    const skipped = assembleMeta({
      cells,
      sides: ['before'],
      captured: { before: [{ label: CELL, viewports: { phone: 'skipped', desktop: 'ok' } }], after: null },
      prevRows: null,
      prevManifest: null,
      cur,
    });
    expect(skipped.rows.before[CELL]).toEqual({ phone: 'skipped', desktop: 'ok' });
    expect(skipped.rows.after[CELL]).toEqual({ phone: 'missing', desktop: 'missing' });
    expect(skipped.manifest.afterTree).toBe('');
  });

  it('block-image-urls', () => {
    const block = build(rowsFor([CELL, 'roster-grid']));
    const urls = blockImageUrls(block);
    expect(urls).toHaveLength(8);
    for (const u of urls) {
      expect(u).not.toContain('&amp;');
      expect(u.startsWith(PAGES_BASE + 'pr/')).toBe(true);
    }
    const withForeign = block.replace(
      SCREENS_END,
      '<img src="https://evil.example/x.png" alt="x">\n' + SCREENS_END
    );
    expect(blockImageUrls(withForeign)).toEqual(urls);
  });

  it('splice-retry-heals-lost-write', () => {
    const block = build(rowsFor([CELL]));
    let state = FIXTURE;
    let writes = 0;
    const io = {
      read: (): string => state,
      write: (body: string): void => {
        writes++;
        if (writes >= 2) state = body; // first write is clobbered by a concurrent editor
      },
    };
    const res = spliceWithRetry(io, block);
    expect(res).toEqual({ outcome: 'inserted', attempts: 2 });
    expect(writes).toBe(2);
    expect(extractScreensBlock(state)).toBe(block);
  });

  it('splice-retry-optimistic-reread', () => {
    const block = build(rowsFor([CELL]));
    const CHANGED = FIXTURE + '\n\nedited concurrently';
    let state = FIXTURE;
    let reads = 0;
    let writes = 0;
    const io = {
      read: (): string => {
        reads++;
        if (reads === 2) state = CHANGED; // body changes between b0 and b1 of attempt 1
        return state;
      },
      write: (body: string): void => {
        writes++;
        state = body;
      },
    };
    const res = spliceWithRetry(io, block);
    expect(res.attempts).toBe(2);
    expect(res.outcome).toBe('inserted');
    expect(writes).toBe(1); // attempt 1 wrote nothing
    expect(state).toContain('edited concurrently');
    expect(extractScreensBlock(state)).toBe(block);
  });

  it('splice-retry-exhausted', () => {
    const block = build(rowsFor([CELL]));
    let writes = 0;
    const io = {
      read: (): string => FIXTURE,
      write: (): void => {
        writes++; // every write is clobbered
      },
    };
    let res: ReturnType<typeof spliceWithRetry> | undefined;
    expect(() => {
      res = spliceWithRetry(io, block);
    }).not.toThrow();
    expect(res).toEqual({ outcome: 'lost', attempts: 3 });
    expect(writes).toBe(3);
  });
});
