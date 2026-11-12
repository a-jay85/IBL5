import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Locator } from '@playwright/test';
import { test, expect } from '../fixtures/base';
import { test as authTest } from '../fixtures/auth';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Barlow first, followed by a comma, so 'Barlow Condensed' (--font-display) never matches.
const BARLOW_FIRST = /^["']?Barlow["']?\s*,/;

const computedFontFamily = (el: Locator): Promise<string> =>
  el.evaluate((node) => getComputedStyle(node).fontFamily);

function collectCssFiles(dir: string): string[] {
  const files: string[] = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...collectCssFiles(full));
    } else if (entry.name.endsWith('.css')) {
      files.push(full);
    }
  }
  return files;
}

test.describe('Numeric font stack — public pages', () => {
  test.use({ storageState: publicStorageState() });

  test('player game log cells use Barlow with tabular digits', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=Player&pa=showpage&pid=1');
    await assertNoPhpErrors(page);

    const td = page.locator('.player-stats-card .stats-table td').first();
    await expect(td).toBeAttached();
    expect(await computedFontFamily(td)).toMatch(BARLOW_FIRST);
    await expect(td).toHaveCSS('font-variant-numeric', 'tabular-nums');
  });
});

authTest.describe('Numeric font stack — Last Sim banner', () => {
  authTest('banner date uses Barlow with tabular digits', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=News');
    await assertNoPhpErrors(page);

    const date = page.locator('.last-sim-recap__tab-date').first();
    await expect(date).toBeAttached();
    expect(await computedFontFamily(date)).toMatch(BARLOW_FIRST);
    await expect(date).toHaveCSS('font-variant-numeric', 'tabular-nums');
  });

  authTest('banner opponent name stays on the display font', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=News');
    await assertNoPhpErrors(page);

    const opp = page.locator('.last-sim-recap__tab-opp').first();
    await expect(opp).toBeAttached();
    expect(await computedFontFamily(opp)).not.toMatch(BARLOW_FIRST);
  });
});

test.describe('Numeric font stack — source scan', () => {
  test('no monospace stack remains in design stylesheets', () => {
    const designDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../design');
    const offenders = collectCssFiles(designDir)
      .filter((file) => path.basename(file) !== 'debug.css')
      .filter((file) => /Monaco|Menlo|Consolas|--font-mono\b/.test(readFileSync(file, 'utf8')))
      .map((file) => path.relative(designDir, file));

    expect(offenders).toEqual([]);

    const tokens = readFileSync(path.join(designDir, 'tokens/tokens.css'), 'utf8');
    expect(tokens).toContain('--font-numeric:');
  });
});
