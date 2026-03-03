import { test, expect } from '../fixtures/auth';

test.describe('Trading flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Trading');

    // Skip all trading tests if trades are currently closed
    const body = await page.locator('body').textContent();
    const tradesClosed =
      body?.includes('Trading is closed') ||
      body?.includes('trades are closed') ||
      body?.includes('trading period');

    if (tradesClosed) {
      test.skip(true, 'Trades are currently closed for the season');
    }
  });

  test('navigate to trade form via team select', async ({ page }) => {
    // Click first team link in the team selection table
    const firstTeamLink = page.locator('.trading-team-select a').first();
    await expect(firstTeamLink).toBeVisible();
    await firstTeamLink.click();

    // Trade form should appear with two roster tables
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
    const rosterTables = page.locator('.trading-roster');
    await expect(rosterTables).toHaveCount(2);
  });

  test('player checkboxes exist in roster tables', async ({ page }) => {
    const firstTeamLink = page.locator('.trading-team-select a').first();
    await firstTeamLink.click();

    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();

    const checkboxes = page.locator('.trading-roster input[type="checkbox"]');
    expect(await checkboxes.count()).toBeGreaterThan(0);
  });

  test('selecting players shows roster preview', async ({ page }) => {
    const firstTeamLink = page.locator('.trading-team-select a').first();
    await firstTeamLink.click();

    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();

    // Preview should be hidden initially
    const preview = page.locator('#trade-roster-preview');
    await expect(preview).toBeHidden();

    // Check one player from each roster table
    const rosterTables = page.locator('.trading-roster');
    for (let i = 0; i < 2; i++) {
      const checkbox = rosterTables.nth(i).locator('input[type="checkbox"]').first();
      if (await checkbox.isVisible()) {
        await checkbox.check();
      }
    }

    // Preview should now be visible
    await expect(preview).toBeVisible();
  });

  test('trade review page loads', async ({ page }) => {
    await page.goto('modules.php?name=Trading&op=reviewtrade');

    // Should show either trade offer cards or an empty state
    const hasOffers = await page.locator('.trade-offer-card').count();
    if (hasOffers > 0) {
      await expect(page.locator('.trade-offer-card').first()).toBeVisible();
    } else {
      // Empty state — just verify no PHP errors
      const body = await page.locator('body').textContent();
      expect(body).not.toContain('Fatal error');
    }
  });
});
