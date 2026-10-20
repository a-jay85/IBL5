import { test, expect } from '../fixtures/auth';
import { withPhases } from '../fixtures/phase';

// daisyUI pilot — computed-style checks on the converted FA negotiate page.
// Read-only: this spec never submits a form, so it writes nothing to ibl_fa_offers
// (bin/check-e2e-fa-offers-owner keeps free-agency-submission.spec.ts the sole writer).
// pid=11 is the CI-seed pure free agent the FA suites already negotiate with.
// Approved visual targets for the manual walkthrough of both pilot pages live in
// `~/claude-plans/_reports/daisyui-spike-2026-10-20/` (`after-*` and `fa-after-*`).

const NEGOTIATE_URL = 'modules.php?name=FreeAgency&pa=negotiate&pid=11';

test.describe('daisyUI pilot -- FA negotiate page', () => {
  withPhases(['Free Agency'], () => {
    test('max level contract label wraps on a phone', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(NEGOTIATE_URL);
      const label = page.locator('.d-label', { hasText: /max level contract/i }).first();
      await expect(label).toBeVisible();

      const { whiteSpace, width, parentWidth } = await label.evaluate((el) => ({
        whiteSpace: getComputedStyle(el).whiteSpace,
        width: el.getBoundingClientRect().width,
        parentWidth: (el.parentElement as HTMLElement).clientWidth,
      }));
      expect(whiteSpace).toBe('normal');
      expect(width).toBeLessThanOrEqual(parentWidth);
    });

    test('text-sm wins over the d-label override', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(NEGOTIATE_URL);
      const label = page.locator('label.d-label.text-sm[for="offeryear1"]');
      await expect(label).toBeVisible();

      // text-sm is 0.875rem: 14px at a 16px root, 15.75px at the site's 18px root.
      // The :where(.d-label) override sets 1rem, so a winning override reads the full root size.
      const { fontSize, rootSize } = await label.evaluate((el) => ({
        fontSize: parseFloat(getComputedStyle(el).fontSize),
        rootSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
      }));
      expect(fontSize).toBeCloseTo(rootSize * 0.875, 2);
    });

    // Verified mutation: pointing the theme's --color-primary at `var(--color-navy-8000)`
    // (an undefined token) makes this read rgb(249, 250, 251) and fail.
    test('primary button resolves the ibl theme navy', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(NEGOTIATE_URL);
      const button = page.getByRole('button', { name: 'Offer / Amend Free Agent Contract' });
      await expect(button).toBeVisible();

      expect(await button.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(17, 24, 39)');
    });

    test('card title bar is navy with white text', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(NEGOTIATE_URL);
      const title = page.locator('.d-card-title', { hasText: 'Contract Negotiation' });
      await expect(title).toBeVisible();

      const { barImage, titleColor } = await title.evaluate((el) => ({
        barImage: getComputedStyle(el.parentElement as HTMLElement).backgroundImage,
        titleColor: getComputedStyle(el).color,
      }));
      expect(barImage.startsWith('linear-gradient')).toBe(true);
      expect(titleColor).toBe('rgb(255, 255, 255)');
    });

    test('daisyUI buttons keep the condensed caps brand font', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(NEGOTIATE_URL);
      const button = page.getByRole('button', { name: 'Offer / Amend Free Agent Contract' });
      await expect(button).toBeVisible();

      const { textTransform, fontFamily } = await button.evaluate((el) => ({
        textTransform: getComputedStyle(el).textTransform,
        fontFamily: getComputedStyle(el).fontFamily,
      }));
      expect(textTransform).toBe('uppercase');
      expect(fontFamily).toContain('Barlow Condensed');
    });
  });
});
