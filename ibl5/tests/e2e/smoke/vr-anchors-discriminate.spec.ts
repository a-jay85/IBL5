/**
 * Verifies that every visual-regression anchor selector resolves to a visible
 * element on its URL. If a module fails to render its primary content, this
 * test surfaces the broken anchor before the VR screenshot diff can silently
 * pass with a blank baseline.
 *
 * Rows are sourced from VR_MANIFEST (the single source of truth) so this
 * discriminator and the screenshot spec (visual-regression.spec.ts) cannot
 * drift apart. Each manifest row produces exactly one anchor check here — the
 * discriminator does not expand by viewport/state/tab (that is the screenshot
 * spec's concern); for multi-state rows it uses the first (canonical/default)
 * state.
 */
import type { Page } from '@playwright/test';
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { test as authRegularTest } from '../fixtures/auth-regular';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { VR_MANIFEST, type AuthMode, type VrRow } from '../vr-manifest';

type AppStateFn = (state: Record<string, string>) => Promise<void>;

function rowsByAuth(auth: AuthMode): VrRow[] {
  return VR_MANIFEST.filter((row) => row.auth === auth);
}

async function checkAnchorRow(page: Page, appState: AppStateFn, row: VrRow): Promise<void> {
  // Multi-state rows (player-movement, draft, free-agency, voting, waivers) use
  // their first state — the canonical/default one — for the anchor check.
  const state = row.states?.[0]?.appState ?? {};
  if (Object.keys(state).length > 0) {
    await appState(state);
  }

  await page.goto(row.url);
  await assertNoPhpErrors(page, `on ${row.url}`);

  const timeoutOpt = row.timeout ? { timeout: row.timeout } : undefined;
  await expect(page.locator(row.anchor).first()).toBeVisible(timeoutOpt);

  if (!row.skipContentCheck) {
    const anchor = page.locator(row.anchor).first();
    if (row.anchor === '.ibl-data-table' || row.anchor.startsWith('table.')) {
      await expect(anchor.locator('tbody tr').first()).toBeVisible(timeoutOpt);
    } else if (row.anchor.startsWith('form[')) {
      await expect(
        anchor.locator('input:not([type="hidden"]), select, textarea').first(),
      ).toBeVisible(timeoutOpt);
    }
  }
}

publicTest.describe('VR anchor discrimination — public pages', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const row of rowsByAuth('public')) {
    publicTest(`${row.name} anchor resolves`, async ({ appState, page }) => {
      await checkAnchorRow(page, appState, row);
    });
  }
});

authTest.describe('VR anchor discrimination — authenticated pages', () => {
  for (const row of rowsByAuth('auth')) {
    authTest(`${row.name} anchor resolves`, async ({ appState, page }) => {
      await checkAnchorRow(page, appState, row);
    });
  }
});

authRegularTest.describe('VR anchor discrimination — non-admin authenticated pages', () => {
  for (const row of rowsByAuth('auth-regular')) {
    authRegularTest(`${row.name} anchor resolves`, async ({ appState, page }) => {
      await checkAnchorRow(page, appState, row);
    });
  }
});
