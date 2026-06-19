---
description: Verdict from the L1 gate-1 counterfactual instrument (read-only, golden byte-identical) over the full ~53 GB archive. Measures the mean-inflation vs curvature split ADR-0057 left indeterminate, by computing at every offensive-rebound resolution the live gate-2 (orebProbability), the dropped sqrt gate-1 (FUN_004e22a0), and their product, plus a baseline sweep and a confirming replace-the-roll experiment. TWO findings. (1) CURVATURE RULED OUT — Cov(gate-2,lnPPS) − Cov(product,lnPPS) is +5e-6…−1e-5 across the whole baseline sweep vs Cov(gate-2,lnPPS) ≈ +1.8e-4 (3–8%), with non-trivial Var(gate-1): the sqrt gate-1 does not differentially couple continuation to efficiency, corroborating ADR-0056's faithful ORB-intensity coupling. (2) The L1 fix is LOCATED, and the composition is REPLACE not MULTIPLY. Three candidates for 5.60's per-resolution rebound rate: gate-2 alone (the engine today, 0.428 → ORB/POSS 0.194, 24% high); gate-1×gate-2 (0.154 → ORB ~0.07, undershoots); gate-1 ALONE (0.356 → measured ORB/POSS 0.160 ≈ real 0.158, within 1.5%). So the engine put gate-2's LINEAR formula (off/(off+def)·0.5+0.25) in the rebound-DETERMINATION slot that 5.60 fills with gate-1's SQRT team-pick (FUN_004e22a0) — right role, wrong formula. Decision GO: the fix is a single-site formula swap (orebProbability→gate1Probability) at the static bundle-derived baseline; direction is verified (caller FUN_004d6f00) and the static formula reproduces 5.60's ORB to within 1.5% with NO x32dbg needed for a first cut (x32dbg is an optional refinement to close the last ~1.5% / pin the exact baseline constant). Status Accepted; no engine behavior changed (instrument read-only; the swap is a documented experiment, implemented + gated in a follow-up fix PR).
last_verified: 2026-06-11
---

# ADR-0058: Rebound-continuation verdict — curvature ruled out; the L1 fix is located (REPLACE the engine's linear rebound roll with 5.60's sqrt gate-1)

**Status:** Accepted
**Date:** 2026-06-11

## Context

ADR-0057 (PR #1047) located the last open engine-fidelity defect (the "L1" carrier):
JSB 5.60 resolves an offensive-rebound continuation with **two formulas** — a sqrt
diminishing-returns team-strength pick (**gate-1**, `FUN_004e22a0`, which team wins the
board) and a linear retention roll (**gate-2**, `off/(off+def)×0.5 + 0.25`,
`FUN_004d6f00`). The engine's single rebound roll (`orebProbability`, at `possession.go`'s
`gs.rebound`) uses gate-2's **linear formula and constants**; ADR-0057 read this as "the
engine models gate-2 and *drops* gate-1," and split the dropped gate into two candidate
mechanisms — **mean-inflation** and **curvature-over-coupling** — declaring the split
**indeterminate from static decompile** and gating the fix on a dynamic instrument.

This PR built that instrument (read-only, golden byte-identical, ADR-0049/0056
precedent): at every offensive-rebound resolution the sim computes — issuing **no rng
draw** — the live gate-2 probability, the **counterfactual** gate-1 (`gate1Probability`,
the sqrt team-pick), and their product, keyed by offensive team, folded into the
`decomposeGateContinuation` discriminator. Because gate-1's baseline term reads two
**loader-populated** globals (`+0x6818`/`+0x6848`, master-ref 186-187) unpinned in the
static decompile, the archive walk **sweeps** the baseline. This ADR records the verdict.

## The measured verdict (regular bucket, committed artifact — runs 20, stride 1, N=484)

`engine/internal/validate/testdata/calibration-5.60-20260611-gate-continuation.json`.
Engine ORB/POSS = **0.19414**, real (.sco) = **0.15750** (ADR-0056). Baseline sweep
(mean gate-1 / per-resolution reductionFrac / curvature `Cov(g2,lnPPS) − Cov(prod,lnPPS)`;
`Cov(g2,lnPPS) ≈ +1.8e-4` throughout):

| gate-1 baseline | mean gate-1 | mean gate-2 | mean product | Var(gate-1) | curvature Δ |
|---|---|---|---|---|---|
| bundle-derived (~36) | 0.3565 | 0.4280 | 0.1539 | 0.000629 | +0.0000047 |
| 40 | 0.3671 | 0.4280 | 0.1584 | 0.000574 | +0.0000058 |
| 60 | 0.4431 | 0.4280 | 0.1907 | 0.000363 | +0.0000143 |
| 80 | 0.5308 | 0.4280 | 0.2282 | 0.000339 | +0.0000015 |
| 95 | 0.5984 | 0.4280 | 0.2571 | 0.000330 | −0.0000095 |

### Finding 1 — CURVATURE mechanism RULED OUT (the load-bearing positive)

`Cov(gate-2,lnPPS) − Cov(product,lnPPS)` is **+5e-6 … −1e-5** across the **entire**
baseline sweep — **3–8% of `Cov(gate-2,lnPPS) ≈ +1.8e-4`**, without a consistent sign.
`Var(gate-1)` is **non-trivial** (3.3–6.3e-4), so this is real, not a degenerate-variance
artifact: gate-1 (driven by rebound strength) is roughly **orthogonal** to scoring
efficiency, so the sqrt form's curvature is **not** the L1 carrier. This **corroborates
ADR-0056** (ORB-intensity coupling faithful: `Cov(ORB/POSS,lnPPS)` engine −0.000151 vs
real −0.000115 — the same regular bucket, in this artifact).

### Finding 2 — the fix is LOCATED, and the composition is REPLACE, not MULTIPLY

The instrument's per-resolution means rank the three composition candidates against the
real ORB level directly. Per-resolution rate → ORB/POSS (the engine's 0.428 roll produces
0.194, so the ratio is ≈ rate/0.428 × 0.194):

| candidate | per-resolution rate | ORB/POSS | vs real 0.158 |
|---|---|---|---|
| gate-2 alone (engine **today**) | 0.4280 | 0.19414 | 24% **too high** |
| gate-1 × gate-2 (ADR-0057's implied premise) | 0.1539 | ~0.07 | ~2.3× **too low** |
| **gate-1 alone** (replace the roll) | 0.3565 | **0.160 (measured)** | **within 1.5% ✓** |

`mean gate-1 / mean gate-2 = 0.3565/0.4280 = 0.833 ≈ real/engine ORB = 0.811`, so 5.60's
realized ORB tracks **gate-1 alone**. A confirming experiment (below) — temporarily
pointing the live `gs.rebound` roll at `gate1Probability` and walking the archive — lands
**ORB/POSS = 0.160 vs real 0.158**.

So ADR-0057's "drops gate-1" framing is sharpened: the engine's single rebound roll is the
**right role** (5.60 also resolves the board with one determination roll — gate-2's
retention roll governs putback-vs-reset, not the ORB *count*, which is why real ORB tracks
the determination gate alone) but the **wrong formula** — it copied gate-2's **linear**
`off/(off+def)×0.5+0.25` into the slot 5.60 fills with gate-1's **sqrt** team-pick. The
**multiplicative** reading (gate1×gate2) is *refuted* — it undershoots 5.60's own ORB ~2.3×.

### Direction verified (not inverted)

The caller `FUN_004d6f00` routes the offense-won-board branch on `puVar3 == C[+0x33e4]`
(`jsb560_decompiled.c:92181`) — that branch holds the gate-2 retention roll
(`92201-92203`) and the retain-flag/ball-handler set (`92204-92205`). `value` in
`FUN_004e22a0` rises with offensive rebound strength, so **P(offense wins) = value/100**
(offense wins when `value ≥ roll`); the measured mean gate-1 ≈ 0.36 ≈ the offensive-rebound
**share** confirms it (the inverted reading implies a nonsensical 0.64 offensive share).

## Decision

**GO — the L1 fix is a single-site formula swap.** A follow-up PR replaces the live
rebound-determination roll in `possession.go`'s `gs.rebound` from `orebProbability` (the
linear gate-2 copy) with `gate1Probability` (5.60's sqrt gate-1, `FUN_004e22a0`), at the
bundle-derived `leagueReboundBaseline`. The static formula + ratings-derived baseline
reproduces 5.60's ORB/POSS to **within 1.5%** (0.160 vs 0.158), so **no x32dbg pin is
required for a first cut**. x32dbg remains an **optional refinement** — to close the last
~1.5% and pin the exact loader baseline (`+0x6818`/`+0x6848`/`_DAT_00669f40`) — not a
prerequisite. The follow-up PR owns the behavior change: a faithfulness gate, a full
re-baseline (Var(lnFGA)/possession-count axes), a golden regen, and an A/B reusing this
instrument's `decomposeGateContinuation` discriminator. THIS PR is read-only.

## Confirming experiment (reproducible)

Temporarily change the live roll in `engine/internal/sim/possession.go` `gs.rebound`:

```go
// from:
if gs.rng.Float64() < gs.orebProb(offStr, defStr) {
// to:
if gs.rng.Float64() < gate1Probability(offStr, defStr, gs.gateBaseline) {
```

then walk a strided archive subset (the gate instrument sets `gs.gateBaseline`):

```
cd engine && JSB_ARCHIVE_DIR=<dir> JSB_ARCHIVE_RUNS=10 JSB_ARCHIVE_STRIDE=50 \
  go test -tags archive ./internal/calibrate -run GateContinuation -v
```

The regular-bucket `engine ORB/POSS` reads **0.160** (vs real 0.158). Revert the edit
afterward — the swap is the follow-up fix PR's deliverable, not this instrument's.

## Alternatives Considered

- **Verdict = "multiplicative gate1×gate2, NO-GO until x32dbg."** Rejected: the product
  undershoots 5.60's own ORB ~2.3× at every baseline, but **gate-1 alone reproduces it to
  1.5%** — the composition is replace, and the static formula suffices for a first cut, so
  the verdict is GO, not NO-GO.
- **Tune the baseline to make the *product* match.** Rejected: mean gate-1 saturates at
  0.60 < the ≥0.81 anchor, so no baseline makes the product reach real — the gap is
  composition (replace vs multiply), not baseline.
- **Emit the gate probabilities on `result.Event`.** Rejected: that is the
  golden-serialized payload; the instrument uses an `Options`-pointer side-channel (the
  `FreezeAccum` precedent) so goldens stay byte-identical (verified by `TestGolden`).
- **Implement the swap in THIS PR.** Rejected: the swap is a behavior change needing its
  own faithfulness gate, re-baseline, golden regen, and A/B — the read-only instrument
  locates the fix; the fix PR lands it.

## Consequences

- The L1 program advances to a **build** PR (the formula swap), not more RE. The instrument
  + `decomposeGateContinuation` + the gate side-channel are reusable as that PR's A/B.
- ADR-0056's faithful ORB-intensity coupling is independently corroborated (Finding 1).
- The fix is small and static-calibratable (single-site, one formula, ratings-derived
  baseline) — a much stronger outcome than the indeterminacy ADR-0057 left.
- The instrument is read-only: goldens byte-identical (`TestGolden`); the always-on
  `TestGateContinuationArtifact_Sane` guards the committed artifact on every default build.

## Reproduce

```
cd engine
go test ./internal/sim -run Golden                 # read-only proof (no -update)
go test ./internal/validate ./internal/calibrate   # unit incl. gate math + decompose + artifact guard
JSB_ARCHIVE_DIR=<dir> JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
  go test -tags archive ./internal/calibrate -run GateContinuation -v -timeout 6h
```

## References

- `ibl5/docs/decisions/0057-rebound-continuation-faithfulness.md` — the gate that located the carrier (its "drops gate-1 → reproduce two-gate" framing is sharpened here to "wrong formula in the determination slot → swap it").
- `ibl5/docs/decisions/0056-continuation-chain-orb-intensity-verdict.md` — the ORB-intensity coupling Finding 1 corroborates.
- `engine/docs/l1-rebound-continuation-faithfulness.md` — the ADR-0057 RE trace (updated with this resolution).
- `engine/internal/sim/rebound.go` — `gate1Probability` (FUN_004e22a0 port) + `leagueReboundBaseline` (the swap target).
- `engine/internal/calibrate/standings.go` — `decomposeGateContinuation` (the discriminator).
- `engine/internal/validate/testdata/calibration-5.60-20260611-gate-continuation.json` — the committed verdict anchor.
- `~/Downloads/jsb_560/decompiled/jsb560_decompiled.c` — `FUN_004e22a0` @ 97322-97410, caller `FUN_004d6f00` @ 92131 (direction verification).
