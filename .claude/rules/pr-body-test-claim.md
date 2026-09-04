---
description: PR body ## Manual Testing must match the plan's Verification Matrix — lazy-loaded on rule doc edits; governs PR authorship.
last_verified: 2026-09-04
paths: ".claude/rules/*.md"
---

# PR Body Test Claim

## Rule

The `## Manual Testing` section of every PR body must accurately reflect what the plan's
Verification Matrix says actually ran.

**When the Verification Matrix has executable rows** (PHPUnit, API-test, E2E, CLI-executable):
describe what was exercised and what the outcome was. Do NOT invent steps that were not run.

**When the Verification Matrix has zero executable rows** (docs-only, config-only, or
purely static changes): write exactly this instead of a testing narrative:

> No manual testing needed — verification is static; the plan's Verification Matrix has no executable rows.

Do NOT write "I clicked through the UI", "I checked the page loaded", or any other
first-person testing claim for a change that has no corresponding Matrix row. A fabricated
claim in the PR body obscures the real coverage level from reviewers and from the post-plan
audit trail.

## What triggered this rule

ci-backlog finding 9.2 (resolved in PR #2044): a docs-only PR body described manual
testing steps that were never performed, giving reviewers false assurance about coverage
level. The Verification Matrix for that PR contained no executable rows.

## Application

| Plan Verification Matrix | PR body `## Manual Testing` |
|---|---|
| Has PHPUnit / API / E2E / CLI rows | Describe what ran and the outcome |
| Zero executable rows (all static) | "No manual testing needed — verification is static; the plan's Verification Matrix has no executable rows." |
| Mix of static + executable | Describe only the executable rows that ran |

## Calibration

This rule governs the **claim** in the PR body, not whether tests exist. If the plan
deliberately carries zero executable rows (e.g. a docs-only change where all verification
is `bin/check-docs` gate output), that is valid — just state it honestly.
