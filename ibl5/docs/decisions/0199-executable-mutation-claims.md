---
description: Verification Matrix mutation claims become executable through a keyed ## Executable Mutations section; one bin/lib script owns the grammar, Phase 5.0 applies each edit, requires the check to fail, reverts, and arming condition (19) holds on any claim that did not trip.
last_verified: 2026-11-12
---

# ADR-0199: Executable mutation claims

**Status:** Accepted
**Date:** 2026-10-15
**Deciders:** a-jay85

## Context

Plans carry 5,545 prose `Mutation:` clauses across 313 of 1,703 plans, and no machine has ever run one. PR #3264 shipped matrix row 20, which claimed that changing the after-merge-checklist checkout `ref:` makes `bin/check-workflow-checkout` fail. That script never reads `ref:`, so the claim was false and nothing caught it until a human added a case to `bin/test-after-merge-checklist`. A prose rule in the architect contract asks for the mutation statement, and a rule alone cannot prove the statement is true.

## Decision

A plan opts in by carrying a `## Executable Mutations` heading. Each entry is a fenced block with the info string `mutation` and the keys `row:`, `file:`, `match:`, `replace:` and an optional `check:`. `row:` must name a post-impl CLI-executable matrix row. The default check is that row's location cell after the gate-V backtick strip. `match:` is a single-line literal that must occur exactly once in `file:`. `replace:` is literal too, and empty means deletion. No plan-authored shell ever edits the tree.

`bin/lib/plan-exec-mutations` is the one owner of the grammar, in the gate-K shared-lib pattern. `bin/check-plan` gate `[XM]`, the skill-path Phase 5.0 block and the harness adapter `tools/postplan-harness/harness/mutations.py` all shell out to it. The harness keeps no Python copy, so no parity test is needed.

For each entry, `run` executes the check on the untouched tree (a non-zero baseline is `BASELINE-FAILED`), journals the original, applies the edit, runs the check again and reverts. Under mutation, exit 0 is `HELD`, exit 124 is `UNDECIDED` and any other non-zero exit is `TRIPPED`. The revert is verified by the target's sha256 and a byte-equal `git status --porcelain=v1 -z` snapshot, which also catches untracked files a check created. A revert that cannot be verified is `REVERT-FAILED` (exit 3), and the journal entry stays for `recover`.

The journal lives under `$(git rev-parse --absolute-git-dir)/exec-mutations/`. That path is per-worktree and outside the porcelain view. While any entry exists, `stage_all` and `commit_all` raise `MUTATION_JOURNAL_PENDING` and the skill path prints `STOP`, so a mutated tree is never committed.

Arming condition (19) holds when the step did not run, when any entry is not `TRIPPED`, or when the tree is suspect. It is additive and run-local, with no `bin/lib/pr-armable.sh` twin. It never releases another condition.

## Alternatives Considered

- **A sixth matrix column.** Rejected because gate N's pipe count, `matrix_split` and the skill's `IFS='|' read` all read columns by position.
- **A tagged clause in the What cell.** Rejected because `bin/lib/plan-matrix-assertions` tokenizes backticked words there and would demand a diff footprint for them. Literals holding `|` or backticks would also need escaping no gate understands.
- **Frontmatter or date opt-in.** A frontmatter key or a date cutoff was rejected because the orchestrator owns the frontmatter and plans carry no reliable authoring date. Heading presence is mechanical, and zero legacy plans carry it.
- **`git stash` or `git checkout --` as the revert.** Rejected because the stash stack is shared across worktrees and `checkout` cannot undo an untracked file a check made.
- **Raising the `_architect-contract.md` size cap.** Rejected. The new bullet fits by moving example lists to the uncapped detail file.

## Consequences

- Positive: a false mutation claim holds the merge and is named in the PR body, before a human reads it.
- Positive: legacy plans and prose clauses stay silent, so the 1,703 existing plans need no edit.
- Negative: every entry runs its check twice, bounded by the 30 s per-check cap.
- Negative: per ADR-0092, `bin/post-plan-now` pins the main-checkout harness, so the harness step is inert until this merges.

## References

- `bin/lib/plan-exec-mutations`: the grammar owner and executor.
- `bin/test-plan-exec-mutations`: parse, run, recover and signal cases, plus the row-20 end-to-end case.
- `bin/check-plan`: gate `[XM]`.
- `tools/postplan-harness/harness/armable.py`: condition (19).
- `tools/postplan-harness/harness/mutations.py`: the harness-side executor (the skill-path block retired with the skill fallback shrink).

## Addendum — adaptive per-check bound (2026-11-02) <!-- slop-ok -->

The 30 s per-check cap above held three open PRs on exit 124 (#3667, #3633, #3673) whose
rows ran a whole suite: 47 s, 142 s and 191 s measured baselines. The cap is now a floor.
`bin/lib/plan-exec-mutations run` takes `--max-timeout` (baseline bound and ceiling),
`--scale K` (mutated bound = K times the measured baseline, clamped to the floor and
ceiling) and `--budget` (total wall clock; later rows print UNDECIDED). The harness passes
30 / 300 / 2 / 1800 and bounds the whole run at budget + 2 x ceiling + 60 s. Baseline rc and
seconds are memoized per check command. A timeout still never counts as TRIPPED.
The `_architect-contract.md` size cap moved from 31232 B to 31744 B for the sentence that
tells architects to narrow each check to one test file or a `-k` filter.

## Amendment (2026-11-12): post-plan reuses pre-fire's recorded pass

Pre-fire (`bin/lib/prefire-verify`) already runs this check at the fire point. When every
row trips, it now also writes a reuse record into its state directory under the worktree's
git dir. The file is `exec-mutations.rows-<id>.reuse`, where `<id>` is the git blob id of
the `plan-exec-mutations parse` output. It holds pre-fire's working-tree hash and the
per-row result lines.

Post-plan's exec-mutations step reads that record before it runs anything. It skips its
own run only when all of these hold: the record matches the current rows; every row's
mutation target and every repo path its check command names is tracked and unchanged
between pre-fire's tree and the current tree; and the executor journal is empty. The
PR-body block and arming condition 19 then judge the recorded per-row results, so a
recorded non-TRIPPED row still holds. In every other case post-plan runs the check as
before. The audit log names which path ran. The fix round's proof run always runs fresh.

The dependency set is the paths the command names. A rebase that changes a file the
check reaches only indirectly, such as a class the named test imports, does not force a
fresh run. CI still runs the test suites on every push.

**Superseded in part by:** ADR-0244. CI's required Plan checks job now runs the non-DB matrix rows and the executable mutations; the local gate keeps only the DB rows.

## Addendum: reuse record removed (2026-11-12)

The 2026-11-12 amendment above added a reuse record. Pre-fire wrote it, and post-plan read it through `reuse_prefire_pass` in `tools/postplan-harness/harness/mutations.py`. After ADR-0244, post-plan no longer runs the mutation rows, so nothing called `reuse_prefire_pass`. This change deletes that function and its helpers. It also deletes `write_xm_sidecar` from `bin/lib/prefire-verify`, so pre-fire no longer writes `exec-mutations.rows-<id>.reuse` files.
