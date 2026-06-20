---
description: Requires plans to classify every verification step into the test-type taxonomy at plan-write time, preventing manual-testing items from deferring to post-plan cleanup, and grounds seed/DOM-dependent E2E assertions in real fixtures.
last_verified: 2026-06-20
---

# Plan Verification Matrix

Shared include — read by `/plan` (Step 1), `/post-plan`, and `_test-spec-corpus`. Not an
always-loaded rule; `/plan` reads it on invocation.

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
| **Truly-manual** | Requires subjective human judgment on **new or redesigned** UI/UX ("does this look/feel good?", "does this new flow work well?"). **Forced** — see § Forced manual-verification trigger — whenever the plan introduces new/redesigned UI/UX; not optional. |

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
- "I can't tell mechanically whether it works" (a silent/integration-only failure mode, an observe-in-prod property) is a **verification gap, not a truly-manual item** → build the self-asserting check (`/plan` autonomy lever 3 / Step 3 § Verification-gap mechanization), do not classify as truly-manual or hold the merge.
- If a plan has zero truly-manual items, state: `All verification is automated — no manual testing needed.`

### Weave tests inline

Pre-implementation tests go **before** their corresponding implementation step. Post-implementation tests go **immediately after**. Never collect all tests into a separate appendix at the bottom.

## Forced E2E triggers

These patterns **require** at least one E2E row in the verification matrix, even when PHPUnit covers the underlying logic. Unit tests verify isolated behavior; only E2E confirms the behavior composes into a rendered page.

| Trigger pattern | Why PHPUnit is insufficient |
|-----------------|----------------------------|
| New POST/form endpoint | Form submission, CSRF, redirect, and resulting page state are browser-only |
| New conditional UI gated by session, cookie, or user identity | Session/cookie hydration and DOM presence depend on the full request lifecycle |
| New navigation entry or menu item | Rendering is composed through `NavigationMenuBuilder` → theme → DOM; unit-testing the builder alone misses integration |
| New HTML route (module `index.php`) | The route may render, redirect, or error — only a browser visit confirms which |
| New `<details>`, modal, toggle, or expandable section | Expand/collapse, visibility toggling, and content rendering are DOM interactions |
| New indicator or status element that changes with state | Visual state feedback (dots, badges, labels) must be verified in-browser across both states |

When a plan introduces any of these patterns, the planner must add a corresponding E2E row — one row per distinct user-visible state. For example, a toggle that shows/hides UI needs two E2E rows: one verifying the ON state, one verifying OFF.

If E2E coverage is blocked by a missing test fixture (e.g., no CI seed user for an admin-gated feature), the plan must include a phase that creates the fixture. "No fixture exists" is not a reason to downgrade to PHPUnit.

### Seed- and DOM-grounded E2E assertions

Any E2E verification-matrix row that asserts a **seed-** or **DOM-dependent** value — a row count, a dropdown/option value, sort order or direction, filter results, or "control X exists / is a `<select>` vs. radio" — must **cite its source**, never an assumed value. An assertion grounded in an imagined value is how PR #887 shipped: it expected a display-cap count (~500, full-league) while the CI seed has only ~24 career rows, so the test was deterministically red the moment it ran.

The source must be one of:

- A specific row or count from `ibl5/tests/e2e/fixtures/ci-seed.sql` (cite the table and the rows that produce the expected value), or
- The rendered form DOM, fetched live from the worktree stack: `curl --cookie "_auto_login=1" http://<slug>.localhost/ibl5/modules.php?name=X` (cite the element the assertion targets). The `_auto_login=1` cookie opts into dev auto-login — localhost is logged-out by default, so an auth-gated form returns the login page without it (see `.claude/rules/browser-login.md`).

Two gotchas this rule exists to catch (cross-referenced from memory):

- **Sort direction is not "ascending by default."** `ibl5/jslib/sorttable.js` sorts **descending** on first click. An assertion on first-click sort order must match that, not an assumed ascending order. See memory `reference_sorttable_descending_first`.
- **Seed cardinality is small.** The CI seed is a fixture, not production — counts, option lists, and "is the list non-empty" assertions must be grounded in what the seed actually contains. See memory `feedback_e2e_seed_grounding`.

## Forced manual-verification trigger (new or redesigned UI/UX)

When a plan introduces **new or redesigned user-visible UI/UX**, the matrix MUST include at least one **Truly-manual** row for the subjective look-and-feel + flow check — *in addition to* (never instead of) any E2E and Visual-regression rows. E2E asserts that elements are present and behave; Visual-regression pins pixels against an **existing** baseline. Neither can judge whether a *newly introduced* design looks right or a *new* multi-step flow feels right — that is the gap PR #1067 shipped through (a new notification bell, unread badge, CSS component, and mark-read flow, all classified E2E/visual with zero manual rows, then auto-merged).

A plan trips this trigger when it adds or restyles any of:

| Trigger | Example surface |
|---------|----------------|
| New or restyled CSS component / stylesheet | a file under `ibl5/design/`, a new `*.css`, a new component class |
| New rendered page or module | a new `ibl5/modules/*/index.php` route a user navigates to |
| New nav/menu entry, indicator, or badge | the nav bell + unread badge from #1067 |
| New multi-step or stateful user flow | mark-read / mark-all-read, a what-if sandbox, a wizard |

**Does NOT trip** (keep nightly autonomy for safe mechanical work): a non-visual refactor, a one-line CSS bugfix with no design change, a JSON/POST endpoint with no visual surface, or any change where **nothing the user sees is new or redesigned**. An *unchanged* UI is covered by Visual-regression alone — see the taxonomy's "If nothing in UI/UX changed" rule; this trigger fires only on genuinely new or redesigned surfaces.

### Phrasing the forced row (gate-3 safe)

The Truly-manual row is a **subjective** judgment, so phrase it as a question of taste — never with an automatable verb (`verify` / `check that` / `confirm` / `ensure`), which `bin/check-plan` gate 3 rejects as a mislabeled-automatable row. Copy this shape:

```
| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| N | Does the new notification bell + inbox look right, and does the mark-read flow feel right? | Truly-manual | post-impl | manual (reviewer walkthrough) |
```

A plan that trips this trigger therefore **cannot** carry the "All verification is automated — no manual testing needed" line, and per `/plan` Step 4 gate 14 must set `auto_merge: false`.

## Hot-file thresholds

Files over **500 LOC** in `classes/` are considered hot. The current list is generated by `bin/check-hot-files`.

When a plan adds **> 100 LOC** to a hot file, the plan must EITHER:

- Propose an extraction (Service/Repository/Helper) in the implementation steps, OR
- Justify the addition inline: state why extraction is premature (single-purpose
  growth, no natural seam, etc.).

If the plan proposes no extraction and no justification, `bin/check-hot-files`
flags the PR (advisory comment, non-blocking).

This rule is advisory — gated growth is acceptable when justified. The goal is
to force a structural conversation at plan-write time, not to enforce a hard
ceiling.

## Decision-trigger pre-classification

`bin/adr-check` fires on PRs that add files matching specific trigger patterns. When a plan phase adds any of these, the plan must pre-decide the resolution:

| Trigger | File pattern |
|---------|-------------|
| PHPStan rule | `ibl5/phpstan-rules/*.php` |
| Agent rule | `.claude/rules/*.md` |
| CI workflow | `.github/workflows/*.yml` |
| Destructive migration | Migration SQL containing `DROP TABLE`, `DROP COLUMN`, or `DROP INDEX` |
| Tool script | `bin/*` (new file, ≥50 lines) |
| New dependency | New entry in `ibl5/composer.json` `require` or `require-dev` |

**Resolution — exactly one of:**

- **ADR:** Add an implementation step to write an ADR under `ibl5/docs/decisions/`. Use when the change introduces a genuinely new architectural constraint not covered by an existing ADR.
- **Bypass:** Add an implementation step to include `<!-- no-adr: reason at least 15 characters -->` in the PR body. Use when the decision is already captured in an existing ADR (e.g., new PHPStan rules enforcing ADR-0001's architecture split).

If the plan has no phases adding trigger-pattern files, no action is needed.

## What the plan must NOT do

- List "verify manually" or "check by hand" for any item that can be asserted by PHPUnit, an API test, E2E, or visual-regression.
- Defer test classification to post-plan Phase 6. Phase 6 is a safety net, not the primary classification point.
- Add a standalone "Testing" or "Verification" section with prose descriptions instead of the matrix.
- Use "run X and check Y" without specifying the test type and file path.
