---
description: Dev auto-login setup: localhost sessions are pre-authenticated via DEV_AUTO_LOGIN; do not navigate to login forms.
last_verified: 2026-04-11
---

# Browser Auto-Login

On localhost (`main.localhost`, `localhost`, `127.0.0.1`), dev auto-login is active.
You are automatically authenticated as the user in `ibl5/.env.test` (`DEV_AUTO_LOGIN`).

- Do NOT navigate to login forms or enter credentials — the session is pre-authenticated on first page load.
- If you see a login page, `.env.test` may be misconfigured or Docker needs restarting.
