import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Team page — expiring-contract players stay visible but render faded during
// Draft / Free Agency. The greps in the plan's Phase 6.5 prove the CSS rule
// compiled; only the computed-style check below proves it wins the cascade
// against the .ibl-data-table striping rules.

const TEAM_RATINGS_URL = 'modules.php?name=Team&op=team&teamid=1&display=ratings';
const DEPTH_CHART_URL = 'modules.php?name=DepthChartEntry';

test.describe('Team page expiring-player fade', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
  });

  test('expiring-contract rows are marked during Free Agency', async ({ page }) => {
    await page.goto(TEAM_RATINGS_URL);

    const fadedRows = page.locator('table.team-table tbody tr.player-fa-expiring-row');
    await expect(fadedRows.first()).toBeVisible();
    // CI seed gives team 1 four players whose contract year equals its total.
    expect(await fadedRows.count()).toBeGreaterThanOrEqual(1);

    await assertNoPhpErrors(page, TEAM_RATINGS_URL);
  });

  test('faded rows use one background distinct from both stripe colors', async ({ page }) => {
    await page.goto(TEAM_RATINGS_URL);

    const table = page.locator('table.team-table').first();
    await expect(table.locator('tbody tr.player-fa-expiring-row').first()).toBeVisible();

    // Comparing one faded row against one normal row would pass trivially:
    // .ibl-data-table stripes odd/even rows differently, so any two rows of
    // opposite parity already differ. Instead collect every background in the
    // tbody -- the faded rows must collapse to a single colour that appears
    // in neither stripe.
    const { faded, normal } = await table.evaluate((el) => {
      const bg = (row: Element) => getComputedStyle(row).backgroundColor;
      const rows = Array.from(el.querySelectorAll('tbody tr'));
      return {
        faded: rows.filter((r) => r.classList.contains('player-fa-expiring-row')).map(bg),
        normal: rows.filter((r) => !r.classList.contains('player-fa-expiring-row')).map(bg),
      };
    });

    expect(new Set(faded).size).toBe(1);
    expect(new Set(normal).size).toBeGreaterThanOrEqual(2); // striping is live
    expect(normal).not.toContain(faded[0]);
  });

  test('Depth Chart never marks expiring rows', async ({ page }) => {
    await page.goto(DEPTH_CHART_URL);

    // DepthChartEntryController shares the render path but never passes the
    // flag, so the class must not appear on any of its rows.
    await expect(page.locator('tr.player-fa-expiring-row')).toHaveCount(0);

    await assertNoPhpErrors(page, DEPTH_CHART_URL);
  });
});
