import { test as authTest } from './auth';

/**
 * Authenticated fixture with an isolated unhealthy-lineup team identity.
 *
 * Overrides `context` to inject a `_test_team` cookie so modules that
 * resolve the user's team via `getTeamnameFromUsername()` see the
 * Huskies (tid=17) roster instead of the logged-in user's Metros.
 * Because `context` is a transitive dependency of every test (via
 * `page`), the cookie is always set — even for tests that only
 * destructure `{ page }`.
 *
 * No other spec touches tid=17. The roster is engineered to trigger
 * exactly one lineup-health warning: `injured_starter` (PG starter,
 * pid=200, injured=4). All other warning conditions are clean.
 */
export const test = authTest.extend({
  context: async ({ context }, use) => {
    const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
    await context.addCookies([{
      name: '_test_team',
      value: 'Huskies',
      domain: new URL(baseUrl).hostname,
      path: '/',
    }]);
    await use(context);
  },
});

export { expect } from './base';
