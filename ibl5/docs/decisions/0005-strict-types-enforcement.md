---
description: Why PHPStan level max plus strict-rules plus RequireStrictTypesRule is the non-negotiable type floor.
last_verified: 2026-04-11
---

# ADR-0005: Strict types + typed properties enforcement

**Status:** Accepted
**Date:** 2026-04-11

## Context

Pre-refactor IBL5 ran on PHP's default loose type semantics. `'0' == false`, `null == 0`, `"abc" == 0` all returned true; query results came back as mixed `array|false|null`; and class properties were untyped by default, allowing anything to be assigned to anything. Debugging sessions regularly turned into "the ID comparison failed because `$player->tid` was a string but the int literal matched the loose comparison anyway, except when `tid === 0` because empty string coerces to 0." These bugs were silent: the wrong player got assigned to the wrong team, the wrong contract year was paid out, and nothing crashed until the data hit production. Test coverage didn't help because loose typing made the tests themselves suspect. The team adopted PHPStan incrementally but hit a floor: without `strict_types=1` and without strict-rules, PHPStan's own checks were neutered on the very patterns that were causing incidents.

## Decision

Every PHP file inside `ibl5/classes/` must begin with `declare(strict_types=1);`. This is enforced by the custom rule `RequireStrictTypesRule` (identifier `ibl.missingStrictTypes`, implemented at `ibl5/phpstan-rules/RequireStrictTypesRule.php`). PHPStan runs at `level: max` with `phpstan-strict-rules` and `bleedingEdge` enabled via `ibl5/phpstan.neon`. Additional requirements codified in CLAUDE.md Type Safety section and enforced by stock PHPStan:

- **Typed properties:** every class property declared with a type.
- **Typed methods:** every parameter and return type declared.
- **Strict equality:** always `===` / `!==`, never `==` / `!=`.
- **Null handling:** nullable types (`?string`) and null coalescing (`??`), no implicit null-to-empty.

The rule fires in PostToolUse during editing and in CI via `tests.yml` PHPStan job.

## Alternatives Considered

- **PHPStan level 8 without strict-rules** — the highest built-in level, no custom rules, no strict-rules extension. Rejected: loose `==` still allowed, implicit numeric string conversion still allowed, function parameter coercion still allowed — exactly the patterns causing incidents. Level max plus strict-rules closes those holes.
- **Gradual typing via `@phpstan-type` aliases** — keep the code untyped but add PHPDoc type aliases. Rejected: doesn't enforce at runtime, splits type knowledge across two places (the PHPDoc and the code), and PHPDoc drift is rampant. The whole point is to make the type the single source of truth.
- **Runtime `assert()` calls** — sprinkle `assert(is_int($tid))` at function boundaries. Rejected: opt-in per call site, easy to miss, runs at runtime (late), and PHPStan already does this check at analysis time when types are declared.
- **Psalm instead of PHPStan** — use a competitor static analyzer. Rejected: PHPStan's custom rule API is what lets IBL5 layer domain-specific rules (XSS, SQL injection, require_once, etc.); a mid-project switch would mean rewriting all those rules for Psalm's API.

## Consequences

- Positive: type coercion bugs are caught before commit. `'0' === 0` no longer compiles — PHPStan flags the mismatch.
- Positive: large-scale refactoring is safe. Renaming a field or changing a return type surfaces every caller through type errors instead of at runtime.
- Positive: agents writing new code inherit the constraint automatically — the PostToolUse PHPStan run rejects loose patterns before they reach commit.
- Positive: onboarding is faster because the type system communicates intent. New contributors don't need to guess what `$row['tid']` contains.
- Negative: PHPStan level max is noisy on legacy code. Modules in `ibl5/classes/SiteStatistics/` are excluded via `phpstan.neon` `excludePaths` because they predate the contract. Deliberate carve-out, not drift.
- Negative: every new PHPStan major version raises new issues. Accepted as the price of strictness; each PHPStan upgrade is its own focused PR.
- Negative: PHP's own strict type checking is not as strong as a statically compiled language. `array|string|false` unions still exist for database return types. PHPStan narrows them via `@phpstan-var` and dedicated type guards — see `.claude/rules/php-classes.md` for the patterns.

## References

- `ibl5/phpstan-rules/RequireStrictTypesRule.php` — the `declare(strict_types=1)` enforcer.
- `ibl5/phpstan.neon` — the level, strict-rules, and bleedingEdge config plus `excludePaths` for legacy carve-outs.
- `.claude/rules/php-classes.md` — the agent-facing rule file with type-narrowing patterns (cites this ADR).
- CLAUDE.md Type Safety section — the project-wide directive.
