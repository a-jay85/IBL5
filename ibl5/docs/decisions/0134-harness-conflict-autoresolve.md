---
description: The harness attempts auto-resolution of ordinary rebase conflicts behind a class gate, a bounded per-file resolver, and a conjunctive TREE-EQUIVALENT proof; condition (14) holds auto-merge until a reviewer issues a CONFLICT-REVIEW=CLEAN verdict.
last_verified: 2026-10-10
---

# ADR-0134: Harness conflict auto-resolution behind a proof gate

**Status:** Accepted
**Date:** 2026-09-18

## Context

Before this change, any rebase conflict inside the harness raises `HarnessError("rebase-conflict", ...)`. `exit_code_for` in `tools/postplan-harness/runner.py` maps that to rc=3, and `should_fallback` in `bin/post-plan-now` treats rc=3 as terminal. No skill fallback fires, and a human resolves by hand.

The wall is sound, but expensive. The most common trigger is the squash trap described in `.claude/rules/linear-history-squash-merge.md`: when a stacked branch is rebased, the parent's now-squashed commits replay as conflicts even though no content was changed. That case is mechanically resolvable. Routing it to a human every time wastes the equivalent of a manual merge review on a change that is structurally equivalent to the original.

## Decision

Before concluding rc=3, the harness attempts resolution through three sequential gates in `tools/postplan-harness/harness/adapters/gitad.py` and `tools/postplan-harness/harness/conflict.py`:

1. **Class gate.** Files under `ibl5/migrations/` ending `.sql`, lockfiles (`composer.lock`, `package-lock.json`, any `.lock`), and any path not presenting all three merge stages exit 3 immediately. The three-stage predicate covers delete-versus-modify and add/add conflicts. No model call fires for these classes.

2. **Bounded per-file resolver.** Each remaining file gets at most `MAX_RESOLVE_ROUNDS` (3) calls to a read/write-only `call_tooled` with `allowed_tools=("Read","Write")` and `denied_tools=("Bash","Agent")`. If any file ends a round without removing all conflict markers, the run aborts, restores the pre-rebase HEAD, and exits 3.

3. **Conjunctive TREE-EQUIVALENT proof.** After `git rebase --continue`, the harness runs `lostwork.sh` from the pinned master SHA. Both conditions must hold: the output must contain the literal `TREE-EQUIVALENT` and the process must exit 0. A diverged tree prints `TREE DIVERGED` with rc=0, which a non-conjunctive gate would admit; this gate rejects it. Failure aborts, restores, and exits 3.

When all three gates pass, `_record_resolution` in `tools/postplan-harness/harness/adapters/gitad.py` writes the condition-(14) flag at `conflict_flag_path(branch)` in `tools/postplan-harness/harness/armable.py` and then calls the read-only reviewer.

## Conflict classes

Always exit 3:
- Files under `ibl5/migrations/` ending `.sql`
- Lockfiles: `composer.lock`, `package-lock.json`, any file whose name ends `.lock`
- Any path whose conflict does not present all three merge stages (stages 1, 2, and 3 all present)

Auto-resolvable: ordinary three-way text conflicts where all three stages are present and no class rule matches.

## Flag lifecycle

The condition-(14) flag is written only when at least one file was model-resolved (i.e. `resolved_files` is non-empty). The mechanical `--onto` replay that produces an equivalent tree without any model edits does not write the flag. When the flag is written, it is written before the reviewer runs, so auto-resolution alone can never arm. The flag is never deleted: a prior `/post-plan` skill run's hold survives into future runs on the same branch.

The one clearing path: a verdict file whose first line is exactly `CONFLICT-REVIEW=CLEAN`, keyed to the post-resolution SHA via a sidecar file. An absent, malformed, or `FOUND-PROBLEM` verdict leaves condition (14) held. The verdict file is SHA-keyed and stale on any subsequent commit; the sidecar (not SHA-keyed) is purged at rebase-attempt entry so a stale CLEAN verdict from a prior run cannot clear a fresh hold.

## What is deliberately not weakened

- rc=3 still suppresses the skill fallback. Auto-resolution ran inside the harness, so escalating to a skill session would re-attempt something the harness just declined to certify.
- The proof gate stays conjunctive. A diverged tree exits 0 while printing `TREE DIVERGED`; accepting rc=0 alone would admit it.
- The resolver writes only to files in the conflict set. It cannot push, cannot touch unrelated files, and cannot spawn subagents.
- Auto-resolution alone never arms auto-merge. The flag holds condition (14) until the read-only reviewer issues a CLEAN verdict.

## Residual risk and backstop

A CLEAN verdict is a model judgment. The backstop is three properties: the reviewer is read-only and cannot write its own verdict file; the harness parses one exact literal (`CONFLICT-REVIEW=CLEAN`) and fails closed on everything else; and the tree proof it sits behind is deterministic.

## Alternatives Considered

- Drop the proof and resolve if `git rebase --continue` succeeds. Rejected: the proof is the only mechanical check that no content was dropped. A resolver that produced a non-equivalent tree would be invisible to code review.
- Let a clean resolution arm directly without a reviewer. Rejected: it removes human review from a category of change specifically chosen for being error-prone.
- Port the resolver into the `/post-plan` skill as well. Rejected: out of scope; the skill already has its own resolution path in `.claude/skills/post-plan/`.

## Consequences

- Positive: the squash-trap class of conflicts no longer requires a manual human resolution step.
- Positive: exit 3 semantics are preserved for every genuinely unresolvable class.
- Negative: auto-merge on a resolved conflict waits for a reviewer call, which adds latency to the arming step.

## References

- `tools/postplan-harness/harness/conflict.py`: new module, class gate, resolver, reviewer, flag/verdict paths.
- `tools/postplan-harness/harness/adapters/gitad.py`: integration, `rebase_onto`, `autoresolve_stacked_rebase`, `_prove_tree_equivalent`, `_record_resolution`.
- `tools/postplan-harness/harness/armable.py`: condition (14) truth table, `conflict_flag_path`, `conflict_verdict_for`.
- `bin/post-plan-now`: rc=3 cause comment; fallback suppression logic unchanged.
- `.claude/rules/linear-history-squash-merge.md`: the squash trap that motivates this change.

## Addendum: conflict evidence survives the abort

A fail-closed exit 3 used to name no files. Both rebase methods abort before `inventory_conflicts()` runs on the LLM-less path, and the abort clears the unmerged index entries, so the conflicted set was gone by the time the `HarnessError` was built. The `h2h-records-finish` run on 2026-09-21 is the worked example: the audit log records that the plain rebase failed and says nothing about which paths conflicted.

Both rebase methods now snapshot the unmerged path set with `diff --name-only --diff-filter=U` **before** any `--abort`, store it on `LiveGit.last_conflict_files`, and the runner writes it to `audit.log` as `phase2: conflicted paths (plain rebase) = ...` and `phase2: conflicted paths (--onto) = ...`. On the two LLM-less paths the list is also appended to the raised detail or the decline reason, because no other channel exists there. The snapshot is independent of `inventory_conflicts()`, which returns `files=()` whenever it classifies a conflict as unresolvable, which is precisely when the operator most needs the list.

This is diagnosis plumbing. No decision changes, no decline becomes a success, and the fail-closed contract is unchanged.

## Addendum (2026-09-30): the verdict parser scans every reply line

The "Residual risk and backstop" section above says the harness reads one exact literal from the reply. The exact-literal half still holds. The position rule changed with branch `conflict-review-verdict-scan`.

**Observed failure.** On PR #2587 the reviewer replied with one summary sentence, a blank line, and then `CONFLICT-REVIEW=CLEAN` on line 3. The parser read line 1 only, recorded `CONFLICT-REVIEW=ABSENT`, and condition (14) held a review the model had passed. It was the only (14) hold in the harness audit logs.

**Decision.** `harness/conflict.py` gained `parse_verdict(reply)`. It splits the reply into lines, strips each line, and treats a line as a verdict token only when it equals `CONFLICT-REVIEW=CLEAN` or `CONFLICT-REVIEW=FOUND-PROBLEM` in full. One distinct token anywhere in the reply yields that verdict. Both tokens present yield `FOUND-PROBLEM`. No token yields `ABSENT`. The prompt now asks for the token on line 1 with nothing before it, and the parser tolerates a reply that ignores the instruction. The verdict file format is unchanged: line 1 is the normalized verdict and the raw reply follows. `armable.py`, the skill readers, and `bin/test-postplan-arm-conditions` are untouched.

**What this widens.** A token on its own line anywhere in the reply now counts, including inside a code fence or a quoted list. A token padded by whitespace (spaces, tabs, a trailing `\r`, NBSP) counts. A token embedded in a prose line, or followed by other text on its line, still does not count. The both-tokens rule closes the case where the reviewer quotes `CLEAN` while explaining a `FOUND-PROBLEM`, and the case where it quotes both. A reply that carries a quoted `CLEAN` on its own line and no real verdict is a false CLEAN and is the accepted residual risk; the read-only reviewer and the deterministic tree proof named above remain the backstops. Fence skipping was rejected because a fence-aware scan would turn a reviewer that fences its whole reply into an ABSENT hold, which is the failure this addendum fixes.

## Addendum (2026-10-10): adapted lines may pass the tree proof under three conjunctive checks

**Observed failure.** In the `live-headless-login-probe-auth-status-20261010-145324-63269` run the plain rebase conflicted on `bin/test-plan-now`. Master commit `1de1f3b81` (PR #3082) had rewritten every `/tmp/plan-now-$RES_TS` path in that file to `"$RL"/plan-now-$RES_TS`. The branch added a new line that still wrote `/tmp/plan-now-$RES_TS.log`, and the resolver correctly rewrote it to `"$RL"/plan-now-$RES_TS.log`. `lostwork.sh` reported the branch line LOST and the run exited 3. The proof worked as designed. It had no notion of a justified adaptation.

**Decision.** A LOST `+` line now passes only when all three checks hold. First, a deterministic near-match in `tools/postplan-harness/harness/adaptations.py`: the HEAD line equals the branch line with only substring rewrites master itself made in that file applied to every occurrence, and HEAD holds more copies of it than master's copy does. Second, the resolver named that exact rewrite in an `ADAPTED-LINE:` JSON justification on its RESOLVED reply. Third, a separate read-only reviewer answered `ADAPTED-LINE-<n>=CONFIRMED` for every line. Tokens count only as whole stripped lines. Both tokens for one index mean `DENIED`, and a missing index means `ABSENT`. Any parse failure, absent verdict, reviewer exception, or `DENIED` restores the tree and exits 3. At most `MAX_ADAPTED_LINES` (50) lines can be adapted in one run. `lostwork.sh` is unchanged: the harness parses its text output and cross-checks each LOST line against the PRE patch and the `CHECKED:` counts.

**What is not weakened.** These all stay strict: lines with no near-match, `-` LOST kinds, missing-file and deletion-lost kinds, degraded `TREE DIVERGED` exits, LOST lines in files no model touched, LLM-less runs, and the runner's BEHIND re-rebase proof. Condition (14) still holds auto-merge for every model-resolved run. The adaptation reviewer never substitutes for `CONFLICT-REVIEW=CLEAN`.

**Residual risk.** A rewrite master made for one reason can apply cleanly to a branch line where it changes meaning. One example is an extra argument master added to one call that is wrong on the branch's new call. The deterministic checks pass that line and only the reviewer can refuse it, and a reviewer can confirm wrongly. A rewrite master made as two separate edits on one line shows up as one wider rewrite. It will not match a branch line that needs only one of them, so that line stays LOST as a false reject. The backstop: every accepted line is listed in the resolution notes, in `adaptations.txt` in the CONFLICT-REVIEW evidence directory, and in `audit.log`, and the PR still waits on condition (14).
