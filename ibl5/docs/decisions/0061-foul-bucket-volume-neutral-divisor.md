---
description: The foul-bucket offQ divisor (offQualityWithHCA) is replaced by a volume-NEUTRAL constant base (offQualityConstant), faithful to 5.60's dead-zero +0xDE0; this measurably relaxes (≈30% on the shots-per-poss axis, not a sign flip) the Fork-B foul-share volume-inversion the old per-player ΣOO·scale divisor injected, re-homes the GATE-1 home-margin calibration knob from offQualityRatingScale to offQualityConstant (corpus stand-in, x32dbg pin is a follow-up), and partially supersedes ADR-0044's off-side compression.
last_verified: 2026-06-12
---

# ADR-0061: Foul-bucket volume-neutral divisor (Fork-B carrier fix)

**Status:** Accepted
**Date:** 2026-06-12

## Context

The FTA/FGA foul-share inversion — engine `corr(roster volume, foulShare)` **−0.357**
vs real **+0.161** (gap −0.518) — was localized by the 2026-06-12 JSB 5.60 RE verdict
(`~/jsb-foulfork-RE-verdict-20260612.md`, decompile + asm confirmed) to the **foul
bucket's offQ divisor**, `offQualityWithHCA` (`engine/internal/sim/teamquality.go`).

Two candidate carriers were RE'd against the 5.60 binary ("possibly both"):

- **Fork A** — `twoPtBucketWeight` (`bucketweights.go`) vs 5.60 `+0xD90` composite
  (`FUN_004cfa50`). **Verdict: FAITHFUL** (byte-identical formula). The measured 2GA/POSS
  over-coupling (+0.140) is a *downstream consequence* of Fork B collapsing the foul
  bucket for high-volume teams — freed bucket mass renormalizes into the 2pt path — not a
  Fork-A formula defect.
- **Fork B** — `offQualityWithHCA`, the `foul/offQ` divisor, vs 5.60 `FUN_004e3f80`.
  **Verdict: THE SOLE CARRIER.** The foul-bucket *shape* is faithful (`foul =
  (foul_base/offQ)·(defQ − teamDef·5/6) + foul_base`, structurally byte-identical to Go
  `foulBucketWeight`). The **divisor INPUT** diverged:

| term | 5.60 | Go (before this ADR) |
|------|------|----|
| `foul_base` (numerator) | floored 0.6 (the `+0xDE0` composite is dead-zero) | `foulFloor = 0.6` ✓ faithful |
| `offQ` (divisor) | `Σ offensive players' +0xDE0` − HCA → **Σ(0) − HCA = a floored constant** | `Σ floor1(OO)·offQualityRatingScale` ≈ 1.65 |

**`+0xDE0` is dead-zero** (proven binary-wide in the RE): every write is a `=0` init, a
Branch-B `×dVar59` scale (`0×x=0`), or a verbatim struct copy — never computed from
stats. So 5.60's offQ is **volume-NEUTRAL**. The Go port instead summed the offensive
`OO` rating, a divisor **+0.62-correlated with roster volume**. That substitution INJECTS
a foul anti-coupling 5.60 does not have: high-volume → large offQ → `foul/offQ` shrinks →
the foul bucket collapses → `corr(vol,foulShare) −0.357`. This is the entire inversion.

## Decision

Replace the per-player `Σ floor1(OO)·offQualityRatingScale` divisor base + `compressQuality`
call in `offQualityWithHCA` with a single **volume-neutral constant**, `offQualityConstant`,
faithful to 5.60's dead-zero `+0xDE0`:

```go
func offQualityWithHCA(offense []onCourt, hcaDelta float64) float64 {
	total := offQualityConstant
	total -= float64(len(offense)) * hcaDelta // HCA shrinks the home divisor
	if total < offQualityFloor {
		return offQualityFloor
	}
	return total
}
```

The foul weight reduces to the **defense-driven** `0.6 + (0.6/offQualityConstant)·(defQ −
teamDef·5/6)`, with the home-favorable HCA term `−len·hcaDelta` and the `offQualityFloor`
clamp unchanged. `defQ` (`defMatchupQuality`) stays in the numerator — the **intended**
defense-driven coupling (RE: "leave it"). The three now-dead off-side consts
(`offQualityRatingScale`, `offQualityNeutralRatingSum`, `offQualityNeutral`) are deleted.

**Chosen: a pure constant base** (faithful to the dead-zero `+0xDE0`).
**Rejected: a centered, non-volume per-player term** (subtract each player's OO from the
league mean — volume-neutral but still per-player). Rejected because (a) 5.60 has no
per-player term at all (the input is a struct-copied zero); (b) it reintroduces a tunable
second axis (per-player slope × center) that re-entangles GATE-1 margin with GATE-2
dispersion — the non-orthogonality ADR-0044 already fought; (c) measurement confirmed the
pure constant preserves FTA dispersion and the home-favorable sign, so the more complex
form buys nothing.

### GATE-1 — home margin re-calibration

`offQualityConstant` becomes the home-court-margin calibration knob that
`offQualityRatingScale` was. The home divisor shrinks by the fixed `len·hcaMagnitude`
(5×0.2 = 1.0), so a **smaller** constant makes that subtraction a larger fraction of the
divisor → a **larger** home margin (steeply sensitive — the documented low-divisor
brittleness, carried from `offQualityRatingScale`). `hcaMagnitude=0.2` (`gametype.go`) is
the faithful decompiled constant and stays fixed.

### GATE-2 — marginals preserved (coupling-only fix)

This is a **coupling** fix (right spread, wrong teams): it must preserve the FTA *level*
(ADR-0045) and not regress the FTA *dispersion* (ADR-0044). Because a constant divisor has
**zero** team-to-team spread, it cannot widen FTA dispersion; the sweep confirmed the gt2
FTA-dispersion ratio **improves** from master's 2.573 to 2.045 at the committed constant
(stays ≫ 1.0 — does not collapse — and drifts *toward* 1.0, a side-benefit, since the
foul-path *share* still varies with the non-foul buckets — the structural residual
ADR-0044 §Calibration recorded as the floor `foulCompress` could not breach). The FTA
*level* moves toward real too: `level_gap_pf` −2.55 (master) → −2.38 at the committed
constant (the engine still under-fouls; the fix narrows that gap).

GATE-2 reads the sweep at runs=20/stride=1 and compares against the committed `branchB-off`
fixture (`internal/validate/testdata/calibration-5.60-2026061*-branchB-off.json`, gt2
2.659 at runs=50/stride=1). ADR-0044 established these dispersion metrics are **runs-stable**
(runs=10/stride=1 reproduces runs=50/stride=1: FTADisp 1.98 vs 1.98), so runs=20 is
comparable to the runs=50 baseline — the verdict is not runs-limited.

### Calibration (dev/local; CI lacks the 53 GB archive)

`jsbcalibrate --mode calibrate --selection season --archive ibl5/backups`, recompile per
candidate (`engine/sweep-offq.sh`; threading a runtime `*float64` override through
`gameState → foulBucketWeight → offQualityWithHCA` is ~9 sites + 2 faithful-function
signature changes — out of proportion to a measurement seam, so each candidate is a
rebuild. A committed archive-tagged Go sweep harness mirroring
`offvolumescale_sweep_archive_test.go` is a follow-up).

**Sweep** (runs=20, stride=1, seed=20240601, archive `--archive ibl5/backups`, 2026-06-12;
sco gt2 home margin 4.124, gt4 4.590). Synthetic-fixture guards: foul-mix minority share
(`TestBucketWeights_FoulPathMix`, ≤0.25) and full-team foul-out rate
(`TestSimulate_FoulOutRate`, ≤0.08):

| offQualityConstant | gt2 gap | gt4 gap | gt2 FTA disp | foul-out rate | foul share | guards |
|--------------------|---------|---------|--------------|---------------|------------|--------|
| 1.50  | **+0.020** | −0.383 | 2.096 | 0.130 | 0.274 | ✗ foul-out, ✗ band |
| 1.525 | −0.249 | −0.585 | 2.078 | 0.102 | 0.265 | ✗ foul-out, ✗ band |
| 1.55  | −0.431 | −0.850 | 2.063 | 0.079 | 0.257 | ✗ band |
| **1.575** | **−0.645** | **−0.995** | **2.045** | **0.063** | **0.249** | **✓ both** |
| 1.60  | −0.812 | −1.069 | 2.033 | ~0.05 | 0.242 | ✓ both |
| *master* | *−0.875* | *−1.266* | *2.573* | *~0.022* | *n/a* | *n/a* |

**Committed `offQualityConstant = 1.575`** — the **smallest** constant (= largest home
margin, GATE-1's pull) that clears **both** synthetic degeneracy guards. Smaller constants
bring gt2 within ±0.5 (1.50 → +0.02) but trip a genuine foul-heavy degeneracy: the
full-team foul-out rate jumps to 0.130 at 1.50 (6× master's 0.022, far above the 0.08
degeneracy ceiling), and the foul-mix minority band breaks at 0.257 (1.55). gt2
`fta_dispersion_ratio` (2.045) improves on the `branchB-off` baseline (2.659) — GATE-2 met.

**GATE-1 not literally met, but improved (finding).** No constant satisfies GATE-1's ±0.5
AND the degeneracy guards: ±0.5 needs const ≲ 1.55, the foul-out guard needs ≳ 1.56, the
minority band needs ≳ 1.572. At the committed 1.575, gt2 gap −0.645 / gt4 −0.995 are
outside ±0.5 — but **master is worse** at matched config (gt2 −0.875, gt4 −1.266), so this
is a **pre-existing HCA undershoot** (the engine's home margin is too small against this
corpus) that the volume-neutral fix *narrows* (gt2 +0.23, gt4 +0.27) but does not close.
Closing it needs the true pinned offQ/defQ/teamDef values (the x32dbg follow-up), not a
smaller stand-in that would re-introduce foul degeneracy. The band was **not** widened
(plan Phase 4a — no silent widen; and the foul-out guard is a real degeneracy signal, not a
relaxable heuristic). gt-4 is pre-existing out of band at master and is **not chased**
(consistent with ADR-0044).

## Relationship to ADR-0044 (partial supersede)

ADR-0044 (Lever-2) narrowed the foul-bucket team-quality dispersion via `foulCompress`,
acting on **both** `offQualityWithHCA` (off side) and `defMatchupQuality` (def side), and
recorded that the lever "works mainly through the uncapped offQ divisor" (the def cap binds
~78% of teams, making def-side compression largely inert).

**ADR-0061 supersedes ONLY ADR-0044's off-side story.** offQ is now a volume-neutral
constant with zero team-to-team spread, so there is nothing on the off side to compress:
`foulCompress` applies to `defQ` only, and the off-side neutral references
(`offQualityNeutral`, `offQualityNeutralRatingSum`) are deleted. What **survives** from
ADR-0044, unchanged:

- the **def-side** `foulCompress` compression (still applied to `defMatchupQuality`),
- the **FTA-dispersion target** (`FidelitySummary.FTADispersionRatio`), and
- ADR-0044's three-axis dispersion verdict and its honest null on total `Var(lnPF)`.

Note ADR-0044's claim that the lever works "mainly through the uncapped offQ divisor" no
longer describes current behavior — the constant divisor removes the off-side spread
entirely, so the residual FTA dispersion now comes from the **non-foul bucket interaction**
(the structural residual ADR-0044 already identified as the `foulCompress` floor), not the
offQ divisor.

## Out of Scope

- **Fork-A `dVar60`** (GP-vs-MIN possession-rate divisor in `twoPtBucketWeight`) — the
  RE verdict found Fork-A faithful; the +0.140 2GA/POSS over-coupling is a downstream
  consequence of Fork-B resolved by this fix. Separate fidelity item if dispersion needs
  it.
- **TOV over-coupling** (engine +0.163 vs real −0.176) — cancels in the FTA/FGA ratio, not
  this carrier. Own RE later.
- **`defMatchupQuality` / `defQ`** — stays in the foul-divisor numerator (intended
  defense-driven coupling). Untouched.
- **`bands.go` regeneration** — decided NO: `internal/validate/bands.go` per-stat residual
  tolerances are runs-stable, GATE-2 is level-preserving (ADR-0045), and the `fta`/`pf`
  bands carry wide AbsFloor gaps that absorb the small foul-share redistribution. No
  CI-gated test reads the committed dispersion fixtures. bands.go retains its dated
  `offQualityRatingScale = 0.059` provenance comment as a historical audit record of the
  #956/#957 calibration run — not current-mechanism prose, so it is left intact.

## Follow-up — pin the true value via dynamic RE (x32dbg)

`offQualityConstant` (and the foul-divisor numerator's effective base, `defQ`, `teamDef`)
are **corpus-calibrated stand-ins** — the same status `offQualityRatingScale` held. The RE
proved offQ is volume-neutral (a constant) but did **not** pin its VALUE: the static
decompile gives `offQ = Σ(+0xDE0=0) − HCA`, which taken literally floors degenerately, so
there is an unrecovered base/init term, or `+0xDE0` is non-zero at the read. **Only
execution resolves this.** A follow-up should attach **x32dbg** to `jumpshot.exe`, break at
`FUN_004e3f80` (offQ), `FUN_004e3d90` (defQ), and the foul site (`:97163`) during a live
possession, and read the actual values. That would:

- replace `offQualityConstant`/`defQ`/`teamDef` stand-ins with faithful pinned values,
- likely close the residual GATE-1 home-margin undershoot (faithful, not calibrated), and
- let the two synthetic degeneracy guards (`TestBucketWeights_FoulPathMix` ceiling,
  `TestSimulate_FoulOutRate`) be re-derived from the faithful foul rate or dropped.

(A model — even a higher tier — cannot shortcut this: it cannot execute the binary, and the
value is runtime/data-dependent, not statically recoverable.)

## Objective verification (the coupling, measured)

The fix's *purpose* is to relax the wrong-signed foul-share coupling, so it is measured
directly (gt2, runs=20/stride=1, master vs committed 1.575; real in parens):

| gt2 covariance | master | fix (1.575) | real | move |
|----------------|--------|-------------|------|------|
| `Cov(lnFGA,lnPPS)`              | −0.00106 | −0.00081 | +0.00027 | +24% toward real |
| `Cov(ln(shots/poss),lnPPS)`     | −0.00074 | −0.00052 | +0.00003 | +30% toward real |
| `Cov(lnPOSS,lnPPS)`             | −0.00032 | −0.00028 | +0.00024 | slight toward real |

The shots-per-possession term — the exact FTA/FGA foul-share axis the RE named as Fork-B's
injected anti-coupling — moves ~30% toward real. This is a **measured partial relaxation**,
NOT a sign flip: the covariance stays negative, consistent with ADR-0044's finding that the
47.8% non-foul-arm residual keeps `Cov(lnFGA,lnPPS)` from reaching the real positive value
from any single lever. So the fix corrects the *mechanism* (volume-neutral divisor, the
faithful shape) and measurably *reduces* the symptom, but does not eliminate it — closing
the rest needs the true pinned values (x32dbg follow-up) and the orthogonal residual arms,
not this carrier alone.

## Consequences

- The foul bucket is offense-volume-NEUTRAL: the volume-coupled `Σ floor1(OO)·scale` divisor
  is gone, and the foul-share/shots-per-poss anti-coupling it injected is measurably reduced
  (above) — though not flipped. The Fork-A 2GA/POSS over-coupling (the downstream symptom)
  relaxes on the same shots-per-poss axis.
- The home-margin calibration knob moves from `offQualityRatingScale` to
  `offQualityConstant`; the three off-side consts are deleted, and the off-side
  `TestDeriveQualityNeutrals` derivation is stripped (the harness now derives only
  `defQualityNeutral`).
- The golden fixture (`internal/sim/testdata/golden.json`) was regenerated (intentional
  output change — the foul-bucket redistribution).
- A committed calibration artifact (`internal/validate/testdata/calibration-5.60-20260612-offQualityConstant.json`)
  records the chosen value's full report.

## References

- `~/jsb-foulfork-RE-verdict-20260612.md` — the Fork-A/Fork-B RE verdict (the carrier
  naming).
- ADR-0044 (foul-bucket quality compression — the off-side story this partially
  supersedes), ADR-0043 (foul-arm attribution), ADR-0045 (FTA level preservation).
- `engine/internal/sim/teamquality.go` (`offQualityConstant`, `offQualityWithHCA`),
  `engine/internal/sim/bucketweights.go` (`foulBucketWeight` consumer),
  `engine/internal/sim/neutral_archive_test.go` (def-only neutral derivation),
  `engine/sweep-offq.sh` (the recompile-per-candidate calibration harness).
