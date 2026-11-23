---
description: New bin/check-* gates declare their gate-backtest replay in a script header, and a CI gate rejects new REPLAY_SPECS keys.
last_verified: 2026-11-23
---

# ADR-0280: New gates declare their gate-backtest replay in a script header

**Status:** Accepted
**Date:** 2026-11-23
**Deciders:** repo owner

## Context

The gate backtest needs a replay spec for every `bin/check-*` script. Authors added that spec as a key in `REPLAY_SPECS` inside `tools/postplan-harness/harness/gate_backtest.py`. That file is an arming path, so the one-line edit held each new-gate PR for owner review. The backtest already reads a `# gate-backtest-argv:` header from the script itself, so the registry key added nothing a header could not carry.

## Decision

A new `bin/check-*` script declares its replay in its own comment header: `# gate-backtest-argv: <argv>`, or `# gate-backtest: not-replayable <reason>`. `bin/check-replay-spec-registry-additions` fails a PR whose `REPLAY_SPECS` block holds a key the merge-base copy lacks. Value edits, reformatting and removals pass. A key line ending in `# replay-spec-ok: <reason>` is exempt, for a spec field a header cannot carry. CI runs the gate and its harness `bin/test-check-replay-spec-registry-additions` in `pr-meta-checks.yml`, and `bin/run-meta-checks-local` runs both before a push.

## Alternatives Considered

- **Keep the registry and accept the hold.** Rejected because every new gate paid an owner review for a line with no risk.
- **Drop gate_backtest.py from the arming paths.** Rejected because the file holds the replay logic, and a change to that logic still needs review.
- **Fold the key check into an existing gate.** Rejected because no sibling check reads gate_backtest.py, and the trigger is one dict in one Python file.

## Consequences

- Positive: a new gate PR no longer touches an arming path just to register its replay.
- Positive: the replay spec sits next to the code it replays.
- Negative: one more `bin/check-*` script and harness to maintain.
- Negative: the key scan is a line match on the `REPLAY_SPECS` block. It exits 2 when the block is missing, so a renamed dict fails loudly.

## References

- `bin/check-replay-spec-registry-additions`
- `bin/test-check-replay-spec-registry-additions`
- `bin/run-meta-checks-local`
- `tools/postplan-harness/harness/gate_backtest.py`
- `tools/postplan-harness/tests/test_gate_backtest.py`
- `.claude/rules/postplan-harness-tests.md`
