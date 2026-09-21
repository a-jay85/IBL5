import { test, expect } from '../fixtures/base';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Head-to-Head Records — public page, no authentication required.
test.use({ storageState: publicStorageState() });

const DIMENSIONS = ['franchises', 'teams', 'gms'] as const;
const PHASES = ['heat', 'regular', 'playoffs', 'all'] as const;

/** Submit the filter form with the given selections and wait for the reload. */
async function applyFilters(
  page: Page,
  dimension: string,
  phase: string,
  scope: string,
): Promise<void> {
  const form = page.locator('form.h2h-filter');
  await expect(form).toBeVisible();
  await form.locator('select[name="dimension"]').selectOption(dimension);
  await form.locator('select[name="phase"]').selectOption(phase);
  await form.locator('select[name="scope"]').selectOption(scope);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    form.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);
}

test.describe('Head-to-Head Records flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=HeadToHeadRecords');
  });

  test('anonymous visitor sees the filter form and the matrix', async ({ page }) => {
    await expect(page.locator('form.h2h-filter')).toBeVisible();
    await expect(page.locator('.h2h-matrix')).toBeVisible();
    await assertNoPhpErrors(page, 'on Head-to-Head Records page');
  });

  // The CI seed carries games in all three game_type buckets (HEAT, regular, playoffs)
  // and ibl_gm_tenures rows for every franchise that plays one, so every
  // dimension × phase combination below renders a populated matrix.
  for (const dimension of DIMENSIONS) {
    for (const phase of PHASES) {
      test(`matrix renders for ${dimension} / ${phase}`, async ({ page }) => {
        await applyFilters(page, dimension, phase, 'all');

        await expect(page.locator('.h2h-matrix')).toBeVisible();

        const rows = page.locator('.h2h-matrix tbody tr');
        expect(await rows.count()).toBeGreaterThanOrEqual(2);

        const titles = await page
          .locator('.h2h-matrix tbody td[title]')
          .evaluateAll((cells) => cells.map((c) => c.getAttribute('title') ?? ''));
        expect(titles.length).toBeGreaterThan(0);
        for (const title of titles) {
          expect(title).toMatch(/^\d+-\d+ \(\d+(\.\d+)?%\)$/);
        }
      });
    }
  }

  test('retired era row carries its seeded branding colors', async ({ page }) => {
    await applyFilters(page, 'teams', 'all', 'all');

    // Franchise 10 is the San Antonio Spurs today; the CI seed gives it a retired
    // "Charlotte Hornets" era in ibl_franchise_seasons plus the matching
    // ibl_franchise_era_branding row. No live team is named Hornets, so this label
    // identifies the era row unambiguously and its colour must come from the
    // branding table rather than from ibl_team_info.
    const hornetsRow = page.locator('th.h2h-row-label', { hasText: 'Hornets' });
    await expect(hornetsRow).toHaveCount(1);
    await expect(hornetsRow).toHaveAttribute('style', /--h2h-row-bg:\s*#00788C/i);
  });

  test('the diagonal cell is blank and marked h2h-self', async ({ page }) => {
    await expect(page.locator('.h2h-matrix')).toBeVisible();

    const diagonal = page.locator('.h2h-matrix tbody tr').first().locator('td.h2h-self').first();
    await expect(diagonal).toHaveCount(1);
    expect((await diagonal.innerText()).trim()).toBe('');
  });

  test('invalid filter values fall back to the defaults', async ({ page }) => {
    const response = await page.request.post('modules.php?name=HeadToHeadRecords', {
      form: { dimension: '<script>alert(1)</script>', phase: 'zzz', scope: '0' },
    });
    expect(response.ok()).toBe(true);

    const body = await response.text();
    expect(body).not.toContain('<script>alert(1)</script>');
    expect(body).toContain('value="franchises"');
    await assertNoPhpErrors(page, 'after an invalid Head-to-Head Records POST');
  });
});
