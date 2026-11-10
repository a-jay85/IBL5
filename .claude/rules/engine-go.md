---
description: Go engine workflow. Run the CI-pinned golangci-lint locally before pushing (a red Engine check holds auto-merge through All checks settled, and master pushes are ungated), the two lint rules it enforces, and the real measured runtime of an archive A/B walk. Also the two-engine model and the decompile-token allowlist gate.
paths: "engine/**"
last_verified: 2026-11-09
---

# Engine (Go) Workflow

Build targets, the `go.mod` toolchain pin, and module layout live in
`engine/.claude/rules/engine-context.md` (nested, attaches on the same tree). This file
carries only what that one does not.

## Two engines and the decompile-token gate

`engine/` is the faithful Go port of JSB. It resumed on 2026-11-08 under [ADR-0236](../../ibl5/docs/decisions/0236-two-engine-model-jsb-port.md), which supersedes ADR-0194. The port aims to drop the dependency on the Windows JSB binary, document the engine the league runs on, and drive further website automation. A separate clean-room engine lives in the private `a-jay85/ibl-engine` repo. The two coexist, and `engine/` has no deletion date.

`bin/check-engine-freeze` runs one gate on every PR, from `.github/workflows/pr-meta-checks.yml`:

- **Decompile-token gate.** Fails any line a PR adds, and any added file name, that carries a decompiler-output token (generated function, data, and label names, struct-offset forms, and the decompile-artifact file names), unless its path sits under `engine/` or `jsb-native/`. Website code under `ibl5/` and every other path stay banned. Lines already in the repo are never flagged. The pattern table and the allowlist live in the script.

`bin/test-check-engine-freeze` is the harness. The script keeps its historical name because ADR-0194 and the decision index cite it.

Decompile material and port work stay in this repo. Never copy a decompile token or port code into `a-jay85/ibl-engine`.

## Lint locally before pushing

`golangci-lint` is not preinstalled locally or on the automouse host. CI catches a
lint failure late on a PR and not at all on a direct master push, so run it yourself
first.

`.github/workflows/engine.yml` is not a required-status check. The live required list
is whatever
`gh api repos/a-jay85/IBL5/branches/master/protection --jq '.required_status_checks.contexts'`
prints, and `.claude/rules/ci-gotchas.md` explains each context. Do not copy that list
into this file. A restated list goes stale, and `bin/check-docs` fails this doc when it
names two or more contexts.

On a PR, engine lint still gates merge through the aggregator. The workflow's `gate`
job reports a check named `Engine`. `All checks settled`
(`.github/workflows/all-checks-settled.yml`, ADR-0149) is required, and it turns `failure` on any
red check on the head and holds `pending` while any check is still running, except names
in its `--ignore=` list. `Engine` is not on that list, so the status stays `pending` until the `Engine`
check reports, and `gh pr merge --auto` holds until `engine.yml` passes. A lint failure
found there still costs a red-PR round trip: fix, push, wait for CI again. `bin/check-docs` fails if `Engine` ever joins `--ignore=`.

Before ADR-0149, auto-merge raced ahead of `engine.yml` and lint failures landed on
**master** (the PR9b / #933 red-master incident: errcheck flagged unchecked
`io.Writer` `Fprint*`/`Close` returns and needed a follow-up fix PR). The aggregator
closed that race for PRs. A direct push to master is still ungated.

On a PR, `engine.yml` runs only when `engine/**` or the workflow file changes (a
workflow-level `on.pull_request.paths` filter). A PR that touches no engine file
gets no "Engine" check at all. Master pushes keep their own `push.paths` filter.

So for any engine PR, install and run the CI-pinned linter yourself first:

```bash
go install github.com/golangci/golangci-lint/v2/cmd/golangci-lint@v2.12.2
cd engine && golangci-lint run --path-prefix=engine   # must report 0 issues
```

`engine.yml` carries **two independent pins** — do not conflate them. The GitHub Action is
SHA-pinned (`golangci/golangci-lint-action@ba0d7d2… # v9.3.0`); the linter binary it runs is
pinned separately by `with: version: v2.12.2`. The version to install locally is the
**tool** pin, `v2.12.2`. When either moves, update this file and the install line together.
The `bin/check-docs` full scan fails a PR whose pins here or in `engine/.claude/rules/engine-context.md` drift from `engine.yml`.

Two rules it enforces that are easy to trip:

- **errcheck excludes concrete `os.Stdout`/`os.Stderr`, but NOT an `io.Writer` parameter.**
  Writing to a passed-in `io.Writer` and ignoring the error is an error. Route the writes
  through a closure that blank-assigns the error once.
- **staticcheck `QF1012` rejects `WriteString(fmt.Sprintf(...))`.** Use `fmt.Fprintf`.

## Archive A/B walks take ~8 min per walk-equivalent, not ~106 min

The header comments in `engine/internal/calibrate/branchb_archive_test.go` and
`engine/internal/calibrate/freeze_archive_test.go` quote "~106 min at stride 1, runs 20".
That figure is roughly 13x conservative. Measured 2026-06-10 (ADR-0053 MakePutback A/B) on
the dev machine: **2435 s total for 5 walk-equivalents** — ~487 s, about 8 min, per
walk-equivalent — for a full `//go:build archive` season-aggregate walk over the 54 GB JSB
backup archive (705 zips, 21 qualifying seasons) at `JSB_ARCHIVE_RUNS=20
JSB_ARCHIVE_STRIDE=1`.

Consequence: a 2–3 config archive A/B is **inline-able in one session, ~40 min total**. Do
not push it to the automouse queue or budget it as an overnight job on the strength of the
committed comment. Run it with `run_in_background: true` and let the harness notify on
completion. Note that a per-season two-pass harvest+frozen arm (ADR-0053 `validateWithArms`)
costs two walk-equivalents per config, versus one for a single-pass toggle like Branch-B.

Engine A/B context beyond the Go tooling: jsb-native/CLAUDE.md (separate repo).
