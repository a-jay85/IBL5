import { test, expect } from '../fixtures/public';
import { gotoWithRetry } from '../helpers/navigation';

// .ibl-stat-highlight uses --accent-700 (#c2410c, about 5.2:1 on white) so the
// 18px bold text clears the 4.5:1 WCAG AA bar. The Standings bottom-locked
// override (standings-rows.css) must keep winning with --gray-600.

test.describe('.ibl-stat-highlight color tokens', () => {
  test('td.ibl-stat-highlight on Record Holders computes to accent-700', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=RecordHolders');
    const cell = page.locator('td.ibl-stat-highlight').first();
    await expect(cell).toBeVisible();
    await expect(cell).toHaveCSS('color', 'rgb(194, 65, 12)');
  });

  test('bottom-locked override still beats the base rule', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=RecordHolders');
    // Build the markup in-page so the check does not depend on seeded Standings rows.
    const colors = await page.evaluate(() => {
      const table = document.createElement('table');
      table.className = 'ibl-data-table';
      table.innerHTML =
        '<tbody><tr class="bottom-locked"><td><span class="ibl-stat-highlight">1</span></td></tr></tbody>';
      document.body.appendChild(table);
      const span = table.querySelector('span.ibl-stat-highlight');
      const probe = document.createElement('span');
      probe.style.color = 'var(--gray-600)';
      document.body.appendChild(probe);
      return {
        span: span === null ? '' : getComputedStyle(span).color,
        probe: getComputedStyle(probe).color,
      };
    });
    expect(colors.span).not.toBe('');
    expect(colors.span).toBe(colors.probe);
  });
});
