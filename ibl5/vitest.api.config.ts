import { defineConfig } from 'vitest/config';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __configDir = dirname(fileURLToPath(import.meta.url));

// Load .env.test if it exists — same pattern as playwright.config.ts
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
  test: {
    include: ['tests/api-e2e/**/*.test.ts'],
    globals: false,
    retry: 2,
    timeout: 15_000,
    reporters: ['verbose'],
    env: {
      BASE_URL: process.env.BASE_URL || 'http://main.localhost/ibl5/',
      IBL_API_KEY: process.env.IBL_API_KEY || 'e2e-test-key-do-not-use-in-production',
    },
  },
});
