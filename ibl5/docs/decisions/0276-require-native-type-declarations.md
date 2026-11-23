---
description: A PHPStan rule, RequireNativeTypeDeclarationsRule, requires a native type on every method and function parameter, return, and property in production code, and bans a bare object type, so a PHPDoc tag can no longer stand in for a native type (backlog#1632).
last_verified: 2026-11-21
---

# ADR-0276: PHPStan Requires Native Type Declarations and Bans Bare object

**Status:** Accepted
**Date:** 2026-11-21
**Deciders:** Claude agent session

## Context

[backlog#1632](https://github.com/a-jay85/IBL5-backlog/issues/1632) found that production code can omit a native type and still pass static analysis. PHPStan at level max reports `missingType.*` errors, but a PHPDoc tag such as `@param int $x` satisfies them. A parameter, return, or property with only a PHPDoc type therefore passes. PHP itself never checks that type at runtime, so a wrong value reaches the method body unchecked.

## Decision

`PHPStanRules\RequireNativeTypeDeclarationsRule` is registered in `ibl5/phpstan.neon` and runs over every path that file analyses. It requires a native type on three things. Every method and function parameter needs one, including promoted constructor parameters. Every return type needs one, except on `__construct` and `__destruct`. Every property needs one. The rule also bans bare `object` in every position, including nullable, union, and intersection forms, under the identifier `ibl.bareObjectType`. Closures and arrow functions are exempt. The `tests/` directory is not covered, and `ibl5/phpstan-tests.neon` is unchanged.

Five sites keep `object` behind an inline `@phpstan-ignore ibl.bareObjectType (...)` comment that states the reason:

- Three sites in `ibl5/classes/Database/BaseMysqliRepository.php`. `MockDatabase::prepare()` returns a plain `MockPreparedStatement`, which is not a `mysqli_stmt`.
- One site in `ibl5/classes/Module/ModuleServices.php`, where the template type is `T of object`.
- One site in `ibl5/classes/Player/Views/PlayerViewFactory.php`, where the factory returns one of 13 unrelated view classes.

No baseline entry was added.

## Alternatives Considered

- **Rely on PHPStan level max.** Keep the existing analysis. Rejected because a PHPDoc tag satisfies the `missingType.*` errors, so level max cannot see the gap.
- **Baseline the existing violations.** Record each one in `ibl5/phpstan-baseline.neon`. Rejected because the code was fixed instead, and the five unavoidable `object` sites carry a reasoned inline ignore that a reader meets at the code.

## Consequences

- Positive: `composer run analyse` fails any PR that adds a PHPDoc-only type or a bare `object` to production code.
- Positive: PHP enforces every declared type at runtime, so a wrong value fails at the call boundary.
- Negative: a stored callable uses the `\Closure` type, because PHP has no `callable` property type. Code converts a callable with `\Closure::fromCallable()` before it stores one.
- Negative: a resource uses `mixed`, because PHP has no resource type.
- Negative: the five `object` sites need an inline ignore, and a new one needs a stated reason.
- Negative: test code is outside the rule, so a PHPDoc-only type in `tests/` still passes.

## References

- `ibl5/phpstan-rules/RequireNativeTypeDeclarationsRule.php`
- `ibl5/phpstan.neon`
- `ibl5/phpstan-tests.neon`
- `ibl5/classes/Database/BaseMysqliRepository.php`
- `ibl5/classes/Module/ModuleServices.php`
- `ibl5/classes/Player/Views/PlayerViewFactory.php`
