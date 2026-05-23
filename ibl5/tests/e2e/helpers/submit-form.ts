import type { Page } from '@playwright/test';

export interface SubmitFormOptions {
  submit: () => Promise<void>;
  expectSameSpot?: () => Promise<void>;
  readBack: () => Promise<void>;
}

export async function submitFormAndAssertEffect(
  page: Page,
  options: SubmitFormOptions,
): Promise<void> {
  await options.submit();

  if (options.expectSameSpot) {
    await options.expectSameSpot();
  }

  await options.readBack();
}
