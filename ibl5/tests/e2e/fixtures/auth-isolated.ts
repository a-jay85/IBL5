import { test as authTest } from './auth';

/**
 * Authenticated fixture with an isolated team identity.
 *
 * Overrides `context` to inject a `_test_team` cookie so modules that
 * resolve the user's team via `getTeamnameFromUsername()` see the
 * Monarchs (tid=8) roster instead of the logged-in user's Metros.
 * Because `context` is a transitive dependency of every test (via
 * `page`), the cookie is always set — even for tests that only
 * destructure `{ page }`.
 *
 * tid=8 is isolated from the trading/extension/waiver specs, which act on
 * the logged-in user's Metros. It is NOT, however, a single-writer roster:
 * every spec importing this fixture writes tid=8, and several write the same
 * depth-chart rows (depth-chart-entry-submission, depth-chart-entry-mobile,
 * depth-chart-saved-dc-api). `test.describe.configure({ mode: 'serial' })` is
 * file-scoped and does not order them against each other, so a spec that
 * writes a tid=8 row AND reads it back must run in the `mutators` project
 * (--workers=1, dedicated DB) — see the mutators comment block in
 * playwright.config.ts.
 */
export const test = authTest.extend({
  context: async ({ context }, use) => {
    const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
    await context.addCookies([{
      name: '_test_team',
      value: 'Monarchs',
      domain: new URL(baseUrl).hostname,
      path: '/',
    }]);
    await use(context);
  },
});

export { expect } from './base';
