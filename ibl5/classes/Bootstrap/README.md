---
description: Wires up the application container and executes ordered bootstrap steps for web, API, and test environments.
last_verified: 2026-07-24
---

# Bootstrap

Provides the composition root for the application. `Application` orchestrates a sequence of `BootstrapStepInterface` implementations, each registered as a `*Bootstrap.php` step class (20+). `Container` is a lightweight service container with lazy factory support. Three factory classes select which steps to run per entry point: `WebApplicationFactory` for `ibl5/mainfile.php`, `ApiApplicationFactory` for `ibl5/api.php`, and `TestApplicationFactory` for test environments.

| Class | Purpose |
|-------|---------|
| `Application` | Registers and executes bootstrap steps in sequence |
| `Container` | Lazy-factory service container |
| `WebApplicationFactory` | Composition root for web requests |
| `ApiApplicationFactory` | Composition root for API requests |
| `TestApplicationFactory` | Composition root for test environments |
| `*Bootstrap.php` (20+) | Individual steps that populate the container |
