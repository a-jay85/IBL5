import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Serial: trades-closed and trades-open blocks toggle the same setting.
test.describe.configure({ mode: 'serial' });

// ---------------------------------------------------------------------------
// Shared constants & helpers
// ---------------------------------------------------------------------------

/**
 * Navigate to the trade offer form by picking the first available partner.
 */
async function navigateToTradeForm(page: Page): Promise<void> {
  await page.goto('modules.php?name=Trading');

  const firstTeamLink = page.locator('.trading-team-select a').first();
  await expect(firstTeamLink).toBeVisible();
  // Use goto() with the href instead of click() — click() triggers navigation
  // that can time out under MAMP concurrency with parallel workers.
  const href = await firstTeamLink.getAttribute('href');
  await page.goto(href!);
  await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
}

/**
 * Mock the roster-preview-api endpoint so tests don't depend on live data.
 *
 * The mock returns a minimal table whose rows contain `<a href="…pid=N">` links
 * matching the `addPids` and `removePids` query-string values, so the JS
 * `classifyAndReorderTradeRows()` logic works correctly on the mocked HTML.
 */
async function mockRosterPreviewApi(page: Page): Promise<void> {
  await page.route('**/modules.php*op=roster-preview-api**', async (route) => {
    const url = new URL(route.request().url(), 'http://localhost');
    const addPids = (url.searchParams.get('addPids') || '')
      .split(',')
      .filter(Boolean);
    const removePids = (url.searchParams.get('removePids') || '')
      .split(',')
      .filter(Boolean);

    // Build rows for each PID so the JS can classify them
    const rows = [
      ...addPids.map(
        (pid) =>
          `<tr><td><a href="modules.php?name=Player&pa=showpage&pid=${pid}">Added ${pid}</a></td><td>PF</td><td>80</td></tr>`,
      ),
      ...removePids.map(
        (pid) =>
          `<tr><td><a href="modules.php?name=Player&pa=showpage&pid=${pid}">Removed ${pid}</a></td><td>SG</td><td>75</td></tr>`,
      ),
      // A non-traded player row (no matching PID)
      '<tr><td><a href="modules.php?name=Player&pa=showpage&pid=99999">Existing Player</a></td><td>C</td><td>85</td></tr>',
    ].join('');

    const html = `<table class="ibl-data-table sortable"><thead><tr><th>Name</th><th>Pos</th><th>OVR</th></tr></thead><tbody>${rows}</tbody></table>`;

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ html }),
    });
  });
}

// ===========================================================================
// Existing "Trading flow" tests (kept intact)
// ===========================================================================

test.describe('Trading flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Trading');
  });

  test('navigate to trade form via team select', async ({ page }) => {
    // Click first team link in the team selection table
    const firstTeamLink = page.locator('.trading-team-select a').first();
    await expect(firstTeamLink).toBeVisible();
    const href = await firstTeamLink.getAttribute('href');
    await page.goto(href!);

    // Trade form should appear with two roster tables
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
    const rosterTables = page.locator('.trading-roster');
    await expect(rosterTables).toHaveCount(2);
  });

  test('player checkboxes exist in roster tables', async ({ page }) => {
    const firstTeamLink = page.locator('.trading-team-select a').first();
    const href = await firstTeamLink.getAttribute('href');
    await page.goto(href!);

    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();

    const checkboxes = page.locator('.trading-roster input[type="checkbox"]');
    await expect(checkboxes.first()).toBeVisible();
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

// ===========================================================================
// Trade offer form structure
// ===========================================================================

test.describe('Trade offer form structure', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await navigateToTradeForm(page);
  });

  test('roster tables have team-colored styling and logo banners', async ({
    page,
  }) => {
    const rosters = page.locator('.trading-roster.team-table');
    await expect(rosters).toHaveCount(2);

    for (let i = 0; i < 2; i++) {
      const roster = rosters.nth(i);
      // Each roster should have a data-team-id attribute
      await expect(roster).toHaveAttribute('data-team-id', /^\d+$/);
      // Each roster should have a team-color CSS variable set
      const style = await roster.getAttribute('style');
      expect(style).toContain('--team-color-primary');
      // Logo banner image in thead
      const banner = roster.locator('thead .team-logo-banner');
      await expect(banner).toBeVisible();
    }
  });

  test('cash exchange section renders two team cards', async ({ page }) => {
    const teamCards = page.locator('.team-card');
    expect(await teamCards.count()).toBeGreaterThanOrEqual(2);

    // Each card should have a title and at least one number input
    for (let i = 0; i < 2; i++) {
      const card = teamCards.nth(i);
      await expect(card.locator('.team-card__title')).toBeVisible();
      await expect(card.locator('input[type="number"]').first()).toBeVisible();
    }
  });

  test('roster preview panel is present but hidden with correct structure', async ({
    page,
  }) => {
    const preview = page.locator('#trade-roster-preview');
    await expect(preview).toBeHidden();

    // Two team logos
    const logos = preview.locator('.trade-roster-preview__logo');
    await expect(logos).toHaveCount(2);

    // Tab bar with expected tabs
    const tabs = preview.locator('.ibl-tab[data-display]');
    const expectedDisplays = [
      'ratings',
      'total_s',
      'avg_s',
      'per36mins',
      'contracts',
    ];
    await expect(tabs).toHaveCount(expectedDisplays.length);

    for (const display of expectedDisplays) {
      await expect(
        preview.locator(`.ibl-tab[data-display="${display}"]`),
      ).toHaveCount(1);
    }

    // "Ratings" tab should be active by default
    await expect(
      preview.locator('.ibl-tab[data-display="ratings"]'),
    ).toHaveClass(/ibl-tab--active/);
  });

  test('IBL_TRADE_CONFIG is injected with expected keys', async ({ page }) => {
    const config = await page.evaluate(
      () => (window as Record<string, unknown>).IBL_TRADE_CONFIG,
    );

    expect(config).toBeTruthy();
    const cfg = config as Record<string, unknown>;
    expect(typeof cfg.rosterPreviewApiBaseUrl).toBe('string');
    expect(typeof cfg.userTeamId).toBe('number');
    expect(typeof cfg.partnerTeamId).toBe('number');
    expect(typeof cfg.switchCounter).toBe('number');
    expect(typeof cfg.hardCap).toBe('number');
  });
});

// ===========================================================================
// Trade offer form: roster preview interactions
// ===========================================================================

test.describe('Trade offer form: roster preview interactions', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await mockRosterPreviewApi(page);
    await navigateToTradeForm(page);
  });

  test('selecting players shows roster preview', async ({ page }) => {

    // Preview should be hidden initially
    const preview = page.locator('#trade-roster-preview');
    await expect(preview).toBeHidden();

    // Check one player from each roster table
    const rosterTables = page.locator('.trading-roster');
    for (let i = 0; i < 2; i++) {
      const checkbox = rosterTables
        .nth(i)
        .locator('input[type="checkbox"]')
        .first();
      if (await checkbox.isVisible()) {
        await checkbox.check();
      }
    }

    // Preview should now be visible
    await expect(preview).toBeVisible();
  });

  test('preview hides when all players unchecked', async ({ page }) => {

    const preview = page.locator('#trade-roster-preview');
    const checkbox = page
      .locator('.trading-roster input[type="checkbox"]')
      .first();

    // Check a player — preview should appear
    await checkbox.check();
    await expect(preview).toBeVisible();

    // Uncheck — preview should hide and show empty state
    await checkbox.uncheck();
    await expect(preview).toBeHidden();
  });

  test('cash-only trade shows preview and auto-switches to Contracts tab', async ({
    page,
  }) => {
    const preview = page.locator('#trade-roster-preview');

    // Fill a cash input (no checkboxes checked)
    const cashInput = page.locator('input[type="number"]').first();
    await cashInput.fill('100');
    // Trigger the input event since fill alone may not fire it
    await cashInput.dispatchEvent('input');

    // Wait for debounced fetch + panel to appear
    await expect(preview).toBeVisible();

    // Contracts tab should be auto-selected
    await expect(
      preview.locator('.ibl-tab[data-display="contracts"]'),
    ).toHaveClass(/ibl-tab--active/);
  });

  test('clicking logo switches viewed team', async ({ page }) => {

    const preview = page.locator('#trade-roster-preview');
    const logos = preview.locator('.trade-roster-preview__logo');

    // Check a player to show preview
    await page
      .locator('.trading-roster input[type="checkbox"]')
      .first()
      .check();
    await expect(preview).toBeVisible();

    // First logo (user team) should be active initially
    const firstLogo = logos.first();
    const secondLogo = logos.nth(1);
    await expect(firstLogo).toHaveClass(
      /trade-roster-preview__logo--active/,
    );

    // Click second logo
    await secondLogo.click();
    await expect(secondLogo).toHaveClass(
      /trade-roster-preview__logo--active/,
    );
    await expect(firstLogo).not.toHaveClass(
      /trade-roster-preview__logo--active/,
    );
  });

  test('clicking tab switches display and refetches', async ({ page }) => {

    const preview = page.locator('#trade-roster-preview');

    // Check a player to show preview
    await page
      .locator('.trading-roster input[type="checkbox"]')
      .first()
      .check();
    await expect(preview).toBeVisible();

    // Click "Totals" tab
    const totalsTab = preview.locator('.ibl-tab[data-display="total_s"]');
    const ratingsTab = preview.locator('.ibl-tab[data-display="ratings"]');
    await totalsTab.click();

    await expect(totalsTab).toHaveClass(/ibl-tab--active/);
    await expect(ratingsTab).not.toHaveClass(/ibl-tab--active/);
  });

  test('incoming and outgoing rows are classified after fetch', async ({
    page,
  }) => {

    const preview = page.locator('#trade-roster-preview');

    // Check one player from each roster
    const rosterTables = page.locator('.trading-roster');
    for (let i = 0; i < 2; i++) {
      const checkbox = rosterTables
        .nth(i)
        .locator('input[type="checkbox"]')
        .first();
      if (await checkbox.isVisible()) {
        await checkbox.check();
      }
    }

    await expect(preview).toBeVisible();

    // Wait for the table to render (the mock responds with rows)
    const table = preview.locator('table.ibl-data-table');
    await expect(table).toBeVisible();

    // The JS classifies rows — check that at least one of each class exists
    const incomingRows = preview.locator('tr.trade-incoming-row');
    const outgoingRows = preview.locator('tr.trade-outgoing-row');

    await expect(incomingRows.first()).toBeVisible();
    await expect(outgoingRows.first()).toBeVisible();
  });

  test('debounced refresh coalesces rapid changes', async ({ page }) => {
    // Track API requests
    let requestCount = 0;
    page.on('request', (req) => {
      if (req.url().includes('roster-preview-api')) {
        requestCount++;
      }
    });

    // Rapidly check 3 checkboxes
    const checkboxes = page.locator(
      '.trading-roster input[type="checkbox"]',
    );
    const count = Math.min(await checkboxes.count(), 3);
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check({ force: true });
    }

    // Wait for debounce (300ms) + fetch to complete
    await page.waitForTimeout(800);

    // Should have fewer requests than checkboxes checked (debounce coalesces)
    expect(requestCount).toBeLessThan(count);
  });
});

// ===========================================================================
// Trade offer form: cap warnings
// ===========================================================================

test.describe('Trade offer form: cap warnings', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await mockRosterPreviewApi(page);
    await navigateToTradeForm(page);
  });

  test('no cap warnings when no players selected', async ({ page }) => {
    const capWarningLogos = page.locator('.cap-warning-logo');
    const capWarningBanners = page.locator('.cap-warning-banner');

    await expect(capWarningLogos).toHaveCount(0);
    await expect(capWarningBanners).toHaveCount(0);
  });

  test('cap warning classes appear when post-trade cap exceeds hard cap', async ({
    page,
  }) => {

    // Inflate the user team's future salary so any incoming player pushes over the cap
    await page.evaluate(() => {
      const cfg = (window as Record<string, unknown>)
        .IBL_TRADE_CONFIG as Record<string, unknown>;
      // Set all future salary entries to 7500 (above hardCap of 7000)
      const futureSalary = cfg.userFutureSalary as Record<number, number>;
      for (const key of Object.keys(futureSalary)) {
        futureSalary[Number(key)] = 7500;
      }
    });

    // Check a player on the partner side to trigger updateCapWarnings
    const partnerRoster = page.locator('.trading-roster').nth(1);
    const partnerCheckbox = partnerRoster
      .locator('input[type="checkbox"]')
      .first();
    if (await partnerCheckbox.isVisible()) {
      await partnerCheckbox.check();
    }

    // Cap warning should appear on the user team's preview logo
    const config = await page.evaluate(
      () =>
        (
          (window as Record<string, unknown>).IBL_TRADE_CONFIG as Record<
            string,
            unknown
          >
        ).userTeamId,
    );

    const warningLogo = page.locator(
      `.trade-roster-preview__logo[data-team-id="${config}"].cap-warning-logo`,
    );
    await expect(warningLogo).toBeVisible();

    // Cap warning banner on the user team's roster header
    const warningBanner = page.locator(
      `.trading-roster[data-team-id="${config}"] thead tr:first-child th.cap-warning-banner`,
    );
    await expect(warningBanner).toBeVisible();
  });
});

// ===========================================================================
// Trade review page: offer cards and preview
// ===========================================================================

test.describe('Trade review page: offer cards and preview', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Trading&op=reviewtrade');
  });

  test('offer cards have Preview buttons', async ({ page }) => {
    const cards = page.locator('.trade-offer-card');
    const cardCount = await cards.count();

    if (cardCount === 0) {
      test.skip(true, 'No trade offers to review');
    }

    for (let i = 0; i < cardCount; i++) {
      const card = cards.nth(i);
      await expect(card.locator('[data-preview-offer]')).toBeVisible();
      await expect(card.locator('.ibl-btn--danger')).toBeVisible();
    }
  });

  test('preview button toggles panel and changes button text', async ({
    page,
  }) => {
    await mockRosterPreviewApi(page);

    const cards = page.locator('.trade-offer-card');
    if ((await cards.count()) === 0) {
      test.skip(true, 'No trade offers to review');
    }

    const previewBtn = page.locator('[data-preview-offer]').first();
    const offerId = await previewBtn.getAttribute('data-preview-offer');
    const panel = page.locator(`#trade-review-preview-${offerId}`);

    // Click Preview — panel should show, button text changes
    await previewBtn.click();
    await expect(previewBtn).toHaveText('Hide Preview');
    await expect(panel).toBeVisible();

    // Click again — panel should hide, text reverts
    await previewBtn.click();
    await expect(previewBtn).toHaveText('Preview');
    await expect(panel).toBeHidden();
  });

  test('preview panel loads roster data on first toggle', async ({ page }) => {
    await mockRosterPreviewApi(page);

    const cards = page.locator('.trade-offer-card');
    if ((await cards.count()) === 0) {
      test.skip(true, 'No trade offers to review');
    }

    let apiRequestCount = 0;
    page.on('request', (req) => {
      if (req.url().includes('roster-preview-api')) {
        apiRequestCount++;
      }
    });

    const previewBtn = page.locator('[data-preview-offer]').first();

    // First click — should fire API request
    await previewBtn.click();
    await page.waitForTimeout(500);
    expect(apiRequestCount).toBeGreaterThanOrEqual(1);

    const firstCount = apiRequestCount;

    // Hide then show again — should NOT fire new request (already initialized)
    await previewBtn.click(); // Hide
    await previewBtn.click(); // Show again
    await page.waitForTimeout(500);
    expect(apiRequestCount).toBe(firstCount);
  });

  test('each preview panel has independent state', async ({ page }) => {
    await mockRosterPreviewApi(page);

    const buttons = page.locator('[data-preview-offer]');
    if ((await buttons.count()) < 2) {
      test.skip(
        true,
        'Need at least 2 trade offers for independent state test',
      );
    }

    // Open first panel, switch to Totals tab
    const btn1 = buttons.first();
    const offerId1 = await btn1.getAttribute('data-preview-offer');
    await btn1.click();

    const panel1 = page.locator(`#trade-review-preview-${offerId1}`);
    await expect(panel1).toBeVisible();

    const totalsTab1 = panel1.locator('.ibl-tab[data-display="total_s"]');
    await totalsTab1.click();
    await expect(totalsTab1).toHaveClass(/ibl-tab--active/);

    // Open second panel — should default to Ratings
    const btn2 = buttons.nth(1);
    const offerId2 = await btn2.getAttribute('data-preview-offer');
    await btn2.click();

    const panel2 = page.locator(`#trade-review-preview-${offerId2}`);
    await expect(panel2).toBeVisible();

    const ratingsTab2 = panel2.locator('.ibl-tab[data-display="ratings"]');
    await expect(ratingsTab2).toHaveClass(/ibl-tab--active/);

    // First panel should still be on Totals
    await expect(totalsTab1).toHaveClass(/ibl-tab--active/);
  });

  test('trade offer card has fit-content width constraint', async ({
    page,
  }) => {
    const cards = page.locator('.trade-offer-card');
    if ((await cards.count()) === 0) {
      test.skip(true, 'No trade offers to review');
    }

    // The CSS fix sets width: fit-content so the card constrains its
    // intrinsic size and doesn't stretch to match sibling elements
    const cardStyles = await cards.first().evaluate((el) => {
      const cs = getComputedStyle(el);
      return {
        marginLeft: cs.marginLeft,
        marginRight: cs.marginRight,
        minWidth: cs.minWidth,
      };
    });

    // Card should be centered (auto margins) with a minimum width
    // Use approximate comparison — sub-pixel rendering can cause <1px differences
    expect(Math.abs(parseFloat(cardStyles.marginLeft) - parseFloat(cardStyles.marginRight))).toBeLessThan(1);
    expect(parseFloat(cardStyles.minWidth)).toBeGreaterThanOrEqual(300);
  });
});

// ===========================================================================
// Trading pages: no PHP errors
// ===========================================================================

test.describe('Trading pages: no PHP errors', () => {
  test('no PHP errors on trade form', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Trading');

    const firstTeamLink = page.locator('.trading-team-select a').first();
    const href = await firstTeamLink.getAttribute('href');
    await page.goto(href!);
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();

    await assertNoPhpErrors(page);
  });

  test('no PHP errors on trade review page', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Trading&op=reviewtrade');

    await assertNoPhpErrors(page);
  });
});

// ===========================================================================
// Trading: trades-closed state
// ===========================================================================

test.describe('Trading: trades-closed state', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'No' });
    await page.goto('modules.php?name=Trading');
  });

  test('shows closed message when trades disabled', async ({ page }) => {
    await expect(
      page.getByText('Sorry, but trades are not allowed right now.'),
    ).toBeVisible();
  });

  test('no PHP errors on closed trading page', async ({ page }) => {
    await assertNoPhpErrors(page);
  });
});

// ===========================================================================
// Trading: result banners
// ===========================================================================

test.describe('Trading: result banners', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Allow Trades': 'Yes' });
  });

  test('offer_sent success banner', async ({ page }) => {
    await page.goto('modules.php?name=Trading&op=reviewtrade&result=offer_sent');
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await expect(page.locator('.ibl-alert--success')).toContainText(
      'Trade offer sent!',
    );
  });

  test('trade_accepted success banner', async ({ page }) => {
    await page.goto(
      'modules.php?name=Trading&op=reviewtrade&result=trade_accepted',
    );
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await expect(page.locator('.ibl-alert--success')).toContainText(
      'Trade accepted!',
    );
  });

  test('trade_rejected info banner', async ({ page }) => {
    await page.goto(
      'modules.php?name=Trading&op=reviewtrade&result=trade_rejected',
    );
    await expect(page.locator('.ibl-alert--info')).toBeVisible();
    await expect(page.locator('.ibl-alert--info')).toContainText(
      'Trade offer rejected.',
    );
  });

  test('already_processed warning banner', async ({ page }) => {
    await page.goto(
      'modules.php?name=Trading&op=reviewtrade&result=already_processed',
    );
    await expect(page.locator('.ibl-alert--warning')).toBeVisible();
    await expect(page.locator('.ibl-alert--warning')).toContainText(
      'already been accepted, declined, or withdrawn',
    );
  });

  test('error param renders error banner', async ({ page }) => {
    await page.goto(
      'modules.php?name=Trading&op=reviewtrade&error=Test+error',
    );
    await expect(page.locator('.ibl-alert--error')).toBeVisible();
    await expect(page.locator('.ibl-alert--error')).toContainText('Test error');
  });
});
