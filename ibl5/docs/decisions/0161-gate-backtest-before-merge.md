---
description: Changed ship-pipeline gates are replayed against recent merged PRs, and Phase 6.5 condition (17) holds auto-merge when the replay flags clean PRs.
last_verified: 2026-10-02
---

# ADR-0161: Backtest changed gates before they merge

**Status:** Accepted
**Date:** 2026-10-02

## Context

A new gate is tested against the fixtures its author wrote and nothing else. The pipeline review of 2026-10-02 found that 20 of 34 recent fix PRs repaired something merged under 2 days earlier. PR [#2506](https://github.com/a-jay85/IBL5/pull/2506) shipped with the matrix-assertion gate as it stood after [#2465](https://github.com/a-jay85/IBL5/pull/2465), and [#2520](https://github.com/a-jay85/IBL5/pull/2520) then had to suppress 5 token-notation false positives in it. Running a changed gate over the PRs that already merged shows how it would have behaved, before it can block anyone.

## Decision

When a PR adds or changes a `bin/check-*` script, a registered `bin/lib` gate, or a `bin/lib` file a check script sources, post-plan replays that gate against the last 30 merged PRs with `bin/gate-backtest`. Each replay runs in one reused detached scratch worktree with the candidate gate files overlaid, with a 60 second per-replay timeout and a 900 second total cap. A historical PR is repaired when a later `fix` PR touching an overlapping file merged within 48 hours, clean when it is older than 48 hours and not repaired, and unsettled otherwise. The catch list goes into a marker-delimited PR-body block. Condition (17) holds auto-merge when one gate flags 2 or more clean PRs, when a check script has no replay spec, or when the replay fails, times out, or has fewer than 5 matured replays. CI workflows, hooks, and arming code are recorded as not replayable and never hold. Condition (17) only adds a hold. Replay specs are declared by a `# gate-backtest-argv:` header in the gate file, with a built-in registry for the existing scripts.

## Alternatives Considered

- **Run the gate on the new PR only.** Rejected because: one sample says nothing about the false-flag rate.
- **Hand-written fixtures per gate.** Rejected because: the author writes the fixtures that the gate already passes, which is the gap this closes.
- **Fold the replay into `bin/run-meta-checks-local`.** Rejected because: that script is bounded to fast CI parity and makes no `gh` history calls.

## Consequences

- Positive: a PR that changes no gate pays one `git diff`.
- Positive: a known-noisy gate holds auto-merge before it can block anyone, and the PR body lists which clean PRs it would have flagged.
- Negative: a gate PR pays up to 15 minutes and one `gh pr list` call.
- Negative: a new check script must declare a replay spec or opt out with a reason.
- Negative: the 48 hour window and the threshold of 2 are judgment calls. They live in `tools/postplan-harness/harness/gate_backtest.py` and can be tuned there.

## References

- `bin/gate-backtest`
- `bin/test-gate-backtest`
- `tools/postplan-harness/harness/gate_backtest.py`
- `tools/postplan-harness/harness/gate_backtest_replay.py`
- `tools/postplan-harness/harness/armable.py`
