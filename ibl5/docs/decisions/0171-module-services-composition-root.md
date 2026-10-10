---
description: The front controller publishes one request-scoped Module\ModuleServices, and every module index.php resolves its object graph through a per-module factory instead of building it with new.
last_verified: 2026-10-04
---

# ADR-0171: Module services composition root

**Status:** Accepted
**Date:** 2026-10-04
**Deciders:** ajaynicolas
**Refines:** ADR-0027, ADR-0032

## Context

Backlog issue a-jay85/IBL5-backlog#211 asked for one composition root for module requests. Before this change, 44 of 48 module `index.php` files built their own object graph with `new` and read the `$mysqli_db` global. The real composition root was spread across those files. Every module rebuilt the same shared repositories: Season, team identity, salary cap and player lookup.

ADR-0027 and ADR-0032 describe manual wiring inside `index.php`. That text now describes the old state. ADRs are append-only, so those two records stay as written and this one names them.

## Decision

`ibl5/modules.php` publishes `Module\ModuleServices` once per request. It does so after the page-cache HIT exit and before `ModuleAccessControl`.

Each module resolves its controller through `ModuleServices::current()->factory(XFactory::class)`. Each factory lives under `ibl5/classes/Module/Factories/` and implements `Module\Contracts\ModuleFactoryInterface`.

Shared services are lazy container entries registered in `ModuleServices::registerSharedServices`, which `ConfigBootstrap` calls. Each entry is built on first use and cached for the request. `ModuleServices::current()` throws a `LogicException` when nothing was published, so a missing publish fails closed.

Guards stay in `index.php`: the file guard, auth, admin, `loginbox` and CSRF checks. They sit above the wiring call and keep their original bytes. Factories read no superglobal, no `$GLOBALS`, no `global` and no auth state. Their methods take no parameter that names an acting user. An object that a module builds inside a branch stays lazy and is requested from the factory inside that same branch.

## Enforcement

- `ibl5/tests/Module/ModuleCompositionRootRatchetTest.php` flags any `new` or `$mysqli_db` token in a module `index.php`. Its allowlist is empty.
- `ibl5/tests/Security/ModuleFactorySecuritySurfaceTest.php` keeps factories free of auth and request state and checks that each guard precedes the wiring call.

## Consequences

A new module needs a factory, or the ratchet fails in that PR's CI.

The `?file=` sibling scripts keep manual wiring. They are tracked in a-jay85/IBL5-backlog#1319.

Shared collaborators are built once per request instead of once per module call.

The recipe for converting a module is in the `## Module composition` section of `ibl5/classes/Module/README.md`.
