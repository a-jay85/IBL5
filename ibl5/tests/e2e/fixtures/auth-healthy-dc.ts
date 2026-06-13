import { test as authTest } from './auth';

/**
 * Authenticated fixture with an isolated healthy-lineup team identity.
 *
 * Overrides `context` to inject a `_test_team` cookie so modules that
 * resolve the user's team via `getTeamnameFromUsername()` see the
 * Nuggets (tid=19) roster instead of the logged-in user's Metros.
 *
 * No other spec touches tid=19 or pids 300-311. The roster is engineered
 * to trigger ZERO lineup-health warnings (all-clear state).
 */
export const test = authTest.extend({
  context: async ({ context }, use) => {
    const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
    await context.addCookies([{
      name: '_test_team',
      value: 'Nuggets',
      domain: new URL(baseUrl).hostname,
      path: '/',
    }]);
    await use(context);
  },
});

export { expect } from './base';
