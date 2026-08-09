import type { Page } from '@playwright/test';
import { expect } from '../fixtures/base';

const CATEGORY_REGEX: Record<string, string> = {
  ECF: 'Eastern.*Frontcourt|ECF',
  ECB: 'Eastern.*Backcourt|ECB',
  WCF: 'Western.*Frontcourt|WCF',
  WCB: 'Western.*Backcourt|WCB',
  MVP: 'MVP|Most Valuable',
  Six: 'Sixth|6th',
  ROY: 'Rookie|ROY',
  GM: 'GM|General Manager',
};

export async function expandVotingCategory(page: Page, cat: string): Promise<void> {
  const pattern = CATEGORY_REGEX[cat];
  if (pattern === undefined) {
    throw new Error(
      `expandVotingCategory: unknown category "${cat}". ` +
        `Known: ${Object.keys(CATEGORY_REGEX).join(', ')}`,
    );
  }
  const header = page.locator('.voting-category').filter({
    hasText: new RegExp(pattern, 'i'),
  });
  await expect(header.first(), 'voting category header must render').toBeVisible();
  const isExpanded = await header.first().getAttribute('aria-expanded');
  // e2e-hygiene-allow: branch is an observable-state toggle, not a silent guard
  if (isExpanded !== 'true') {
    await header.first().click();
  }
}
