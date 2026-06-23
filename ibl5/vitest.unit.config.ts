import { defineConfig } from 'vitest/config';

// Pure-unit config (no DB, no browser, no env) — sibling of vitest.api.config.ts.
// Scopes the fast, I/O-free unit specs under tests/unit/ that lock the contracts
// of the pure VR-review helpers (vr-coverage-map.ts, vr-review-comment.ts).
export default defineConfig({
  test: {
    include: ['tests/unit/**/*.test.ts'],
    globals: false,
    reporters: ['verbose'],
  },
});
