import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { withPhases } from '../fixtures/phase';

// Offer salary row geometry — requires authenticated user (Metros GM).
//
// Seed and DOM sources:
// - pid=30 'Extension Vet' (Metros, owned by the CI user) is renegotiation-eligible in
//   Regular Season. Source: the `negotiation` row notes in tests/e2e/vr-manifest.ts.
//   The extension inputs are 5 `.offer-salary-cell` children of `.offer-salary-row--inputs`,
//   rendered by NegotiationOfferView::renderEditableOfferFields().
// - pid=11 is the free agent the FA E2E suites negotiate with, rendered only in Free Agency.
//   Source: the `free-agency-offer` row notes in tests/e2e/vr-manifest.ts. The 6 cells
//   (offeryear1..6) come from FreeAgencyFormView::renderOfferInputs(), on a container
//   carrying `data-raise-percentage`.

const EXTENSION_URL = 'modules.php?name=Player&pa=negotiate&pid=30';
const FA_URL = 'modules.php?name=FreeAgency&pa=negotiate&pid=11';

const EXTENSION_ROW = 'form[name="ExtensionOffer"] .offer-salary-row--inputs';
// The quick-offer preset forms share the form name, so the :has filter is required.
const FA_ROW = 'form[name="FAOffer"]:has(input.offer-salary-input) .offer-salary-row--inputs';

/**
 * Count the `.offer-salary-cell` boxes on each visual line, top to bottom.
 * Cells are bucketed by their top edge with a 4px tolerance, e.g. `[3, 2]`.
 */
async function rowGroups(
  row: Locator,
  expectedCells: number,
  cellSelector = '.offer-salary-cell',
): Promise<number[]> {
  const cells = row.locator(cellSelector);
  await expect(cells).toHaveCount(expectedCells);

  const tops: number[] = [];
  for (let i = 0; i < expectedCells; i++) {
    const box = await cells.nth(i).boundingBox();
    if (box === null) {
      throw new Error(`${cellSelector} ${i} has no bounding box`);
    }
    tops.push(box.y);
  }

  const buckets: { y: number; count: number }[] = [];
  for (const y of tops) {
    const bucket = buckets.find((b) => Math.abs(b.y - y) <= 4);
    if (bucket === undefined) {
      buckets.push({ y, count: 1 });
    } else {
      bucket.count += 1;
    }
  }
  return buckets.sort((a, b) => a.y - b.y).map((b) => b.count);
}

async function gotoClean(page: Page, url: string, context: string): Promise<void> {
  await page.goto(url);
  await assertNoPhpErrors(page, context);
}

test.describe('offer salary row at phone width', () => {
  // The VR mobile size from tests/e2e/smoke/visual-regression.spec.ts.
  test.use({ viewport: { width: 375, height: 812 } });

  withPhases(['Regular Season'], () => {
    test('extension offer inputs wrap three then two', async ({ page }) => {
      await gotoClean(page, EXTENSION_URL, 'on extension offer page at phone width');
      expect(await rowGroups(page.locator(EXTENSION_ROW), 5)).toEqual([3, 2]);
    });

    test('label stacks above its input', async ({ page }) => {
      await gotoClean(page, EXTENSION_URL, 'on extension offer page at phone width');
      const cell = page.locator(EXTENSION_ROW).locator('.offer-salary-cell').first();
      const labelBox = await cell.locator('label').boundingBox();
      const inputBox = await cell.locator('input').boundingBox();
      if (labelBox === null || inputBox === null) {
        throw new Error('first offer-salary-cell has no label or input box');
      }
      expect(labelBox.y + labelBox.height).toBeLessThanOrEqual(inputBox.y + 1);
      const labelCenter = labelBox.x + labelBox.width / 2;
      const inputCenter = inputBox.x + inputBox.width / 2;
      expect(Math.abs(labelCenter - inputCenter)).toBeLessThanOrEqual(4);
    });
  });

  withPhases(['Free Agency'], () => {
    test('free agency offer inputs wrap three then three', async ({ page }) => {
      await gotoClean(page, FA_URL, 'on free agency offer page at phone width');
      expect(await rowGroups(page.locator(FA_ROW), 6)).toEqual([3, 3]);
    });

    test('raise-percentage hook survives on the FA row', async ({ page }) => {
      await gotoClean(page, FA_URL, 'on free agency offer page at phone width');
      // Pins the offer-salary-hints.js selector against a class or attribute rename.
      await expect(page.locator('.offer-salary-row--inputs[data-raise-percentage]')).toHaveCount(1);
    });
  });
});

test.describe('offer salary row at desktop width', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  withPhases(['Regular Season'], () => {
    test('desktop extension row keeps its current wrap', async ({ page }) => {
      await gotoClean(page, EXTENSION_URL, 'on extension offer page at desktop width');
      // Captured from unchanged master: the extension card is narrow enough that the 5th cell
      // already wraps at 1280px. The phone-only grid rule must leave this grouping alone.
      expect(await rowGroups(page.locator(EXTENSION_ROW), 5)).toEqual([4, 1]);
    });
  });

  withPhases(['Free Agency'], () => {
    test('desktop rows stay single-line (free agency)', async ({ page }) => {
      await gotoClean(page, FA_URL, 'on free agency offer page at desktop width');
      expect(await rowGroups(page.locator(FA_ROW), 6)).toEqual([6]);
    });
  });
});
