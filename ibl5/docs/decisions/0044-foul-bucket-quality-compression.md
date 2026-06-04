---
description: Lever-2 narrows the foul-bucket team-quality dispersion (foulCompress) toward the corpus league mean, calibrated to the engine's too-wide FTA-rate dispersion; the paired offVolumeScale raise is refuted on its own Var(lnFGA) target, and the Cov(lnFGA,lnPPS) sign is the emergent (never tuned) readout. Records the partial three-axis verdict.
last_verified: 2026-06-04
---

# ADR-0044: Foul-bucket team-quality compression (Lever-2)

**Status:** Accepted
**Date:** 2026-06-04

## Context

ADR-0041 located the engine's team-scoring defect on **three orthogonal axes**:
`Var(lnFGA)` and `Var(lnPPS)` both ~2–2.6× too WIDE, AND `Cov(lnFGA,lnPPS)`
wrong-signed (real +, engine −). The wrong-signed covariance is the load-bearing
one: it CANCELS the two too-wide marginals, so the engine's **total** scoring
spread `Var(lnPF) = Var(lnFGA) + Var(lnPPS) + 2·Cov` collapses to ~3.4× too NARROW.
ADR-0042 named the mechanism (a missing volume-rate→shot-COUNT pathway); ADR-0043's
freeze lattice attributed the wrong-signed covariance, finding the **foul-only arm is
47.6% of |Cov|** — the single largest contributor.

ADR-0043's pre-registered follow-on was a **Lever-2 pair**:

- **Lever-2(1)** — narrow the foul-rate dispersion (cut the negative-Cov foul arm).
- **Lever-2(2)** — raise `offVolumeScale` (the volume→count channel) to add back
  make-COUPLED dispersion, the only lever that can flip the Cov sign.

This ADR records what each lever was calibrated against, the **independent-target**
discipline (each knob to its OWN corpus target; the Cov sign is the emergent check,
never a tuning knob — the metric-gaming ADR-0041 forbids), and the honest partial
verdict.

## Decision

### Lever-2(1) — `foulCompress` (LANDED)

A mean-preserving compression of the two team-quality aggregators
(`offQualityWithHCA`, `defMatchupQuality`, `sim/teamquality.go`) toward the corpus
league mean:

```
compressQuality(total, neutral, factor) = total + (factor−1)·(total − neutral)
```

- `factor == 1.0` is the **exact** floating-point identity (the `(factor−1)` term is
  `0×x`), so the change is byte-stable at the identity and the matrix-row-6 test
  locks it.
- `factor < 1.0` pulls each team's quality value toward `neutral`, scaling the
  team-to-team **spread** by `factor`. That spread drives the foul-bucket divisor
  term `(foul/offQ)·(defQ − teamDef·5/6)` — the ADR-0043 foul arm — so narrowing it
  narrows the engine's too-wide FTA-rate dispersion.
- **Committed `foulCompress = 0.45`** (see Calibration below).

**Independent target (Constraint 1):** the corpus team-level **FTA-rate dispersion**,
measured by the new `FidelitySummary.FTADispersionRatio = stdev(engine FTA/g) /
stdev(sco FTA/g)` (Step 2 of this PR; `ftaFor` mirrors `fgaFor`). Baseline ratio ≈
**2.9 (gt 2)** — the engine's team-to-team FTA spread is far too WIDE — so
`foulCompress < 1.0` is the corpus-faithful direction. A target cannot be calibrated
to if it cannot be measured; this metric is added in the same PR.

**Neutral references (mean-preservation).** Compression mean-preserves only when
`neutral` is the true corpus league mean of the value compressed. Both were DERIVED
from the .sco archive (`TestDeriveQualityNeutrals`, package `sim`, archive-tagged; 10
seasons 88-89…06-07, 269 team-snapshots), the .sco analog of how `offVolumeNeutral=161`
was derived:

- `offQualityNeutral = offQualityNeutralRatingSum (29.24) × offQualityRatingScale`.
  Stored in **rating space** so it **co-varies** when `offQualityRatingScale` is
  re-tuned (Constraint 2) — the compression stays mean-preserving without
  re-derivation. A drift guard in the harness (±20%) catches any desync.
- `defQualityNeutral = 8.21` (mean PRE-cap total; compression is applied before the
  cap). **The def cap binds for ~78% of teams**, so the post-cap def output is already
  near-constant at the 7.5 ceiling and the def-side compression is **largely inert**
  — it only pulls the below-cap minority up toward the cap. The lever therefore works
  mainly through the **uncapped offQ** divisor.

**HCA stays outside the compression (Constraint 2).** In `offQualityWithHCA` the
quality sum is compressed first, then the fixed `len(offense)·hcaDelta` HCA subtraction
is applied. So the home/away divisor delta is exactly `len·hcaMagnitude` regardless of
`foulCompress` — the #955-calibrated home-margin magnitude and home-favorable sign are
preserved. By Jensen the mean home **margin** still drifts (it scales ∝ ~1/offQ²); that
is handled empirically (see Calibration / margin).

### Lever-2(2) — raising `offVolumeScale` (REFUTED on its own target)

`offVolumeScale` stays at the ADR-0042 minimal-presence floor **0.02**. Its OWN target
(Constraint 1) is `EngineVarLnFGA → RealVarLnFGA`. The archive sweep refuted the raise:

- After `foulCompress=0.45`, `EngineVarLnFGA` (≈0.00269 committed, gt 2) is still
  **ABOVE** real (0.00133) — `foulCompress` narrows it but **never undershoots**, so
  there is no room to refill with make-coupled dispersion. The foul arm is simply not a
  large enough share of total FGA variance.
- A `0.02→0.14` sweep (at `fc=0.45`) only widens `VarLnFGA` further from real
  (0.00265→0.00392) and does **not** improve Cov (−0.00176→−0.00189).

Raising it would therefore push `VarLnFGA` AWAY from its own target purely to chase the
emergent Cov flip — the metric-gaming Constraint 1 / ADR-0041 forbid. So Lever-2(2) is
recorded as refuted, not applied.

### Calibration (dev/local; CI lacks the 53 GB archive)

`jsbcalibrate --mode calibrate --selection season --archive ibl5/backups`.

**`foulCompress` sweep** (runs=10 stride=4, search-grade — the qualitative shape):

| fc | FTADisp (gt2) | EngineVarLnFGA (gt2) | Cov (gt2) | MarginGap (gt2) |
|----|---------------|----------------------|-----------|-----------------|
| 1.0 (baseline) | 2.90 | 0.00405 | −0.00334 | −0.08 |
| 0.60 | 2.29 | 0.00298 | −0.00212 | −0.43 |
| **0.45** | **2.06** | **0.00265** | **−0.00176** | **−0.49** |
| 0.34 | 1.90 | 0.00249 | −0.00158 | −0.60 |
| 0.25 | 1.80 | 0.00241 | −0.00149 | −0.61 |
| 0.15 | 1.69 | 0.00232 | −0.00140 | −0.71 |

`fc` was chosen by its FTA target **bounded by the gt-2 margin-in-band constraint**.
**FTADispersionRatio floors around ~1.5–1.7** as `fc→0` — `foulCompress` narrows the
FTA spread substantially (2.9→~2.0) but **cannot reach 1.0**: a structural residual
(the foul-path SHARE still varies with the non-foul buckets) that one quality knob does
not address. Recorded as a Constraint-3 limitation, not forced.

### Three-axis verdict (Constraint 3 — NOT a binary on the flip)

Committed values `foulCompress=0.45`, `offVolumeScale=0.02`,
`offQualityRatingScale=0.0565`. Numbers read at `runs=20 stride=1` (full season
sample); the metrics are **runs-stable** — `runs=10 stride=1` reproduces the
`runs=50 stride=1` verdict at the reference point (e.g. gt-2 `Var(lnFGA)` 0.00256 vs
0.00254, `Cov` −0.00155 vs −0.00152, `FTADisp` 1.98 vs 1.98), so stride=1 is the
authoritative config and per-run noise is not the limiting factor.

**gt 2 (regular season, N=484 team-snapshots):**

| Axis (gt 2) | Real | Baseline (fc=1.0, scale=0.059) | Committed (fc=0.45, scale=0.0565) | Move |
|---|---|---|---|---|
| `Var(lnFGA)`        | 0.00133 | 0.00361  | 0.00269  | ↓ toward real (2.7×→2.0× wide) |
| `Var(lnPPS)`        | 0.00145 | 0.00281  | 0.00163  | ↓ to ≈real (1.9×→1.1×) **fixed** |
| `Cov(lnFGA,lnPPS)`  | +0.00027| −0.00271 | −0.00167 | toward + (≈halved, **no flip**) |
| **`Var(lnPF)`**     | 0.00332 | 0.00101  | 0.00097  | **flat (~3.4× too NARROW)** |
| `FTADispersionRatio`| 1.0     | 2.79     | 2.07     | ↓ toward 1 (partial; floors ~1.5) |
| `VolumeDispersionRatio` | 1.0 | ~1.17    | 0.99     | → ≈1 |
| `EfficiencyDispersionRatio` | 1.0 | 1.70 | 1.17     | ↓ toward 1 |
| `MarginGap` (gt 2)  | 0       | −0.354   | −0.229   | restored, **in ±0.5** |
| `MarginGap` (gt 4)  | 0       | −0.591   | −0.573   | ≈baseline (pre-existing out) |

gt 4 (playoffs, N=194) committed fidelity: `FTADisp` 1.73, `VolDisp` 0.98, `EffDisp`
0.88, `Var(lnPF)` 0.00134 vs real 0.00455 (same ~3.4× too-narrow total spread).

The honest verdict (all from→to read at one config, never across mixed run/stride):

- **`Var(lnPPS)` is FIXED.** Engine ≈ real (gt 2: 0.00163 vs 0.00145), down from ~2×
  too wide — the too-wide efficiency marginal that ADR-0041 flagged.
  `EfficiencyDispersionRatio` 1.70→1.17.
- **`Var(lnFGA)` narrows** toward real (≈2.6×→≈1.9× too wide); `VolumeDispersionRatio`
  ≈1.0.
- **`Cov(lnFGA,lnPPS)` moves toward + but does NOT flip** (roughly halved): the
  ADR-0043 foul arm is cut, but the 47.8% non-arm residual keeps it negative.
- **`Var(lnPF)` is essentially UNCHANGED at ~3.4× too NARROW.** This is the key honest
  finding: the marginal-narrowing and the Cov-toward-zero move OPPOSE each other in the
  identity `Var(lnPF)=Var(lnFGA)+Var(lnPPS)+2·Cov`, so the collapsed total-scoring
  spread does not lift. `foulCompress` fixes the *shape* of the dispersion (marginals,
  efficiency spread, foul rate) without fixing the total-spread *magnitude* — which
  requires the Cov SIGN to flip, and ADR-0043's 47.8% non-foul residual means no single
  arm does that.

This is a valid, shippable partial verdict (Constraint 3): a real fix to two of the
three axes' marginals, an honest null on the total-spread magnitude, continuing the
0040→0043 chain.

**HCA margin (Constraint 2).** `foulCompress=0.45` regresses the gt-2 home margin out
of band (the compression net-weakens the home foul advantage), restored with one
`offQualityRatingScale` step down (the co-varying neutral auto-updates, drift-guard
stays green). **gt 4 (playoffs) was already out of ±0.5 at `fc=1.0` (master)** — a
pre-existing condition, not introduced here; the scale step helps it but does not reach
±0.5, recorded as pre-existing.

**Structural non-orthogonality (Constraint-3 limitation).** `offQualityRatingScale`
sets BOTH the HCA margin AND (inversely, through the offQ scale that divides the foul
bucket) the FTA dispersion: lowering it grows the margin but RAISES FTADispersionRatio,
while lowering `foulCompress` lowers both. The two knobs are therefore non-orthogonal on
`(margin, FTADisp)` — you cannot drive both to their targets simultaneously. The
committed operating point is one scale step (margin-priority), with the resulting
FTADispersionRatio recorded as residual.

## Alternatives Considered

- **Jointly sweep `foulCompress` and `offVolumeScale` to maximize a positive
  Cov(lnFGA,lnPPS).** Rejected as the exact metric-gaming ADR-0041 forbids: it fits the
  emergent acceptance metric directly instead of calibrating each lever to an
  independently-faithful corpus target. Each knob is tuned to its OWN target; the Cov
  sign is the emergent check.
- **Raise `offVolumeScale` anyway (the literal Lever-2(2)).** Rejected on its own
  Var(lnFGA) target (see above) — refuted by the archive sweep, not by preference.
- **Compress toward the dev-DB mean (the `offVolumeNeutral=161` precedent path).**
  The archive population (what feeds `FidelitySummary` and the held margin) is the
  correct mean-preservation population, and the archive harness derives it directly,
  so it is used instead of the dev DB.

## Consequences

- The engine's team-to-team FTA-rate dispersion narrows from ~2.9× too wide toward
  ~2× (partial); FGA-volume dispersion and the `Var(lnPPS)` marginal reach real; the
  wrong-signed Cov is roughly halved but not flipped, so total `Var(lnPF)` stays ~3.4×
  too narrow (an honest null on the total-spread magnitude).
- `offQualityNeutral` co-varies with `offQualityRatingScale`, so the HCA-margin re-tune
  keeps the compression mean-preserving automatically (drift-guarded).
- The golden fixture was regenerated (intentional output change). The `FTADispersionRatio`
  metric is now available to all future calibration runs as the foul-rate target.
- A new archive-tagged derivation harness (`TestDeriveQualityNeutrals`) reproduces the
  committed neutrals.

## References

- ADR-0041 (three-axis defect), ADR-0042 (coupling mechanism), ADR-0043 (foul-arm
  attribution — the 47.6% figure this lever targets).
- `engine/internal/sim/teamquality.go` (`foulCompress`, `compressQuality`, neutrals),
  `engine/internal/sim/tempo.go` (`offVolumeScale` Lever-2 re-test note),
  `engine/internal/calibrate/standings.go` (`FTADispersionRatio`),
  `engine/internal/sim/neutral_archive_test.go` (neutral derivation).
