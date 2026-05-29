/**
 * Free Agency E2E helpers.
 *
 * Shared locator plumbing consumed by `flows/free-agency.spec.ts` and
 * `flows/free-agency-submission.spec.ts`. Extracted verbatim so both files
 * reference one copy.
 */
import type { Page, Locator } from '@playwright/test';

/**
 * Scope form inputs to the visible custom offer form (not hidden quick-offer
 * forms). The negotiate page renders one `form[name="FAOffer"]` with number
 * inputs (the custom-offer form) plus several hidden quick-offer forms; the
 * `has` filter keeps only the custom one.
 */
export const offerForm = (page: Page): Locator =>
  page
    .locator('form[name="FAOffer"]')
    .filter({ has: page.locator('input[type="number"]') });
