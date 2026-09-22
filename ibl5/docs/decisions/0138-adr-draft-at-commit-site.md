---
description: The ADR decision-trigger gate and the harness auto-draft both move to the commit site, ahead of the Phase 2 commit.
last_verified: 2026-09-21
---

# ADR-0138: ADR Auto-Draft Fires at the Phase 2 Commit Site

**Status:** Accepted
**Date:** 2026-09-21
**Deciders:** A-Jay

## Context

`bin/adr-check` only ever ran at push time and in CI, so a branch could commit a decision-trigger surface and discover the missing ADR one or more commits later, with the trigger already buried in history. ADR-0135 placed the auto-drafter at the push sites, and its Decision states that the wrapper sits outside `_push_with_lease_retry` and runs at the Phase 2 and Phase 5.5 push sites. Moving the gate earlier makes the push-site-only half of that sentence wrong, which is what this record corrects. Inheriting the draft from the new hook was never available: `_commit_with_gate_remediation` re-raises gate class `"adr"` fail-closed and can remediate only doc staleness.

## Decision

Three parts. First, `bin/adr-check` gains a `--commit` mode whose range is `git diff --cached <merge-base HEAD origin/master>`, the committed range union the index, so an ADR committed earlier on the branch satisfies a trigger staged now. `--staged` and `--pr` are unchanged. Second, `bin/pre-commit-hook` gains a hard gate running that mode, degrading open when `origin/master` is unresolvable and printing the `pre-commit-adr-gate:` marker on denial. Third, the harness auto-draft moves to an explicit `_commit_with_adr_draft` step in `tools/postplan-harness/runner.py` that runs before `commit_all()`. It drafts with `check_mode="commit"` and `commit=False`, so the ADR is staged and lands in the Phase 2 commit rather than a follow-on one. `_push_with_adr_draft` stays at the Phase 5.5 push site as a backstop for a trigger that first appears after the Phase 2 commit, and the `res.adr_drafted` guard keeps the run at exactly one bounded `claude-opus-5` call. Every other ADR-0135 invariant is preserved: Read, Grep, Glob and Write with no Bash; the structural validator; the numbering-collision renumber; `result.json` recording `adr_drafted`, `adr_path` and `adr_draft_model`; and a drafter failure re-raising the original denial so the run exits 3.

## Alternatives Considered

- **Scope the hook with `--staged` alone.** Judge only the index. Rejected because it refuses the create-the-ADR-first workflow `ibl5/docs/decisions/README.md` documents: the ADR sits in an earlier commit and never appears in the index.
- **Inherit the draft inside `_commit_with_gate_remediation`.** Reuse the existing remediation wrapper. Rejected because it classifies `"adr"` as fail-closed by design, and widening it would put a bounded model call inside a generic remediation path.
- **A `commit-msg` hook.** Restore the bypass affordance at commit time. Rejected because `bin/install-git-hooks` installs no `commit-msg` hook, and `git commit --no-verify` is already the documented escape for the gates already in that file.
- **Delete the push-site drafter.** Keep a single firing point. Rejected because it is the only cover for a trigger introduced by Phase 5.5 remediation, after the Phase 2 commit.

## Consequences

- Positive: a missing ADR is caught on the commit that introduces the trigger, not several commits later, and the drafted ADR ships inside the same commit as the work it describes.
- Positive: the drafter's scope check now compares against a pre-draft `git status --porcelain` snapshot instead of treating every dirty path as a stray. At the commit site the whole shippable diff is dirty, and the old check would have reverted the run's own work.
- Negative: the gate runs on every commit whenever `origin/master` resolves, costing two `git diff --name-only` invocations.
- Negative: a trigger landed by an earlier `--no-verify` commit and still unsatisfied blocks every later commit until the ADR exists. That is the fail-closed direction and matches the pre-push hook.
- Negative: a bypass at commit time is unavailable, because the commit message does not exist yet. `--no-verify` is the escape.
- Negative: the baseline snapshot misses a drafter edit that leaves a file's two-character status code unchanged. The PR body's rendered file list still does not name the drafted ADR, unchanged from the push-site behavior.
- Neutral: `bin/pre-push-adr-hook`, `bin/run-meta-checks-local`'s advisory pre-push arm and `bin/check-plan`'s mirrored trigger regexes are all untouched.

## References

- `bin/adr-check` carries the `--commit` mode, `adrCommitBase()` and `gitDiffArgs()`.
- `bin/pre-commit-hook` carries the gate block and the `pre-commit-adr-gate:` marker.
- `tools/postplan-harness/runner.py` defines `_commit_with_adr_draft` beside the surviving `_push_with_adr_draft`.
- `tools/postplan-harness/harness/adr_draft.py` defines `commit_gate()`, `_adr_check_args()`, `_status_map()` and the baseline-aware `_stray_paths()`.
- `tools/postplan-harness/harness/adapters/gitad.py` maps the new marker to gate class `"adr"` in `_GATE_CLASSES`.
- `ibl5/docs/decisions/0135-harness-adr-draft-on-denial.md` records the push-site placement this record narrows.
- `bin/test-adr-check` and `tools/postplan-harness/tests/test_adr_draft_on_denial.py` hold the regression coverage.
