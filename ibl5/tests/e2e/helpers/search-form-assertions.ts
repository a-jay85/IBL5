import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Assert the full search form is present (input + submit button).
 */
export async function assertSearchFormPresent(page: Page): Promise<void> {
  await expect(page.locator('input[name="query"]')).toBeVisible();
  await expect(page.locator('.ibl-search__btn').first()).toBeVisible();
}

/**
 * Assert filter dropdowns are present (topic, category, author, days).
 */
export async function assertFilterDropdownsPresent(page: Page): Promise<void> {
  const selects = page.locator('.search-form__select');
  expect(await selects.count()).toBeGreaterThanOrEqual(3);
}

/**
 * Assert search type radio buttons are present with stories checked by default.
 */
export async function assertSearchTypeRadiosPresent(page: Page): Promise<void> {
  const radios = page.locator('input[name="type"]');
  expect(await radios.count()).toBeGreaterThanOrEqual(2);

  const storiesRadio = page.locator('input[name="type"][value="stories"]');
  await expect(storiesRadio).toBeChecked();
}

/**
 * Assert search form submits and navigates to expected URL.
 */
export async function assertSearchSubmitsTo(
  page: Page,
  query: string,
  expectedUrlPart: string,
): Promise<void> {
  await page.locator('input[name="query"]').fill(query);
  await page.locator('.ibl-search__btn').first().click();
  // HTMX boost swaps content without full navigation — wait for URL change
  await page.waitForURL(new RegExp(expectedUrlPart.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), { timeout: 10000 });
  expect(page.url()).toContain(expectedUrlPart);
}
