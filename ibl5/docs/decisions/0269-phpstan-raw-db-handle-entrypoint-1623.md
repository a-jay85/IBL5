---
description: A PHPStan rule, BanRawDbHandleInEntryPointRule, stops a Module Factory from exposing a public db() method that returns mysqli and stops a module index.php from calling ->db()->prepare() or ->db()->query(), so entry points get role-based services only (ADR-0171).
last_verified: 2026-11-20
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0269: PHPStan Bans the Raw mysqli Handle in Module Entry Points and Factories

**Status:** Accepted
**Date:** 2026-11-20
**Deciders:** post-plan harness (auto-draft)

## Context

ADR-0171 made each module `index.php` resolve its object graph through a per-module factory under `ibl5/classes/Module/Factories/`. Its two tests check for `new`, the `$mysqli_db` global, and auth state in factories. Neither test stops a factory from handing the raw mysqli handle back to `index.php` through a public `db()` method. With that method in place, an entry point can run SQL with `$factory->db()->prepare(...)` and skip the repository layer, and the ratchet test sees no `new` token to flag. `bin/adr-check` flagged the new custom rule `ibl5/phpstan-rules/BanRawDbHandleInEntryPointRule.php` as a mechanical enforcement decision.

## Decision

`PHPStanRules\BanRawDbHandleInEntryPointRule` is registered in `ibl5/phpstan.neon` and reports two errors. The first, `ibl.rawDbHandleInFactory`, fires on a public method named `db` whose declared return type is `mysqli` or `?mysqli`, in any file whose path contains `/classes/Module/Factories/`. A private or protected `db()` stays allowed, so a factory can still read the handle to build its repositories. The second, `ibl.rawDbHandleInEntryPoint`, fires on `->db()->prepare(...)` or `->db()->query(...)`, including the nullsafe form, in a file that matches `modules/<Module>/index.php`. Other files in a module directory are out of scope. Each message names ADR-0171 and says where the code should move: into the factory for the first error, and into a repository for the second.

## Alternatives Considered

- **Extend the ratchet test.** Add a token scan for `->db()` to `ibl5/tests/Module/ModuleCompositionRootRatchetTest.php`. Rejected because a token scan cannot tell a public method from a private one or read a return type, and PHPStan already parses both.
- **Ban every `->db()` call in an entry point.** Flag any call named `db` in `index.php`. Rejected because other services may have a `db()` method that returns something other than a handle. The rule bans the two calls that run SQL, `prepare` and `query`, and the `GoodModule` fixture keeps a bare `->db()` call legal.
- **Rely on code review.** Leave the ADR-0171 boundary to reviewers. Rejected because the boundary spans 44 factories and every module `index.php`, and a single missed review lets raw SQL back into an entry point.

## Consequences

- Positive: `composer run analyse` fails any PR that gives an entry point the raw handle or runs SQL through it, and the message tells the author where to move the code.
- Positive: factories keep internal access to the handle through a non-public `db()` or the injected services.
- Negative: the rule matches by name and path. A method with another name that returns mysqli, or SQL run through a handle stored in a local variable first, passes the rule.
- Negative: the factory check reads only the declared return type. A public `db()` with no return type, or one typed as an interface, passes.

## References

- `ibl5/phpstan-rules/BanRawDbHandleInEntryPointRule.php`
- `ibl5/tests/PHPStanRules/BanRawDbHandleInEntryPointRuleTest.php`
- `ibl5/tests/PHPStanRules/Fixtures/modules/GoodModule/index.php`
- `ibl5/phpstan.neon`
- `ibl5/tests/Module/ModuleCompositionRootRatchetTest.php`
- `ibl5/docs/decisions/0171-module-services-composition-root.md`
