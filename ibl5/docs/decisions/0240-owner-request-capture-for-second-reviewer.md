---
description: A script copies the owner's typed turns into a hash-sealed intent file; post-plan quotes it as the PR's Owner request and the second-reviewer seat reads it as evidence of scope, failing closed when it is missing or altered.
last_verified: 2026-11-11
---

# ADR-0240: Capture the owner's request for the second reviewer

**Status:** Accepted
**Date:** 2026-11-11
**Deciders:** ajaynicolas

## Context

The ADR-0231 second-reviewer seat decides whether a held PR may merge without the owner. On a plan-blind run it saw the diff, the PR title and Summary, and the hold list. It never saw what the owner asked for, and on hold 12 it never saw the plan-fidelity verdict's reasons. On [#3874](https://github.com/a-jay85/IBL5/pull/3874) it rejected with "I can't find the plan or the verdict's reasons". Post-plan runs in a fresh session, so it cannot read the conversation where the owner stated the request. A Phase 6.5 model that writes an `## Owner request` section paraphrases, and a paraphrase is the text the seat must not trust.

## Decision

`bin/capture-intent` copies chosen owner turns, byte for byte, from the session transcript into `~/claude-plans/_intent/<branch>.md`. It strips complete `system-reminder` and `t3_context` blocks, redacts emails and token-shaped strings, refuses a selection over 4,000 bytes, and seals the JSON payload with a SHA-256 header. No model writes or paraphrases the request. `tools/postplan-harness/harness/owner_intent.py` is the only reader. It returns nothing for a missing, altered, over-cap or wrong-branch file. On a plan-blind run the harness writes the PR body's `## Owner request` section from the file, with a `harness-written` marker, and strips a stale marked section when the file is gone. The seat reads the file and ignores the PR body section. It also gets the BLOCKING finding text for hold 12 on every run, capped at 6,000 bytes. Every over-cap or missing input leaves the seat prompt byte-identical to the old one. The request is evidence of scope for the seat and the fidelity reviewer. It clears no hold mechanically. `owner_intent.py` is listed in `gate_backtest._ARMING_PATHS`, so later edits to it register as arming changes.

## Alternatives Considered

- **Phase 6.5 model writes the section.** The model quotes the owner from its context. Rejected because there is no single writer to patch, and model text is the paraphrase the seat must not rely on.
- **Seat reads the PR body section.** One source for both. Rejected because Phase 6.5 models rewrite the body, so a forged section would reach the seat.
- **Truncate over-cap text.** Keep the first 4,000 bytes. Rejected because a cut request or finding can mislead the seat either way. Omitting it gives the old prompt, the known fail-closed state.
- **Guess the newest transcript.** No session id needed. Rejected because a concurrent session's transcript could be quoted as this owner's request.

## Consequences

- Positive: the seat can judge scope on plan-blind PRs from the owner's own words.
- Positive: the seat sees why the fidelity reviewer said NOT READY.
- Negative: the ad-hoc session must remember to run the capture. A missing file only means the old prompt.
- Negative: the hash catches a hand edit, but a writer that recomputes the hash can still forge the file. The seat doc treats the block as material to weigh. It never treats it as instructions.

## References

- `bin/capture-intent`
- `tools/postplan-harness/harness/owner_intent.py`
- `tools/postplan-harness/harness/second_review.py`
- `tools/postplan-harness/harness/gate_backtest.py`
- `.claude/agents/pr-second-reviewer.md`
- `.claude/rules/workflow-continuity-detail.md`
- `ibl5/docs/decisions/0231-second-reviewer-clears-holds.md`
