---
description: Requires plans to classify every verification step into the test-type taxonomy at plan-write time, preventing manual-testing items from deferring to post-plan cleanup.
last_verified: 2026-04-26
---

# Plan Verification Matrix

Every plan must include a **Verification Matrix** — a table classifying each verification item at plan-write time. No verification step may be deferred as "manual" or left unclassified.

## Required format

Each implementation phase that changes behavior must have a corresponding row (or rows) in the matrix. Place the matrix after the implementation phases, before any "Out of Scope" section.

```
| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | Example: salary cap calculation rejects over-cap trades | PHPUnit | pre-impl (characterization) | tests/Trade/TradeValidatorTest.php |
| 2 | Example: form submits and redirects | E2E | post-impl | e2e/trades/submit-trade.spec.ts |
```

### Test type — exactly one of:

| Test type | When to use |
|-----------|-------------|
| **PHPUnit** | DB state, service output, calculation, validation logic |
| **API-test** | HTTP request/response (endpoint returns correct JSON/HTML, status codes, headers) |
| **E2E** | Browser interaction (form submit, page navigation, HTMX swap, DOM state) |
| **Visual-regression** | "Does output still match?" / production comparison where UI/UX was NOT intentionally redesigned |
| **CLI-executable** | A command Claude can run directly during implementation (curl, bin/db-query) — not a test, but a one-shot verification |
| **Truly-manual** | Requires subjective human judgment on **new or redesigned** UI/UX ("does this look/feel good?", "does this new flow work well?") |

### Timing — exactly one of:

| Timing | When to use |
|--------|-------------|
| **pre-impl** | Characterization test locking in current behavior before modifying it (refactors, behavior changes, signature changes, shared infrastructure) |
| **post-impl** | New method/class, new UI, additive behavior |

### Classification rules

- "Verify X returns Y", "check that Z happens", "confirm the redirect works" → automatable. Never classify as truly-manual.
- "Compare against production" / "does output still match iblhoops.net?" → **visual-regression** (screenshot diff), not truly-manual — unless UI/UX was intentionally redesigned.
- If nothing in UI/UX changed, visual regression covers it. Do not classify as truly-manual.
- The **only** truly-manual items are subjective judgment on **new or redesigned** UI/UX.
- If a plan has zero truly-manual items, state: `All verification is automated — no manual testing needed.`

### Weave tests inline

Pre-implementation tests go **before** their corresponding implementation step. Post-implementation tests go **immediately after**. Never collect all tests into a separate appendix at the bottom.

## What the plan must NOT do

- List "verify manually" or "check by hand" for any item that can be asserted by PHPUnit, an API test, E2E, or visual-regression.
- Defer test classification to post-plan Phase 7. Phase 7 is a safety net, not the primary classification point.
- Add a standalone "Testing" or "Verification" section with prose descriptions instead of the matrix.
- Use "run X and check Y" without specifying the test type and file path.
