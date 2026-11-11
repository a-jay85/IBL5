---
description: Routes and handles all REST API requests entering through ibl5/api.php.
last_verified: 2026-11-09
---

# Api

Houses the full REST API layer for the application, entered via `ibl5/api.php`. The `Router` dispatches GET and POST requests to controllers, with `PUBLIC_ROUTES` (e.g. `health`) exempt from authentication. All other routes pass through `ApiKeyAuthenticator` and `RateLimiter` middleware before reaching a controller.

| Component | Purpose |
|-----------|---------|
| `Controller/` | 27 controllers including `PlayerDetailController`, `StandingsController`, `TeamDetailController`, `TradeAcceptController`. The trade accept/decline, reaction, and wake controllers call `Middleware/ServiceKeyGuard` first, so a standard-tier key gets 403 |
| `Middleware/` | `ApiKeyAuthenticator` and `RateLimiter` |
| `Transformer/` | Shapes domain objects into API response payloads |
| `Pagination/` | Cursor/offset pagination support |
| `Cache/` | Response-level caching layer |
| `Repository/` | Data access used by controllers |
| `Response/` | Standardized HTTP response envelope |

## Service-key routes

Five bug-pipeline routes accept only the bot's service key: `bug-pipeline/wake`, `bug-pipeline/reaction`, `bug-pipeline/enqueue`, `bug-pipeline/source-updated`, and `bug-pipeline/source-deleted`. Each controller calls `ServiceKeyGuard::callerIsServiceKey()` as the first statement of `handle()`. A missing key, an unknown key, or a key whose `rate_limit_tier` is `standard` gets 403 `forbidden` before any body field is read or any row is written. Self-service keys are always `standard`, so only an operator-provisioned key passes. The trust boundary is described in ADR-0213.
