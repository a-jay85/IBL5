---
description: Centralizes shared contract modifier formulas (winner, tradition, loyalty, playing time) into ContractRules static methods, eliminating three divergent implementations.
last_verified: 2026-04-26
---

# ADR-0014: Centralize Contract Modifier Formulas

**Status:** Accepted
**Date:** 2026-04-26

## Context

Three contract calculators were independently extracted from legacy PHP files in 2025: `ExtensionOfferEvaluator` (PR #49), `NegotiationDemandCalculator` (PR #108), and `FreeAgencyDemandCalculator` (PR #132). Each faithfully ported its legacy source, but the legacy sources had drifted over years of separate maintenance. Winner and tradition used different scaling (raw differential vs normalized win-rate), and playing time had three distinct formulas across the modules. No canonical source was ever established, and the divergence caused incorrect extension evaluations and negotiation demands.

## Decision

All shared modifier formulas are centralized as static methods on `ContractRules`: `calculateWinnerModifier`, `calculateTraditionModifier`, `calculateLoyaltyModifier`, and `calculatePlayingTimeModifier`. Consumer classes (`ExtensionOfferEvaluator`, `NegotiationDemandCalculator`, `FreeAgencyDemandCalculator`) delegate to these methods instead of implementing their own formulas. `FreeAgencyDemandCalculator` is the canonical source for the unified formulas. Enforced by `ModifierConsistencyTest` integration test.

## Alternatives Considered

- **Shared trait** — inject formulas via trait. Rejected because: `ContractRules` already exists as the CBA single-source-of-truth; a trait would fragment contract logic across two surfaces.
- **Abstract base class** — shared base for all three calculators. Rejected because: the three classes have different constructors, dependencies, and lifecycles; forced inheritance for four helper methods is disproportionate.
- **Inline fix without centralization** — fix each formula in place. Rejected because: leaves three copies vulnerable to future drift, which is the root cause of the bugs being fixed.

## Consequences

- Positive: single source of truth for modifier formulas; new changes require updating one location.
- Positive: `ContractRules` scope expands naturally from CBA salary rules to include modifier formulas, keeping all contract math in one class.
- Negative: `ContractRules` grows in scope — future reviewers must understand it covers both salary rules and demand modifiers.

## References

- `ibl5/classes/ContractRules.php` — canonical modifier methods and constants
- `ibl5/tests/Integration/ModifierConsistencyTest.php` — cross-module divergence guard
- `ibl5/tests/ContractRulesTest.php` — unit tests for modifier methods
