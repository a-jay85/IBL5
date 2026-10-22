---
description: Wires up the application container and executes ordered bootstrap steps for web, API, and test environments.
last_verified: 2026-10-20
---

# Bootstrap

Provides the composition root for the application. `Application` orchestrates a sequence of `BootstrapStepInterface` implementations, each registered as a `*Bootstrap.php` step class (20). `Container` is a lightweight service container with lazy factory support. Three factory classes select which steps to run per entry point: `WebApplicationFactory` for `ibl5/mainfile.php`, `ApiApplicationFactory` for `ibl5/api.php`, and `TestApplicationFactory` for test environments.

| Class | Purpose |
|-------|---------|
| `Application` | Registers and executes bootstrap steps in sequence |
| `Container` | Lazy-factory service container |
| `WebApplicationFactory` | Composition root for web requests |
| `ApiApplicationFactory` | Composition root for API requests |
| `TestApplicationFactory` | Composition root for test environments |
| `*Bootstrap.php` (20) | Individual steps that populate the container |

## SharedSeasonBootstrap

Arms the request-scoped Season memo (`Season\SharedSeason`). `WebApplicationFactory` registers it last; `ApiApplicationFactory` registers it right after `ConfigBootstrap`, before routing and dispatch. `TestApplicationFactory` never registers it. The step is a no-op when the SAPI is exactly `cli`, because CLI scripts also boot through `mainfile.php`. The built-in server (`cli-server`, used by `router.php`) arms like any web SAPI. The container's `season` entry delegates to `SharedSeason::get()`, so there is never a second instance.
