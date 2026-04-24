import { test as authTest } from './auth';

/**
 * Authenticated fixture with an isolated depth-chart team.
 *
 * Overrides `context` to inject a `_test_dc_team` cookie so the
 * DepthChartEntry module renders the Monarchs (tid=8) roster instead
 * of the logged-in user's Metros. Because `context` is a transitive
 * dependency of every test (via `page`), the cookie is always set —
 * even for tests that only destructure `{ page }`.
 *
 * No other spec touches tid=8, so the roster is immune to parallel
 * mutations from trading/extension/waiver specs.
 */
export const test = authTest.extend({
  context: async ({ context }, use) => {
    const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
    await context.addCookies([{
      name: '_test_dc_team',
      value: 'Monarchs',
      domain: new URL(baseUrl).hostname,
      path: '/',
    }]);
    await use(context);
  },
});

export { expect } from '@playwright/test';
