---
description: Whole-day JSB rand() replay into the Go engine finds the first divergent function for the parity loop.
last_verified: 2026-11-22
---

# ADR-0279: Whole-day JSB draw replay finds the first divergent function

**Status:** Accepted
**Date:** 2026-11-22
**Deciders:** repo owner

## Context

The first parity loop traces one JSB function at a time. It cannot say which function makes a whole simulated day diverge from the real game. The engine's seeded PCG stream can never equal JSB's `rand()` stream, so no seed makes the two engines draw the same numbers.

## Decision

Record the raw draws JSB makes during a real sim day and replay them through the Go engine. Do not change the engine's generator.

- `rng.RNG` gains `Raw()`, which returns 0..32767, and `rng.NewReplay`. A replay source fails loudly with `*rng.ExhaustedError` when it runs out, and it logs every `Float64` and `IntN` use with the raw value it consumed.
- The engine tags each draw by its runtime caller name. `sim.DrawSites` maps each name to the JSB function it ports, taken from the single address token in that function's doc comment. `TestDrawSitesCoverEveryCallSite` parses the package and fails when a draw site has no table entry.
- `sim.Options.RNG` is the only seam. A nil value keeps the seeded stream.
- `engine/cmd/jsbreplay` plays one recorded day, one game per `SimulateWith` call. It writes a draw log and box scores in one shape for both engines, so a finder can diff like with like. A game that runs out of draws is reported and does not stop the rest of the day.
- Recordings, league snapshots and the function table stay in the private jsb-native repo, pinned by commit and sha256. They never enter IBL5.
- The default PCG path and `golden.json` are unchanged.

This ADR names no JSB address and quotes no decompiled text (ADR-0236).

## Alternatives Considered

- An MSVC `rand()` source in Go. Rejected because it still needs a known start state for every sim day and gains nothing over replaying the recorded values.
- Explicit per-call-site tag arguments. Rejected because they touch about 30 call sites and 14 signatures for a name the runtime already has.
- 80-bit x87 emulation. Rejected because the measured control word was `0x27F`, which is 53-bit precision, so ordinary `float64` arithmetic matches.

## Consequences

- Positive: a whole-day replay names the first function whose draw pattern differs from JSB, which a single-function trace cannot.
- Positive: the default simulation path and its golden snapshot stay byte-identical.
- Negative: renaming a Go function changes its tag, and the AST test fails until `DrawSites` follows.
- Negative: a port that wraps a raw draw in `Float64` or `IntN` shows up as a `value-use` mismatch until it reads `Raw()` and copies JSB's arithmetic.
- Port PRs keep the trace-pinned proof of ADR-0266 unchanged.

## References

- ADR-0236 (`ibl5/docs/decisions/0236-two-engine-model-jsb-port.md`).
- ADR-0266 (`ibl5/docs/decisions/0266-trace-pinned-golden-clearance.md`).
- ADR-0278 (`ibl5/docs/decisions/0278-jsb-native-hosted-trace-captures.md`).
- `engine/internal/rng/replay.go`
- `engine/internal/sim/drawsites.go`
- `engine/internal/sim/drawsites_test.go`
- `engine/cmd/jsbreplay/main.go`
- `engine/docs/jsb-trace-capture.md`
