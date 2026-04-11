---
description: Why every module in ibl5/classes/ uses interface-driven Repository/Service/View split with Contracts/ subdirs.
last_verified: 2026-04-11
---

# ADR-0001: Interface-driven Repository/Service/View architecture

**Status:** Accepted
**Date:** 2026-04-11

## Context

Before the 30-module refactor, IBL5 was a PHP-Nuke codebase: database queries, business logic, input handling, and HTML rendering were interleaved inside top-level `modules/*.php` files. Nothing could be unit-tested because there were no class boundaries to mock. Security review required reading hundreds of lines of mixed concerns to trace whether a single `$_GET` value reached `echo` or SQL unescaped. Type safety was impossible — everything was mixed `array|string|false` with implicit coercion. The team had tried incremental cleanup for years without a governing structure; the structure always drifted back to the old shape because there was no convention for *where* a new responsibility should live.

## Decision

Every module in `ibl5/classes/<ModuleName>/` is split into three roles with explicit interfaces in a `Contracts/` subdirectory:

- **Repository** — prepared-statement database access, extending `BaseMysqliRepository`.
- **Service** — business logic, validation, orchestration. Consumes repository interfaces.
- **View** — HTML rendering via output buffering with `HtmlSanitizer::e()` (see ADR-0002).

Controllers, Processors, and Validators are added as additional sibling classes when a module needs them. Interfaces live in `Contracts/*Interface.php`; concrete classes `implements` them. Tests mock interfaces, not concrete classes — this is the entire point of the split. Architectural boundaries are enforced structurally (naming conventions) and by PHPStan level `max` + `strict-rules` (see ADR-0005), which rejects the type coercion patterns the old code relied on.

## Alternatives Considered

- **Trait-based composition** — mix repository/service/view traits into one class per module. Rejected: traits cannot be mocked in PHPUnit, so every test would hit the real database; and a class with three traits has three responsibilities hiding inside one name, defeating the whole clarity goal.
- **Factory pattern without interfaces** — a module-level factory builds repository and service instances. Rejected: factories become god objects that know how every class is assembled, and without interfaces the factories still can't be mocked effectively.
- **Monolithic modules with layered directory structure** — keep procedural code but organize files by layer. Rejected: no compile-time enforcement of layering; regressions always drift inward because nothing mechanical blocks them.
- **Hexagonal / ports-and-adapters** — full hexagonal architecture with ports between every seam. Rejected: too much ceremony for a single-app codebase with no microservice boundaries; 3 roles is the right granularity for IBL5's scale.

## Consequences

- Positive: every module is unit-testable via interface mocks. `ibl5/tests/Waivers/`, `ibl5/tests/Player/`, and every other module test suite depend on this.
- Positive: security review can read one `View` file to check XSS, one `Repository` file to check SQL injection, and ignore the rest.
- Positive: agents can pattern-match new modules from existing ones (`ibl5/classes/Waivers/` is the canonical example cited in CLAUDE.md).
- Positive: PHPStan enforces the structural contract across every refactored module.
- Negative: every module now has 6+ files instead of 1. Boilerplate cost accepted as the price of the other properties.
- Negative: learning curve for contributors coming from procedural PHP. Mitigated by `ibl5/docs/ARCHITECTURE_PATTERNS.md` and the canonical example.

## References

- `ibl5/docs/ARCHITECTURE_PATTERNS.md` — the how-to (structure, interface standards, reference implementations).
- `ibl5/docs/REFACTORING_HISTORY.md` — the what-changed timeline for all 30 modules.
- `ibl5/classes/Waivers/` — canonical example cited in CLAUDE.md.
- `ibl5/classes/Player/` — the largest module, 30 interfaces + 35 classes + 205 tests.
- `.claude/rules/php-classes.md` — the rules file that codifies the convention for agents.
