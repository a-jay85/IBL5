---
description: PHPStan rules ban implicit superglobals and global keyword outside bootstrap, enforcing constructor injection.
last_verified: 2026-07-22
---

# ADR-0032: Ban Implicit Superglobals and Global Keyword

## Status

Accepted

## Context

The codebase used PHP superglobals (`$_SESSION`, `$_SERVER`, `$_FILES`, `$GLOBALS`) and the `global` keyword freely in service and repository classes, creating hidden dependencies that made code harder to test and reason about. The existing `BanRawSuperglobalsRule` covered `$_GET`, `$_POST`, `$_REQUEST`, and `$_COOKIE` but left the remaining superglobals unregulated.

## Decision

1. **Expand `BanRawSuperglobalsRule`** to cover `$_SESSION`, `$_SERVER`, `$_FILES`, and `$GLOBALS` with per-superglobal allowlists (suffixes + file basenames).

2. **Add `BanGlobalKeywordRule`** to ban the `global` keyword in all files except a small bootstrap allowlist (`LegacyFunctions.php`, `ConfigBootstrap.php`, `NukeCompat.php`, `PageLayout.php`, `PdoConnection.php`, `DebugOutput.php`).

3. **Refactor existing violations** using constructor injection patterns:
   - `global $user` + `cookieDecode()` → `AuthServiceInterface::getUsername()`
   - `global $leagueContext` → `LeagueContext` constructor parameter
   - `global $cookie` → `AuthServiceInterface` injection
   - `$_SERVER['SERVER_NAME']` in service classes → `string $serverName` constructor parameter

## Consequences

- New service/repository classes cannot access superglobals or use `global` without being added to the allowlist — violations fail PHPStan at level max.
- Bootstrap files (`*Bootstrap.php`) remain the boundary where superglobals are read and injected into the dependency graph.
- The `Discord` class now uses a static `init(string $serverName)` call at bootstrap time rather than reading `$_SERVER` at each call site.
- Module entry files (`modules/*/index.php`) thread `$authService` and `$leagueContext` from bootstrap globals into controllers.
