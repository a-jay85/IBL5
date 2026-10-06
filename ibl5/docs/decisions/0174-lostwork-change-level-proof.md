---
description: The post-plan lost-work proof reads the post-rebase tree per branch line instead of comparing numstat rows, so master edits to the same file no longer block; ambiguity still blocks.
last_verified: 2026-10-06
---

# ADR-0174: Lost-work proof compares branch changes against the post-rebase tree

**Status:** Accepted
**Date:** 2026-10-06
**Deciders:** ajaynicolas

## Context

The Phase 2 and Phase 7 lost-work proof (`.claude/review-shared/scripts/lostwork.sh`, ADR-0134 item 3) compared the sorted `git apply --numstat` of the pre-rebase and post-rebase patches. Over 14 days (335 runs) the harness blocked 6 runs on "lost-work proof failed" and 15 on "rebase conflict, human required". Of 12 `TREE DIVERGED` audit logs, 5 were the empty-patch case (owned by the `postplan-merged-noop-hold-reason` work) and 7 were real numstat deltas. Every one of the 7 followed a plain-rebase conflict on the same file. The resolver rewrote hunks, master had edited the same file, and the branch's numstat row legitimately changed. In one run a file's row vanished because master had absorbed the change. In another the run gained a row.

Numstat equality was the wrong invariant. It measured the diff against a moving base. The question the gate exists to answer is whether the branch's changes survived the rebase.

Related: ADR-0134 (conflict auto-resolve and the conjunctive proof gate). This ADR changes the proof's mechanism and leaves the gate's shape alone, so it supersedes nothing.

## Decision

1. The proof reads the post-rebase tree at `HEAD`. For each file entry in the pre-rebase patch, the file must still exist at `HEAD`, or still be absent for a deletion. Every significant added line (one holding a letter or digit) must appear verbatim as a whole line in `HEAD:<path>`. Every significant deleted line that the same entry does not also add must appear fewer times at `HEAD` than at `origin/master`.
2. Absorption is free. A branch line master also landed is present in the tree, so the check passes even though the line left the diff. Gained work never blocks.
3. Ambiguity blocks. A line both sides edited, whose resolution produced a third version, is reported as `LOST:` and the run holds. The guards for a missing argument, an absent or empty patch, an unparseable patch, a git-quoted path, and zero file entries exit 1. An entry made only of insignificant lines (`}`, blank lines) is proved at the file level only.
4. The output contract is unchanged for consumers: `TREE-EQUIVALENT` with rc 0, or `TREE DIVERGED — inspect before pushing` with rc 0, behind the conjunctive stdout-and-rc gate from ADR-0134. New `LOST:` and `CHECKED:` lines come before the verdict.
5. The harness runs the script from the pinned master SHA, so the new proof takes effect for runs that start after this ADR's PR merges. The fixtures in `tools/postplan-harness/tests/test_lostwork_change_level.py` and `bin/test-postplan-arm-conditions` §5.9g pin the behavior table. `tools/postplan-harness/scripts/lostwork-backtest.sh` reconstructs real runs.

The parser also reads entries that carry no `---`/`+++` header lines: binary files, empty new files, mode-only changes, and pure renames. It takes their paths from the `rename from`/`rename to` (or `copy`) headers, or from a `diff --git a/P b/P` line whose two halves match. Any other header shape is unparseable and exits 1.

## Alternatives Considered

- **range-diff.** `git range-diff` compares two commit series. The pre side is a working-tree patch with no series behind it, and it would report every conflict-resolution rewrite as a changed commit. Rejected because it answers whether the commits changed, and the proof needs to know whether the changes survived.
- **patch-id.** `git patch-id --stable` per commit changes whenever any hunk's context changes, so all seven real runs would still block. Rejected for that reason and because one id per side loses the per-line diagnostics.
- **Three-way re-apply.** Re-applying the pre patch with `git apply --3way` onto `origin/master` redoes the rebase and hits the same conflicts. Rejected because it has nothing to compare exactly where the old proof failed.
- **Diff-to-diff lines.** Comparing pre `+` lines against the post diff's `+` lines fails the absorption case, where master landed the same lines and the post diff has no row. Rejected.
- **Resolved-hunk exemption.** Skipping lines inside resolved hunks exempts the exact files where all seven failures occurred, which makes the proof vacuous there. Rejected.

## Consequences

### Backtest

`tools/postplan-harness/scripts/lostwork-backtest.sh` rebuilt the seven numstat-blocked runs from their saved `/tmp` patches on 2026-10-06, with the new proof in place:

```text
KEY | master@run | verdict | lost-lines | PR | proof-at-merge
ci-shell-harness-parallel | - | UNRECONSTRUCTIBLE | - | - | -
css-legacy-markup-styles | 412b19c1a87f65d0a582a64a4800d19114ae072f | BLOCK | 4 | #2515 OPEN | -
google-sheet-oauth-export | - | UNRECONSTRUCTIBLE | - | - | -
jsb-constants-rename | 693442a783855a990e25a5447976859f657df9aa | PASS | 0 | #2796 OPEN | -
leaderboards-hub | - | UNRECONSTRUCTIBLE | - | - | -
phase-rank-order-sync-782 | 2df9ba6700782a57fdfb8d8f91885df19d903119 | BLOCK | 4 | #2817 MERGED | BLOCK
player-module-renames-181-182 | a7e5be387c89648a26d830a25456938bde5cbea7 | BLOCK | 6 | #2775 MERGED | BLOCK
SUMMARY: pass=1 block=3 unreconstructible=3 of 7
```

Per key:

- **jsb-constants-rename.** Flips to pass. The PR is still open, so there is no merge cross-check.
- **css-legacy-markup-styles.** Real catch. All four `LOST:` lines are the branch's `Go Back` link in `ibl5/modules/Player/index.php` with the `mt-2 inline-block` classes. Master had already moved that `echo` into `PlayerActionController.php` with a different class. The resolution kept master's version, so the branch's class change landed nowhere.
- **phase-rank-order-sync-782.** Real catch. The four `LOST:` lines are comment lines and a `mkdir` line the branch added to `ibl5/tests/Cli/CheckDocsCliTest.php`. They are absent from master at run time and from the merge commit, so master never absorbed them. `proof-at-merge=BLOCK` names the same four lines. The record does not show whether a human dropped them on purpose later.
- **player-module-renames-181-182.** Mixed. One `LOST:` line is the `SeasonRosterChangesRepository` class declaration, which both sides edited (master moved the base class to `\Database\BaseMysqliRepository`). That is the accepted fail-closed cost. The other five are false positives in `ibl5/tests/e2e/flows/player-database.spec.ts`. The lines are in the merge-commit tree, but that blob uses CRLF endings and the branch's lines are LF. Exact matching reads the CR-only difference as lost.
- **ci-shell-harness-parallel.** Unreconstructible. No pre or post patch was saved for it.
- **google-sheet-oauth-export** and **leaderboards-hub.** Unreconstructible. Their saved post patches carry PNG snapshot entries that `git apply` rejects without a full index line. A diagnostic retry that excluded `*.png` gave `TREE-EQUIVALENT` for leaderboards-hub (the run that gained a row). It gave a block for google-sheet-oauth-export, with `LOST:` lines in `.claude/rules/codebase-map.md` and `docker-compose.ci.yml`. That block is unconfirmed, because the timestamp-derived master may sit a minute before the real one.

The backtest supports the change. Every numstat block in the corpus was a false positive by construction. Under the new proof, the blocks that remain name specific lines, and two of the three blocked runs carry a line the branch really lost.

### Accepted residuals

- A line both sides edited holds the run for a human, as condition (14) already does for every auto-resolved conflict.
- A rename's deletion count uses the old path at `origin/master`. A path master lacks counts as zero, which pushes toward a block.
- Whitespace-only or re-indented resolutions block, because matching is exact. That includes a line-ending change: a branch's LF line in a file master stores as CRLF reads as lost, as five player-module-renames-181-182 lines did.
- An added line that already appears elsewhere in the file proves present even if the branch's own copy was dropped. The added-line check tests presence and does not count copies.
- `git range-diff` and `patch-id` stay rejected for the reasons above.

## References

- `.claude/review-shared/scripts/lostwork.sh`: the proof.
- `tools/postplan-harness/harness/adapters/gitad.py`: `_load_lostwork`, `_prove_tree_equivalent`, `prove_lostwork` consume the unchanged contract.
- `.claude/skills/post-plan/_phase-2-conflict-resolution.md`: Step 6 describes the proof.
- `tools/postplan-harness/tests/test_lostwork_change_level.py` and `bin/test-postplan-arm-conditions` §5.9g: the behavior table.
- `tools/postplan-harness/scripts/lostwork-backtest.sh`: reconstructs the seven real runs.
- `ibl5/docs/decisions/0134-harness-conflict-autoresolve.md`: the conjunctive gate this proof sits behind.
