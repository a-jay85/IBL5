---
description: PR body authoring rules — version/baseline citations must name their source file; negative-claim bullets must be re-read after every commit.
last_verified: 2026-09-16
---

# PR Body Claims

## Citation rule

When a PR body states a **version string** (`v2 → v3`, `@v3`), a **numeric baseline**
(`147 call sites`), or an **`X → Y` figure**, name the **authoritative source file**
inline — e.g. ``147 → 134 call sites (per `ibl5/phpstan-baseline.neon`)``.

Uncited claims silently stale. Trigger: L43 (2026-09-02, PR #2059).

| Claim type | Citation form |
|---|---|
| Version string | ``(per `.github/workflows/security.yml` (example) line N)`` |
| Numeric baseline | ``(per `ibl5/phpstan-baseline.neon`)`` |
| `X → Y` figure | ``(per `ibl5/phpstan-baseline.neon` — N sites)`` |
| ADR frozen section rewritten | See `.claude/rules/adr-append-only.md` |

Governs the **claim** (citation present), not whether the figure is correct.
Applies to human-authored and autonomous-loop PR bodies alike.
**Headless:** applies — automouse/`/post-plan` PR bodies must include inline citations.

## Negative-claim re-check rule

A "What is NOT in this PR" bullet is a claim about the **final** diff, not about the diff as
it stood when you wrote the body. Any commit that lands after the body is written can
falsify it silently.

**Before opening or updating a PR, and again after every commit pushed to an open PR, re-read
every bullet under `## What is NOT in this PR` (or any equivalent residual / out-of-scope /
follow-up list) and confirm the diff still does not contain it.**

The trigger is the **commit**, not PR-open. The failure mode is ordered: body written →
remediation commit lands a plan deliverable → body is now false, and nothing re-reads it.
A `/post-plan` remediation commit is the highest-risk moment, because remediation exists
precisely to close a gap the body may have already described as still open.

Delete or rewrite any bullet the diff has overtaken. Do not leave it standing with a
softening qualifier — a residual entry that is no longer residual misreports scope to
reviewers and poisons the post-merge audit trail.

Trigger: E46, PR #2077.

| What just happened | What to do with the negative-claim list |
|---|---|
| Remediation commit landed a plan deliverable | Re-read every bullet; delete each one the deliverable satisfies |
| Any commit pushed to an open PR | Re-read the list against the new diff before considering the push done |
| Body written and no commit since | Nothing to do — the list still describes the diff it was written against |
| A bullet is now only *partly* true | Rewrite it to name the residual precisely; never leave the original wording |

This rule governs the **claim**, not the scope. Deliberately leaving work out of a PR is
fine and normal — say so accurately. The defect is a stale absence assertion, not a real one.

Applies to any residual / out-of-scope / follow-up list under any heading wording, not only
the literal string "What is NOT in this PR".

**Headless:** applies — an automouse or `/post-plan` run authoring a PR body performs the same
re-read; there is no human in the loop to catch the stale bullet later.
