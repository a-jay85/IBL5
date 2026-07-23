---
description: J21 finding — 5.60 rounds the possession clock-step half-up (int() truncation is a confirmed infidelity), but the archive A/B shows the faithful round-half-up shipped ALONE fails to flip the wrong-signed Cov(lnPOSS,lnPPS) and regresses mean pace; truncation was HELD, the coupled fix shipped in J23 (#1495); J24 then ported the full per-possession step-class mix (half-court jitter + steal + DRB-push) with a NO-GO on the faithful 16.0 center — provisional re-centered 13.65→17.7, dispersion/Cov residual open.
last_verified: 2026-07-19
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

## Update: J24 RE shows the round-half-up pin was incomplete (2026-07-16)

The J24 static RE session (`jsb-native/re-artifacts/jsb-J24-pace-dispersion-RE-20260716.md`, machine-local) re-derived `FUN_004e42e0` in full: 5.60 does round half-up, but it rounds a **per-possession jittered draw** — `pt/2 + uniform[0, pt)` (mean pt, width pt) with a `{3..23}` redraw when the rounded step equals `trunc(pt)` — and two whole step classes bypass the draw entirely (steal transition `rand(3)` = 0–2s; DRB push `rand(3)+2` = 2–4s, gated on the outlet's TO rating and the team tempo strategy). This ADR's A/B compared truncation vs rounding of a **deterministic** step; both arms were unfaithful to the step's distribution, which is why the Cov sign could not flip here. The "separate subsystem" this Update's documented-null paragraph pointed at is now fully pinned; the faithful port is backlogged as **J24** (`engine/docs/backlog/jsb-native-backlog.md`). The 13.65 `baseTimeMid` re-center is superseded-in-principle: 5.60's pt for typical rosters is ~15.3–15.5, and the effective mean step ≈ 13.8 emerges from the fast-class mix — 13.65 was absorbing the missing fast paths into the midpoint. Nothing shipped here regresses; J23's round-half-up survives as a component of the faithful rule.

## Update: J24 shipped the step-class mix; faithful center NO-GO, provisional re-centered 13.65 → 17.7 (2026-07-17)

**What shipped (J24 Phases 0-4).** The full FUN_004e42e0 per-possession step-class mix is now live in `engine/internal/sim/`:

- **u = 0 ground truth (Phase 0, binary-proven).** `CEngine+0x38` — the multiplier on every composite/param term of FUN_004e4150's base_time ratio — is unconditionally 0.0 (single writer averages two stack doubles whose only writers are prologue zero-stores; exhaustive modrm scan). With u = 0 the ratio evaluates 2880/0 → +inf → clamped to the 16.0 ceiling **every call**: 5.60's base_time is a per-matchup CONSTANT and the composite ratio is dead code. This retired the ADR-0042 roster-dependent additive `teamBaseTime` stand-in (Phase 1) — it modeled dispersion 5.60 does not have. Full proof chain: `jsb-native/re-artifacts/jsb-J24-pace-dispersion-RE-20260716.md` §8 (machine-local).
- **Half-court jitter (Phase 2).** `possessionTime(baseTime, r)` draws `round-half-up(pt/2 + U[0,pt))` per possession, with a single `{3..23}` redraw on the trunc(pt) hit (no loop — faithful). J23's round-half-up survives as the rounding rule of the jittered draw.
- **Steal-transition class (Phase 3).** A possession following a steal drains `rand_int(3)` → {0,1,2}s. `possession()` now returns a 3-valued `possOutcome` (normal/steal/DRB) instead of the old fast-break bool. **(LABEL CORRECTED 2026-07-17 — see the step-class label-correction Update below: FUN_004e42e0's `rand_int(3)` {0,1,2}s class is the OREB quick-putback class, NOT the steal class; a steal is faithfully a code-7 transition PUSH. The engine's steal→{0,1,2}s routing is a known wrong-class stand-in, not a faithful mapping.)**
- **DRB-push class (Phase 4).** A possession following a defensive rebound, when the (single-draw, captured) `transitionTriggers` gate fires, drains `rand_int(3)+2` → {2,3,4}s. `strategy_adj = 0` stand-in documented (`.lge +0x12c` coach/tempo term not yet pinned).

**Phase 5 GO/NO-GO: NO-GO.** The gate asked for Var(lnPOSS) ≥ ~0.0006 with positive Cov (GO-1) and mean pace ∈ [103.5, 105.5] at the faithful 16.0 center (GO-2). Archive smoke (runs=4 stride=4, seed 20240601):

| config | mean pace (poss/g) | Cov(lnPOSS,lnPPS) | Var(lnPOSS) |
|--------|-------------------|-------------------|-------------|
| baseTimeMid 13.65 (old provisional) | 132.14 | −0.000049 | 0.000247 |
| baseTimeMid 16.0 (faithful) | 114.68 | −0.000057 | 0.000262 |
| **baseTimeMid 17.7 (new provisional)** | **104.25** | −0.000055 | 0.000270 |
| real | ~104.6 | +0.000241 | 0.000721 |

Both gates fail. The mix ports the step CLASSES faithfully but the engine ARMS them at ~29% of possessions (implied half-court weight 0.706 from the 13.65/16.0 pace pair) vs real ~11.5% (~24 transition markers / ~209 possessions) — ~2.5× the real share. Consequences: (a) the faithful 16.0 center overshoots pace by ~10 poss/g, so the provisional center is retained and re-centered to **17.7** (bracket of record 17.5 → 105.38, 17.7 → 104.25, 17.9 → 103.06; deliberately above the faithful [13,16] clamp — it compensates the over-armed fast classes); (b) Var(lnPOSS) is **unchanged** vs the pre-port 0.000254 record and Cov did not flip — the class MIX as armed by the engine's own steal/transition rates carries pace, not cross-team dispersion. The Var/Cov carrier in 5.60 remains unidentified.

**J24 status: ◑ Partial.** Residual sub-steps carried in the backlog: (1) close the fast-class arming-share gap (~29% → ~11.5%) — **[DIAGNOSIS CORRECTED 2026-07-17, see the label-correction Update below] steals are in the wrong step CLASS entirely**: 5.60 routes a steal-sourced break through the code-7 transition-PUSH gate ({2,3,4}s, the outlet TO-rating roll — the same gate DRB pushes use), NOT through an ungated {0,1,2}s draw, and reserves {0,1,2}s for OREB quick-putbacks. The engine over-arms because steals bypass the push gate and always fire a fast class; the fix routes steals to the gated code-7 class (and adds an OREB-putback {0,1,2}s class), rather than "the steal class needs the same gating as the transition RUN"; (2) trace the `CEngine+0x30` forced-redraw flag writer; (3) pin `.lge +0x12c` for the real `strategy_adj`; (4) once the share closes, walk `baseTimeMid` back to the faithful 16.0 and re-run this gate. Instruments kept: `basetimemid_sweep_archive_test.go` (re-center bracket of record), `possessioncoupling_archive_test.go` (J24 UPDATE block records the post-port four-term numbers).

## Update: FUN_004e42e0 step-class label correction (2026-07-17)

The J24 arming-share RE re-trace (`jsb-native/re-artifacts/jsb-J24-arming-share-RE-20260717.md`, machine-local) corrected the labeling of FUN_004e42e0's fast step classes as recorded in the two Updates above. The `param_2` argument was mislabeled a "steal-transition" flag; it is the **OREB quick-putback** flag (`param_2==1`, set only from the rebound handler). The corrected class map:

| FUN_004e42e0 class | trigger | step (s) |
|---|---|---|
| **OREB quick putback** | `param_2==1` (rebound handler only) | `rand_int(3)` → {0,1,2} |
| **transition push (steal OR DRB sourced)** | `param_3==7` (code 7) | `rand_int(3)+2` → {2,3,4} |
| half-court | `param_3==6` (code 6) | round-half-up jitter |

A steal-sourced fast break is faithfully a **code-7 transition push** ({2,3,4}s), the same class as a DRB push — the fast-break arming flag (`CEngine+0x4be4`) is set unconditionally by steals and at 94% by DREBs, then consumed by ONE outlet TO-rating roll. FUN_004e42e0 is the clock-step function throughout; the outlet player-pick / arming trigger is the SEPARATE `CEngine+0x4be4` gate, not FUN_004e42e0.

**Effect on the shipped engine (unchanged by this Update — documentation only):** `engine/internal/sim/gameloop.go` still routes `possSteal` → an ungated {0,1,2}s draw and reaches the code-7 {2,3,4}s class only via DRB. That steal→{0,1,2}s routing is now understood as a **wrong-class stand-in** (the J24 §1d residual, restated in residual (1) above), to be corrected by the arming-share port — not by this ADR. Nothing shipped changes here; only the labels and the residual-(1) diagnosis are corrected. Authoritative reference: `jsb-native/jsb_560/decompiled/00_MASTER_REFERENCE.md` "Possession clock step — FUN_004e42e0".

## Lineage

Extends the ADR-0049 possession-coupling instrument (the four-term decomposition this A/B reads) and the ADR-0054 budget-mirror discipline (`Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov`, the fourth gate term). Supersedes no prior decision — it records a faithfulness finding and holds. RE grounded in the machine-local decompile `FUN_004e42e0` (`jsb560_decompiled.c:98386-98438`) and the raw `jumpshot.exe` `.rdata` byte confirmation of `_DAT_00669ef0 = 0.5` (git-excluded artifacts).
