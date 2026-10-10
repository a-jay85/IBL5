import { existsSync, readFileSync, writeFileSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import { test } from './fixtures/base';
import {
  type ManualRowResult,
  type ManualRowStatus,
  type ManualVrRow,
  type VrRole,
} from './vr-manual-rows';
import { VIEWPORTS, manualShotFileFor, type Viewport } from './vr-pr-screens';

/**
 * Capture spec for the truly-manual visual-row screenshot pipeline (ADR-0126).
 *
 * Input:  ibl5/vr-manual-rows.json — written by
 *         `bin/vr-review-comment --manual-rows-from-pr=N` (shipped in #2216).
 * Output: ibl5/vr-manual-shots/<label>.phone.png and <label>.desktop.png per row, plus the SAME
 *         vr-manual-rows.json rewritten in place with a `status` (and `error`)
 *         merged onto every row. That merged file is what
 *         `bin/vr-review-comment --manual-gallery=…` reads to build the
 *         sticky PR comment.
 *
 * These screenshots are one-shot review aids, never a gate: nothing is compared
 * against a baseline, and the workflow step runs under `continue-on-error`.
 * Every row therefore records a status instead of throwing — one broken `vr:`
 * cell must not suppress the other rows' screenshots.
 *
 * DB safety: this runs in the `VR PR screens` workflow's render job, on its own
 * stack with its own DB, so a `setup` mutation can never reach a baseline. The
 * `vr:` grammar restricts `setup` to `test-state.php?action=…`, whose actions
 * are individually idempotent, so running them once per viewport is safe.
 */

const __specDir = dirname(fileURLToPath(import.meta.url));
const IBL5_DIR = resolve(__specDir, '..', '..');
const ROWS_JSON = resolve(IBL5_DIR, 'vr-manual-rows.json');
const SHOTS_DIR = resolve(IBL5_DIR, 'vr-manual-shots');

const AUTH_STATE: Record<Exclude<VrRole, 'anon'>, string> = {
  admin: resolve(IBL5_DIR, 'playwright/.auth/user.json'),
  regular: resolve(IBL5_DIR, 'playwright/.auth/regular.json'),
};

const ANON_STATE = { cookies: [], origins: [] };

type RowsFile = { rows?: ManualVrRow[]; errors?: string[] };

function loadRows(): { file: RowsFile; rows: ManualVrRow[] } {
  if (!existsSync(ROWS_JSON)) return { file: {}, rows: [] };
  try {
    const parsed = JSON.parse(readFileSync(ROWS_JSON, 'utf-8')) as RowsFile;
    return { file: parsed, rows: Array.isArray(parsed.rows) ? parsed.rows : [] };
  } catch {
    // A corrupt input file means zero rows, never a crashed capture step.
    return { file: {}, rows: [] };
  }
}

const { file: rowsFile, rows } = loadRows();

// Statuses accumulate across tests; afterAll merges them back into the file.
// Keyed by `${label}\u0000${viewport}` so each viewport records its own status.
const statuses = new Map<string, { status: ManualRowStatus; error?: string }>();

function record(label: string, viewport: Viewport, status: ManualRowStatus, error?: string): void {
  statuses.set(`${label}\u0000${viewport}`, error === undefined ? { status } : { status, error });
}

function statusFor(label: string, viewport: Viewport): { status: ManualRowStatus; error?: string } {
  return statuses.get(`${label}\u0000${viewport}`) ?? { status: 'failed', error: 'not captured' };
}

if (rows.length > 0) {
  test.afterAll(() => {
    const merged: Array<ManualRowResult & { viewports: Record<Viewport, ManualRowStatus> }> = rows.map((r) => {
      const phone = statusFor(r.label, 'phone');
      const desktop = statusFor(r.label, 'desktop');
      // Overall status: ok only when both are ok, else the first non-ok viewport's.
      const worst = phone.status !== 'ok' ? phone : desktop;
      return {
        label: r.label,
        row: r.row,
        status: worst.status,
        viewports: { phone: phone.status, desktop: desktop.status },
        ...(worst.error ? { error: worst.error } : {}),
      };
    });
    writeFileSync(ROWS_JSON, JSON.stringify({ ...rowsFile, rows: merged }, null, 2) + '\n');
  });

  for (const row of rows) {
    // Fall back to ANON_STATE when regular.json is absent so test.use() doesn't
    // throw before the body guard can record 'skipped' (Playwright resolves a
    // path-string storageState in the context fixture, before the test body runs).
    const storageState =
      row.role === 'anon'
        ? ANON_STATE
        : row.role === 'regular' && !existsSync(AUTH_STATE.regular)
          ? ANON_STATE
          : AUTH_STATE[row.role];

    for (const viewport of Object.keys(VIEWPORTS) as Viewport[]) {
      test.describe(`manual row ${row.row} — ${row.label} (${viewport})`, () => {
        test.use({ storageState, viewport: VIEWPORTS[viewport] });

        test(`capture ${row.label}`, async ({ page, request }) => {
          // A `regular` row is skipped rather than failed when regular.json is
          // absent — auth-regular.setup.ts skips itself when
          // IBL_TEST_USER_REGULAR is unset, which is the documented local default.
          if (row.role === 'regular' && !existsSync(AUTH_STATE.regular)) {
            record(row.label, viewport, 'skipped', 'playwright/.auth/regular.json missing (IBL_TEST_USER_REGULAR unset)');
            return;
          }

          try {
            for (const s of row.setup) {
              const method = s.method.toUpperCase();
              const resp =
                method === 'POST'
                  ? await request.post(s.path)
                  : method === 'DELETE'
                    ? await request.delete(s.path)
                    : await request.get(s.path);
              if (!resp.ok()) {
                // Abandon the row — never retried, never fixed up.
                record(row.label, viewport, 'failed', `setup ${method} ${s.path} -> HTTP ${resp.status()}`);
                return;
              }
            }

            await page.goto(row.url);
            await page.locator(row.anchor).first().waitFor({ state: 'visible' });
            await page.screenshot({
              path: resolve(SHOTS_DIR, manualShotFileFor(row.label, viewport)),
              fullPage: true,
            });
            record(row.label, viewport, 'ok');
          } catch (err) {
            record(row.label, viewport, 'failed', err instanceof Error ? err.message : String(err));
          }
        });
      });
    }
  }
}
