import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import type { Locator, Page } from '@playwright/test';
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { test as authRegularTest } from '../fixtures/auth-regular';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import {
  VR_MANIFEST,
  DEFAULT_STATE,
  snapshotFilename,
  type AuthMode,
  type StateVariant,
  type Viewport,
  type VrRow,
  type HtmxTab,
} from '../vr-manifest';

const GLOBAL_MASK_SELECTORS: string[] = [
  'time.local-time',
  '.news-article__meta',
  '[data-volatile="timestamp"]',
];

// Per-run scratch dir for the PR's own renders (two per cell, .a/.b). Never
// committed (see ibl5/.gitignore); consumed by bin/vr-build-gallery to triage
// each cell against master's committed baseline.
const ACTUALS_DIR = 'vr-actuals';

function buildMasks(page: Page, extraMask: string[] = []): Locator[] {
  return [...GLOBAL_MASK_SELECTORS, ...extraMask].map((sel) => page.locator(sel));
}

// The VR gate (`toHaveScreenshot`, below) re-samples the render until it stops
// changing before comparing to the baseline; the raw `.a`/`.b` gallery captures
// were otherwise one-shot, so a render still settling — full-page mobile height,
// a late font/image, or a transient capture throw — made A and B disagree and
// demoted an otherwise-passing cell to infra/flake. Mirror the gate's discipline
// for the raw captures: retry a thrown capture, and re-sample until two
// consecutive shots agree before writing. This is capture FIDELITY, not the
// ADR-gated triage selection (vr-gallery.ts / vr-build-gallery).
const STABLE_MAX_ATTEMPTS = 5;
const STABLE_SETTLE_MS = 250;
// Two consecutive shots count as "settled" when under 0.5% of pixels differ —
// the same GATE_MAX_DIFF_PIXEL_RATIO floor the gallery's A/B self-stability
// check uses (bin/vr-build-gallery). GATE_PIXEL_THRESHOLD 0.2 mirrors the gate's
// per-pixel YIQ threshold (vr-gallery.ts).
const STABLE_MAX_DIFF_RATIO = 0.005;

function consecutiveDiffRatio(a: Buffer, b: Buffer): number {
  const pa = PNG.sync.read(a);
  const pb = PNG.sync.read(b);
  if (pa.width !== pb.width || pa.height !== pb.height) return 1;
  const changed = pixelmatch(pa.data, pb.data, null, pa.width, pa.height, {
    threshold: 0.2,
  });
  return changed / (pa.width * pa.height);
}

type CaptureOpts = { animations: 'disabled'; mask: Locator[]; fullPage?: boolean };

// Capture the target repeatedly until two consecutive shots agree (the render
// has settled), then write the settled buffer. If every attempt throws we write
// nothing — the gallery triages the missing file as infra. If it renders but
// never converges we write the last shot, so a genuine cross-render instability
// still surfaces as an A≠B flake rather than being papered over.
async function captureStable(
  page: Page,
  captureTarget: Locator | Page,
  path: string,
  opts: CaptureOpts,
): Promise<void> {
  let prev: Buffer | null = null;
  for (let attempt = 0; attempt < STABLE_MAX_ATTEMPTS; attempt++) {
    let shot: Buffer;
    try {
      shot = await captureTarget.screenshot(opts);
    } catch {
      // eslint-disable-next-line playwright/no-wait-for-timeout -- deliberate settle: let a transiently-failing render advance before retrying
      await page.waitForTimeout(STABLE_SETTLE_MS);
      continue;
    }
    if (prev && consecutiveDiffRatio(prev, shot) <= STABLE_MAX_DIFF_RATIO) {
      mkdirSync(dirname(path), { recursive: true });
      writeFileSync(path, shot);
      return;
    }
    prev = shot;
    // eslint-disable-next-line playwright/no-wait-for-timeout -- deliberate settle: let the render advance (fonts/images/height) before the next sample
    await page.waitForTimeout(STABLE_SETTLE_MS);
  }
  if (prev) {
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, prev);
  }
}

async function captureSnapshot(
  page: Page,
  row: VrRow,
  state: StateVariant,
  viewport: Viewport,
  tab?: HtmxTab,
): Promise<void> {
  if (viewport === 'mobile') {
    await page.setViewportSize({ width: 375, height: 812 });
  }

  const filename = snapshotFilename(row, state, viewport, tab);
  const title = filename.replace(/\.png$/, '');
  const anchor = page.locator(row.anchor).first();

  // Re-establish the same visual state after a (re)load: settle the network,
  // wait for the anchor, and re-trigger the HTMX tab swap if any. Runs after
  // both the initial navigation and the render-B reload.
  async function settle(): Promise<void> {
    await page.waitForLoadState('networkidle');
    await anchor.waitFor({ state: 'visible' });
    if (tab) {
      await page.locator(tab.trigger).first().click();
      await page.locator(tab.swapTarget).first().waitFor({ state: 'visible' });
      await page.waitForLoadState('networkidle');
    }
  }

  await gotoWithRetry(page, row.url);
  await assertNoPhpErrors(page, `on ${row.url}`);
  await settle();

  // What to screenshot, and whether it's a full-page capture (page only).
  const fullPage = !tab?.swapTarget && !row.elementScreenshot;
  const captureTarget: Locator | Page = tab?.swapTarget
    ? page.locator(tab.swapTarget).first()
    : row.elementScreenshot
      ? anchor
      : page;

  // Capture options for the raw PR renders. Deliberately EXCLUDE
  // maxDiffPixelRatio — that governs the toHaveScreenshot() gate below, not a
  // raw render capture.
  const captureOpts = {
    animations: 'disabled' as const,
    mask: buildMasks(page, row.extraMask),
    ...(fullPage ? { fullPage: true } : {}),
  };

  // Render A — the PR's actual render of this cell. captureStable retries a
  // thrown capture and re-samples until settled; if every attempt throws it
  // writes no .a.png and the gallery builder triages the cell as infra.
  await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.a.png`, captureOpts);

  // Render B — an independent second render after a full reload, used to demote
  // self-disagreeing (flaky) cells out of the change gallery.
  try {
    await page.reload({ waitUntil: 'load' });
    await settle();
    await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.b.png`, captureOpts);
  } catch {
    // A missing .b.png skips the self-stability check (gallery handles null B).
  }

  // The pass/fail gate stays LAST and unchanged — this is what the
  // `update-baselines` regen workflow signs off and what the green/red check
  // reflects. The gallery above is independent of this assertion's outcome.
  const screenshotOpts = {
    animations: 'disabled' as const,
    mask: buildMasks(page, row.extraMask),
    ...(row.extraMaxDiffPixelRatio !== undefined
      ? { maxDiffPixelRatio: row.extraMaxDiffPixelRatio }
      : {}),
  };

  if (tab?.swapTarget) {
    const target = page.locator(tab.swapTarget).first();
    await expect(target).toHaveScreenshot(filename, screenshotOpts);
  } else if (row.elementScreenshot) {
    await expect(anchor).toHaveScreenshot(filename, screenshotOpts);
  } else {
    await expect(page).toHaveScreenshot(filename, {
      fullPage: true,
      ...screenshotOpts,
    });
  }
}

function rowsByAuth(auth: AuthMode): VrRow[] {
  return VR_MANIFEST.filter((r) => r.auth === auth);
}

function expandRow(row: VrRow): Array<{
  state: StateVariant;
  viewport: Viewport;
  tab?: HtmxTab;
  testName: string;
}> {
  const states = row.states ?? [DEFAULT_STATE];
  const viewports = row.viewports ?? ['desktop'];
  const tabs: Array<HtmxTab | undefined> = [undefined, ...(row.htmxTabs ?? [])];
  const cells: Array<{
    state: StateVariant;
    viewport: Viewport;
    tab?: HtmxTab;
    testName: string;
  }> = [];

  for (const state of states) {
    for (const viewport of viewports) {
      for (const tab of tabs) {
        const filename = snapshotFilename(row, state, viewport, tab);
        cells.push({
          state,
          viewport,
          tab: tab ?? undefined,
          testName: filename.replace(/\.png$/, ''),
        });
      }
    }
  }
  return cells;
}

function registerTests(
  testFn: typeof publicTest,
  auth: AuthMode,
  label: string,
  beforeEachHook?: (fixtures: { appState: (s: Record<string, string>) => Promise<void> }) => Promise<void>,
): void {
  testFn.describe(`Visual regression — ${label}`, () => {
    if (beforeEachHook) {
      testFn.beforeEach(async ({ appState }) => {
        await beforeEachHook({ appState });
      });
    }

    for (const row of rowsByAuth(auth)) {
      const cells = expandRow(row);
      for (const cell of cells) {
        testFn(cell.testName, async ({ appState, page }) => {
          if (cell.state.appState && Object.keys(cell.state.appState).length > 0) {
            await appState(cell.state.appState);
          }
          if (row.notes) {
            console.log(`[visual-regression] ${row.name}: ${row.notes}`);
          }
          await captureSnapshot(page, row, cell.state, cell.viewport, cell.tab);
        });
      }
    }
  });
}

// ============================================================
// Public visual regression — no authentication required
// ============================================================

registerTests(publicTest, 'public', 'public pages (full-page)', async ({ appState }) => {
  await appState({ 'Trivia Mode': 'Off' });
});

// ============================================================
// Authenticated visual regression — requires test user
// ============================================================

registerTests(authTest, 'auth', 'authenticated pages (full-page)');

// ============================================================
// Non-admin visual regression — roles_mask=0, no franchise
// ============================================================

registerTests(authRegularTest, 'auth-regular', 'non-admin authenticated pages');
