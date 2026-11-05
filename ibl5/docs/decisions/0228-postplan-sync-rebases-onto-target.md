---
description: Post-plan's master sync rebases the branch's own commits onto the sync target with an explicit lease push, and fails closed.
last_verified: 2026-11-04
---

# ADR-0228: Post-plan sync rebases onto the target

**Status:** Accepted. Supersedes in part ADR-0064's fix advice for a branch behind `origin/master`. The ADR-0064 pre-push check itself stands.
**Date:** 2026-11-04
**Deciders:** ajaynicolas

## Context

The harness Phase 2 ran `git merge --no-edit <target>`. The pre-push hook needs `origin/master` as an ancestor of HEAD, and a merge does not replay a squash-merged parent. The owner's norm is rebase. On 2026-10-26 the harness pushed merge commit `e13c4d1` onto PR [#3281](https://github.com/a-jay85/IBL5/pull/3281), and the owner reset it to `f1e3991` by hand. bash-guard rule 19 already denies a hand-run local merge of master and prints the rebase-plus-lease recipe.

## Decision

Sync with `git rebase --onto <target> <upstream>`. `<upstream>` is the iblBase tip when that tip is in HEAD but not in the target. Otherwise it is `merge-base HEAD <target>`. On conflict, squash the branch's own commits and retry, so the resolver sees one stop with the branch's side in stage 3. Push with `--force-with-lease=refs/heads/<b>:<sha>`, with the SHA read from the tracking ref right before the push.

The sync fails closed. A conflict that is not resolved and proved aborts, restores the pre-sync SHA, pushes nothing and exits 3. A resolved conflict still holds under arming condition (14). `bin/postplan-fix --headless` uses the same single-stop rebase. The success line reads `REBASE=rebased onto <target>`. The clean line, `REBASE=clean (HEAD already contains origin/master)`, is unchanged.

Enforcement: `tools/postplan-harness/harness/adapters/gitad.py` (`rebase_onto`), `tools/postplan-harness/runner.py` (Phase 2), and the tests in `tools/postplan-harness/tests/test_master_sync_linear.py`.

## Alternatives Considered

- **Keep merge.** Rejected because it contradicts the owner's norm and leaves merge commits on PR branches.
- **Merge, then squash the merge into one commit.** Rejected because it relabels a merge as a rebase and drops the branch's commit list on every sync.
- **Resolve each replayed commit at its own stop.** Rejected because the lost-work proof and the conflict review are built for one stop, and a 10-commit branch could ask the LLM for 10 reviews.
- **`--rebase-merges`.** Rejected because it keeps exactly the merge commits this ADR removes.

## Consequences

- Positive: PR branches stay linear, and a stacked branch replays only its own commits.
- Positive: a clean sync keeps commit granularity. A conflicted one collapses to one commit, which the squash merge does anyway.
- Positive: GitHub update-branch merges (ADR-0081) are linearized at the next sync.
- Negative: every non-noop sync rewrites the PR branch. A later `git pull` (merge) of the old remote duplicates history. The runner's `remote-head-diverged` refusal and the lease are the guards.
- Negative: inline review comments on a rewritten commit go outdated, as they already do after any force push.

## Lineage

This ADR replaces ADR-0064's fix advice (`git fetch origin master && git merge origin/master`) for a behind branch. The hook's printed advice is now the rebase-plus-lease recipe. ADR-0081 is not superseded: the update-branch merge it records stands.

## References

- `ibl5/docs/decisions/0064-local-pre-push-adr-gate.md`
- `ibl5/docs/decisions/0081-scheduled-branch-update-for-armed-behind-prs.md`
- `tools/postplan-harness/harness/adapters/gitad.py`
- `tools/postplan-harness/runner.py`
- `.claude/rules/linear-history-squash-merge.md`
