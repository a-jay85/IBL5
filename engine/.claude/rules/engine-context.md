---
description: Go/RE development context for the JSB native engine under engine/ — Makefile targets, the pinned-toolchain rationale, module layout, and where engine STATE vs runtime-behavior facts vs RE artifacts live. Fires on any engine/ file interaction.
last_verified: 2026-07-22
---

# Engine Development Context (Go / RE)

Scope: how to build, test, and cite sources when working in `engine/`. This is **process context, not state** — engine STATUS (open J-items, current frontier, NOT-A-LEVER traps) lives only in `engine/docs/backlog/jsb-native-backlog.md`; runtime-behavior facts live only in the master reference. Do not restate either here — the companion-memory split-brain was deliberately eliminated (see `engine/.claude/rules/jsb-engine-post-work.md`).

## Build & test (engine/Makefile — mirrors `.github/workflows/engine.yml`)

- `make build` → `./bin/jsbsim`; `make vet`; `make test` (`go test ./...`).
- `make fmt-check` (gofmt); `make lint` (golangci-lint **v2.x** — CI pins v2.12.2; install and run it locally before merging, since auto-merge can race ahead of the CI lint job and redden master).
- `make cover` enforces `COVER_MIN` (90.0% floor — a ratchet; raise as coverage improves, never lower).
- `make golden-update` (`go test ./internal/sim -run Golden -update`) regenerates the golden-master snapshot **only after an intentional output change**.

## Pinned toolchain (go.mod)

`go 1.22`, `toolchain go1.26.3` — pinned so seedable-PRNG golden snapshots are byte-reproducible across machines/CI (`math/rand/v2` consumption is not guaranteed identical across minor Go versions). Do not bump the toolchain without regenerating and reviewing goldens.

## Module layout (`github.com/a-jay85/IBL5/engine`)

- `cmd/`: `jsbsim`, `jsbcalibrate`, `jsbvalidate`.
- `internal/`: `sim`, `calibrate`, `validate`, `result`, `rng`, `bundle`, `backup`.
