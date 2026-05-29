/**
 * Trading E2E helpers.
 *
 * Shared trade-form plumbing consumed by `flows/trading.spec.ts` and
 * `flows/trading-submission.spec.ts`. Extracted verbatim from those specs so
 * both files reference one copy: `navigateToTradeForm` (form navigation),
 * `buildFormBody` (POST body assembly), `collectNewOfferIds` (review-page id
 * diff), and `rejectOfferSafe` (best-effort cleanup). The `FormData` /
 * `FormField` shapes the body builder depends on live here too.
 */
import { expect } from '@playwright/test';
import type { Page, APIRequestContext } from '@playwright/test';
import { gotoWithRetry } from './navigation';

export interface FormField {
  index: string;
  type: string;
  contract: string;
  hasCheckbox: boolean;
}

export interface FormData {
  offeringTeam: string;
  listeningTeam: string;
  switchCounter: number;
  fieldsCounter: number;
  cashStartYear: number;
  cashEndYear: number;
  fields: FormField[];
}

/**
 * Navigate to the trade offer form by picking the first available partner.
 */
export async function navigateToTradeForm(page: Page): Promise<void> {
  await gotoWithRetry(page, 'modules.php?name=Trading');

  const firstTeamLink = page.locator('.trading-team-select a').first();
  await expect(firstTeamLink).toBeVisible();
  // Use goto() with the href instead of click() — click() triggers navigation
  // that can time out under concurrency with parallel workers.
  const href = await firstTeamLink.getAttribute('href');
  await page.goto(href!);
  await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
}

/**
 * Build the POST form body from extracted form data, checking specified indices.
 */
export function buildFormBody(
  formData: FormData,
  checkedIndices: number[],
  userCash?: Record<number, number>,
  partnerCash?: Record<number, number>,
  csrfToken?: string,
): Record<string, string> {
  const body: Record<string, string> = {
    offeringTeam: formData.offeringTeam,
    listeningTeam: formData.listeningTeam,
    switchCounter: String(formData.switchCounter),
    fieldsCounter: String(formData.fieldsCounter),
  };

  if (csrfToken) {
    body['_csrf_token'] = csrfToken;
  }

  for (let k = 0; k < formData.fieldsCounter; k++) {
    const field = formData.fields[k];
    body[`index${k}`] = field.index;
    body[`type${k}`] = field.type;
    body[`contract${k}`] = field.contract;
    if (checkedIndices.includes(k)) {
      body[`check${k}`] = 'on';
    }
  }

  for (let i = 0; i < 7; i++) {
    body[`userSendsCash${i}`] = String(userCash?.[i] ?? 0);
    body[`partnerSendsCash${i}`] = String(partnerCash?.[i] ?? 0);
  }

  return body;
}

/**
 * Collect all offer IDs from the review page that are NOT in the exclusion set.
 */
export async function collectNewOfferIds(
  page: Page,
  excludeIds: Set<number>,
): Promise<number[]> {
  await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
  const buttons = page.locator('[data-preview-offer]');
  const count = await buttons.count();
  const ids: number[] = [];
  for (let i = 0; i < count; i++) {
    const idStr = await buttons.nth(i).getAttribute('data-preview-offer');
    const id = parseInt(idStr ?? '0', 10);
    if (!excludeIds.has(id)) {
      ids.push(id);
    }
  }
  return ids;
}

/**
 * Reject a trade offer via API POST for cleanup (best-effort).
 */
export async function rejectOfferSafe(
  request: APIRequestContext,
  offerId: number,
  teamRejecting: string,
  teamReceiving: string,
): Promise<void> {
  try {
    await request.post('/ibl5/modules/Trading/rejecttradeoffer.php', {
      form: {
        offer: String(offerId),
        teamRejecting,
        teamReceiving,
      },
      maxRedirects: 0,
    });
  } catch {
    // Best-effort cleanup — offer may already be processed
  }
}
