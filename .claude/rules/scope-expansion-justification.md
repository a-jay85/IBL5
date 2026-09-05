---
description: A PR whose diff touches production module code must justify that scope expansion in the PR body — lazy-loaded on rule-doc edits; governs PR authorship.
last_verified: 2026-09-04
paths: ".claude/rules/*.md"
---

# Scope Expansion Justification

## Rule

When a PR's diff touches production module code under the `ibl5/modules/` tree, the PR
body's `## Scope` prose must contain a sentence naming **why** each such file was touched.

The machine-generated files-changed block records **what** changed; the `## Scope` prose is
the only place **why** can live. A module file listed with no matching justification sentence
is the defect this rule names.

The justification is per-file-or-per-group, not a blanket "also touched some modules" —
name the file or the logical group and state the specific reason.

Without an explicit justification, a reviewer cannot tell a deliberate scope expansion from
an accidental one — and the audit trail after merge carries that same silence.

## What triggered this rule

PR #1807 (2026-09-03) carried production PHP changes — null guards in
`ibl5/modules/Player/index.php` and a free-agent fallback in
`ibl5/modules/LeagueStarters/index.php` — into a test-scoped PR with no plan phase, no
structured code review, and no scope justification. Reviewers had no signal distinguishing
deliberate from incidental; six PR-body scope claims were contradicted by the actual diff.
Maintenance-backlog item 15.29 tracks this finding.

## Application

| Diff touches | PR body `## Scope` must |
|---|---|
| No `ibl5/modules/` files | Nothing extra — the files-changed block is sufficient |
| `ibl5/modules/` files, and the PR title is `feat:` | Name why each module file changed (the feature already declares user-facing intent, so one sentence per group is enough) |
| `ibl5/modules/` files, and the PR title is **not** `feat:` | Name why each module file changed **and** state explicitly that the production change is intended, not incidental — this is the undeclared-expansion shape |

## Calibration

This rule governs the **claim in the PR body**, not whether the module change is correct —
that is `/pr-review`'s job. A deliberate, justified production change in a `chore:` PR is
fine; an unjustified one is the violation.

The `/post-plan` Phase 2 check added in Phase 3 is a **prompt**, not a blocker — it surfaces
the requirement without failing the run. Enforcement is the reviewer's gate: a PR body
without justification for a visible `ibl5/modules/` diff is a request-for-change.
