---
description: Faithful JSB 5.60 sqrt gate-1 offensive-rebound continuation roll shipped UNCONDITIONALLY ON as the default live engine. ADR-0057 positively identified the L1 carrier — 5.60 resolves an offensive-rebound continuation with a TWO-gate chain: a sqrt diminishing-returns team-determination "which team wins the board" (gate-1, FUN_004e22a0, jsb560_decompiled.c:97352-97405) applied AHEAD of the linear retention roll (gate-2, orebProbability) — but the engine omitted gate-1 and rolled gate-2 alone. ADR-0058 ran the counterfactual decomposition instrument and returned a GO verdict: gate-1-alone reproduces ORB/POSS ≈0.160 ≈ real 0.158, where the live gate-2-alone sits at ≈0.194 (the ADR-0056 ~23%-high ORB excess). The fix REPLACES (not multiplies) the live continuation roll: gs.orebProb resolves the single determination via gate1Probability(off, def, gateBaseline) by default. The league rebound baseline is lifted to resolve UNCONDITIONALLY in gameloop.go (it was previously gated behind the read-only instrument) because the live faithful roll now reads it on every run. INVERTED-POLARITY escape hatch FreezeConfig.UnfaithfulOreb (default false = faithful = production) restores the old linear gate-2 path for the archive A/B's OFF walk ONLY. Golden bytes change BY DESIGN (regenerated with -update); TestDeterminism stays green. The read-only ADR-0057/0058 decomposition instrument (GateContAccum) is untouched and stays byte-identical. Measured as a 2-config A/B (OFF=UnfaithfulOreb gate-2 / ON=faithful gate-1) over the full backup archive (stride 1, runs 20). VERDICT — SHIP on RE-faithfulness merits with a curvature caveat: the GATE passes decisively (ON ORB/POSS 0.15818 ≈ real 0.15750, closing the entire ADR-0056 ~23% excess the OFF gate-2 walk still carries at 0.19414; OFF reproduces ADR-0058's gate-2 baseline to the digit, proving the escape hatch is leak-free). TRIPWIRE fired small-and-explained: Var(lnPPS) widened 0.001458→0.001485 (gap 7e-6→3.4e-5, touching the ADR-0055 ≈real claim) as the downstream of a curvature OVERSHOOT — Cov(ORB/POSS,lnPPS) went from over-coupled (OFF −0.000151) past real (−0.000115) to under-coupled (ON −0.000060), the exact mean-vs-curvature axis ADR-0058 named; the fix nailed the ORB mean/level and slightly over-corrected the coupling. Recorded as a monitor-only watch-item (curvature now UNDER real, so no decay/cap). The headline Cov(lnFGA,lnPPS) barely moved and the count axis is flat — REPORTED, not gated (OOS — ADR-0054 / offVolumeScale #974).
last_verified: 2026-06-11
---

# ADR-0060: Faithful gate-1 offensive-rebound continuation

**Status:** Accepted
**Date:** 2026-06-11

## Context

ADR-0056 measured the engine's offensive-rebound intensity (`ORB/POSS`) at ~23% above
real. ADR-0057 (the L1 carrier RE) positively identified why: JSB 5.60 resolves an
offensive-rebound continuation as a **two-gate chain** — a sqrt diminishing-returns
team-determination ("which team wins the board," `gate-1`, `FUN_004e22a0`,
`jsb560_decompiled.c:97352-97405`) applied **ahead** of the linear retention roll
(`gate-2`, `orebProbability`) — but the engine **omitted gate-1** and rolled gate-2
alone, over-crediting offensive boards. The carrier RE settled the call site
(`FUN_004d6f00:92181`), the handler (`FUN_004e22a0`), and that the loop is flat and
uncapped (no decay-cap; ADR-0057).

ADR-0058 ran a read-only counterfactual decomposition instrument (`GateContAccum` /
`accumulateGateCont`) over the real archive to decide the **shape** of the fix —
whether the dropped gate-1's effect is a mean inflation or a curvature (variance)
coupling, and whether to **replace** the live roll with gate-1 or **multiply** the two
gates. The verdict was **GO / REPLACE**: gate-1-alone reproduces `ORB/POSS` ≈0.160 ≈
real 0.158, while the live gate-2-alone sits at ≈0.194. The mean channel explains the
ORB-level excess; replacing the live roll with the faithful sqrt gate-1 is the fix.

This ADR ships that swap.

## Decision

Ship the faithful sqrt **gate-1** as the **live offensive-rebound continuation roll**,
**unconditionally ON** as the default engine — a faithfulness fix that *becomes*
production behavior, NOT a default-off measurement seam (mirroring the ADR-0055 faithful
putback precedent, not the ADR-0053 freeze arms).

1. **The swap (`freeze.go`, `gs.orebProb`).** The single ORB-continuation determination
   resolves via `gate1Probability(off, def, gs.gateBaseline)` by default; the old linear
   `orebProbability` (gate-2) path survives only behind the escape hatch. `gs.orebProb`
   is the sole injection point — the half-court and transition rebound paths both route
   through it (`possession.go` `gs.rebound`), so one swap covers both. The freeze-arm
   accumulator writes the live `p`, so the ORB freeze arm auto-tracks the new value.
2. **Unconditional baseline lift (`gameloop.go`).** The league rebound baseline
   (`leagueReboundBaseline(b)`, or the `GateBaseline` override) is resolved into
   `gs.gateBaseline` on **every** run. It was previously gated behind `opts.GateCont !=
   nil` (the read-only instrument); the live faithful roll now reads it on every run, so
   gating it would feed `baseline=0` to the sqrt branch on production runs — the wrong
   branch, biasing ORB. `leagueReboundBaseline` returns a neutral 50.0 for an all-zero
   bundle, so the lift is safe on every fixture.

### Inverted-polarity escape hatch

`FreezeConfig.UnfaithfulOreb` (default `false` = faithful = production) mirrors
`UnfaithfulPutback`: its zero value is NOT "live engine." Set `true` ONLY by the archive
A/B's OFF walk to RESTORE the old linear gate-2 path as the diagnostic baseline. It
consumes no `FreezeMeans` (`validate()` ignores it, mirroring `UnfaithfulPutback` /
`BranchB`), rides the existing `gs.freeze` threading, and is plumbed CLI →
`calibrate.Options.UnfaithfulOreb` → `sim.FreezeConfig.UnfaithfulOreb` (flag
`--unfaithfulOreb`). Production never sets it.

### Read-only instrument retained

The ADR-0057/0058 decomposition instrument (`GateContAccum` / `accumulateGateCont`) is
untouched: it issues no rng draw and measures the per-resolution `gate-1` / `gate-2` /
product regardless of which is live, so it stays byte-identical and is kept for the
isolated per-resolution decomposition. This A/B is distinct: it measures the downstream
full-sim FidelitySummary delta between default-on (gate-1) and `--unfaithfulOreb`
(gate-2).

## Pre-registered success criterion

Written BEFORE the archive run (the gate the Results are judged against):

> - **GATE (hard):** the ON walk's `ORB/POSS` matches real (≈0.158-0.160), distinctly
>   better than the OFF walk's gate-2 deficit (≈0.194). The PR merges on this being the
>   faithful behavior — NOT on aggregate headline improvement.
> - **TRIPWIRE:** `Var(lnPPS)` ≈ real (the ADR-0055 SOLVED constraint, ≈0.001451).
>   Gate-1 touches the ORB determination, not make-value variance — so any material
>   movement is investigate-worthy and must be explained before merge.
> - **REPORTED, NOT GATED:** `Var(lnFGA)`, the −23.5ppg level headline, and the count
>   axis. A faithful fix that only partially closes the count axis still ships (OOS —
>   ADR-0054 / `offVolumeScale` #974).

## Results

Measured as a 2-config A/B (`OFF` = `UnfaithfulOreb` old linear gate-2; `ON` =
zero-Options faithful gate-1 production) over the backup archive at `JSB_ARCHIVE_RUNS=20
JSB_ARCHIVE_STRIDE=1` (seed 20240601), regular bucket, engine. Artifacts:
`engine/internal/validate/testdata/calibration-5.60-20260611-oreb-gate1-faithful-{off,on}.json`.

| Channel (regular bucket, engine) | OFF (gate-2) | ON (gate-1) | real | movement |
|---|---|---|---|---|
| **GATE** ORB/POSS | 0.19414 | **0.15818** | 0.15750 | nails real (gap 0.194→0.0007) |
| TRIPWIRE Var(lnPPS) | 0.001458 | 0.001485 | 0.001451 | **regressed** (gap 7e-6 → 3.4e-5) |
| Cov(ORB/POSS,lnPPS) | −0.000151 | −0.000060 | −0.000115 | **overshot** (over-coupled → under-coupled) |
| REPORTED Var(lnFGA) | 0.001727 | 0.001626 | 0.001330 | narrowed toward real |
| REPORTED Cov(lnFGA,lnPPS) | −0.001120 | −0.001063 | +0.000269 | barely moved (count axis OOS) |
| REPORTED Cov(lnPOSS,lnPPS) | −0.000319 | −0.000320 | +0.000241 | flat (count axis OOS) |

Verdict signals: `orb_moved_toward_real=true  pps_regressed=true`.

**GATE — PASS (decisively).** The faithful gate-1 ON walk reproduces real `ORB/POSS` to
three places (0.15818 vs 0.15750), closing the entire ADR-0056 ~23% offensive-rebound
excess that the OFF (gate-2) walk still carries (0.19414). This matches the ADR-0058
counterfactual prediction (gate-1-alone ≈0.160, gate-2-alone ≈0.194) almost exactly. The
PR merges on this being the faithful behavior.

**OFF reproduces gate-2 to the digit (0.19414).** The `UnfaithfulOreb` OFF walk matches
the ADR-0058 gate-2-alone baseline exactly — the corpus-scale proof that the escape hatch
is leak-free and the baseline threading is correct (a leaky hatch or a mis-threaded
baseline would have shifted OFF off the known 0.194). The unit tests prove the hatch at
the call site (`TestOrebContinuation_LiveGate1Rate` negative path) and
`TestGateCont_Accumulates` proves the unconditional baseline lift; this proves both
across the whole archive.

**TRIPWIRE — fired (small, explained, recorded as a watch-item).** `Var(lnPPS)` widened
from 0.001458 (OFF, ≈ real, the ADR-0055-SOLVED state) to 0.001485 (ON) — a ~1.9%
widening that moves the gap from 7e-6 to 3.4e-5 and touches the ADR-0055 claim that
`Var(lnPPS)` ≈ real. The mechanism is measured, not hypothesized: the curvature channel
`Cov(ORB/POSS,lnPPS)` **overshot** — it went from over-coupled (OFF −0.000151, more
negative than real −0.000115) *past* real to under-coupled (ON −0.000060). The faithful
gate-1 nailed the ORB **mean/level** (the GATE) and slightly **over-corrected the
curvature coupling** — the exact mean-vs-curvature axis ADR-0058 was named for. The
`Var(lnPPS)` widening is the downstream of that same over-correction, not a separate
defect. (That the over-correction co-moves with `Var(lnPPS)` is measured; any deeper
account of *why* the curvature overshoots is hypothesis.)

Mitigation framing: even regressed, ON `Var(lnPPS)` (0.001485) stays within ~2.3% of real
and remains far closer than the pre-ADR-0055 baseline (0.001570). Because the curvature is
now slightly *under* real (not over, as the old gate-2 was), the future lever is
**monitor**, NOT the decay/cap that `standings.go` hints at for the old over-coupling — a
cap would push it further under. Recorded as a watch-item, not a follow-up commitment.

**REPORTED, not gated.** `Var(lnFGA)` narrowed toward real (still high — count axis). The
headline `Cov(lnFGA,lnPPS)` barely moved (−0.001120 → −0.001063, real +0.000269) and the
count residual `Cov(lnPOSS,lnPPS)` is flat — both stay out of scope (ADR-0054 /
`offVolumeScale` #974). A faithful fix that only partially closes the count axis still
ships.

**Verdict: SHIP on RE-faithfulness merits with a curvature caveat** (structurally the
same honest posture as ADR-0055's magnitude caveat). The GATE — the RE-verified faithful
ORB level — passes decisively and is the merge basis; the `Var(lnPPS)` tripwire is a
small, fully-explained curvature over-correction recorded for monitoring, and the
headline/count axis stays REPORTED-not-gated.

## Consequences

- Positive: the engine's offensive-rebound intensity is RE-faithful — the live
  continuation roll is the sqrt gate-1 team-determination 5.60 actually applies, closing
  the ADR-0056 ~23% ORB excess.
- Positive: the faithful path also REDUCES degenerate full-team foul-outs on the thin
  5-man `richBundle` test fixture (measured ~2.25% of team-games under gate-1 vs ~3.25%
  under gate-2) — a clean side-effect of the lower offensive-board credit shortening the
  miss→ORB→retry loop. The non-degeneracy guard was split into a RATE-based test
  (`TestSimulate_FoulOutRate`) because a full foul-out on a 5-man fixture is a
  probabilistic artifact, not a per-seed invariant.
- Negative: the golden fixture (`engine/internal/sim/testdata/golden.json`) changes by
  design (gate-1 ≠ gate-2 at unequal fatigue). `TestDeterminism` and the full non-archive
  suite stay green.

## Lineage

Completes (does not supersede) the ADR-0057 (carrier RE) → ADR-0058 (GO/REPLACE
verdict) program by shipping the swap. Mirrors the ADR-0055 faithful-putback
inverted-polarity escape-hatch precedent.

## References

- `engine/internal/sim/freeze.go` — the swap (`gs.orebProb`), the `UnfaithfulOreb`
  escape-hatch field.
- `engine/internal/sim/gameloop.go` — the unconditional baseline lift.
- `engine/internal/sim/rebound.go` — `gate1Probability` (the live roll), `orebProbability`
  (gate-2, kept for the OFF walk), `leagueReboundBaseline`.
- `engine/internal/sim/rebound_test.go` — `TestOrebContinuation_LiveGate1Rate` (the live
  ORB-rate hard gate + `UnfaithfulOreb` negative path).
- `engine/internal/sim/sim_test.go` — `TestSimulate_FoulOutRate` (foul-out rate guard).
- `engine/internal/calibrate/orebgate1faithful_archive_test.go` — the archive A/B
  (`//go:build archive`).
- `engine/cmd/jsbcalibrate/main.go` — the `--unfaithfulOreb` CLI flag.
- `ibl5/docs/decisions/0057-rebound-continuation-faithfulness.md` — the carrier RE.
- `ibl5/docs/decisions/0058-rebound-continuation-mean-vs-curvature-verdict.md` — the
  GO/REPLACE verdict.
- `ibl5/docs/decisions/0055-faithful-putback-shot-resolution.md` — the inverted-polarity
  escape-hatch + faithful-on-by-default precedent.
