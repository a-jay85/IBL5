import { describe, it, expect } from 'vitest';
import {
  parseVrCell,
  parseManualBullets,
  manualShotFile,
  manualShotUrl,
  buildManualSection,
  type ManualRowResult,
} from '../e2e/vr-manual-rows';

const PAGES_URL = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review/';

const FULL_CELL =
  'vr: label=roster-grid; role=admin; url=modules.php?name=Roster&teamID=1; ' +
  'anchor=.ibl-data-table; setup=DELETE test-state.php?action=reset-draft-order';

function bullet(n: number, cell: string): string {
  return `- [ ] **Row ${n}** — does it look right? \`${cell}\``;
}

function body(...bullets: string[]): string {
  return ['# PR', '', '## Manual Testing', '', ...bullets, '', '## Next Section', 'other'].join(
    '\n'
  );
}

describe('parseVrCell', () => {
  it('parses a full vr cell', () => {
    const r = parseVrCell(FULL_CELL);
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.row.label).toBe('roster-grid');
    expect(r.row.role).toBe('admin');
    expect(r.row.url).toBe('modules.php?name=Roster&teamID=1');
    expect(r.row.anchor).toBe('.ibl-data-table');
    expect(r.row.setup).toEqual([
      { method: 'DELETE', path: 'test-state.php?action=reset-draft-order' },
    ]);
  });

  it('parses a minimal vr cell', () => {
    const r = parseVrCell('vr: label=home; role=anon; url=index.php');
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.row.anchor).toBe('body');
    expect(r.row.setup).toEqual([]);
  });

  it('strips wrapping backticks', () => {
    const r = parseVrCell('`vr: label=home; role=anon; url=index.php`');
    expect(r.ok).toBe(true);
  });

  it('keeps repeated setup requests in written order', () => {
    const r = parseVrCell(
      'vr: label=home; role=anon; url=index.php; ' +
        'setup=GET test-state.php?action=clear-throttle; ' +
        'setup=POST test-state.php?action=seed-reset-user'
    );
    expect(r.ok).toBe(true);
    if (!r.ok) return;
    expect(r.row.setup.map((s) => s.method)).toEqual(['GET', 'POST']);
  });

  it('rejects a pipe in the cell', () => {
    const r = parseVrCell('vr: label=home; role=anon|admin; url=index.php');
    expect(r).toEqual({ ok: false, error: 'vr cell must not contain "|"' });
  });

  it('rejects an unknown key', () => {
    const r = parseVrCell('vr: labl=home; role=anon; url=index.php');
    expect(r).toEqual({ ok: false, error: 'unknown key: labl' });
  });

  it('rejects a bad role', () => {
    const r = parseVrCell('vr: label=home; role=owner; url=index.php');
    expect(r).toEqual({ ok: false, error: 'bad role: owner' });
  });

  it('rejects a setup outside test-state.php', () => {
    const r = parseVrCell('vr: label=home; role=anon; url=index.php; setup=GET index.php?action=x');
    expect(r).toEqual({
      ok: false,
      error: 'setup path must start with "test-state.php?action=": index.php?action=x',
    });
  });

  it('rejects a bad setup method', () => {
    const r = parseVrCell(
      'vr: label=home; role=anon; url=index.php; setup=PATCH test-state.php?action=clear-throttle'
    );
    expect(r).toEqual({ ok: false, error: 'bad setup method: PATCH' });
  });

  it('rejects an absolute url', () => {
    const r = parseVrCell('vr: label=home; role=anon; url=http://evil.test/index.php');
    expect(r).toEqual({
      ok: false,
      error: 'url must be BASE_URL-relative (no scheme): http://evil.test/index.php',
    });
  });

  it('rejects a url with a leading slash', () => {
    const r = parseVrCell('vr: label=home; role=anon; url=/index.php');
    expect(r).toEqual({ ok: false, error: 'url must not start with "/": /index.php' });
  });

  it('rejects an uppercase label', () => {
    const r = parseVrCell('vr: label=Home; role=anon; url=index.php');
    expect(r).toEqual({ ok: false, error: 'label is not a kebab slug: Home' });
  });

  it('rejects a missing required key', () => {
    expect(parseVrCell('vr: label=home; role=anon')).toEqual({
      ok: false,
      error: 'missing required key: url',
    });
    expect(parseVrCell('vr: role=anon; url=index.php')).toEqual({
      ok: false,
      error: 'missing required key: label',
    });
  });

  it('rejects a duplicate non-setup key', () => {
    const r = parseVrCell('vr: label=home; label=away; role=anon; url=index.php');
    expect(r).toEqual({ ok: false, error: 'duplicate key: label' });
  });

  it('rejects a cell that does not start with vr:', () => {
    expect(parseVrCell('label=home; role=anon; url=index.php')).toEqual({
      ok: false,
      error: 'vr cell must start with "vr:"',
    });
  });
});

describe('parseManualBullets', () => {
  it('reads only the Manual Testing section', () => {
    const b = [
      '## Other',
      bullet(1, 'vr: label=outside; role=anon; url=index.php'),
      '## Manual Testing',
      bullet(2, 'vr: label=inside; role=anon; url=index.php'),
      '## After',
      bullet(3, 'vr: label=after; role=anon; url=index.php'),
    ].join('\n');
    const { rows, errors } = parseManualBullets(b);
    expect(errors).toEqual([]);
    expect(rows.map((r) => r.label)).toEqual(['inside']);
    expect(rows[0].row).toBe('2');
  });

  it('skips a bullet with no vr token', () => {
    const { rows, errors } = parseManualBullets(
      body('- [ ] **Row 1** — the CSV downloads and opens in Excel')
    );
    expect(rows).toEqual([]);
    expect(errors).toEqual([]);
  });

  it('rejects a duplicate label', () => {
    const { rows, errors } = parseManualBullets(
      body(
        bullet(1, 'vr: label=dup; role=anon; url=index.php'),
        bullet(2, 'vr: label=dup; role=admin; url=other.php')
      )
    );
    expect(rows.map((r) => r.label)).toEqual(['dup']);
    expect(errors).toEqual(['Row 2: duplicate label: dup']);
  });

  it('reports a malformed cell as an error, not a row', () => {
    const { rows, errors } = parseManualBullets(
      body(bullet(1, 'vr: label=home; role=owner; url=index.php'))
    );
    expect(rows).toEqual([]);
    expect(errors).toEqual(['Row 1: bad role: owner']);
  });

  it('accepts a checked box', () => {
    const { rows } = parseManualBullets(
      body('- [x] **Row 4** — looks fine `vr: label=done; role=anon; url=index.php`')
    );
    expect(rows.map((r) => r.label)).toEqual(['done']);
  });

  it('returns empty when there is no Manual Testing section', () => {
    expect(parseManualBullets('# PR\n\n## Summary\n\ntext')).toEqual({ rows: [], errors: [] });
  });
});

describe('manualShotFile / manualShotUrl', () => {
  it('names the PNG after the label', () => {
    expect(manualShotFile('roster-grid')).toBe('roster-grid.png');
  });

  it('builds the pages URL under manual-rows/', () => {
    expect(manualShotUrl(PAGES_URL, 'roster-grid')).toBe(`${PAGES_URL}manual-rows/roster-grid.png`);
  });

  it('buildManualSection normalizes a pages url without a trailing slash', () => {
    const base = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review';
    expect(manualShotUrl(base, 'home')).toBe(`${base}/manual-rows/home.png`);
    const md = buildManualSection([{ label: 'home', row: '1', status: 'ok' }], base);
    expect(md).toContain(`${base}/manual-rows/home.png`);
  });
});

describe('buildManualSection', () => {
  it('buildManualSection emits an image per ok row', () => {
    const results: ManualRowResult[] = [
      { label: 'home', row: '1', status: 'ok' },
      { label: 'roster-grid', row: '2', status: 'ok' },
    ];
    const md = buildManualSection(results, PAGES_URL);
    expect((md.match(/!\[/g) ?? []).length).toBe(2);
    expect(md).toContain(`![home](${PAGES_URL}manual-rows/home.png)`);
    expect(md).toContain('### 📸 Manual-row screenshots');
  });

  it('buildManualSection emits no image for a failed row', () => {
    const md = buildManualSection(
      [{ label: 'broken', row: '3', status: 'failed', error: 'timeout waiting for .x' }],
      PAGES_URL
    );
    expect(md).not.toContain('![');
    expect(md).toContain('**Row 3 — `broken`** — failed: timeout waiting for .x');
  });

  it('buildManualSection emits no image for a skipped row', () => {
    const md = buildManualSection([{ label: 'later', row: '4', status: 'skipped' }], PAGES_URL);
    expect(md).not.toContain('![');
    expect(md).toContain('**Row 4 — `later`** — skipped');
  });

  it('buildManualSection mixes ok and failed rows without cross-contamination', () => {
    // Mutation guard: an image must be tied to its OWN row's status. If the
    // builder ever emitted an image per row regardless of status, the count
    // would be 2 instead of 1 and the failed label would appear inside `![`.
    const md = buildManualSection(
      [
        { label: 'good', row: '1', status: 'ok' },
        { label: 'bad', row: '2', status: 'failed', error: 'nav timeout' },
      ],
      PAGES_URL
    );
    expect((md.match(/!\[/g) ?? []).length).toBe(1);
    expect(md).toContain(`![good](${PAGES_URL}manual-rows/good.png)`);
    expect(md).not.toContain('![bad]');
    expect(md).toContain('**Row 2 — `bad`** — failed: nav timeout');
  });

  it('buildManualSection returns empty for zero rows', () => {
    expect(buildManualSection([], PAGES_URL)).toBe('');
  });
});
