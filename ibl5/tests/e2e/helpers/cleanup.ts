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
