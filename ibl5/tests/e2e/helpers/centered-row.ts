import type { Locator } from '@playwright/test';
import { expect } from '../fixtures/base';

/**
 * Asserts every visual line of `.ibl-btn` buttons inside `row` is centered:
 * buttons are grouped into lines by top edge, and each line's left and right
 * gap inside the row must match within `tolerance` px.
 */
export async function expectButtonRowCentered(row: Locator, tolerance = 3): Promise<void> {
  await expect(row.locator('.ibl-btn').first()).toBeVisible();
  const gaps = await row.evaluate((el) => {
    const box = el.getBoundingClientRect();
    const lines = new Map<number, { left: number; right: number }>();
    for (const btn of el.querySelectorAll('.ibl-btn')) {
      const r = btn.getBoundingClientRect();
      const top = Math.round(r.top);
      const line = lines.get(top) ?? { left: r.left, right: r.right };
      lines.set(top, { left: Math.min(line.left, r.left), right: Math.max(line.right, r.right) });
    }
    return [...lines.values()].map((l) => ({ left: l.left - box.left, right: box.right - l.right }));
  });
  expect(gaps.length).toBeGreaterThan(0);
  for (const gap of gaps) {
    expect(Math.abs(gap.left - gap.right)).toBeLessThanOrEqual(tolerance);
  }
}
