---
description: When a plan prescribes expected values for phase-sensitive salary/contract boundary cases, trace the resolver chain — domain intuition is insufficient.
last_verified: 2026-09-05
paths:
  - "ibl5/classes/Player/Contract/**/*"
  - "ibl5/classes/Team/**/*"
  - "ibl5/classes/Season/**/*"
---

# Phase-Sensitive Expected Values in Plans

When a plan phase specifies a specific numeric expected value for a characterization test involving a phase-sensitive calculation (anything routing through `resolveCurrentContractYear()`, `Season::advancesContractYears()`, or a similar phase-dispatch chain), **trace the resolver path** under each relevant phase condition to derive the expected value — never assume it from domain knowledge.

**Why:** For boundary inputs (e.g., `cy=0`, `cy=cyt`), the dispatch chain can collapse apparent differences. `cy=0` advancing to `cy=1` and `cy=0` staying at `cy=0` both resolve to `salaryForContractYear(1)` = `contractYear1Salary`, so the offseason branch returns the same value as the in-season branch even though intuition suggests it should return a different year's salary. Domain knowledge predicts the wrong expected value; only the trace reveals the correct one. A plan that guesses wrong carries a stale assertion that a later plan phase must overwrite.

**How:** In the plan phase that writes the characterization test, include an inline trace before writing the assertion:

1. State the input: `cy`, `cyt`, and which phase condition is active
2. Trace `resolveCurrentContractYear()` → effective year
3. Derive `salaryForContractYear(effective year)` → expected value
4. Repeat for each phase condition (in-season, offseason)

Write the assertion from the trace, not from intuition.
