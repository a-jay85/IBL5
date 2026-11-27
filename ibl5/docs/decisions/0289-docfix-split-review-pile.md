---
description: ADR-0289. bin/docfix-split splits each nightly docfix commit into an auto-merge docs-stale-refresh- PR and a held docs-content-review- PR, file by file, so one held file no longer blocks the mergeable docs.
last_verified: 2026-11-27
---

# ADR-0289: Split the nightly docfix run into an auto-merge PR and a held review PR

**Status:** Accepted
**Amends:** the one-PR-per-night flow of ADR-0086 and the veronly arming of ADR-0104. The rest of both stands.
**Date:** 2026-11-27
**Deciders:** repo owner

## Context

ADR-0086 runs the nightly stale-docs remediation from the Mac poller, and `bin/docfix-run` opens one PR per night on `docs-stale-refresh-YYYYMMDD`. ADR-0104 lets a verification-only refresh arm auto-merge through `bin/docfix-check-veronly`, and the seeded plan reads `auto_merge: docs-only-ready` so condition (7) can arm a docs-only refresh.

Condition (7) judges the whole PR. PR #4257 changed ten files. Nine were docs that condition (7) accepts. One was a body edit to `.claude/rules/headless-marker-contract-detail.md`, which its path clause holds. That one file held the whole PR, so the nine mergeable docs waited for the owner too.

## Decision

The headless Claude run in `bin/docfix-run` stops at commit. The generated runner then calls `bin/docfix-split` from the main checkout, only when `claude` exited 0.

- **Split rule.** A changed file goes to the auto pile when `pr_docs_refresh_path_ok` or `pr_docs_refresh_rules_date_only` in `bin/lib/pr-armable.sh` accepts it. Every other file goes to the review pile. A rename stays auto only when both paths pass. A copy, type change or unknown status goes to review. A date-only bump moves to review when its `paths:` frontmatter names a held file or it shares a `-detail` family with one.
- **Branches.** The auto pile stays on `docs-stale-refresh-YYYYMMDD` with the seeded `auto_merge: docs-only-ready` plan. The review pile goes on `docs-content-review-YYYYMMDD` with a plan reading `auto_merge: false` and no `hold_trigger`, so it waits for the owner. `DOCFIX_REVIEW_BRANCH_PREFIX` in `bin/lib/docfix-dm.sh` owns the prefix. The condition (7) branch clause rejects it, which is the guard this design relies on.
- **Commits.** With an empty review pile, no git history changes. Otherwise the split moves the review paths by patch onto a fresh worktree off master and recommits the auto branch without them. An empty pile opens no PR.
- **Issue marker.** Each seeded plan carries one `<!-- tracking-issue: closes #N -->` or `<!-- tracking-issue: refs #N -->` line. `tools/postplan-harness/runner.py` writes `Closes #N` or `Refs #N` into the PR body from it. The review PR closes the issue when one exists. Otherwise the auto PR closes it. The auto PR says `Refs` when a review PR exists from this run or is still open from an earlier one.
- **veronly.** `bin/docfix-split` runs `bin/docfix-check-veronly` only when the review pile is empty. On a mixed night the auto plan stays `docs-only-ready`, so the merge-danger and READY clauses still apply. veronly refuses any slug outside `docs-stale-refresh-`.
- **Skip guard.** An open review PR no longer blocks the next run. `bin/docfix-run` reads the files of each open review PR, old and new paths, and drops them from the next night's worklist. It skips the run when every worklist file is held, or when either `gh` call fails.
- **Fail closed.** The split asserts both plans and rechecks every auto file before any fire, and exits 3 with zero fires on any miss. An abort before the first fire restores the auto branch and removes the review worktree and plan.

## Alternatives Considered

- **A prompt-driven split.** Rejected because the split decides what merges unattended, and a test can check a script.
- **A third copy of the path predicate.** Rejected because two copies already exist and `tools/postplan-harness/tests/test_armable_docs_refresh.py` pins them to each other.
- **Carry `Closes #N` in Claude's commit body.** Rejected because merged docfix PRs #4221, #4182 and #4144 had it only in branch commits. The squash commit takes the PR body, so it never reached master.
- **Retire veronly.** Rejected because it checks untracked files and strictly advancing dates, which condition (7) lacks.

## Consequences

- Positive: a mixed night merges its allowlisted docs and holds only the content edits.
- Positive: an open held review PR no longer stops the nightly run.
- Negative: date-only bumps of root `CLAUDE.md`, root `AGENTS.md` and `.claude/skills/**` now go to the held review PR. veronly armed them before on an all-date-only night.
- Negative: an auto date bump can still depend on a held edit through a glob or a body mention. The split catches only literal `paths:` entries and `-detail` families.
- Negative: a failed patch apply, for example when master changed a held file, aborts the split. `bin/docfix-poll` then sees the same unfired branch it handles today.

## References

- `bin/docfix-split`
- `bin/docfix-run`
- `bin/docfix-check-veronly`
- `bin/lib/docfix-dm.sh`
- `tools/postplan-harness/runner.py`
- `ibl5/docs/decisions/0086-runnerless-mac-poll-for-stale-docs-remediation.md`
- `ibl5/docs/decisions/0104-verification-only-doc-refresh-auto-merge.md`
