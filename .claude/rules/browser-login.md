---
description: Localhost browsing is logged-out by default; set an `_auto_login=1` cookie to auto-authenticate via DEV_AUTO_LOGIN. Identity matrix for all test layers.
last_verified: 2026-06-19
---

# Browser Auto-Login

On localhost (`main.localhost`, `localhost`, `127.0.0.1`, `<slug>.localhost`), manual browsing is **logged-out by default**. Dev auto-login is opt-in: it only fires when an `_auto_login=1` cookie is present AND `DEV_AUTO_LOGIN` is set in `ibl5/.env.test`.

To auto-authenticate as the `.env.test` `DEV_AUTO_LOGIN` user:
- **curl:** `curl --cookie "_auto_login=1" http://<slug>.localhost/ibl5/...`
- **Playwright:** `page.context().addCookies([{ name: '_auto_login', value: '1', domain, path: '/' }])`

Without the cookie you will see the login form — that is expected, not a misconfiguration.

A separate `_e2e=1` cookie gates PageCache-skip for unauthenticated E2E browser contexts (no auth effect).

## Identity Matrix

| Layer | User | Source | Used by |
|-------|------|--------|---------|
| Local browser dev | A-Jay (or per-developer) | `.env.test` `DEV_AUTO_LOGIN` + `_auto_login=1` cookie | Localhost browsing on real dev DB |
| CI E2E browser | Configured at runtime | `secrets.IBL_TEST_USER` / `IBL_TEST_PASS` | Playwright in CI |
| DatabaseIntegration tests | testadmin / testgm | `tests/DatabaseIntegration/Fixtures/db-seed.sql` | PHPUnit `#[Group('database')]` tests |

These layers do not need to share usernames. Each is independently scoped.
