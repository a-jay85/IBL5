import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';

export interface A11yOptions {
  disableRules?: string[];
}

/**
 * Run axe-core WCAG 2.1 AA analysis on the current page.
 * Throws with a formatted violation report if any issues are found.
 */
export async function assertNoA11yViolations(
  page: Page,
  context?: string,
  options?: A11yOptions,
): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']);

  if (options?.disableRules?.length) {
    builder = builder.disableRules(options.disableRules);
  }

  const results = await builder.analyze();

  if (results.violations.length === 0) {
    return;
  }

  const lines: string[] = [
    `${results.violations.length} accessibility violation(s)${context ? ` ${context}` : ''}:`,
    '',
  ];

  for (const violation of results.violations) {
    lines.push(`[${violation.impact}] ${violation.id}: ${violation.description}`);
    const nodes = violation.nodes.slice(0, 3);
    for (const node of nodes) {
      lines.push(`  → ${node.target.join(' > ')}`);
    }
    if (violation.nodes.length > 3) {
      lines.push(`  … and ${violation.nodes.length - 3} more`);
    }
    lines.push('');
  }

  throw new Error(lines.join('\n'));
}
