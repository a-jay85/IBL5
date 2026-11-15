import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
  collectRenderedViews,
  galleryRenderedViewsError,
  renderedViewFields,
  vrRecordingEnabled,
  type CellRecord,
} from './rendered-views';

const BUTTONS = 'ibl5/classes/Player/Views/PlayerButtonsView.php';
const FLIP = 'ibl5/classes/Player/Views/PlayerTradingCardFlipView.php';

const tmpDirs: string[] = [];
let failed = false;

function fixture(records: CellRecord[]): string {
  const dir = mkdtempSync(join(tmpdir(), 'vr-rv-'));
  tmpDirs.push(dir);
  records.forEach((r, i) => {
    writeFileSync(join(dir, `${r.cell}.${i}.json`), JSON.stringify(r));
  });
  return dir;
}

function rec(row: string, cell: string, files: string[]): CellRecord {
  return { row, cell, uri: '/ibl5/modules.php', files };
}

function check(name: string, body: () => string | null): void {
  try {
    const problem = body();
    if (problem === null) {
      console.log(`ok ${name}`);
    } else {
      failed = true;
      console.log(`FAIL ${name}: ${problem}`);
    }
  } catch (e) {
    failed = true;
    console.log(`FAIL ${name}: threw ${e instanceof Error ? e.message : String(e)}`);
  }
}

function same(actual: unknown, expected: unknown): string | null {
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  return a === e ? null : `expected ${e}, got ${a}`;
}

function rejected(result: ReturnType<typeof collectRenderedViews>, reason: string, exact: boolean): string | null {
  if (result.ok) return 'expected ok: false';
  const matches = exact ? result.reason === reason : result.reason.includes(reason);
  return matches ? null : `reason was ${JSON.stringify(result.reason)}`;
}

const playerRecords = (): CellRecord[] => [
  rec('player', 'player-desktop', [FLIP, BUTTONS]),
  rec('player', 'player-mobile', [FLIP, BUTTONS]),
];

check('union-sorted-deduped', () => {
  const r = collectRenderedViews(fixture(playerRecords()), ['player-desktop', 'player-mobile']);
  if (!r.ok) return `expected ok, got ${r.reason}`;
  return same(r.files, [BUTTONS, FLIP]);
});

check('by-row', () => {
  const dir = fixture([...playerRecords(), rec('box', 'box-desktop', [])]);
  const r = collectRenderedViews(dir, ['player-desktop', 'player-mobile', 'box-desktop']);
  if (!r.ok) return `expected ok, got ${r.reason}`;
  return same(r.byRow.player, [BUTTONS, FLIP]) ?? same(r.byRow.box, []);
});

check('missing-dir', () => {
  const dir = mkdtempSync(join(tmpdir(), 'vr-rv-'));
  tmpDirs.push(dir);
  return rejected(collectRenderedViews(join(dir, 'nope'), ['a']), 'missing', false);
});

check('missing-cell', () => {
  const dir = fixture([rec('r', 'a', [BUTTONS])]);
  return rejected(collectRenderedViews(dir, ['a', 'b']), 'no record for cells: b', true);
});

check('invalid-path', () => {
  const dir = fixture([rec('r', 'a', ['ibl5/mainfile.php'])]);
  return rejected(collectRenderedViews(dir, ['a']), 'invalid path', false);
});

check('empty-union', () => {
  const dir = fixture([rec('r', 'a', []), rec('r', 'b', [])]);
  return rejected(collectRenderedViews(dir, ['a', 'b']), 'no View files recorded in any cell', true);
});

check('near-miss-title', () => {
  const dir = fixture([rec('player', 'player-mobile', [BUTTONS])]);
  return rejected(collectRenderedViews(dir, ['player', 'player-mobile']), 'no record for cells: player', true);
});

check('extraneous-cell', () => {
  const dir = fixture([rec('r', 'a', [BUTTONS]), rec('r', 'z', [FLIP])]);
  const r = collectRenderedViews(dir, ['a']);
  if (!r.ok) return `expected ok, got ${r.reason}`;
  return same(r.files, [BUTTONS]);
});

check('no-flag-omits', () => same(renderedViewFields(null, ['a']), {}));

check('failed-collect-omits', () => {
  const dir = mkdtempSync(join(tmpdir(), 'vr-rv-'));
  tmpDirs.push(dir);
  return same(renderedViewFields(join(dir, 'nope'), ['a']), {});
});

check('env-unset-off', () => same(vrRecordingEnabled(undefined), false));
check('env-empty-off', () => same(vrRecordingEnabled(''), false));
check('env-one-on', () => same([vrRecordingEnabled('1'), vrRecordingEnabled('true')], [true, false]));

check('gallery-missing-field', () =>
  galleryRenderedViewsError({ renderedCells: 1 }) === null ? 'expected an error message' : null);
check('gallery-empty-array', () =>
  galleryRenderedViewsError({ renderedViewFiles: [] }) === null ? 'expected an error message' : null);
check('gallery-valid', () =>
  galleryRenderedViewsError({ renderedViewFiles: [BUTTONS] }) === null ? null : 'expected null');

for (const dir of tmpDirs) rmSync(dir, { recursive: true, force: true });
if (failed) process.exit(1);
