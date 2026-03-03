import { test as base } from '@playwright/test';

/**
 * Authenticated test fixture. Tests importing `test` from this file
 * use the stored auth state from auth.setup.ts — no login needed.
 */
export const test = base.extend({});

export { expect } from '@playwright/test';
