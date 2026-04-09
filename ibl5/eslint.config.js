import playwright from 'eslint-plugin-playwright';
import tseslint from 'typescript-eslint';

// Burn-down policy:
// -----------------
// 327 pre-existing lint problems were found on first run — well above the 30-violation
// threshold the plan set for fixing everything in-PR. This config ships the ESLint
// bootstrap today while downgrading the high-count rules to 'warn' so CI turns green.
// Each warn-level rule carries a TODO so follow-up PRs can ratchet it back to 'error'
// once the backlog is burned down. Rules at 'error' must stay 'error' — they either
// have zero pre-existing violations or are too dangerous to let slip.

export default [
  {
    ignores: [
      'node_modules/**',
      'themes/**',
      'test-results/**',
      'playwright-report/**',
      'blob-report/**',
    ],
  },
  ...tseslint.configs.recommended,
  {
    ...playwright.configs['flat/recommended'],
    files: ['tests/e2e/**/*.ts', 'playwright.config.ts'],
    rules: {
      ...playwright.configs['flat/recommended'].rules,

      // --- Enforced from day one (zero pre-existing violations) ---
      // Partial mechanization of the hidden-actionability incident: blocks the
      // `{ force: true }` escape hatch that the feedback memory warns against.
      'playwright/no-force-option': 'error',
      // Silent-pass bugs are too dangerous to let slip.
      'playwright/missing-playwright-await': 'error',
      // Prefer web-first assertions over manual waits on selectors.
      'playwright/no-wait-for-selector': 'error',

      // --- Burn-down: pre-existing violations tracked as warnings ---
      // TODO: tighten playwright/no-wait-for-timeout to error after burn-down (7 violations)
      'playwright/no-wait-for-timeout': 'warn',
      // TODO: tighten playwright/prefer-web-first-assertions to error after burn-down (27 violations)
      'playwright/prefer-web-first-assertions': 'warn',
      // TODO: tighten playwright/no-networkidle to error after burn-down (22 violations)
      'playwright/no-networkidle': 'warn',
      // TODO: tighten playwright/no-wait-for-navigation to error after burn-down (12 violations)
      'playwright/no-wait-for-navigation': 'warn',
    },
  },
];
