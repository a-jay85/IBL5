---
description: Lets the Phase 5.5 fidelity remediation fixer commit a bounded edit to a rules file that a blocking finding names and the PR already changes, with every condition checked by git and bin/check-* exit codes.
last_verified: 2026-10-19
---

# ADR-0204: Phase 5.5 remediation may commit a small fix to a rules file a blocking finding names

**Status:** Accepted
**Date:** 2026-10-19
**Deciders:** post-plan harness maintainers

## Context

Phase 5.5's plan-fidelity remediation hands a fixer the `NOT READY` verdict and lets the harness commit its worktree edits. The fixer prompt denies the gate-owning paths in `fidelity.GATE_OWNING_PREFIXES` (`.claude/rules/`, `.github/workflows/`, `bin/check-*`, `harness/armable.py`), and `denied_gate_edits` discards a commit that touches one. PR #2943's verdict 2 carried one `[BLOCKING]` finding, "restore the stuck-run sentence in `.claude/rules/visual-review-prs.md`". That file sits under the deny, so the fixer could only edit the PR body, the re-review stayed `NOT READY`, and a human made the one-line fix by hand. The deny exists so a fixer can never weaken an executable gate. A prose rule file that the reviewer names is a different case. ADR-0184 already carved one narrow exception out of the same deny for the Phase 7 ci-fix loop, with every condition computed from git and `bin/check-*` exit codes.

## Decision

`tools/postplan-harness/harness/fidelity_filefix.py` adds a second carve-out, consulted only at the deny site in `fidelity.remediate`. Before the fixer spawns, the harness snapshots the eligible set: the `.claude/rules/*.md` files that (a) an explicitly `[BLOCKING]`-marked bullet under the verdict's `## FINDINGS` heading names in a code span, by full path or by a basename unique among the PR's changed files, and (b) are already in the PR diff against `origin/master`. The prompt tells the fixer that set and the limits.

After the commit, before any push, the harness re-checks with git. Each gate-owning hit must be in the eligible set, be a modification (never an add, delete or rename), change at most 20 lines with at most 8 deletions, and keep its frontmatter identical apart from `last_verified`. A commit may hold at most 3 such files and 40 changed lines in total. Then `bin/check-rules-byte-budget`, `bin/check-prose --since=origin/master` and `bin/check-docs --since=origin/master --no-staleness` must pass. Any failure refuses the whole commit on the existing `gate-path-edit` path, and the commit stays local.

`.github/workflows/`, `bin/check-*`, `harness/armable.py` and every `LOCAL_GATE_PATH_PREFIXES` entry can never enter the eligible set. `denied_gate_edits` and `GATE_OWNING_PREFIXES` are unchanged for their other callers. The fixer's own statements never enter the verdict.

## Alternatives Considered

- **Put the exception inside `denied_gate_edits`.** Rejected because the function has four callers and three must keep the full deny. This is the same reason ADR-0184 gave.
- **Let the fixer edit any file a finding names.** Rejected because a workflow or `bin/check-*` named in a finding would become editable by the agent the gate exists to bound. The #2943 blocking bullet itself names `vr-pr-screens.yml`.
- **Additive-only edits.** Rejected because a finding can ask for a wrong sentence to be corrected. The deletion cap keeps the same bound with less friction.
- Leaving rules-file findings to a human was the status quo. It cost a wasted re-review round and a hand fix on #2943.

## Consequences

- Positive: a `[BLOCKING]` finding about a rules file the PR already edits can clear in the same run.
- Positive: the reviewer picks the file, git confirms the PR already changed it, the size and frontmatter rules keep the edit to a sentence, and the re-review on the new head judges the result.
- Negative: a finding that names no file, names a file outside `.claude/rules/*.md`, or names a rules file the PR did not touch stays body-only or waits for a human, as today.
- Negative: one more module sits next to the deny, so a later widening of its constants needs a test change and a new ADR.

The loop's push, re-review, arming and `human-signoff` behaviour is unchanged.

## References

- `tools/postplan-harness/harness/fidelity_filefix.py`
- `tools/postplan-harness/harness/fidelity.py`
- `tools/postplan-harness/tests/test_fidelity_filefix.py`
- `ibl5/docs/decisions/0184-cifix-rules-budget-carveout.md`
