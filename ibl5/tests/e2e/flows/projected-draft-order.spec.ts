import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { setDraftPickNotes } from '../helpers/test-state';

// Projected Draft Order — public page.
test.use({ storageState: publicStorageState() });

const TABLE_SEL = '.projected-draft-order-table, .ibl-data-table';

const TEAM_LINK_SEL =
  '.projected-draft-order-table a[href*="teamid="], .ibl-data-table a[href*="teamid="]';

test.describe('Projected Draft Order flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ProjectedDraftOrder');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft Order/i);
  });

  test('draft order table is visible', async ({ page }) => {
    const table = page.locator(TABLE_SEL).first();
    await expect(table).toBeVisible();
  });

  test('table has expected columns', async ({ page }) => {
    const table = page.locator(TABLE_SEL).first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Team');
  });

  test('table has at least 28 team rows', async ({ page }) => {
    // CI seed has 28 teams in standings — all should appear in round 1
    const table = page.locator(TABLE_SEL).first();
    const rows = table.locator('tbody tr:not(.projected-draft-order-separator)');
    expect(await rows.count()).toBeGreaterThanOrEqual(28);
  });

  test('pick numbers start at 1', async ({ page }) => {
    const table = page.locator(TABLE_SEL).first();
    const pickCells = table.locator('tbody tr:not(.projected-draft-order-separator) td:first-child');
    const firstPick = await pickCells.first().textContent();
    expect(parseInt(firstPick?.trim() ?? '', 10)).toBe(1);
  });

  test('team cells link to team pages', async ({ page }) => {
    const teamLinks = page.locator(TEAM_LINK_SEL);
    const count = await teamLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);

    const href = await teamLinks.first().getAttribute('href');
    expect(href).toContain('name=Team');

    // Navigate and verify
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from Projected Draft Order');
  });

  test('round separator rows exist for multiple rounds', async ({ page }) => {
    // CI seed has round 1 and round 2 picks
    const separators = page.locator('.projected-draft-order-separator');
    const count = await separators.count();
    expect(count).toBeGreaterThanOrEqual(1);
    await expect(separators.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Projected Draft Order page');
  });

  test('notes expander toggles from the keyboard and truncates until expanded', async ({ page, request }) => {
    const year = Number((await page.locator('.ibl-title').first().textContent())?.match(/(\d{4})/)?.[1]);
    expect(year).toBeGreaterThan(1900);

    // 252 characters: under the 280 cap and far wider than the 550px cell cap.
    const note = 'via E2E trade '.repeat(18);
    const previous = await setDraftPickNotes(request, year, 1, 'Metros', note);
    try {
      await page.reload();
      const toggle = page.locator('.projected-draft-order-notes__toggle');
      // The expanded state lives on the cell (td.is-expanded), not on the button.
      const expandedCell = page.locator('td.projected-draft-order-notes.is-expanded');

      // Every other pick's notes are NULL in the seed: no button on those cells.
      await expect(toggle).toHaveCount(1);

      await expect(toggle).toHaveAttribute('aria-expanded', 'false');
      const collapsed = await toggle.evaluate((el) => {
        const cs = getComputedStyle(el);
        const parent = el.closest('td');
        if (parent === null) {
          throw new Error('notes toggle is not inside a td');
        }
        const parentCs = getComputedStyle(parent);
        return {
          whiteSpace: cs.whiteSpace,
          textOverflow: cs.textOverflow,
          truncated: el.scrollWidth > el.clientWidth,
          backgroundColor: cs.backgroundColor,
          borderTopWidth: cs.borderTopWidth,
          fontSize: cs.fontSize,
          color: cs.color,
          parentFontSize: parentCs.fontSize,
          parentColor: parentCs.color,
        };
      });
      expect(collapsed.whiteSpace).toBe('nowrap');
      expect(collapsed.textOverflow).toBe('ellipsis');
      expect(collapsed.truncated).toBe(true);
      expect(collapsed.backgroundColor).toBe('rgba(0, 0, 0, 0)');
      expect(collapsed.borderTopWidth).toBe('0px');
      expect(collapsed.fontSize).toBe(collapsed.parentFontSize);
      expect(collapsed.color).toBe(collapsed.parentColor);

      await toggle.focus();
      await page.keyboard.press('Enter');
      await expect(expandedCell).toHaveCount(1);
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
      expect(await toggle.evaluate((el) => getComputedStyle(el).whiteSpace)).toBe('normal');

      await page.keyboard.press('Space');
      await expect(expandedCell).toHaveCount(0);
      await expect(toggle).toHaveAttribute('aria-expanded', 'false');
      expect(await toggle.evaluate((el) => getComputedStyle(el).whiteSpace)).toBe('nowrap');

      // Mouse parity: a click on the cell padding outside the button still toggles.
      await page
        .locator('td.projected-draft-order-notes:has(.projected-draft-order-notes__toggle)')
        .click({ position: { x: 2, y: 2 } });
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    } finally {
      await setDraftPickNotes(request, year, 1, 'Metros', previous);
    }
  });

  test('set-draft-pick-notes rejects bad input and unknown rows', async ({ request }) => {
    const badRound = await request.delete(
      'test-state.php?action=set-draft-pick-notes&year=2026&round=3&teampick=Metros&notes=x',
    );
    expect(badRound.status()).toBe(400);

    const unknownRow = await request.delete(
      'test-state.php?action=set-draft-pick-notes&year=2026&round=1&teampick=__no_such_team__&notes=x',
    );
    expect(unknownRow.status()).toBe(404);
  });
});
