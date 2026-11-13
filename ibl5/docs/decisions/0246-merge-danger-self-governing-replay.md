---
description: ADR-0246. An edit to a Python arming file drops the Self-governing segment only when a replay of old and new arming code over a checked-in corpus shows no decision got looser and every changed line ran; every other outcome keeps it
last_verified: 2026-11-12
---

# ADR-0246: Self-governing only when a replay shows an arming decision loosens

**Status:** Accepted
**Date:** 2026-11-12

## Context

ADR-0234 sets ` · Self-governing: yes` on any edit to an arming path, and condition (18) holds every such PR for the owner. Since 2026-11-09, 7 of 15 merge-danger holds were Self-governing. Two of them could not loosen any arming decision: a log-message edit to `fidelity.py`, and an add-only reviewer hold. The rule could not tell an edit that loosens arming from one that only rewords or tightens it.

## Decision

`derive_from_worktree` in `tools/postplan-harness/harness/merge_danger.py` hands `derive_merge_danger` a replay callable from `tools/postplan-harness/harness/arming_replay.py`. The replay runs only when every arming-path trigger is a `.py` file under `tools/postplan-harness/harness/`. Any other arming path in the diff keeps the segment for the whole diff, and the Why reads `replay not run (<file> outside its reach)`. Those paths are the shell twin, the second-reviewer agent file, the two classifier scripts, and the seat-feed trigger on `runner.py`.

The replay copies `tools/postplan-harness/harness` and `bin/lib` from the worktree into two temp trees and reverse-applies the diff to one with `git apply -R`. Each side runs `evaluate` in its own Python process over `harness/arming_replay_corpus.json` under a line tracer. The corpus is a checked-in list of 48 hand-built `ArmInputs` records.

The judge clears the `arming-path` kind only when all of these hold:

- both sides decode the same `ArmInputs` field set;
- the corpus has at least 20 records, the two sides return the same record ids, and the old side has both an armed and a held record;
- every changed line ran during the `evaluate` calls on the side where it exists, or `tokenize` shows it holds only a comment or blank space;
- no record got looser. A record loosens when it moves from held to armed, and also when it stays held but loses a blocking condition, because a second hold can mask a removed one.

Every other outcome keeps the segment. The kept reasons are `inputs-shape-changed`, `corpus-too-small`, `result-mismatch`, `corpus-degenerate`, `unreadable-section`, `unreadable-source`, `unexercised-change`, a loosened count, `corpus-missing`, `tree-missing`, `apply-failed`, `timeout`, and `error`. The function never raises. The Why leads with the note, `replay cleared arming-path (…)` or `replay kept arming-path (…)`, and the note carries the record counts.

The harness reads the corpus and the driver from the directory of its own running module. `bin/post-plan-now` runs the main checkout's harness, so the copies on `master` judge each PR. The four files the replay runs from are guard paths: `arming_replay.py`, `arming_replay_driver.py`, `arming_replay_corpus.json`, and `merge_danger.py`. An edit to one always sets the segment, and the replay never clears it.

A cache keyed on the diff text, the corpus bytes, and both copied directories returns the same verdict at Phase 2 and at arm time. Module constants set the time limits: 15 s for the reverse apply and 45 s per side.

The owner approved this narrowing on 2026-11-12.

## Alternatives Considered

- Rebuilding the corpus from merged PRs was rejected. About half of `ArmInputs` never reaches GitHub (Phase 5 status, fidelity verdicts, mutation state, conflict flags), so the rebuilt records would be guesses that decide the verdict.
- A scratch `git worktree` at the base commit was rejected. The shim gets no base ref, and the classifier would resolve from a different root than the new side.
- Clearing any Python arming edit whose decisions match was rejected. It would clear an edit to a branch the corpus never enters, and the line trace makes reach a per-line fact.
- Importing both versions in one process was rejected. Two `harness` packages share `sys.modules` keys, and a crash or hang in candidate code would take the harness run with it.

## Consequences

- Positive: a log-message edit or an add-only hold in a Python arming file arms without owner review, when the corpus reaches the changed line.
- Negative: the proof covers the corpus only. An edit that loosens a decision for an input shape missing from the corpus clears when the corpus runs the changed line. Line coverage does not show which half of a one-line `and`/`or` ran.
- Negative: edits to the shell twin, the second-reviewer agent file, the classifiers, and `merge_danger.py` still hold. A diff that adds an `ArmInputs` field still holds.
- Negative: the corpus grows by hand until a recorder exists.

## References

- `tools/postplan-harness/harness/arming_replay.py`: `encode_inputs`, `judge`, `replay_worktree`.
- `tools/postplan-harness/harness/arming_replay_driver.py`: the per-side driver.
- `tools/postplan-harness/harness/merge_danger.py`: `derive_merge_danger`, `derive_from_worktree`, `_REPLAY_GUARD_PATHS`.
- `ibl5/docs/decisions/0234-merge-danger-self-governing.md`: the rule this narrows.
