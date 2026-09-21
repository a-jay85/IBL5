---
description: The harness attempts auto-resolution of ordinary rebase conflicts behind a class gate, a bounded per-file resolver, and a conjunctive TREE-EQUIVALENT proof; condition (14) holds auto-merge until a reviewer issues a CONFLICT-REVIEW=CLEAN verdict.
last_verified: 2026-09-20
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
