---
description: Faithful 5.60 asymmetric foul-bucket pair (home deterministic defense-coupled / away-neutral stochastic) with one swept bucket-basis scale constant, superseding ADR-0061's offQ-divisor stand-in.
last_verified: 2026-07-12
---

# ADR-0082: Faithful 5.60 foul bucket — asymmetric pair × bucket-basis scale

**Status:** Superseded by ADR-0084 (defMatchupQuality composite fidelity claim)
**Date:** 2026-07-10

## Context

ADR-0061's `offQualityConstant = 1.575` was an explicitly documented STAND-IN carrier for JSB 5.60's dead-zero `+0xDE0` off-quality divisor in the foul bucket. The static pin (2026-07-07, `~/jsb-foulfork-RE-verdict-20260612.md` orientation) closed the divisor question: the real 5.60 foul bucket is a home/away **asymmetric pair** — HOME: a deterministic, defense-coupled weight `(defQ − 5·(5/6)·teamDef)/5 + hca`; AWAY/NEUTRAL: a stochastic `U[0, 0.6)` draw with no defense coupling. This PR ports that pair into `engine/internal/sim/bucketweights.go`, deletes the stand-in constants, and regenerates the golden master.

The first implementation attempt (2026-07-09) STOPPED at the plan's Directional SIGN gate: home margin +0.87 pts/game at 2.58σ (< 3σ), and `TestSimulate_FoulOutStopsMinutes` lost its foul-out fixture. Post-hoc measurement against the real `.sco` archive (`jsbcalibrate --mode calibrate --runs 20 --sample-stride 1 --seed 20240601`) showed this was not a test-power problem: the **unscaled** faithful pair broke all three archive fidelity gates because its magnitudes are expressed in 5.60's raw bucket units, ~8× smaller than the Go engine's play-outcome basis (2pt composite ≈ 16.5). The old stand-in had been implicitly calibrated to Go's basis (home ≈ 7.36 / away ≈ 2.35).

## Decision

Land the faithful pair's **structure** (deterministic defense-coupled home / stochastic away-neutral, keyed on the game-type-aware `hca`, not team role) and convert its magnitudes to the engine's bucket basis with **one constant**, `foulBucketScale = 8.6`, applied to the return value of both arms — including the `+hca` 0.2 term and the away `U[0, 0.6)` draw, so the pinned home/away ratio is preserved. The constant is a **corpus-calibrated stand-in** swept against the archive fidelity gates — the same epistemic status ADR-0061 assigned `offQualityConstant`; the raw-unit RE of the defender `+0xDD0` / team-defense `+0x68A8` magnitudes remains the eventual faithful pin.

## The signature correction (load-bearing)

The branch keys on **`hca`**, not the orientation-pinned `isHome bool`. 5.60's behavior at neutral sites is the primary grounding: at ASG, `param_5 == 0` for both sides → both take the stochastic path → symmetric foul weights. The Go model expresses this through `hcaDelta == 0` at ASG game types; keying on `hca <= 0` reproduces it, while an `isHome`-keyed branch would hand the home-role team the deterministic weight even at ASG — a divergence from 5.60 that also breaks the committed `TestSimulate_HomeCourtAdvantage_ASGNeutral` symmetry test. In every regular game `isHome ⟺ hca > 0`, so the corpus/sweep/golden are numerically identical to the pinned variant; the divergence is ASG-only and is the correct one. Recorded as an explicit adapt-on-primary-source-evidence override of the spec.

## The scale sweep (A/B vs the real archive)

All runs: `jsbcalibrate --mode calibrate --runs 20 --sample-stride 1 --seed 20240601` over the full backups corpus. A = pre-change baseline (ADR-0061 stand-in). Gates (vs A): Var(lnPPS) within 5% relative per block (ADR-0055 SOLVED constraint), `level_gap_pf` not widening by > 0.5 pt/game, positive engine home margin. gt2 = regular season, gt4 = playoffs.

| Config | margin gt2/gt4 | level_gap_pf gt2/gt4 | ΔVar(lnPPS) gt2/gt4 | Verdict |
|---|---|---|---|---|
| A (old stand-in) | 3.48 / 3.59 | −2.37 / −0.66 | — | baseline |
| unscaled (k=1) | 0.46 / 0.72 | −5.76 / −4.04 | −54.8% / −56.0% | STOP (all gates) |
| k=8.3 | 3.36 / 3.68 | −2.38 / −0.77 | −5.8% / +4.1% | STOP (Var gt2) |
| **k=8.6 (chosen)** | **3.44 / 3.76** | **−2.27 / −0.75** | **−3.5% / +4.2%** | **GO** |
| k=9.0 | 3.58 / 3.90 | −2.11 / −0.59 | −1.5% / +5.7% | STOP (Var gt4) + band-excluded |

Real `.sco` home margins are 4.12 (gt2) / 4.59 (gt4); real Var(lnPPS) 0.001451 / 0.003086 vs engine-A 0.001225 / 0.001550 — the engine sits below real in both blocks, so upward Var movement is toward-real. k ≥ 9.0 is excluded twice over: its gt4 Var moves +5.7% (past the 5% gate), and the home weight (≈ 0.872·k) would push the `TestBucketWeights_FoulPathMix` home foul share past its 0.25 ceiling — the foul-out degeneracy bound this suite never raises. k=8.6 is thus bracketed: 8.3 undershoots Var gt2, 9.0 overshoots Var gt4.

At k=8.6 the full local suite is green with **zero band or fixture edits**: Directional +7.65 pts/game at 23.4σ, ASGNeutral 0.25σ (win-rate 0.4974), FoulOutRate passes, FoulOutStopsMinutes passes naturally (the first attempt's fixture problem dissolved with scale), FoulPathMix home share 0.2486 inside [0.02, 0.25].

## Alternatives Considered

- **3pt-bucket HCA complement** — add the deferred site-2 3pt HCA channel to restore the Directional margin. Rejected because: it could only add margin; it cannot restore FTA level or Var(lnPPS), which are foul-volume properties — the deficit was a scale mismatch, not a missing channel.
- **Raise Directional n (test power)** — n=20,000 yields z=6.05 on the unscaled pair's true ~+0.65 effect. Rejected because: it would make the SIGN gate pass while masking a genuine three-gate archive fidelity regression.
- **Scale only the defense-coupled term (hca/floor outside the scale)** — Rejected because: the probe that scaled everything restored all gates and the local suite; excluding the `hca`/floor terms would distort the pinned ~3:1 home/away ratio the pair exists to express.

## Part-6 band decision

Both empirical bands survive verbatim — nothing was widened or re-derived: `FoulPathMix` home foul share 0.2486 ∈ [0.02, 0.25]; `FoulOutRate` stays under its 0.08 degeneracy bound.

## Two seed-pinned test re-pins (RNG-stream/realization shift, documented)

The away arm consumes one `Float64()` draw the old formula did not, and the new weights shift bucket thresholds, so realizations at pinned seeds reshuffle. Two structural tests were re-grounded after inspection confirmed no regime change:

- `TestSimulate_NonDegeneracy` seed 42 → 45: seed 42 now lands a legitimate zero-block tail game. Measured over 200 seeds: zero-block rate 3.5%, mean 3.16 BLK/game vs pristine HEAD's 3.0% / 3.00 — statistically identical regime; a pinned-seed artifact.
- `TestVolumeCountChannel_ZeroVolumeDegenerate` seed 9: the doubly-degenerate fixture (zero-rated visitor vs a 5-man neutral five that fully fouls out) now ties 47-47 and stays tied through 20 scoreless overtimes. That is the engine's **documented** termination design (`maxOvertime` hard cap, `engine/internal/sim/gameloop.go`); the test over-asserted "resolves to a winner". The assertion now accepts a tie **only** when the event stream shows the full OT cap was exhausted — a tie without the cap remains a hard failure.

## Redraw guard

The home arm's `if w <= 0 { redraw }` is faithful to the binary's non-positive-weight redraw but dynamically unreachable through `defMatchupQuality` (post-compression floor 4.5155 > threshold 3.1667). Retained for faithfulness; ~2 uncovered lines, package coverage 96.7% (total 93.8%, floor 90).

## Fidelity caveats (explicit residuals)

- **defQ is compressed, not raw-pin-faithful.** The pin's `defQ` is a raw capped sum `min(Σ₅ defender[+0xDD0], 1.5·teamDef)`; the Go home arm uses ADR-0044's compressed form (`0.45·Σ + 4.5155`). Faithful in coefficient (0.2 exact) and structure (defense-coupled/stochastic split); the defQ summand remains corpus-deferred.
- **`foulBucketScale` is calibrated, not pinned.** The raw-unit magnitudes of the defender/teamDef inputs are the eventual RE target that would replace it.
- **Go's site-2 `+hca` on the 2pt bucket may be under-scaled by the same basis factor.** The 2pt bucket carries a raw `+0.2` HCA nudge on an O(10s) composite; if 5.60's site-2 term is also in raw bucket units, it is ~8× under-expressed. Deferred — the Directional/archive gates pass without touching it.
- **Cov(lnFGA,lnPPS)** is an acceptance signal, never a tuning target (ADR-0041); unchanged in kind by this port (count axis remains out of scope, ADR-0054).

## Consequences

- Positive: the foul bucket's mechanism is now the RE-pinned 5.60 structure; HCA on the foul path is intrinsic (home arm) rather than a post-hoc nudge, and ASG symmetry is structural.
- Positive: all three archive fidelity gates hold at k=8.6 with margins (Var(lnPPS) −3.5%/+4.2%), and the local suite needed zero band edits.
- Negative: one calibrated constant (`foulBucketScale`) remains between the port and full raw-unit faithfulness — the same class of debt ADR-0061 carried, now smaller (structure faithful, single scalar residual).
- Negative: the RNG-stream change re-pinned two seed-based tests and regenerated the golden master (documented above; golden hash change verified against the pre-change baseline).

## Lineage

Supersedes **ADR-0061** for the foul-bucket mechanism: the offQ-divisor stand-in (`offQualityConstant`, `offQualityFloor`, `offQualityWithHCA`) is deleted; `defMatchupQuality` and the def-side stand-ins are unchanged and remain corpus-deferred. Builds on ADR-0044 (defensive compression), ADR-0055 (Var(lnPPS) SOLVED constraint, protected by this sweep's gate), and the HCA sign work in PR #955.

## References

- `engine/internal/sim/bucketweights.go` — `foulBucketWeight` (the pair) and `foulBucketScale` (the basis constant).
- `engine/internal/sim/teamquality.go` — off-quality divisor deletions; def side unchanged.
- `engine/internal/sim/possession.go`, `engine/internal/sim/freeze.go` — call-site signature (`hca`-keyed) and BranchB single-call collapse.
- `engine/internal/sim/gameloop.go` — `maxOvertime` termination design grounding the ZeroVolumeDegenerate re-assertion.
- Sweep artifacts: `/tmp/jsb-ffp-calibrate-{A,B,P,k8.6,k9.0}.txt` (machine-local).
- RE verdict: `~/jsb-foulfork-RE-verdict-20260612.md` (machine-local, orientation).
