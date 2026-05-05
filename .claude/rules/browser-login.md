---
description: Dev auto-login setup: localhost sessions are pre-authenticated via DEV_AUTO_LOGIN; do not navigate to login forms. Identity matrix for all test layers.
last_verified: 2026-05-04
---

# Browser Auto-Login

On localhost (`main.localhost`, `localhost`, `127.0.0.1`), dev auto-login is active.
You are automatically authenticated as the user in `ibl5/.env.test` (`DEV_AUTO_LOGIN`).

- Do NOT navigate to login forms or enter credentials — the session is pre-authenticated on first page load.
- If you see a login page, `.env.test` may be misconfigured or Docker needs restarting.

## Identity Matrix

| Layer | User | Source | Used by |
|-------|------|--------|---------|
| Local browser dev | A-Jay (or per-developer) | `.env.test` `DEV_AUTO_LOGIN` | Localhost browsing on real dev DB |
| CI E2E browser | Configured at runtime | `secrets.IBL_TEST_USER` / `IBL_TEST_PASS` | Playwright in CI |
| DatabaseIntegration tests | testadmin / testgm | `tests/DatabaseIntegration/Fixtures/db-seed.sql` | PHPUnit `#[Group('database')]` tests |

These layers do not need to share usernames. Each is independently scoped.
