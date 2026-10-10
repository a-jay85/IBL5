import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Voting tests — ASG and EOY ballot rendering (read-only).
// Submission tests are in voting-submission.spec.ts.

// ============================================================
// ASG Voting (Regular Season)
// ============================================================

test.describe('ASG Voting', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');
  });

  test('ASG ballot form renders', async ({ page }) => {
    await expect(page.locator('form[name="ASGVote"]')).toBeVisible();
  });

  test('four category sections exist', async ({ page }) => {
    // Category tables: ECF, ECB, WCF, WCB
    for (const cat of ['ECF', 'ECB', 'WCF', 'WCB']) {
      const table = page.locator(`#${cat}`);
      // Tables may be hidden initially (click to expand)
      await expect(table).toHaveCount(1);
    }
  });

  test('category tables have candidate checkboxes', async ({ page }) => {
    // Click a category header to reveal the table
    const ecfHeader = page.locator('.voting-category').first();
    await ecfHeader.click();

    // Wait for the first visible table to show
    const firstVisibleTable = page.locator(
      '#ECF, #ECB, #WCF, #WCB',
    ).first();
    await expect(firstVisibleTable).toBeVisible();

    // Should have checkboxes
    const checkboxes = firstVisibleTable.locator('input[type="checkbox"]');
    await expect(checkboxes.first()).toBeVisible();
  });

  test('submit button visible', async ({ page }) => {
    const submitBtn = page.getByRole('button', { name: /submit votes/i });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('category header toggles with Enter and Space from the keyboard', async ({ page }) => {
    const toggle = page.locator('.voting-category-toggle').first();
    const controls = await toggle.getAttribute('aria-controls');
    expect(controls).not.toBeNull();
    const table = page.locator(`#${controls}`);

    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(table).toBeHidden();

    const urlBefore = page.url();
    await toggle.focus();
    await page.keyboard.press('Enter');

    await expect(table).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    // type="button" keeps Enter from submitting the ballot form.
    expect(page.url()).toBe(urlBefore);

    await page.keyboard.press('Space');

    await expect(table).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    // aria-allowed-attr: aria-expanded stays off the wrapper div. The wrapper keeps its
    // onclick, which smoke/mobile-auth.spec.ts still locates (mobile-auth depends on it).
    await expect(page.locator('.voting-category').first()).not.toHaveAttribute('aria-expanded', /.*/);
  });

  test('header toggle inherits the heading look', async ({ page }) => {
    const styles = await page.evaluate(() => {
      const button = document.querySelector('.voting-category-toggle');
      const heading = button?.closest('h2.voting-category-title');
      if (button === null || button === undefined || heading === null || heading === undefined) {
        throw new Error('voting category toggle or its h2 heading not found');
      }
      const pick = (el: Element) => {
        const cs = getComputedStyle(el);
        return {
          fontSize: cs.fontSize,
          fontWeight: cs.fontWeight,
          fontFamily: cs.fontFamily,
          lineHeight: cs.lineHeight,
          textTransform: cs.textTransform,
          letterSpacing: cs.letterSpacing,
          color: cs.color,
          textAlign: cs.textAlign,
        };
      };
      const buttonStyle = getComputedStyle(button);
      return {
        button: pick(button),
        heading: pick(heading),
        backgroundColor: buttonStyle.backgroundColor,
        borderTopWidth: buttonStyle.borderTopWidth,
        paddingTop: buttonStyle.paddingTop,
      };
    });

    expect(styles.button).toEqual(styles.heading);
    expect(styles.backgroundColor).toBe('rgba(0, 0, 0, 0)');
    expect(styles.borderTopWidth).toBe('0px');
    expect(styles.paddingTop).toBe('0px');
  });

  test('no PHP errors on ASG ballot', async ({ page }) => {
    await assertNoPhpErrors(page, 'on ASG ballot');
  });

  test('responsive-table class survives collapse-then-resize on ballot category', async ({ page }) => {
    // Narrow viewport so ballot tables overflow and get responsive treatment
    await page.setViewportSize({ width: 320, height: 800 });

    // Expand the ECF category
    const ecfHeader = page.locator('.voting-category').first();
    await ecfHeader.click();

    const ecfTable = page.locator('#ECF');
    await expect(ecfTable).toBeVisible();

    // Process at the narrow viewport — table should overflow and get responsive-table
    await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).IBL_refreshResponsiveTables();
    });
    await expect(ecfTable).toHaveClass(/responsive-table/);

    // Collapse the category so the table is hidden
    await ecfHeader.click();
    await expect(ecfTable).not.toBeVisible();

    // Simulate a resize while the table is hidden — the class must not be stripped
    await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).IBL_refreshResponsiveTables();
    });

    // Re-expand and confirm responsive state survived
    await ecfHeader.click();
    await expect(ecfTable).toBeVisible();
    await expect(ecfTable).toHaveClass(/responsive-table/);
  });

  test.describe('at phone width from first paint', () => {
    // Viewport is set on the context before the beforeEach goto, so no
    // resize/orientationchange event fires after the click. The only thing
    // that can add responsive-table to the expanded table is the ShowAndHide
    // click handler's own refresh call.
    test.use({ viewport: { width: 375, height: 812 } });

    test('clicking a ballot category header makes its table responsive', async ({ page }) => {
      const ecfTable = page.locator('#ECF');

      // Collapsed at load: responsive-tables.js skips hidden tables, so no class yet
      await expect(ecfTable).toBeHidden();
      await expect(ecfTable).not.toHaveClass(/responsive-table/);

      await page.locator('.voting-category').first().click();

      await expect(ecfTable).toBeVisible();
      await expect(ecfTable).toHaveClass(/responsive-table/);
    });
  });
});

// ============================================================
// EOY Voting (Free Agency / non-Regular Season)
// ============================================================

test.describe('EOY Voting', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');
  });

  test('EOY ballot form renders', async ({ page }) => {
    await expect(page.locator('form[name="EOYVote"]')).toBeVisible();
  });

  test('four award categories exist', async ({ page }) => {
    // Category tables: MVP, Six, ROY, GM
    for (const cat of ['MVP', 'Six', 'ROY', 'GM']) {
      const table = page.locator(`#${cat}`);
      await expect(table).toHaveCount(1);
    }
  });

  test('category tables have radio buttons', async ({ page }) => {
    // Click a category header to reveal it
    const header = page.locator('.voting-category').first();
    await header.click();

    const firstTable = page.locator('#MVP');
    await expect(firstTable).toBeVisible();

    const radios = firstTable.locator('input[type="radio"]');
    await expect(radios.first()).toBeVisible();
  });

  test('submit button visible', async ({ page }) => {
    const submitBtn = page.getByRole('button', { name: /submit votes/i });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('no PHP errors on EOY ballot', async ({ page }) => {
    await assertNoPhpErrors(page, 'on EOY ballot');
  });
});
