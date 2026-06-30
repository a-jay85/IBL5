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

  // Render A — the PR's actual render of this cell.
  try {
    await captureTarget.screenshot({
      path: `${ACTUALS_DIR}/${title}.a.png`,
      ...captureOpts,
    });
  } catch {
    // A failed render leaves no .a.png; the gallery builder triages it as infra.
  }

  // Render B — an independent second render after a full reload, used to demote
  // self-disagreeing (flaky) cells out of the change gallery.
  try {
    await page.reload({ waitUntil: 'load' });
    await settle();
    await captureTarget.screenshot({
      path: `${ACTUALS_DIR}/${title}.b.png`,
      ...captureOpts,
    });
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
