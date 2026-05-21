import { test, expect } from '../fixtures/public';
import { gotoWithRetry } from '../helpers/navigation';

test.describe('gotoWithRetry helper', () => {
  test('throws on HTTP 500 with non-blank body', async ({ page }) => {
    await page.route('**/status-500-fixture', (route) => {
      route.fulfill({
        status: 500,
        contentType: 'text/html',
        body: '<html><body>Internal Server Error - long enough body to pass the 20-char gate</body></html>',
      });
    });

    const url = 'http://localhost/status-500-fixture';
    await expect(gotoWithRetry(page, url)).rejects.toThrow(/HTTP 500/);
  });

  test('throws on HTTP 404 with non-blank body', async ({ page }) => {
    await page.route('**/status-404-fixture', (route) => {
      route.fulfill({
        status: 404,
        contentType: 'text/html',
        body: '<html><body>Not Found page with long enough content to pass the gate</body></html>',
      });
    });

    const url = 'http://localhost/status-404-fixture';
    await expect(gotoWithRetry(page, url)).rejects.toThrow(/HTTP 404/);
  });

  test('does not retry on 4xx status', async ({ page }) => {
    let hitCount = 0;
    await page.route('**/status-404-no-retry', (route) => {
      hitCount++;
      route.fulfill({
        status: 404,
        contentType: 'text/html',
        body: '<html><body>Not Found page with long enough content to pass the gate</body></html>',
      });
    });

    const url = 'http://localhost/status-404-no-retry';
    await expect(gotoWithRetry(page, url)).rejects.toThrow(/HTTP 404/);
    expect(hitCount).toBe(1);
  });

  test('throws on persistently blank body', async ({ page }) => {
    await page.route('**/blank-body-fixture', (route) => {
      route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: '',
      });
    });

    const url = 'http://localhost/blank-body-fixture';
    await expect(gotoWithRetry(page, url)).rejects.toThrow(/blank content/);
  });

  test('succeeds on HTTP 200 with non-blank body', async ({ page }) => {
    await page.route('**/status-200-fixture', (route) => {
      route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: '<html><body>This is a valid page with enough content to pass the body check</body></html>',
      });
    });

    const url = 'http://localhost/status-200-fixture';
    await expect(gotoWithRetry(page, url)).resolves.toBeUndefined();
  });
});
