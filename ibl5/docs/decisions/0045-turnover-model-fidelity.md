---
description: Replaces the mis-ported independent-turnover check (which jammed (TVR×5.8)² into the [2,5] energy slot, firing ~24%/poss) with the faithful JSB two-part model — a negligible [2,5]-energy independent check plus a dominant steal-driven turnover tied to offensive carelessness × defensive STL — and recalibrates the 2pt make-value. Closes the season scoring-level deficit; records the Cov re-run, with a full-precision (18-season/20-run) addendum confirming the Cov null (no flip; wrong sign lives in the non-arm residual; Control-B structural-vs-noise left to a 2nd seed).
last_verified: 2026-06-04
---

# ADR-0045: Turnover-model fidelity + 2pt make-value calibration

**Status:** Accepted
**Date:** 2026-06-04

## Context

The ADR-0040→0044 chain isolated the engine's team-scoring **dispersion** defect.
Orthogonally, the engine carried a large **level** deficit: season scoring **88.8**
PTS/team vs `.sco` **117.6**, FGA **73.8** vs **99.5**. The ADR-0044 follow-up
instrument (`internal/calibrate/possession_archive_test.go`, build-tag `archive`,
over the real 53 GB local backup archive) localized it: **not a pace bug** — the engine
reaches near-real possessions (99.8 vs 106.6). It is **within-possession**: only
~74% of possessions became a field-goal attempt vs ~93% real. Two measured root
causes, both fixed here.

### (A) Turnover port bug (dominant)

JSB's independent turnover roll reads slot `+0xDF8` — the dc-minutes **energy**
parameter clamped **[2,5]** (`00_MASTER_REFERENCE.md` +0xDF8, lines 9617-9623) — as
`rand(1,1793) ≤ sqrt(+0xDF8)`, i.e. **~0.1%/poss (negligible)**. JSB turnovers are
therefore overwhelmingly **steal-driven** (`00_MASTER_REFERENCE.md` "Steal
Probability": per-player weight = offensive-turnover-stat × fatigue, total capped
`param × 1.5`, roll vs total → victim).

The engine instead jammed `(TVR×5.8)²` into that independent slot
(`turnoverPropensityScale=5.8`, `turnoverDenom=1793`), so `sqrt ≈ 429` → **~24%/poss
→ ~28 TO/team** (≈2× real 14.5, ≈200× the intended independent rate). It was also
**inverted** (TVR is stored as ball-*security*, higher = fewer turnovers, but the
engine made higher TVR → *more*) and tied to the **wrong quantity** (ball-handler
TVR, never the defense). There was **no defense-steal→TO path** — `creditSteal` only
*labelled* an already-fired TO via a `stealFraction=0.55` post-hoc split.

### (B) 2pt make-value under-calibrated

Engine 2pt% **42.5%** vs `.sco` **49.8%** (~7pp low); 3pt% was already faithful. The
mean make-value `base2pt(fgp) = fgp × fgpToPermille` (`fgpToPermille=9.0`) was too
low. `Var(lnPPS)` was already ≈real (ADR-0044), so the FGP *spread* was fine — only
the *mean* make-value was low.

## Decision

### (A) Faithful two-part turnover model

1. **Independent check → the [2,5] energy ceiling.** `selectOutcome`'s
   `rand(1,1793) ≤ sqrt(turnoverDefValue)` gate is **unchanged in form**; it is now
   fed `energyCeiling(bh)` — the per-player JSB `+0xDF8` value `(48 − min(dc_minutes,
   28)) × 0.03 × conditioning + 1`, clamped **[2,5]**, derived from existing bundle
   fields (DCMinutes, Stamina). This makes the independent check negligible
   (~0.08–0.12%/poss), matching JSB. It is an **unforced** change of possession — no
   stealer, no fast break.

2. **Dominant steal-driven turnover.** A per-possession roll
   `rand < turnoverProb(carelessness, stealPressure)`, where
   `carelessness = carelessnessBase − TVR` (oriented so **higher TVR → fewer
   turnovers**) and `stealPressure = Σ defenders' STL × fatigue` (the JSB
   `param × 1.5` threshold side). A successful steal **is** the turnover: it emits
   `EventTurnover` (the victim keeps `GameTOV`) **and** `EventSteal` (a defender
   credited `GameSTL` via the existing `selectStealer`), and arms the defense's fast
   break. This ties turnover volume to **defensive team quality** — the structural
   property the ADR-0042 audit found missing, and the prerequisite for the Cov
   hypothesis (steals → quality-tied fast-break volume → transition shots that are
   both extra volume and higher-EV).

The model is wired identically at both `outcomeInputs` assembly sites (half-court
`possession.go` and fast-break `transition.go`). The freeze-lattice **TVR arm**
(`freeze.go`) now freezes `turnoverProb` (the per-possession steal-turnover
probability) instead of the old linear TVR threshold — the correct injection point
for the Cov re-run, since freezing it collapses the STL→steal→fast-break coupling.

`stealTurnoverScale = 2.75e-5` is a documented validation-phase stand-in (same class
as `offVolumeScale` / `foulCompress`): the carelessness and pressure **shapes** are
faithful; the scalar pins only the **level**, calibrated to TO/team ≈ 14.5 against
the archive.

### (B) 2pt make-value calibration

`fgpToPermille` 9.0 → **9.4** and `leagueBaseline` 233 → **250** (the `.sco`-implied
baseline = sco 3pt% × 666.7 ≈ 248, since JSB 3pt make = baseline × 1.5), so engine
2pt% lands ≈49.8% while 3pt% stays faithful ≈37%. Documented stand-ins, same class.

### Fixture realism (test-only)

The shared `mkPlayer` test fixture default TVR was 40 (a very turnover-prone
player); under the calibrated model that produced ~58 turnovers/game in full-game
fixtures, cascading into too-many injuries / FTA>FGA / no-block degeneracy. Raised
to a realistic **70** (the only consumer of TVR is now `turnoverCarelessness`), which
restores ~29 TO/game (realistic) in those fixtures. The ASG-neutral symmetry test's
sample was enlarged (n 2000 → 8000) so the symmetric fixture's true zero margin is
robust to the RNG-stream shift (verified convergent: n=8000 → +0.4σ, n=20000 →
−0.8σ).

## Calibration (dev/local; CI lacks the 53 GB archive)

Measured via `possession_archive_test.go` over the full archive (13 zips, 1560
engine / 1354 `.sco` team-games):

| metric | before (broken) | after (this PR) | `.sco` |
|--------|-----------------|-----------------|--------|
| TO/team | ~28 | **14.6** | 14.5 |
| 2PT% | 42.5% | **49.5%** | 49.8% |
| 3PT% | (faithful) | **36.4%** | 37.2% |
| FGA/team | 73.8 | **89.3** | 99.5 |
| PTS/team | 88.8 | **117.1** | 117.6 |

The **PTS-level deficit is closed** (dPTS −0.4, was −28.8) and TO/2pt% land on the
`.sco` corpus. The residual FGA gap (−10.2) is the **possession-count** gap
(dPOSS −7.3 — the `base_time`/pace placeholder, out of scope) plus the FTA over-fire
(dFTA +16.5 — the ADR-0044 `foulCompress` calibration, out of scope; a faithful port
must not add a team-foul bonus, verified `00_MASTER_REFERENCE.md`).

## Cov re-run (HYPOTHESIS — recorded, not gated)

ADR-0043's freeze-lattice attribution and ADR-0043's transition/Make-arm refutation
tested the **broken** engine; the steal-driven turnover rebuilds the STL→fast-break
coupling, so they do not survive this fix and were not assumed. The post-fix
`TestRealArchive_FreezeLatticeAttribution` + season-aggregate were re-run to **record**
the post-fix `Cov(lnFGA,lnPPS)` direction and the freeze-lattice arm re-attribution.

**Result — a NULL on the flip (recorded, not gated).** A reduced-scope re-run
(4 seasons, 4 runs; the full 13-season/20-run run is ~90 min and out of this PR's
budget) records the baseline engine `Cov(lnFGA,lnPPS)` still **negative** (−0.0013,
the ADR-0042 wrong sign), **not flipped** to +. The companion
`TestRealArchive_VolumeFGPCoupling` control confirms the *target* sign is real:
real-archive corr(volume composite, FGP) = **+0.57** (dev-DB was +0.265) — the
engine simply does not yet reproduce it.

The arm re-attribution **did move**, in the intended direction. The **turnover
(TVR) arm** now carries a non-trivial **−0.24** of the |Cov| collapse — freezing it
*increases* |Cov|, i.e. the new steal-driven turnover variance pushes Cov **toward
+** — where ADR-0043's broken-engine attribution had the TVR arm minor. The
**foul-only arm** remains the single largest (+0.32). The all-frozen residual ≈
baseline (frac ≈1.04), so the wrong-signed covariance still lives **outside** the
four arms (pace / shot-mix / FT / rebound-count), consistent with ADR-0043's ~48%
non-arm residual — the steal-driven turnover participates but is not the missing
lever. (At this reduced resolution the all-frozen ≤ baseline sanity, Control B, is
tripped by a ~4% margin — a low-runs noise artifact and/or the turnover arm's new
positive contribution, which the broken-engine premise did not anticipate; a
full-precision run is the proper settle — see the **Full-precision settle**
addendum below.)

A Cov flip toward + was the win condition, but a **null is a valid, publishable
result** (it would say the volume-coupling lives elsewhere), and this PR does **not**
block on a flip. Caveat: the repurposed TVR arm now conflates offense-carelessness
and defense-STL variance in one frozen scalar, so a null could reflect low cross-team
**input** variance rather than a wrong model.

### Full-precision settle (2026-06-04 addendum)

The reduced-scope re-run above left Control B's trip ("is the all-frozen `|Cov|` >
baseline a low-runs artifact, or the turnover arm's new positive contribution?") for
a full-precision settle. That run is now done: `JSB_ARCHIVE_RUNS=20
JSB_ARCHIVE_STRIDE=1` over the full archive (18 seasons, N=484 pooled gt-2 team-rows),
artifact `calibration-5.60-20260604-freeze-attribution.json`.

**The NULL holds, and is now robust.** Baseline engine `Cov(lnFGA,lnPPS) = −0.00122`
— still the ADR-0042 wrong (negative) sign, **no flip**, stable against the reduced
run's −0.0013. Control A (baseline negative, in the ~1e-3 band) is solid. The
marginals confirm and improve on the ADR-0044/0045 picture: `Var(lnPPS)` 0.00158 ≈
real 0.00145 (fixed); `Var(lnFGA)` **narrowed further to 0.00179** (was 0.00269 at
ADR-0044; real 0.00133 — the turnover fix pulled it from ~2.0× to ~1.35× too wide);
`Var(lnPF)` 0.00094 still ~3.5× too narrow (real 0.00332) — the collapsed
total-scoring spread is unchanged precisely because Cov did not flip.

**The wrong sign still lives ~entirely outside the four instrumented arms.** Arm
collapse fractions: TVR **−0.178** (freezing it *raises* `|Cov|` — the steal-driven
turnover adds positive cov, as designed), Foul **+0.163** (adds negative cov), ORB
−0.033, Make +0.021. The two large arms are opposite-signed and **nearly cancel**;
all-four-frozen `Cov = −0.00125`, residual-frac **1.028** of baseline. So the four
arms are **exhausted** — none is the missing lever — and the volume-coupling lever
search (the ADR-0042 follow-on) is confirmed pointed at the **non-arm residual**
(pace / shot-mix / FT / rebound-count), consistent with ADR-0043's ~48% non-arm share.

**Control B (structural vs. noise) is NOT settled by one seed — left open,
deliberately.** The trip shrank from ~4% (reduced) to **2.8%** (full-precision) — it
converged *toward* 1.0, not away. residual-frac 1.028 is a small difference of two
large opposite-signed arms (TVR −0.178, Foul +0.163): the noise-dominated regime,
measured at a **single seed**. The harness pools all rows into one covariance and
stores no per-run values, so a run-to-run σ is not recoverable from the artifact. A
2.8% one-seed excess is therefore **not** sufficient to declare Control B's premise
("freezing arms never ADDS covariance") stale — even though post-ADR-0045 the TVR arm
genuinely does add positive cov, which *would* push residual-frac ≥ 1. The clean
discriminator is **one more full-precision seed** (`JSB_ARCHIVE_SEED=…`): two seeds at
~1.02–1.03 ⇒ structural (then relax Control B to a band, do **not** invert); scatter
across 1.0 ⇒ noise. **Control B's assertion is left unchanged pending that second
seed** — it is a pre-registered control, and ADR-0041's anti-metric-gaming discipline
forbids loosening a control on one-seed evidence.

**Net settle.** The Cov flip is a confirmed null at full precision; ADR-0045's
steal-driven turnover behaves as designed (a genuine positive-cov arm) but is not the
missing coupling; the next lever lives in the non-arm residual. The only open thread
is the 2.8% Control-B excess, which one additional seed resolves.

## Alternatives Considered

- **Just rescale `turnoverPropensityScale` 5.8 → ~3.0 to hit 14.5 TO.** Cheapest, but
  keeps the rate tied to the *wrong* quantity (ball-handler TVR only, no defensive
  steal pressure), still inverted, and leaves the Cov coupling broken — the post-fix
  Cov re-run (the whole point) would be meaningless. Rejected; the faithful
  steal-driven model is required.
- **Ship the turnover fix and the 2pt calibration as two PRs.** Rejected: both are
  level-deficit drivers, both calibrate against the *same* archive instrument, both
  change the golden fixture (one regen), and the Cov re-run only makes sense after
  both land. Splitting forces two golden regens and a 2pt recalibration after
  turnovers move.

## Consequences

- The within-possession field-goal-attempt rate rises from ~74% toward ~90%; the
  season **scoring level** reaches the corpus (PTS dPTS −0.4) with TO/team and 2pt%/
  3pt% all on `.sco`.
- Turnover volume is now **defensively driven** (Σ STL), a real structural coupling
  the engine lacked.
- The freeze-lattice TVR arm semantics changed (now freezes `turnoverProb`); the
  `FreezeMeans.TurnThresh` field was renamed `TurnProb`. The calibrate harness passes
  `FreezeMeans` by value, so only `freeze.go` / `freeze_test.go` were touched.
- The golden fixture was regenerated (intentional output change).
- Out of scope and unchanged: the foul over-fire (ADR-0044 `foulCompress`) and the
  `base_time`/pace placeholder.

## References

- ADR-0042 (coupling mechanism — missing volume→count pathway), ADR-0043 (empty-FGA
  source isolation), ADR-0044 (foul-bucket compression).
- `engine/internal/sim/steal.go` (`turnoverCarelessness`, `teamStealPressure`,
  `stealTurnover`, `stealTurnoverScale`), `engine/internal/sim/possession.go`
  (`energyCeiling`, trip wiring), `engine/internal/sim/transition.go` (mirror),
  `engine/internal/sim/freeze.go` (`turnoverProb` TVR arm),
  `engine/internal/sim/shotdecision.go` (`fgpToPermille`, `leagueBaseline`).
- `engine/internal/calibrate/possession_archive_test.go` (level instrument),
  `engine/internal/calibrate/freeze_archive_test.go` (Cov re-run harness).
- `00_MASTER_REFERENCE.md` (+0xDF8 energy clamp, "Steal Probability", no team-foul
  bonus).
