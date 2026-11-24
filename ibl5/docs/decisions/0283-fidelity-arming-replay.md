---
description: The arming replay's second stage replays fidelity.py's deterministic readers over a committed corpus and clears a fidelity.py edit only when it is dead in production or reachable only through replayed roots.
last_verified: 2026-11-24
---

# ADR-0283: Fidelity arming replay

**Status:** Accepted
**Date:** 2026-11-23

## Context

The arming replay (ADR-0246) runs the base and candidate `evaluate` over a stored corpus and drops the `Self-governing: yes` segment when no decision loosens. A `harness/fidelity.py` edit almost never runs inside that corpus, so the replay keeps it as `unexercised-change`. PRs #4192, #4148, #4143 and #4067 all held that way. The fidelity module also feeds arming through data channels the first stage cannot see: the verdict file, the stored shadow, the sticky carry-forward and the LLM packet.

## Decision

`harness/arming_replay.py` runs a second stage, `harness/fidelity_replay.py`, whenever a diff touches `harness/fidelity.py`. The main-checkout driver `harness/fidelity_replay_driver.py` runs each side's arming-facing fidelity readers over `harness/fidelity_replay_corpus.json` under a line tracer. The stage clears a changed line by one of two proofs:

1. **Dead in production.** No production file reaches the changed function from any fidelity name it references.
2. **Replayed only.** Every production path to the function starts at a replayed root, the changed line ran on the corpus, no replayed call records a runtime side effect, and the changed line carries no `global`, `nonlocal`, `exec`, `eval`, `compile`, `__import__` or `importlib`, and no replayed output got looser. A function a replayed root shields must give equal outputs.

Anything else keeps the flag with a named reason. Looseness follows this table:

| Output kind | Looser when |
|---|---|
| verdict | the rank rises (`None` < `NOT READY` < `READY WITH NOTES` < `READY`), or a word is unknown |
| set (holds, findings) | any old item is missing; a reworded item counts as missing |
| flag (`gate_edit_only`, `verdict_file_usable`) | it turns True |
| equal (reviewed tree, parsed shadow, sticky terminal, arguments after the call) | it changes at all |
| any | one side raises or lacks the function and the other does not |

The stage defends 21 bypass classes:

1. Stage self-edit: an edit to the stage, its driver or its corpus holds as `fidelity-replay-self-edit`, and all three are arming paths.
2. Corpus tampering or deletion: the same self-edit hold.
3. Missing or empty corpus: `fidelity-corpus-missing`, the `MIN_FIDELITY_RECORDS` floor, and driver exit 4 on empty verdict text.
4. Exception divergence counts as looser.
5. Nondeterminism: the driver runs with `PYTHONHASHSEED=0`, `TZ=UTC` and `LC_ALL=C`, and any difference counts.
6. A disabled tracer leaves lines unexecuted, and a `sys.settrace`, `sys.setprofile` or `sys.addaudithook` call inside a replayed call records a side effect.
7. In-process stdout forgery stays a residual shared with the first stage.
8. Dynamic dispatch (`globals()`, `eval`, computed `getattr`, `sys.modules`) holds as `fidelity-unresolvable-dispatch`.
9. A module alias read as a value holds as `fidelity-unresolvable-reference`. The check resolves each name in its own scope, so a parameter named `fidelity` does not trip it.
10. A duplicate or shadowing def holds as `fidelity-duplicate-def`.
11. The call graph is the union of both sides.
12. Nested defs count as part of their top-level function.
13. Import-time code (decorators, non-constant defaults, constants, class bodies) holds as `fidelity-module-level-change`.
14. Reworded hold text counts as a lost item.
15. The cross-run sticky channel must stay equal.
16. A change reachable from an LLM-input root holds as `fidelity-unreplayed-channel`.
17. Side effects and argument mutation: runtime watches in the driver subprocess: an audit hook that records write-mode `open` events and the `os.`, `shutil.`, `subprocess.`, `socket.`, `tempfile.` and `ctypes.` event families; a deepcopy compare of every replayed root's arguments; a per-record snapshot of side-module globals and `os.environ`; a check after each replayed call that records a live thread or a new `atexit` callback the call left behind. Anything the watch cannot copy or compare holds. A changed-line static check covers inputs the corpus never has.
18. A function reachable from replayed and unreplayed roots holds through the unreplayed root.
19. Signature drift: each side gets only the keywords its own signature names, and a raise counts as looser.
20. A new production caller on the candidate side: roots come from both sides.
21. Bench and test files are left out of the roots, and a bench import from production holds.

`planfile.py`, `labels.py` and `owner_intent.py` join later, one PR each, with their own corpus and ordering.

## Alternatives Considered

- **Line-ran check only.** Rejected because an edit to `verdict_items` would clear while it feeds `body_recheck`.
- **ArmInputs call graph.** Rejected because it misses the verdict file, the sticky and the LLM packet.
- **Stubbed LLM replay.** Replaying `re_review` and `body_recheck` against recorded LLM output was rejected for now because a prompt edit would pass against a recorded answer.

## Residual gap

The watches see only the corpus inputs, so an effect behind an input the corpus lacks goes unseen. Two checks bound that gap: the line-ran check makes the changed line run on a corpus record, and the changed-line static check holds on `global`, `nonlocal`, `exec`, `eval`, `compile`, `__import__` and `importlib`. The watch detects and records. The replayed code still runs unsandboxed in a throwaway subprocess, as it did on #4205. The driver does not see these writes:

- a C extension write that raises no audit event;
- a write to a function's `__dict__` or an `lru_cache`, because callables compare by identity;
- a write to a stdlib module attribute such as `subprocess.run`, because the globals snapshot covers only modules under the side root and a module attribute write raises no audit event (not probed);
- a write from a plain refcount finalizer that happens to run inside the driver's own temp-file step, the one place the watch is off. The driver runs pending finalizers under the watch before that step and pauses gc during it.

The watch stays on between replayed calls and after the last one. A gc finalizer, a thread a call left running, and an `atexit` callback are all recorded. A thread still alive at the end is recorded too, and the driver then exits with `os._exit`, so nothing runs after its output.

The globals snapshot skips three things that never carry replay state: the driver's own module, module-valued names, and the `__future__` flag that `from __future__ import annotations` binds. That flag has no value equality, so a deepcopy of it never compares equal.

## Consequences

- Positive: a fidelity.py edit that only tightens reviewer holds, or that touches dead code, can merge without the owner.
- Negative: edits to `re_review`, `build_packet`, `body_recheck` or module constants still hold. #4192, #4143 and #4067 stay held.
- Negative: #4148 also stays held as `fidelity-unexercised-change`. The replay runs `_landed_for_arming`, but its new docstring lines (1774 and 1775 on the candidate) never fire a trace event, so the line-ran check keeps the flag.

## References

- `tools/postplan-harness/harness/fidelity_replay.py`
- `tools/postplan-harness/harness/fidelity_replay_driver.py`
- `tools/postplan-harness/harness/arming_replay.py`
- `tools/postplan-harness/tests/test_arming_replay.py`
- ADR-0246 (the first replay stage)
