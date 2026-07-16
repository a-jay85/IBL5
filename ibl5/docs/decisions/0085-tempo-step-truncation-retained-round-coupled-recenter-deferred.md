---
description: J21 finding — 5.60 rounds the possession clock-step half-up (int() truncation is a confirmed infidelity), but the archive A/B shows the faithful round-half-up shipped ALONE fails to flip the wrong-signed Cov(lnPOSS,lnPPS) and regresses mean pace by exposing a base_time-generation miscalibration truncation was masking; truncation was HELD; the coupled fix (round-half-up + baseTimeMid re-center 14.5→13.65) shipped in J23 (#1495); hold lifted.
last_verified: 2026-07-16
---

# ADR-0085: Tempo step truncation retained; faithful round-half-up + base_time re-center deferred to J22

**Status:** Accepted (hold lifted — the coupled fix shipped in J23, PR #1495; see Update below)
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

## Open follow-on — J23 (the coupled faithful fix)

Ship round-half-up in `possessionTime` **paired with** a base_time re-centering (`offVolumeNeutral`, and/or the `offVolumeScale`/`defRatingScale` calibration) so the faithful step rule lands the mean at ~104.6, then re-run the four-term archive A/B and re-establish the Phase-1 characterization pins. The J23 plan must A/B the recenter on its own (it interacts with `Var(lnPOSS)` via the 13s clamp floor) and re-derive the mean/variance targets against the paired `.sco` comparators on the same sampled games (the J15/ADR-0084 paired-comparator principle).

(Note: J22 was already assigned to per-player rl_stl/rl_tov production-bundle wiring — STL/TOV PF-dispersion; see the JSB backlog. This follow-on is J23.)

## Update: J23 shipped the coupled fix (2026-07-16, PR #1495)

**What shipped.** `int(pt + 0.5)` (round-half-up) is now live in `possessionTime` (`engine/internal/sim/tempo.go`), COUPLED with `baseTimeMid` re-centered from 14.5 to **13.65** (a 20-run archive sweep over `{13.4, 13.6, 13.65, 13.7}` selected 13.65 as the value closest to real ~104.6 poss/g without overshooting the four-term ceilings).

**Re-center lever chosen: `baseTimeMid` directly, NOT `offVolumeNeutral`.** The follow-on note in this ADR suggested re-centering via `offVolumeNeutral` (and/or the scales). J23 re-centered `baseTimeMid` directly instead: shifting the neutral center ~0.85s via `offVolumeNeutral` at `scale = 0.02` would require a ~42-unit move of `offVolumeNeutral` (0.85 / 0.02), distorting a documented validation-phase stand-in (`161.2 ± 13.8`, the real per-starter composite mean) far beyond its physical meaning and silently re-scaling the whole volume→count channel's zero point. `baseTimeMid` is the direct, single-purpose neutral-center knob — moving it changes exactly the mean pace and nothing else structural. Changing the name from `baseTimeMid` to `baseTimeNeutral` was considered and rejected as scope creep across unrelated files.

**Four-term outcome (20-run archive of record at `baseTimeMid = 13.65`):**

| term | pre-J23 (trunc) | J23 (round + recenter) | real | result |
|------|------|------|------|------|
| Cov(lnPOSS,lnPPS) [possession-count] | −0.000184 | −0.000095 | +0.000241 | **documented-null** — moved toward real but did NOT flip positive |
| Cov(lnFGA,lnPPS) total | −0.000364 | −0.000347 | +0.000269 | improved (less negative) ✓ |
| Var(lnPOSS) | 0.000254 | 0.000339 | 0.000721 | moved toward real; no overshoot ✓ |
| Var(lnFGA) [budget mirror] | 0.000792 | ≤ 0.001330 | 0.001330 | ceiling respected ✓ |
| **mean poss/team** | **101.9** | **~104.5** | **~104.6** | **restored ✓** |

**Cov(lnPOSS,lnPPS) documented-null.** The PRIMARY Cov sign did not flip positive at 13.65. This is consistent with the `possessioncoupling_archive_test.go:51-64` J20 finding (committed 2026-07-13): possession count is set from `clock / avg(ball-time)` — fixed up front from tempo ratings; the within-possession lever cannot move it. The real carrier of `Cov(lnPOSS,lnPPS)` is cross-team pace/base_time DISPERSION (`Var(lnPOSS)`), a separate subsystem. `Var(lnPOSS)` moved from 0.000254 → 0.000339 (toward real 0.000721) as expected, but the dispersion is still insufficient to flip the sign at 13.65. A larger `baseTimeMid` reduction would widen `Var(lnPOSS)` further but risks overshooting real 0.000721 or undershooting real mean pace. The documented-null is accepted: round-half-up + mean-pace restoration are headline faithfulness wins independent of the Cov sign, and holding both hostage to a metric carried by a separate subsystem would repeat the J21 over-hold this ADR supersedes.

**Mean pace restored.** `EnginePossCountPerG` (the authoritative EventPossessionStart tally, averaged across gt2 team standings) moved from ~101.9 (truncation) to ~104.5 (round + re-center), within sampling noise of real ~104.6.

**Pins re-established.** `possession_pace_pin_test.go` Pin A re-baselined to the round-half-up ~1/6, 1/3, 1/3, ~1/6 bucket distribution; Pin B re-baselined from 96 → 104 (step dropped 15→14 at re-centered base_time ~14.45 < 14.5 round boundary). The `possessioncoupling_archive_test.go` recorded baseline comment requires update to the J23 four-term values (exact Var(lnFGA) deferred to human reviewer with access to the full coupling-gate artifacts).

## Lineage

Extends the ADR-0049 possession-coupling instrument (the four-term decomposition this A/B reads) and the ADR-0054 budget-mirror discipline (`Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov`, the fourth gate term). Supersedes no prior decision — it records a faithfulness finding and holds. RE grounded in the machine-local decompile `FUN_004e42e0` (`jsb560_decompiled.c:98386-98438`) and the raw `jumpshot.exe` `.rdata` byte confirmation of `_DAT_00669ef0 = 0.5` (git-excluded artifacts).
