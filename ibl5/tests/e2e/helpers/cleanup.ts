/**
 * E2E Cleanup Helpers.
 *
 * Wrapper functions over the DELETE endpoints in test-state.php. Each helper
 * is a thin POST/DELETE wrapper used in `test.afterAll` / `test.afterEach`
 * blocks to reset mutated state between submission tests.
 *
 * The endpoints themselves enforce safety gates (pid allowlist for
 * extensions, year range for draft order, `e2e_` prefix for user deletion);
 * these helpers do not duplicate that validation.
 */
import type { APIRequestContext } from '@playwright/test';

export interface ResetExtensionResult {
  reset: 0 | 1;
}

export interface ResetDraftOrderResult {
  cleared: number;
}

export interface DeleteTestUserResult {
  deleted: number;
}

/**
 * Reset the rolling extension-test player's contract back to seed values.
 * Today only pid=30 (Extension Vet seed) is supported by the endpoint.
 */
export async function resetExtension(
  request: APIRequestContext,
  pid: number,
): Promise<ResetExtensionResult> {
  const response = await request.delete(
    `test-state.php?action=reset-extension&pid=${pid}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-extension failed: ${response.status()} ${await response.text()}`,
    );
  }
  return (await response.json()) as ResetExtensionResult;
}

/**
 * Clear `ibl_draft` rows for one season year and reset the
 * `Draft Order Finalized` setting so a later run of the
 * ProjectedDraftOrder save_order test sees an empty slate.
 */
export async function resetDraftOrder(
  request: APIRequestContext,
  year: number,
): Promise<ResetDraftOrderResult> {
  const response = await request.delete(
    `test-state.php?action=reset-draft-order&year=${year}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-draft-order failed: ${response.status()} ${await response.text()}`,
    );
  }
  return (await response.json()) as ResetDraftOrderResult;
}

/**
 * Delete a registration-test user. The endpoint refuses any username that
 * does not start with `e2e_`, so accidental wipes of real accounts are
 * impossible even if E2E_TESTING is mistakenly enabled.
 */
export async function deleteTestUser(
  request: APIRequestContext,
  username: string,
): Promise<DeleteTestUserResult> {
  const response = await request.delete(
    `test-state.php?action=delete-test-user&username=${encodeURIComponent(username)}`,
  );
  if (!response.ok()) {
    throw new Error(
      `delete-test-user failed: ${response.status()} ${await response.text()}`,
    );
  }
  return (await response.json()) as DeleteTestUserResult;
}

/**
 * Restore the dedicated waivable player (pid=200000031) after a waive/drop test:
 * resets ordinal/droptime and deletes the "make waiver cuts" news story. The
 * endpoint allowlists only pid=200000031.
 */
export async function resetWaiverPlayer(
  request: APIRequestContext,
  pid: number,
): Promise<void> {
  const response = await request.delete(
    `test-state.php?action=reset-waiver-player&pid=${pid}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-waiver-player failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Restore the rookie-option player (pid=200000032) after an option-exercise test:
 * resets salary_yr3/salary_yr4 and deletes the news story. The endpoint
 * allowlists only pid=200000032.
 */
export async function resetRookieOption(
  request: APIRequestContext,
  pid: number,
): Promise<void> {
  const response = await request.delete(
    `test-state.php?action=reset-rookie-option&pid=${pid}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-rookie-option failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Clear a single ibl_draft slot after a successful draft selection so the test
 * is idempotent: un-drafts the prospect, removes the created ibl_plr row, and
 * blanks the slot.
 */
export async function resetDraftPick(
  request: APIRequestContext,
  round: number,
  pick: number,
  year: number,
): Promise<void> {
  const response = await request.delete(
    `test-state.php?action=reset-draft-pick&round=${round}&pick=${pick}&year=${year}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-draft-pick failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Restore the Monarchs (teamid=8) saved-DC fixture names after rename /
 * rename-active mutations and remove any active snapshot row that was created.
 * The endpoint allowlists only teamid=8.
 */
export async function resetSavedDcNames(
  request: APIRequestContext,
  teamid: number,
): Promise<void> {
  const response = await request.delete(
    `test-state.php?action=reset-saved-dc-names&teamid=${teamid}`,
  );
  if (!response.ok()) {
    throw new Error(
      `reset-saved-dc-names failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Delete the seeded trade-review offers (1-6) to expose the empty-state. Offers
 * 7-8 (reserved for the REST spec) survive. Pair with {@link resetTradeOffers}.
 */
export async function clearTradeOffers(
  request: APIRequestContext,
): Promise<void> {
  const response = await request.delete(
    'test-state.php?action=clear-trade-offers',
  );
  if (!response.ok()) {
    throw new Error(
      `clear-trade-offers failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Re-seed the trade-review offers (1-6) to their ci-seed state so offers-present
 * tests (and parallel specs) see the seed again.
 */
export async function resetTradeOffers(
  request: APIRequestContext,
): Promise<void> {
  const response = await request.delete(
    'test-state.php?action=reset-trade-offers',
  );
  if (!response.ok()) {
    throw new Error(
      `reset-trade-offers failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Undo the trade-block toggle tests: delete every gm_trade_block row owned by a
 * Metros (teamid=1) player and clear Metros' seeking note. Scoped to teamid=1 so
 * the seeded cross-team fixture (Cougars pid=23 / teamid=3) survives.
 */
export async function resetTradeBlock(
  request: APIRequestContext,
): Promise<void> {
  const response = await request.delete(
    'test-state.php?action=reset-trade-block',
  );
  if (!response.ok()) {
    throw new Error(
      `reset-trade-block failed: ${response.status()} ${await response.text()}`,
    );
  }
}

/**
 * Restore state after a block.php assign_free_agents test: returns the three FA
 * players (pids 10/11/12) to their pre-signing seed, restores Metros' MLE/LLE,
 * deletes the assign news story, and re-seeds ibl_fa_offers.
 */
export async function resetFaSignings(
  request: APIRequestContext,
): Promise<void> {
  const response = await request.delete(
    'test-state.php?action=reset-fa-signings',
  );
  if (!response.ok()) {
    throw new Error(
      `reset-fa-signings failed: ${response.status()} ${await response.text()}`,
    );
  }
}
