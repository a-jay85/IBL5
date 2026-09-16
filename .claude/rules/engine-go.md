---
description: Go engine workflow — run the CI-pinned golangci-lint locally before merging (auto-merge races ahead of engine.yml), the two lint rules it enforces, and the real measured runtime of an archive A/B walk.
paths: "engine/**"
last_verified: 2026-09-16
---

# Engine (Go) Workflow

Build targets, the `go.mod` toolchain pin, and module layout live in
`engine/.claude/rules/engine-context.md` (nested, attaches on the same tree). This file
carries only what that one does not.

## Lint locally BEFORE merging — CI is not a gate here

`golangci-lint` is not preinstalled locally or on the automouse host, and
`.github/workflows/engine.yml` is **not a required-status check** (the four required
contexts are `Tests and Analysis`, `E2E Tests`, `human-signoff`, and `Meta checks` —
`engine.yml` is not among them), so `gh pr merge --auto` merges as soon as those four
pass — before `engine.yml` has run lint. Deferring lint to CI therefore lands failures
on **master** (that is the PR9b / #933 red-master incident: errcheck flagged unchecked
`io.Writer` `Fprint*`/`Close` returns and needed a follow-up fix PR).

So for any engine PR, install and run the CI-pinned linter yourself first:

```bash
go install github.com/golangci/golangci-lint/v2/cmd/golangci-lint@v2.12.2
cd engine && golangci-lint run --path-prefix=engine   # must report 0 issues
```

`engine.yml` carries **two independent pins** — do not conflate them. The GitHub Action is
SHA-pinned (`golangci/golangci-lint-action@ba0d7d2… # v9.3.0`); the linter binary it runs is
pinned separately by `with: version: v2.12.2`. The version to install locally is the
**tool** pin, `v2.12.2`. When either moves, update this file and the install line together.

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
