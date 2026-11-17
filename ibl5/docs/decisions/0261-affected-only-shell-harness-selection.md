---
description: CI runs only the bin/test-* harnesses whose derived coverage names a path the PR changed, picked once by the harness-select job in tests.yml, and falls back to every harness on any shared path, unclaimed path, push, re-run, or failed diff.
last_verified: 2026-11-17
---

# ADR-0261: Run only the shell harnesses a PR's changed files can affect

**Status:** Accepted
**Date:** 2026-11-17
**Deciders:** ajaynicolas
Related: ADR-0150 (parallel shell harnesses), ADR-0186 (memo-only CI gating), ADR-0253 (prefire shell stage)

## Context

The three `harness-tests` legs are the CI critical path. On 2026-11-16 each leg took 300 to 460 s, against about 100 s for PHPUnit. Every PR ran all 164 harnesses, including PRs that touched only PHP under `ibl5/classes`, which no harness reads. ADR-0186 rejected hand-kept lists of which tests a path needs, because such a list goes stale the day someone adds a read and forgets the list.

## Decision

A new `harness-select` job in `.github/workflows/tests.yml` runs `bin/run-shell-harnesses --select-from-merge` once per run. Every `harness-tests` leg and every `harness-tests-isolated` step reads its output and runs only the harnesses it names. The rules live in `bin/lib/harness-select.sh`:

- The scan derives each harness's coverage from the repo paths its script names, followed through every script it calls. It also adds the extra arguments its `.github/workflows/harness-legs.txt` line passes.
- A harness whose scripts read a path the scan cannot see (a `git ls-files`, a glob, a `find`) is always-run.
- `bin/lib/harness-covers.txt` holds widen-only overrides. An `inert` line names paths that select nothing when no harness claims them. A `bin/test-NAME` (example) line adds paths to one harness. No line can make a harness skip.
- The changed paths come from `git diff HEAD^1 HEAD` on the merge checkout, so a stacked PR diffs against its own base.
- Every harness runs when the event is not `pull_request`, the run attempt is above 1, HEAD is not a merge commit, the diff fails, the list is empty, or a changed path sits under `bin/lib/`, `.github/`, `bin/run-shell-harnesses`, or the repo top level. A changed path that no harness claims and no `inert` line covers also runs every harness.
- `bin/run-shell-harnesses --check-coverage` fails an override line that names a missing path, a missing harness, or a glob. The selector's fixture cases run first in the `harness-select` job as `--self-test`, and a failed selection falls back to every harness with a warning.

## Relation to ADR-0186

No hand-kept list decides what skips. The scan reads coverage from the harness scripts themselves, so a new harness is covered when it lands. The override file can only add paths, so a stale override costs CI time and never a missed test.

## Phase findings

`bin/lib/harness.sh` reads only mktemp fixtures. Every dynamic-read match in it is a comment or a shellcheck directive. Its one `source` loads `bin/lib/usage-test-isolation.sh`, and a change there already runs every harness because it sits under `bin/lib/`. So `hs_force_all` needs no extra path.

The coverage guard on 2026-11-17 printed `coverage: map OK (139 always-run, 25 narrow harnesses, 4 inert paths)`. Most harnesses are always-run today because one of their scripts uses a command on the dynamic-read list (`HS_DYNAMIC_RE` in `bin/lib/harness-select.sh`). Each one that gets a literal read list moves to narrow on its own.

## Alternatives Considered

- **A hand-written map from path to harness.** Rejected because: ADR-0186 bans lists that decide what skips.
- **A workflow `paths:` filter.** Rejected because: a required check with a path filter leaves PRs pending forever (`.claude/rules/ci-gotchas.md`).
- **Selection per leg.** Rejected because: three legs would each diff and scan, and the isolated job would need its own copy.

## Consequences

- Positive: a PHP-only PR runs only the always-run harnesses, and a narrow-path PR runs the harnesses that name its paths.
- Positive: the `harness-select` log lists each harness as run or skip with the reason.
- Negative: a harness that reads a path built entirely from variables, with no literal prefix, is a gap the scan cannot see. `bin/lib/harness-select-replay` replays merged PRs against the current scan and fails when a harness that failed in CI would have been skipped.
- Negative: the job adds one checkout and about 15 s of self-test before the legs start.

## References

- `bin/lib/harness-select.sh`
- `bin/lib/harness-covers.txt`
- `bin/lib/harness-select-replay`
- `bin/run-shell-harnesses`
- `.github/workflows/tests.yml`
- `.github/workflows/harness-legs.txt`
