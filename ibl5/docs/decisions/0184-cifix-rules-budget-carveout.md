---
description: Lets the Phase 7 ci-fix fixer shrink a rules file the PR already changed, under git-enforced size and companion checks, when only the rules byte budget fails.
last_verified: 2026-10-08
---

# ADR-0184: Phase 7 ci-fix may shrink an in-diff rules file when only the byte budget fails

**Status:** Accepted
**Date:** 2026-10-08
**Deciders:** post-plan harness maintainers

## Context

The post-plan harness forbids every fixer from editing gate-owning paths: `.claude/rules/**`, `.github/workflows/**`, `bin/check-*` and `tools/postplan-harness/harness/armable.py`. `denied_gate_edits` in `tools/postplan-harness/harness/fidelity.py` is the enforcement: a fixer commit touching one of them stays local and ends the loop. On 2026-10-07 two PRs failed only `bin/check-rules-byte-budget`, because their own rules edits pushed a file over its cap. The Phase 7 ci-fix fixer read the log, named the fix, and refused to apply it because the file sat under the deny. Both attempts ended `no-change` and a human did the trim by hand (PR #2950 moved three sections into a path-scoped companion, PR #2943 trimmed prose in place). The remedy for this failure class only ever removes bytes from a rule or moves them into a lazy companion. It cannot loosen a gate, so the blanket deny costs a human round trip for no safety gain.

## Decision

Phase 7 ci-fix gains one mechanical carve-out, implemented in `tools/postplan-harness/harness/rules_budget_carveout.py` and consulted only at the `_ci_fix_loop` site in `tools/postplan-harness/runner.py`. The fixer may change a `.claude/rules/*.md` file when all of these hold:

- (a) the file is in the PR diff against `origin/master` on the pre-fix tree;
- (b) the triaged failing-check set is exactly `Static guards`, and `bin/check-rules-byte-budget` exits nonzero in the worktree before the fix;
- (c) every modified rules file ends with fewer bytes than before, and every added rules file is a `*-detail.md` whose frontmatter carries a `paths:` list.

The harness computes each condition with git (`diff --name-status --no-renames`, `cat-file -s`, `show`) and never from the model's reply. When every touched gate path passes, it reruns `bin/check-rules-byte-budget`, `bin/check-prose --since=origin/master` and `bin/check-docs --since=origin/master --no-staleness`. Any miss or failure discards the commit on the existing `error:gate-path-edit` path. Deleted rules files, renames, files outside the PR diff, growth of any size, a new file without `paths:`, and any co-edit under `bin/check-*`, `.github/workflows/**` or `armable.py` stay denied. The Phase 5.5 fidelity fixer, thread ingestion and prosefix keep the unqualified deny. No cap moves and `bin/check-rules-byte-budget` is untouched.

## Alternatives Considered

- **Parse the CI log.** Read the failing step from `Static-guards.log`. Rejected because: it couples the harness to log formatting, and the local script run is deterministic.
- **Put the carve-out inside `denied_gate_edits`.** One shared function, one exception. Rejected because: it has four callers and three must keep today's behavior.
- **Raise a cap.** Make the budget pass by loosening it. Rejected because: the script's own failure text forbids it.

## Consequences

- Positive: a PR red only on the rules byte budget heals itself in Phase 7.
- Positive: the deny's purpose survives. A fixer can only remove bytes from a rule it already changed, and the script that judges the budget stays out of reach.
- Negative: a fixer may cut rule text a reviewer wanted kept. The cut lands in the PR diff, where the Phase 5.5 review and the human reviewer see it, and a `feat:` PR still waits for signoff.
- Negative: each allowed attempt runs three local gates, which takes seconds.

## References

- `tools/postplan-harness/harness/rules_budget_carveout.py`
- `tools/postplan-harness/harness/fidelity.py`
- `tools/postplan-harness/runner.py`
- `tools/postplan-harness/tests/test_rules_budget_carveout.py`
- `bin/check-rules-byte-budget`
