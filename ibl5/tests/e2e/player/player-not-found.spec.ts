import { test, expect } from '../fixtures/base';
import { publicStorageState } from '../helpers/public-storage-state';

// Unknown pid answers HTTP 404 with a static not-found panel, not a 500.
// pid 99999999 is absent from ci-seed.sql.
test.use({ storageState: publicStorageState() });

test('unknown pid renders the not-found panel with HTTP 404', async ({ page }) => {
  const response = await page.goto('modules.php?name=Player&pa=showpage&pid=99999999');
  expect(response?.status()).toBe(404);
  await expect(page.getByRole('heading', { name: 'Player Not Found' })).toBeVisible();
});
