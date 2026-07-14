---
description: J21 finding — 5.60 rounds the possession clock-step half-up (int() truncation is a confirmed infidelity), but the archive A/B shows the faithful round-half-up shipped ALONE fails to flip the wrong-signed Cov(lnPOSS,lnPPS) and regresses mean pace by exposing a base_time-generation miscalibration truncation was masking; truncation is HELD and the faithful round+recenter fix is deferred to J22.
last_verified: 2026-07-13
---

# ADR-0085: Tempo step truncation retained; faithful round-half-up + base_time re-center deferred to J22

**Status:** Accepted
**Date:** 2026-07-13

## Context

The J21 plan (`jsb-j21-pace-dispersion-fidelity`) targeted the two coupled pace-generation defects the ADR-0049 possession-coupling instrument localized on the 20-run archive of record (regular/gt2):

| term | engine (trunc) | real | note |
|------|--------|------|------|
| Cov(lnPOSS,lnPPS) [poss-count] | −0.000184 | +0.000241 | wrong sign; 89% of the real positive total — the PRIMARY target |
| Cov(ln(FGA/POSS),lnPPS) [spp] | −0.000180 | +0.000027 | real ≈ 0 |
| Var(lnPOSS) | 0.000254 | 0.000721 | engine under-disperses pace ~2.8× — the SECONDARY target |

The design fork the plan opened (Step-3a): is the under-dispersed, wrong-signed possession-count channel a **Go faithfulness bug** in the base_time → possession-step mapping, or an **un-RE'd 5.60 tempo mechanism**? Resolving it required reverse-engineering 5.60's possession-clock update.

### RE finding — 5.60 rounds the step HALF-UP; int() truncation is a confirmed infidelity

`FUN_004e42e0` (the possession-clock update, `jsb560_decompiled.c:98386-98438`) computes the integer clock step by truncating `possession_time` via `__ftol` (`:98407`, `:98411`) and then adding 1 when the fractional part is `≥ _DAT_00669ef0` (`:98412-98414`), before subtracting it from the possession clock at CEngine offset `0x4c24` (`:98425`). That is **round-half-up**.

`_DAT_00669ef0` was confirmed to be **exactly 0.5** from the raw `.rdata` bytes of `jumpshot.exe` (`objdump -s -j .rdata` at VMA `0x669ef0` → little-endian `00000000 0000e03f` = `0x3fe0000000000000` = the IEEE-754 double `0.5`). So the threshold is a half, not some other rounding pivot.

The original Go port used `int()` **truncation** (`engine/internal/sim/tempo.go`, `possessionTime`), which is therefore a **confirmed infidelity** — `floor(pt + 0.5)` is the faithful mirror. This closed the design fork on the "Go faithfulness bug" side.

### But the faithful fix, shipped ALONE, does not help and regresses mean pace

Because the mechanism is a per-possession clock step whose value is quantized to `{13,14,15,16}` (the base_time clamp is `[13,16]` and `tempoFactor = 1.0`), round-half-up **redistributes probability mass between the four buckets** — it does not add granularity. A clean same-config 20-run A/B (both `RUNS=20 STRIDE=1`, seed 20240601, real archive) measured:

| term | trunc (shipped) | round | real | read |
|------|---|---|---|---|
| Cov(lnPOSS,lnPPS) (PRIMARY) | −0.000184 | −0.000163 | +0.000241 | **no sign flip**; Δ within sampling noise |
| Cov(lnFGA,lnPPS) (total) | −0.000364 | −0.000351 | +0.000269 | toward real, still negative |
| Cov(ln(FGA/POSS),lnPPS) [spp] | −0.000180 | −0.000187 | +0.000027 | ~flat |
| Var(lnPOSS) (SECONDARY) | 0.000254 | 0.000274 | 0.000721 | +8% (deterministic) but still 2.6× under |
| Var(lnFGA) (budget mirror) | 0.000792 | 0.000792 | 0.001330 | unchanged — well under the real ceiling |
| **mean POSS/team** | **101.9** | **97.6** | **104.6** (real `.sco` proxy) | **round regresses the mean ~4 further under** |

Two facts separate the noise from the signal:

- **The Cov(lnPOSS,lnPPS) delta is within sampling noise.** Its sign flipped between the stride-1 (+0.000021) and stride-4 (−0.000018) configs, so it is NOT sign-robust. Round-half-up does not flip the PRIMARY target's wrong sign; the fidelity carries the change, not the metric.
- **The mean-possession regression is a deterministic mechanism effect, NOT noise.** A neutral matchup averages to `baseTimeMid = 14.5` — exactly on the round boundary — so round-half-up *lengthens* the central step from 14 (trunc) to 15, lowering mean possessions every run. The engine-count mean drops from ~101.9 (trunc) to ~97.6 (round), against a real `.sco`-proxy mean of ~104.63 ± 3.96. Truncation (101.9) is **closer** to the real mean than round (97.6).

### Interpretation — truncation was masking a base_time-generation miscalibration

The engine already under-produces possessions under both rules (real ~104.6; trunc 101.9; round 97.6). The clock model (`gameloop.go`: 4 × `quarterSeconds = 720` = 2880s regulation, stepped on a shared alternating clock ⇒ per-team possessions ≈ 1440 / step) makes a real ~104.6 poss/team imply an effective step of ~13.8s (1440 / 104.6 = 13.76; OT-inclusive games only shrink the gap), versus the engine's neutral center `baseTimeMid = 14.5s` — the engine's base_time generation is **~0.7s too slow**. Truncation's systematic downward bias (`floor` always rounds toward a shorter step ⇒ more possessions) was **accidentally compensating** that too-slow base_time, landing the mean closer to real by luck. The faithful round-half-up removes that accidental compensation and **exposes** the base_time miscalibration.

The base_time neutral reference points (`offVolumeNeutral = 161.0`, `defRatingNeutral = 24.0`) are documented validation-phase stand-ins (see `engine/internal/sim/tempo.go`). Re-centering `offVolumeNeutral` ~0.7s faster is a substantial recalibration of a stand-in that *interacts with the dispersion goal* (a faster center pushes the fast tail toward the 13s clamp floor, which may compress `Var(lnPOSS)` rather than widen it). It therefore needs its own A/B and re-pin — a separate lever, not a Phase-4 bolt-on.

## Decision

**HOLD. Retain `int()` truncation in `possessionTime`; do NOT ship round-half-up alone. Defer the faithful fix — round-half-up COUPLED with a base_time re-centering — to J22.** This ADR is the finding of record.

Rationale, three independent reasons converging:

1. **The plan's own accept gate fails.** Branch-D was pre-registered (plan line 217) as a faithfulness correction that "routes to HOLD, not a defect to chase" if `Var(lnPOSS)` doesn't move materially and the Cov sign doesn't flip. `Var(lnPOSS)` moved only +8%; the PRIMARY Cov sign did not flip (Δ within noise). Both gate conditions land on HOLD.
2. **Round-alone would ship a regression.** Mean pace — a GM-visible number — moves measurably *away* from real (101.9 → 97.6 vs 104.6) for a marginal, sign-fragile variance gain. Truncation is the mean-closer of the two imperfect states.
3. **The faithful end-state is a coupled change.** Round-half-up is only correct *together with* a base_time re-center that restores the mean. Shipping the rounding half of a two-part faithful fix — the half that regresses the headline metric — is worse than holding until both land together (J22).

Fidelity-first does not mean shipping each faithful micro-correction in isolation when doing so regresses a headline metric and a *coupled* faithful correction is required; it means the shipped end-state must be faithful. That end-state is round + re-center (J22).

### What ships in this PR (no engine behavior change)

- `engine/internal/sim/tempo.go` — truncation retained; the `possessionTime` docblock records the RE finding, the `_DAT = 0.5` raw-byte confirmation, the mean-regression, and the J22 deferral.
- `engine/internal/calibrate/possessioncoupling_archive_test.go` — the permanent `Var(lnFGA)` budget-mirror log (build-tag `archive`, never in CI) that emits the fourth A/B term this ADR cites. Kept as the reproducibility instrument for the finding.
- This ADR.

No change to `possessionTime`'s output, the Pin A characterization, the `tempo_coupling_test.go` fixture, or the committed archive artifact (all remain at the shipped truncation baseline).

## Consequences

- **No pace/possession behavior change.** The engine ships byte-identical possession steps; the archive artifact of record stays the truncation run. `go test ./internal/sim ./internal/calibrate` green.
- **`auto_merge: false` (honored).** The disposition (accept-vs-hold) was an irreducible human adjudication over an A/B on data unreachable from CI — exactly the reason the plan set `auto_merge: false`. The human chose HOLD; the PR opens for review rather than auto-merging.
- **Two known-imperfect states remain, both documented.** `int()` truncation is not faithful to 5.60's round-half-up, AND base_time generation is ~0.7s too slow. They partially cancel on the mean today. J22 fixes both together.

## Open follow-on — J22 (the coupled faithful fix)

Ship round-half-up in `possessionTime` **paired with** a base_time re-centering (`offVolumeNeutral`, and/or the `offVolumeScale`/`defRatingScale` calibration) so the faithful step rule lands the mean at ~104.6, then re-run the four-term archive A/B and re-establish the Phase-1 characterization pins. The J22 plan must A/B the recenter on its own (it interacts with `Var(lnPOSS)` via the 13s clamp floor) and re-derive the mean/variance targets against the paired `.sco` comparators on the same sampled games (the J15/ADR-0084 paired-comparator principle).

## Lineage

Extends the ADR-0049 possession-coupling instrument (the four-term decomposition this A/B reads) and the ADR-0054 budget-mirror discipline (`Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov`, the fourth gate term). Supersedes no prior decision — it records a faithfulness finding and holds. RE grounded in the machine-local decompile `FUN_004e42e0` (`jsb560_decompiled.c:98386-98438`) and the raw `jumpshot.exe` `.rdata` byte confirmation of `_DAT_00669ef0 = 0.5` (git-excluded artifacts).
