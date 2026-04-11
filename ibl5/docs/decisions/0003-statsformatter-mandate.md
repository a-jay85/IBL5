---
description: Why a single centralized StatsFormatter is mandatory and number_format() is banned project-wide.
last_verified: 2026-04-11
---

# ADR-0003: `StatsFormatter` mandate, `number_format()` banned

**Status:** Accepted
**Date:** 2026-04-11

## Context

IBL5 renders basketball statistics across 31 modules: points per game, field-goal percentages, per-36 totals, career aggregates, leaderboards, boxscores, team stats, player stats. Each stat category has a canonical format — PPG is one decimal, FG% is three decimals, totals use comma separators, zero-division is a dash not `NaN`. When each module chose its own formatting via direct `number_format()` calls, the output drifted: one screen showed `23.4 PPG`, another showed `23.40 PPG`, a third showed `23,40 PPG` on European locales. Cross-module bugs ("why is Alice's FG% showing as 0.476 on the roster page and 47.6% on the leaderboard?") kept recurring because there was no single source of truth for "how does IBL5 format a percentage." Zero-division guards were inconsistently added; some pages showed `NaN%` when a player had zero attempts.

## Decision

Use `BasketballStats\StatsFormatter` for every stat format in the project. Direct `number_format()` calls are banned by `BanNumberFormatRule` (identifier `ibl.bannedNumberFormat`, implemented at `ibl5/phpstan-rules/BanNumberFormatRule.php`), with one exception: `StatsFormatter.php` itself is allowed to call `number_format()` because it *is* the wrapper. `StatsFormatter`'s public API includes the domain-specific helpers every stat screen needs:

- `formatPercentage($made, $attempted)` — FG%, 3P%, FT%.
- `formatPerGameAverage($total, $games)` — PPG, RPG, APG, BPG, SPG.
- `formatPer36Stat($total, $minutes)` — per-36 rate stats.
- `formatTotal($value)` — career and season totals with comma separators.
- `formatWithDecimals($value, $decimals)` — custom decimal places where needed.
- `safeDivide($num, $denom)` — zero-division guard returning a typed default.

The rule fires in PostToolUse and CI; a PR with a new `number_format()` call outside `StatsFormatter.php` cannot merge.

## Alternatives Considered

- **Per-module formatting helpers** — each module has its own `format*` helpers and the style guide says "use the module's helper." Rejected: exactly the drift the project was suffering. 31 modules means 31 drift points, and no mechanical check prevents one more copy.
- **Third-party number formatter (Symfony NumberFormatter, internationalization libs)** — a PHP package with locale awareness. Rejected: none of the candidates express IBL5's domain semantics (per-36, per-game, zero-division-as-dash), so every call would still need project-specific wrapping. Adds a dependency for zero gain.
- **Trait mixed into stat-producing classes** — `use StatsFormattingTrait;` and call `$this->formatPercentage()`. Rejected: same drift problem — nothing blocks a contributor from writing `number_format` inline alongside the trait call. No mechanical enforcement, just a suggestion.
- **Extend `BaseMysqliRepository` with format methods** — bake formatting into the repository layer. Rejected: conflates data access and rendering, violates ADR-0001, and doesn't cover non-repository code paths.

## Consequences

- Positive: one source of truth for every stat format. Changing "percentages should show two decimals, not three" is one line change in one file.
- Positive: zero-division guards are centralized. `safeDivide` is the only place that handles the edge case, and every caller inherits the fix.
- Positive: PHPStan rejects new `number_format()` calls during editing — agents learn the convention immediately instead of at code review.
- Positive: localization is tractable later. If IBL5 ever needs to render numbers in European locale, one file changes.
- Negative: `StatsFormatter`'s public API is the project's lingua franca — growing it is a design decision, not a drive-by change. Adding a new method requires a PR that also adds test coverage. Deliberate friction.
- Negative: one-off formatting needs (e.g., rendering a git SHA count) that are not "stats" still have to go through `StatsFormatter::formatWithDecimals` or similar. Slightly awkward; in practice rare.

## References

- `ibl5/classes/BasketballStats/StatsFormatter.php` — the wrapper and canonical API.
- `ibl5/phpstan-rules/BanNumberFormatRule.php` — the enforcing rule.
- `.claude/rules/php-classes.md` — the agent-facing cheat sheet listing every public method (cites this ADR).
- `ibl5/tests/BasketballStats/StatsFormatterTest.php` — the behavioral spec.
