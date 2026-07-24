---
description: Handles login, registration, password reset, and development/demo auth shortcuts.
last_verified: 2026-07-24
---

# Auth

Provides authentication for the application, wrapping the `delight-im/auth` library in `AuthService`. `AuthRepository` handles all database queries for user and team lookups. Two auxiliary classes handle non-production flows: `DemoLoginGate` is a fail-closed token check for the demo-login endpoint, and `DevAutoLogin` provides localhost-only auto-login when `DEV_AUTO_LOGIN` is set in `.env.test` and the `_auto_login=1` cookie is present.

| Class | Purpose |
|-------|---------|
| `AuthService` | Wraps delight-im/auth for login, registration, password reset |
| `AuthRepository` | Database queries for user and team lookups |
| `DemoLoginGate` | Fail-closed token check for the demo-login endpoint |
| `DevAutoLogin` | Localhost-only auto-login for development |
