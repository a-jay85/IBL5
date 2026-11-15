---
description: bin/lib/prefire-verify gains a shell stage that runs only the bin/test-* harness named for each changed bin/ file, once per harness-legs.txt line, within a 300 s local budget, and leaves every caller harness to CI.
last_verified: 2026-11-14
---

# ADR-0253: Run only the changed bin/ files' own harnesses in prefire-verify, within a 300 s budget

**Status:** Accepted
**Date:** 2026-11-14
**Deciders:** ajaynicolas
Related: ADR-0241 (phpunit stage), ADR-0249 (pytest stage)

## Context

On 2026-11-14 one function changed in `bin/lib/usage-gate.sh`. An agent then ran every harness that sources the file: 30 harness files, serially, for 44 minutes. Only `bin/test-usage-gate`, about 2 minutes, tested the change. CI runs every harness over 4 parallel legs in about 20 minutes. Before this change the fire point ran no shell harness at all.

## Decision

`bin/lib/prefire-verify` gains a `shell` stage, last in `all`. A changed `bin/test-foo` (example) maps to itself. A changed `bin/foo` (example), `bin/lib/foo` (example) or `bin/lib/foo.sh` (example) maps to `bin/test-foo` (example), and `bin/automouse/queue` maps to `bin/test-automouse-queue`. The stage runs that harness once per line `.github/workflows/harness-legs.txt` gives it, with the line's env and args. A file with no harness, a harness with no legs line, and a line whose estimate would pass the budget each print a `shell deferred to CI:` line. An exempt line prints `shell exempt, not run:`. The budget is 300 s (`PREFIRE_SHELL_BUDGET`). The estimate for a line is its last measured duration in the per-worktree `shell-durations.tsv`, or 120 s when unmeasured. The stage never kills a started harness, stops at the first red harness with exit 1, and never chases callers.

## Alternatives Considered

- Run every harness that sources or calls the changed file. Rejected because that produced the 44-minute run, and CI already runs every harness on each push.
- A checked-in table of harness durations. Rejected because it goes stale and needs CI timing data the legs file does not carry.
- Kill a harness at the budget. Rejected because a killed harness gives no verdict. The budget gates the start of each line only.
- A `bin/test-foo-*` (example) prefix glob for shards. Rejected because it pulls in unrelated harnesses such as `bin/test-usage-gate-extra` (example). Shards are separate legs lines for the same script.

## Consequences

- Positive: a `bin/` change gets its own harness's verdict at the fire point, in under 5 minutes.
- Positive: exit 9 from `bin/post-plan-now` covers a red harness.
- Negative: a caller harness broken by a lib change reaches CI and never the fire point.
- Negative: a fresh worktree has no measured durations, so its first run uses the 120 s default estimate.
- Neutral: `bin/automouse/prompt-impl` Step 6.9 still lists three stages, and the fire point's `all` run covers the shell stage.

## References

- `bin/lib/prefire-verify` (`prefire_shell_select`, `stage_shell`)
- `bin/test-prefire-verify`
- `.github/workflows/harness-legs.txt`
- `ibl5/docs/decisions/0249-prefire-pytest-stage.md`
- `.claude/rules/workflow-continuity-prefire.md`
