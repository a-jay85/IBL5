import { defineConfig, devices } from '@playwright/test';
import { readFileSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

// Capture-only config for the truly-manual visual-row screenshot pipeline
// (ADR-0126). A SIBLING of playwright.visual.config.ts, deliberately not a
// widening of it: that config pins testMatch to visual-regression.spec.ts, and
// broadening the regex would run capture inside the baseline-diff step, so a
// typo'd `vr:` cell would turn the Visual Regression step red. Keeping capture
// in its own config lets the workflow run it under continue-on-error: true.
//
// This config compares nothing — it only shoots — so the toHaveScreenshot and
// snapshotPathTemplate keys are deliberately absent.

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
  testMatch: /manual-rows\.spec\.ts/,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['list']],

  expect: {
    timeout: 10_000,
  },

  use: {
    baseURL: process.env.BASE_URL || 'http://main.localhost/ibl5/',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    viewport: { width: 1280, height: 900 },
    reducedMotion: 'reduce',
  },

  projects: [
    {
      name: 'setup',
      // Matches both auth.setup.ts (admin) and auth-regular.setup.ts (non-admin).
      // auth-regular.setup.ts skips when IBL_TEST_PASS_REGULAR is unset.
      testMatch: /auth(-regular)?\.setup\.ts$/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 900 },
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testIgnore: /auth(-regular)?\.setup\.ts$/,
    },
  ],
});
