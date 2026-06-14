import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Cap "What-If" Calculator — owner-only sandbox (GET, no persistence).
// The auth fixture owns Metros (teamid 1); the page renders BOTH the baseline
// and the scenario in a single request, so all delta assertions read from one
// page render and are immune to parallel roster mutation and seed size.
//
// Seed grounding (ci-seed.sql:373): pid 200000031 "Waive Target" is a Metros
// (tid=1) active player with salary_yr1 = 1.
const WAIVE_TARGET_PID = '200000031';
const WAIVE_TARGET_SALARY_YR1 = 1;

interface Year1Cells {
  baselineSpent: number;
  baselineSpace: number;
  scenarioSpent: number;
  scenarioSpace: number;
}

// Columns: [0] Year, [1] Baseline Spent, [2] Baseline Space,
//          [3] Scenario Spent, [4] Scenario Space. First tbody row = year1.
async function readYear1(page: Page): Promise<Year1Cells> {
  const cells = page.locator('table.ibl-data-table tbody tr').first().locator('td');
  const toNum = async (i: number): Promise<number> =>
    parseInt(((await cells.nth(i).textContent()) ?? '').trim(), 10);
  return {
    baselineSpent: await toNum(1),
    baselineSpace: await toNum(2),
    scenarioSpent: await toNum(3),
    scenarioSpace: await toNum(4),
  };
}

test.describe('Cap What-If Calculator flow', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Ending Year': '2026', 'Trivia Mode': 'Off' });
  });

  test('baseline view renders with a numeric cap table', async ({ page }) => {
    await page.goto('modules.php?name=CapWhatIf');
    await assertNoPhpErrors(page, 'on Cap Calculator baseline');

    await expect(page.locator('.ibl-title')).toContainText(/Cap Calculator/i);
    await expect(page.locator('table.ibl-data-table').first()).toBeVisible();
    await expect(page.locator('form[method="get"]')).toBeVisible();

    // With no scenario params, baseline and scenario columns are identical.
    const y1 = await readYear1(page);
    expect(Number.isNaN(y1.baselineSpace)).toBe(false);
    expect(y1.scenarioSpent).toBe(y1.baselineSpent);
    expect(y1.scenarioSpace).toBe(y1.baselineSpace);
  });

  test('adding a signing raises year1 scenario spent by the flat salary', async ({ page }) => {
    await page.goto('modules.php?name=CapWhatIf&years=2&salary=1000');
    await assertNoPhpErrors(page, 'on Cap Calculator signing scenario');

    const y1 = await readYear1(page);
    expect(y1.scenarioSpent - y1.baselineSpent).toBe(1000);
  });

  test('waiving + adding computes the net year1 delta', async ({ page }) => {
    // Waive the seeded Waive Target (salary_yr1 = 1) and add 3yr @ $1000.
    // Net year1 spent delta = -1 + 1000 = 999; space moves the opposite way.
    await page.goto(
      `modules.php?name=CapWhatIf&waive=${WAIVE_TARGET_PID}&years=3&salary=1000`,
    );
    await assertNoPhpErrors(page, 'on Cap Calculator combined scenario');

    await expect(page.getByText(/Waiving:\s*Waive Target/)).toBeVisible();

    const y1 = await readYear1(page);
    const expectedDelta = 1000 - WAIVE_TARGET_SALARY_YR1;
    expect(y1.scenarioSpent - y1.baselineSpent).toBe(expectedDelta);
    expect(y1.baselineSpace - y1.scenarioSpace).toBe(expectedDelta);
  });

  test('request-supplied teamid does not change the resolved owner team', async ({ page }) => {
    // teamid=2 is ignored — the page must still render the OWNER's (Metros)
    // roster, proven by the Metros-only Waive Target appearing in the waive
    // select. Team 2's roster would not contain it.
    await page.goto('modules.php?name=CapWhatIf&teamid=2');
    await assertNoPhpErrors(page, 'on Cap Calculator with teamid injection');

    const waiveSelect = page.locator('select[name="waive"]');
    await expect(waiveSelect.locator('option', { hasText: 'Waive Target' })).toHaveCount(1);
  });
});
