---
description: Routes and handles all REST API requests entering through ibl5/api.php.
last_verified: 2026-07-24
---

# Api

Houses the full REST API layer for the application, entered via `ibl5/api.php`. The `Router` dispatches GET and POST requests to controllers, with `PUBLIC_ROUTES` (e.g. `health`) exempt from authentication. All other routes pass through `ApiKeyAuthenticator` and `RateLimiter` middleware before reaching a controller.

| Component | Purpose |
|-----------|---------|
| `Controller/` | 24 controllers including `PlayerDetailController`, `StandingsController`, `TeamDetailController`, `TradeAcceptController` |
| `Middleware/` | `ApiKeyAuthenticator` and `RateLimiter` |
| `Transformer/` | Shapes domain objects into API response payloads |
| `Pagination/` | Cursor/offset pagination support |
| `Cache/` | Response-level caching layer |
| `Repository/` | Data access used by controllers |
| `Response/` | Standardized HTTP response envelope |
