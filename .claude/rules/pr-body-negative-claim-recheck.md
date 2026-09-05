---
description: Re-check every "What is NOT in this PR" negative claim after a remediation commit lands a plan deliverable — always-loaded; governs PR body authorship.
last_verified: 2026-09-05
---

# PR Body Negative-Claim Re-check

## Rule

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

## What triggered this rule

dev-efficiency backlog finding E46, from Phase 6 review of PR #2077. That PR's body carried a
"What is NOT in this PR" residual entry claiming the conversion "is not yet self-enforcing".
The scoped enforcement test `ibl5/tests/Http/ControllerSuperglobalFreedomTest.php` had already
landed in the same PR's remediation commit, so the enforcement the bullet said was absent was
sitting in the diff being described.

No existing gate checks a negative scope claim against the actual diff. `/pr-ready` Phase 6
catches this class only when it fires on that specific check; this rule is the cheap
always-on reminder that sits upstream of it.

## Application

| What just happened | What to do with the negative-claim list |
|---|---|
| Remediation commit landed a plan deliverable | Re-read every bullet; delete each one the deliverable satisfies |
| Any commit pushed to an open PR | Re-read the list against the new diff before considering the push done |
| Body written and no commit since | Nothing to do — the list still describes the diff it was written against |
| A bullet is now only *partly* true | Rewrite it to name the residual precisely; never leave the original wording |

## Calibration

This rule governs the **claim**, not the scope. Deliberately leaving work out of a PR is
fine and normal — say so accurately. The defect is a stale absence assertion, not a real one.

Applies to any residual / out-of-scope / follow-up list under any heading wording, not only
the literal string "What is NOT in this PR".

**Headless:** applies — an automouse or `/post-plan` run authoring a PR body performs the same
re-read; there is no human in the loop to catch the stale bullet later.
