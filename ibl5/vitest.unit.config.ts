import { defineConfig } from 'vitest/config';

// Pure-unit config (no DB, no browser, no env) — sibling of vitest.api.config.ts.
// Scopes the fast, I/O-free TS unit specs under tests/ts-unit/ that lock the
// contracts of the pure VR-review helpers (vr-coverage-map.ts, vr-review-comment.ts).
// NB: the dir is tests/ts-unit/ (not tests/unit/) to avoid a case-only collision
// with the existing PHP PSR-4 tests/Unit/ dir on case-insensitive filesystems.
export default defineConfig({
  test: {
    include: ['tests/ts-unit/**/*.test.ts'],
    globals: false,
    reporters: ['verbose'],
  },
});
