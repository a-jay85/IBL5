import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { assertNoHorizontalOverflow } from '../helpers/mobile';

// Depth Chart Entry — mobile card view tests.
// Runs at 375x812 (iPhone viewport) to verify the card layout.

test.describe('Depth Chart Entry: mobile card view', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');
  });

  test('no PHP errors at mobile viewport', async ({ page }) => {
    await assertNoPhpErrors(page, 'on DCE mobile');
  });

  test('no horizontal overflow', async ({ page }) => {
    await assertNoHorizontalOverflow(page, 'on DCE mobile');
  });

  test('mobile cards are visible', async ({ page }) => {
    const cards = page.locator('.dc-mobile-cards');
    await expect(cards).toBeVisible();
  });

  test('desktop table is hidden on mobile', async ({ page }) => {
    const tableWrapper = page.locator('.depth-chart-form .text-center');
    await expect(tableWrapper).not.toBeVisible();
  });

  test('each card has a player photo', async ({ page }) => {
    const photos = page.locator('.dc-card__photo');
    await expect(photos.first()).toBeVisible();
    expect(await photos.count()).toBeGreaterThan(0);
  });

  test('each card has a position badge', async ({ page }) => {
    const badges = page.locator('.dc-card__pos-badge');
    await expect(badges.first()).toBeVisible();
    const text = await badges.first().textContent();
    expect(['PG', 'SG', 'SF', 'PF', 'C']).toContain(text?.trim());
  });

  test('each card has player name link', async ({ page }) => {
    const names = page.locator('.dc-card__name');
    await expect(names.first()).toBeVisible();
    const href = await names.first().getAttribute('href');
    expect(href).toContain('pid=');
  });

  test('active toggle checkbox renders in each card', async ({ page }) => {
    const toggles = page.locator('.dc-card__active-cb');
    await expect(toggles.first()).toBeAttached();
    expect(await toggles.count()).toBeGreaterThan(0);
  });

  test('active toggle changes card opacity', async ({ page }) => {
    const firstCard = page.locator('.dc-card').first();
    const checkbox = firstCard.locator('.dc-card__active-cb');
    const toggle = firstCard.locator('.dc-card__active-toggle');

    const wasChecked = await checkbox.isChecked();

    // Click the visible toggle label (checkbox is visually hidden)
    await toggle.click();

    if (wasChecked) {
      await expect(firstCard).toHaveClass(/dc-card--inactive/);
    } else {
      await expect(firstCard).not.toHaveClass(/dc-card--inactive/);
    }

    // Toggle back
    await toggle.click();
  });

  test('position selects are enabled on mobile', async ({ page }) => {
    const pgSelects = page.locator('.dc-mobile-cards select[name^="pg"]');
    await expect(pgSelects.first()).toBeEnabled();
  });

  test('position grid has 5 columns per card', async ({ page }) => {
    const firstGrid = page.locator('.dc-card__pos-grid').first();
    const fields = firstGrid.locator('.dc-card__field');
    await expect(fields).toHaveCount(5);
  });

  test('settings grid has 6 columns per card', async ({ page }) => {
    const firstGrid = page.locator('.dc-card__settings-grid').first();
    const fields = firstGrid.locator('.dc-card__field');
    await expect(fields).toHaveCount(6);
  });

  test('changing a card select triggers glow', async ({ page }) => {
    const firstSelect = page.locator('.dc-mobile-cards select[name^="pg"]').first();
    const originalValue = await firstSelect.inputValue();

    // Find a different value
    const options = firstSelect.locator('option');
    const count = await options.count();
    for (let i = 0; i < count; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val !== originalValue) {
        await firstSelect.selectOption(val!);
        break;
      }
    }

    // Glow should appear on the changed select
    await expect(page.locator('.dc-mobile-cards [class*="dc-glow-"]').first()).toBeAttached();

    // Revert
    await firstSelect.selectOption(originalValue);
  });

  test('nav bar stays fixed when scrolling', async ({ page }) => {
    const nav = page.locator('nav').first();

    // Scroll down
    await page.evaluate(() => window.scrollTo(0, 1000));
    await page.waitForTimeout(100);

    const navBox = await nav.boundingBox();
    expect(navBox).not.toBeNull();
    expect(navBox!.y).toBeLessThanOrEqual(1);
  });

  test('mobile footer has submit and reset buttons', async ({ page }) => {
    const footer = page.locator('.dc-mobile-cards__footer');
    await expect(footer).toBeVisible();
    await expect(footer.locator('.depth-chart-submit-btn')).toBeVisible();
    await expect(footer.locator('.depth-chart-reset-btn')).toBeVisible();
  });

  test('player names are abbreviated', async ({ page }) => {
    // Names should be abbreviated on mobile (e.g., "B. Hurley" not "Bobby Hurley")
    const firstCardName = page.locator('.dc-card__name').first();
    const text = await firstCardName.textContent();
    // Abbreviated names have a period after first initial
    expect(text?.trim()).toMatch(/^[A-Z]\./);
  });

  test('saved DC dropdown fits within viewport', async ({ page }) => {
    const dropdown = page.locator('.saved-dc-dropdown-container');
    await expect(dropdown).toBeVisible();
    const box = await dropdown.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeLessThanOrEqual(375);
  });
});

test.describe('DCE mobile: resize sync', () => {
  test('switching from mobile to desktop syncs values', async ({ page }) => {
    // Start at mobile
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');

    // Change a value on mobile card
    const mobileSelect = page.locator('.dc-mobile-cards select[name^="pg"]').first();
    await expect(mobileSelect).toBeEnabled();
    const originalValue = await mobileSelect.inputValue();
    const newValue = originalValue === '0' ? '1' : '0';
    await mobileSelect.selectOption(newValue);

    // Switch to desktop
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.waitForTimeout(200); // debounce

    // Desktop table should now show the changed value
    const desktopSelect = page.locator('.depth-chart-table select[name^="pg"]').first();
    await expect(desktopSelect).toBeEnabled();
    const desktopValue = await desktopSelect.inputValue();
    expect(desktopValue).toBe(newValue);
  });
});
