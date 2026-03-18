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
  testMatch: /visual-regression\.spec\.ts/,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],

  expect: {
    timeout: 10_000,
    toHaveScreenshot: {
      maxDiffPixelRatio: 0.005,
    },
  },

  // Strip OS and browser from snapshot file names — baselines are OS-agnostic
  // via Docker. Result: "standings-table.png" instead of "standings-table-chromium-linux.png"
  snapshotPathTemplate: '{testDir}/{testFileDir}/{testFileName}-snapshots/{arg}{ext}',

  use: {
    baseURL: process.env.BASE_URL || 'http://main.localhost/ibl5/',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    viewport: { width: 1280, height: 900 },
    reducedMotion: 'reduce',
  },

  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 900 },
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testIgnore: /auth\.setup\.ts/,
    },
  ],
});
