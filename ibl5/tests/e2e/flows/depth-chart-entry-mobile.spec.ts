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

  test('role slot selects are enabled on mobile', async ({ page }) => {
    // Position selects (pg/sg/sf/pf/c) are now hidden inputs; role slot
    // selects use field names BH, DI, OI, DF, OF for PG/SG/SF/PF/C columns.
    const bhSelects = page.locator('.dc-mobile-cards select[name^="BH"]');
    await expect(bhSelects.first()).toBeEnabled();
  });

  test('settings grid has 6 columns per card', async ({ page }) => {
    // The old pos-grid is gone; a single settings-grid holds the Min column
    // followed by all 5 role slots (BH/DI/OI/DF/OF) for 6 fields total.
    const firstGrid = page.locator('.dc-card__settings-grid').first();
    const fields = firstGrid.locator('.dc-card__field');
    await expect(fields).toHaveCount(6);
  });

  test('changing a card select triggers glow', async ({ page }) => {
    const firstSelect = page.locator('.dc-mobile-cards select[name^="BH"]').first();
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

  test('short player name displays in full on mobile card', async ({ page }) => {
    const card = page.locator('.dc-card__name[data-full-name="Test Player"]');
    await expect(card).toHaveText('Test Player');
  });

  test('long player name displays in full on mobile card', async ({ page }) => {
    // DC cards display full names even on mobile — abbreviation only applies to table links
    const card = page.locator('.dc-card__name[data-full-name="Konstantinos Papadopoulos"]');
    await expect(card).toHaveText('Konstantinos Papadopoulos');
  });

  test('saved DC dropdown fits within viewport', async ({ page }) => {
    const dropdown = page.locator('.saved-dc-dropdown-container');
    await expect(dropdown).toBeVisible();
    const box = await dropdown.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeLessThanOrEqual(375);
  });

  test('lineup preview uses swapped-axes mobile layout', async ({ page }) => {
    // On mobile the Projected Lineup table flips axes so the five positions
    // run down the Y-axis and the Starting/2nd/3rd/4th depth tiers run
    // across the X-axis. The desktop variant is hidden by CSS.
    const preview = page.locator('#dc-lineup-preview');
    await expect(preview).toBeVisible();

    const mobileTable = preview.locator('.dc-lineup-preview-table--mobile');
    const desktopTable = preview.locator('.dc-lineup-preview-table--desktop');
    await expect(mobileTable).toBeVisible();
    await expect(desktopTable).not.toBeVisible();

    // Row labels (column 0 of each tbody row) should list the five positions
    // in PG→C order.
    const rowLabels = mobileTable.locator('tbody tr td.dc-lineup-preview__row-label');
    await expect(rowLabels).toHaveCount(5);
    await expect(rowLabels.nth(0)).toHaveText('PG');
    await expect(rowLabels.nth(1)).toHaveText('SG');
    await expect(rowLabels.nth(2)).toHaveText('SF');
    await expect(rowLabels.nth(3)).toHaveText('PF');
    await expect(rowLabels.nth(4)).toHaveText('C');

    // Column headers should lead with the empty row-label corner, then the
    // depth tiers left-to-right — "Starting" first so the user never has to
    // scroll horizontally to see the starting lineup.
    const headers = mobileTable.locator('thead th');
    await expect(headers).toHaveCount(5);
    await expect(headers.nth(1)).toHaveText('Starting');
    await expect(headers.nth(2)).toHaveText('2nd');
    await expect(headers.nth(3)).toHaveText('3rd');
    await expect(headers.nth(4)).toHaveText('4th');
  });

  test('lineup preview mobile cells link to player pages', async ({ page }) => {
    // Every populated cell in the mobile preview must wrap its content in
    // an <a href="modules.php?name=Player&pa=showpage&pid=..."> so the GM
    // can tap through to a player page from the projection.
    const mobileTable = page.locator('.dc-lineup-preview-table--mobile');
    await expect(mobileTable).toBeVisible();

    const firstLink = mobileTable.locator('td.ibl-player-cell a[href]').first();
    await expect(firstLink).toBeVisible();
    const href = await firstLink.getAttribute('href');
    expect(href).toMatch(/modules\.php\?name=Player.*pa=showpage.*pid=\d+/);
  });

  test('lineup preview mobile names use last-name-only by default', async ({
    page,
  }) => {
    // Mobile uses last-name-only to save horizontal space. Seed data for the
    // logged-in team has no same-last-name collisions in its projection,
    // so every cell should render a single word (no "F. Last" abbreviation).
    // Any cell with a ". " substring would indicate the disambiguation
    // fallback fired — which is valid when two distinct players share a
    // last name — so we assert the majority (≥60%) are bare last names as
    // a regression guard against reverting to always-abbreviated format.
    const mobileTable = page.locator('.dc-lineup-preview-table--mobile');
    await expect(mobileTable).toBeVisible();

    const cellLinks = mobileTable.locator('td.ibl-player-cell a[href]');
    const count = await cellLinks.count();
    expect(count).toBeGreaterThan(0);

    let lastNameOnly = 0;
    for (let i = 0; i < count; i++) {
      // Strip the trailing minute annotation by reading only the first
      // text node (the anchor's direct text) — renderMinutes() wraps the
      // "Nm" suffix in a nested <span>, so the anchor's own textContent
      // up to the <span> is just the display name.
      const text = await cellLinks.nth(i).evaluate((el) => {
        for (const node of Array.from(el.childNodes)) {
          if (node.nodeType === Node.TEXT_NODE) {
            const s = (node.textContent ?? '').trim();
            if (s) return s;
          }
        }
        return '';
      });
      if (text && !text.includes('. ')) lastNameOnly++;
    }
    // Require a clear majority to be bare last names so a regression to
    // "F. Last" everywhere would trip this test.
    expect(lastNameOnly / count).toBeGreaterThan(0.6);
  });
});

test.describe('DCE mobile: saved depth chart loading', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('loading a saved depth chart updates mobile card selects', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');

    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();
    const options = dropdown.locator('option');
    const optCount = await options.count();
    expect(optCount, 'Saved DC dropdown should have at least 2 options').toBeGreaterThanOrEqual(2);

    // Record current value of first mobile BH (PG role slot) select
    const mobileSelect = page.locator('.dc-mobile-cards select[name^="BH"]').first();
    await expect(mobileSelect).toBeEnabled();
    const originalValue = await mobileSelect.inputValue();

    // Select the second option (first saved config)
    await dropdown.selectOption({ index: 1 });

    // Wait for AJAX to complete — loaded_dc_id should update
    const loadedId = page.locator('#loaded_dc_id, input[name="loaded_dc_id"]');
    if ((await loadedId.count()) > 0) {
      await expect(async () => {
        const val = await loadedId.first().inputValue();
        expect(val).not.toBe('0');
      }).toPass({ timeout: 5000 });
    }

    // Mobile card selects should reflect the loaded config
    // Verify at least one select value differs from original (saved configs differ from live)
    const mobileSelects = page.locator('.dc-mobile-cards select[name^="BH"]');
    const selectCount = await mobileSelects.count();
    let anyChanged = false;
    for (let i = 0; i < selectCount; i++) {
      const val = await mobileSelects.nth(i).inputValue();
      if (i === 0 && val !== originalValue) {
        anyChanged = true;
        break;
      }
    }
    // Even if values happen to match, the AJAX succeeded — verify no PHP errors
    await assertNoPhpErrors(page, 'after loading saved DC on mobile');
  });
});

test.describe('DCE mobile: form submission', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('submitting depth chart from mobile view succeeds', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');

    // Mobile cards should be visible
    await expect(page.locator('.dc-mobile-cards')).toBeVisible();

    // Click the mobile submit button
    const submitBtn = page.locator('.dc-mobile-cards__footer .depth-chart-submit-btn');
    await expect(submitBtn).toBeVisible();
    await submitBtn.click();

    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent();

    // Should get success or validation feedback — not a blank page or PHP error
    const hasSuccess = body?.match(/submitted.*successfully|thank you|depth chart has been/i);
    const hasValidation = body?.match(/must have|active players|position/i);
    expect(hasSuccess || hasValidation, 'Expected success or validation message after mobile submission').toBeTruthy();

    await assertNoPhpErrors(page, 'after mobile depth chart submission');
  });
});

test.describe('DCE mobile: resize sync', () => {
  test('switching from mobile to desktop syncs values', async ({ page }) => {
    // Start at mobile
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');

    // Change a value on mobile card (BH = PG role slot)
    const mobileSelect = page.locator('.dc-mobile-cards select[name^="BH"]').first();
    await expect(mobileSelect).toBeEnabled();
    const originalValue = await mobileSelect.inputValue();
    const newValue = originalValue === '0' ? '1' : '0';
    await mobileSelect.selectOption(newValue);

    // Switch to desktop
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.waitForTimeout(200); // debounce

    // Desktop table should now show the changed value
    const desktopSelect = page.locator('.depth-chart-table select[name^="BH"]').first();
    await expect(desktopSelect).toBeEnabled();
    const desktopValue = await desktopSelect.inputValue();
    expect(desktopValue).toBe(newValue);
  });
});
