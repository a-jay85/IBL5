---
description: ADR for RequireLeagueContextInFactoryRule, which flags a factory method that builds a LeagueContext-aware class without forwarding the LeagueContext it holds
last_verified: 2026-11-20
---

# ADR-0271: Require LeagueContext Forwarding in Factory Methods

**Status:** Accepted
**Date:** 2026-11-20

## Context

Backlog item 1624 asked for a gate on factories that drop the `LeagueContext`. Several repositories take an optional `?LeagueContext $leagueContext = null` and fall back to IBL tables when it is `null`. A factory that holds a `LeagueContext` but calls `new SomeRepository($db)` silently reads IBL data on an Olympics page. The `OnThisDayFactory` occurrence was fixed in PR #3351.

A rule that flags every `new` of a class with a `LeagueContext` constructor parameter would hit 28 to 45 sites. Most of those factories are IBL-only and hold no `LeagueContext`, so the hits would be noise. The narrow rule below flags 5 sites.

## Decision

Add `RequireLeagueContextInFactoryRule` (`ibl5/phpstan-rules/RequireLeagueContextInFactoryRule.php`, identifier `ibl.factoryOmitsLeagueContext`). It runs on each method in a file whose name ends in `Factory.php`.

A method has a `LeagueContext` in scope when any of these three forms appears:

- a parameter typed `League\LeagueContext`, nullable or not
- a variable whose name contains `leagueContext`
- a call to a method named `leagueContext()`

In such a method, every `new X(...)` is checked. The rule resolves the effective constructor through PHPStan reflection, so a class that inherits its constructor from `BaseMysqliRepository` counts. If that constructor has a `League\LeagueContext` parameter and the call supplies no argument for it (by position, by name, or by unpacking), the rule reports an error on the `new` line.

An intentional exemption is an inline `// @phpstan-ignore ibl.factoryOmitsLeagueContext (<reason>)` comment. The reason must name the IBL-only tables the class reads. `LeagueControlPanelFactory` carries two, for `VotingRepository` and `MaintenanceRepository`.

## Alternatives Considered

- Flag every `new` of a LeagueContext-aware class: it would report 28 to 45 sites, mostly IBL-only factories. Rejected because the noise would force a large baseline.
- Match file names other than `Factory.php`: rejected because composition roots such as module entry points legitimately build IBL-only objects.

## Consequences

- Positive: A factory that already holds a `LeagueContext` cannot drop it without a visible, reasoned exemption.
- Positive: The 5 existing sites are fixed or exempted, so the baseline does not grow.
- Negative: An IBL-only factory with no `LeagueContext` in scope stays silent, even when the class it builds accepts one.
- Negative: A factory that starts reading the `LeagueContext` is then held to forwarding it to every constructor that accepts one.

## References

- `ibl5/phpstan-rules/RequireLeagueContextInFactoryRule.php`
- `ibl5/tests/PHPStanRules/RequireLeagueContextInFactoryRuleTest.php`
- `ibl5/classes/Module/Factories/LeagueControlPanelFactory.php`
- `ibl5/phpstan.neon`
