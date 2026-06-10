import { defineConfig, devices } from '@playwright/test';
import { readFileSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __configDir = dirname(fileURLToPath(import.meta.url));

// Load .env.test if it exists (no external dependency needed)
try {
  const envFile = readFileSync(resolve(__configDir, '.env.test'), 'utf-8');
  for (const line of envFile.split('\n')) {
    const trimmed = line.trim();
    if (trimmed && !trimmed.startsWith('#')) {
      const eqIndex = trimmed.indexOf('=');
      if (eqIndex > 0) {
        const key = trimmed.slice(0, eqIndex).trim();
        const value = trimmed.slice(eqIndex + 1).trim();
        process.env[key] ??= value;
      }
    }
  }
} catch {
  // .env.test doesn't exist — env vars must be set externally
}

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 4,
  reporter: [['html', { open: 'never' }], ['list']],

  expect: {
    timeout: 5_000,
  },

  use: {
    baseURL: (process.env.BASE_URL || 'http://main.localhost/ibl5/').replace(/\/?$/, '/'),
    actionTimeout: 7_000,
    navigationTimeout: 15_000,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'setup',
      // Matches both auth.setup.ts (admin) and auth-regular.setup.ts (non-admin).
      // The regular setup is skipped at runtime when IBL_TEST_USER_REGULAR is
      // unset (local devs can opt out) — see auth-regular.setup.ts.
      testMatch: /auth(-regular)?\.setup\.ts$/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testIgnore: [/auth\.setup\.ts/, /auth-regular\.setup\.ts/, /visual-regression/, /updater-awards\.spec\.ts$/, /league-control-panel\.spec\.ts$/],
    },
    {
      // Destructive full-season updater specs mutate GLOBAL DB rows (schedules,
      // standings, sims, awards, season phase) that other specs read. Isolated
      // into their own project so CI can run them in a dedicated, NON-sharded
      // job against a fresh DB — see ADR-0052. They are excluded from chromium's
      // testIgnore above so the sharded run never touches them.
      name: 'mutators',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testMatch: [/updater-awards\.spec\.ts$/, /league-control-panel\.spec\.ts$/],
    },
  ],
});
