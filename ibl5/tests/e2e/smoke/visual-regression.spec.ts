// Do NOT swap page.goto for gotoWithRetry in this PR — that's a behavior
// change; defer to a follow-up.
// TODO: migrate vr-anchors-discriminate.spec.ts to consume vr-manifest.ts.
import type { Locator, Page } from '@playwright/test';
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { test as authRegularTest } from '../fixtures/auth-regular';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
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
  await page.goto(row.url);
  await assertNoPhpErrors(page, `on ${row.url}`);
  await page.waitForLoadState('networkidle');
  const anchor = page.locator(row.anchor).first();
  await anchor.waitFor({ state: 'visible' });

  if (tab) {
    await page.locator(tab.trigger).first().click();
    await page.locator(tab.swapTarget).first().waitFor({ state: 'visible' });
    await page.waitForLoadState('networkidle');
  }

  const filename = snapshotFilename(row, state, viewport, tab);
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
