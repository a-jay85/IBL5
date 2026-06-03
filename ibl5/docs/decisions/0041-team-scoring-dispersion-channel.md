---
description: Team-scoring dispersion deviates on three axes — both volume and efficiency marginals are over-dispersed AND their covariance is wrong-signed; the faithful fix restores the positive volume↔efficiency covariance AND narrows both marginals (covariance alone overshoots ~2.3×), so volume-marginal fixes (ADR-0040 A/B) cannot close it.
last_verified: 2026-06-03
---

# ADR-0041: Team-scoring dispersion is a three-axis defect — wrong-signed volume↔efficiency covariance plus two over-wide marginals

**Status:** Accepted
**Date:** 2026-06-03

## Context

ADR-0040 concluded that 5.60 disperses team offense through per-48 shot-VOLUME
rates and shipped candidate **A** — source the real rates into the +0xD90 2pt-
bucket composite. A was implemented (PR #966) and proved **null on dispersion**
(`pf_dispersion_ratio` 0.346→0.345, memory `reference_jsb_season_aggregate_verdict`).
A fresh, measurement-first audit
(`engine/docs/team-scoring-dispersion-channel-audit.md`) was run to answer the
question A's null left open: on the full archive, what actually produces the real
team-scoring spread, and which part does the engine fail to reproduce?

The audit extended the calibrate harness to decompose team scoring on **both**
sides of the comparison via the exact within-season-demeaned log-variance identity
`Var(lnPF) = Var(lnFGA) + Var(lnPPS) + 2·Cov(lnFGA, lnPPS)` (PPS := PF/FGA), over
N = 484 regular-season team-seasons. The result inverts the framing of the
prompting hypothesis (which guessed "efficiency out-varies volume ~2×"):

1. **The engine does not COMPRESS either marginal — it over-disperses both.** Real
   vs engine: `Var(lnFGA)` 0.00133 vs **0.00345** (engine volume spread 2.6× real);
   `Var(lnPPS)` 0.00145 vs 0.00293 (efficiency spread 2.0× real, `efficiency_dispersion_ratio`
   = 1.659 > 1). Yet engine `Var(lnPF)` is **0.34×** real.

2. **The deficit is the COVARIANCE SIGN.** Real `Cov(lnFGA, lnPPS) = +0.00027`
   (high-volume teams are also more efficient — the channels reinforce); engine
   `Cov = −0.00262` (anti-coupling), and the cancellation collapses total scoring
   despite wider marginals.

3. **Stated artifact-free, in box-score-summed variables** (`lnPF`, `lnFGA`; PPS is
   derived and shares the `lnFGA` term): the **scoring-on-volume slope**
   `Cov(lnPF,lnFGA)/Var(lnFGA)` is **1.20 real vs 0.24 engine**. *Shoot 10% more →
   score 12% more in 5.60, only 2.4% in the engine.* That is what `pf_corr ≈ 0`
   looks like at the mechanism level. The slope is runs-stable (held at engine
   ≈ 0.21 vs real ≈ 1.45 at `--runs 10`).

4. **Candidate A's null is explained, not contradicted — and A was null, not
   harmful.** The 2pt-bucket composite A re-sourced is a within-possession shot-TYPE
   allocator (memory `reference_play_outcome_buckets`), not the team-volume knob and
   not an efficiency knob. Measured directly against the **pre-A** engine (revert
   `e6f1f18fc`, same pass): engine `Var(lnFGA)` was already **0.00364** (2.7× real)
   and slope **0.214** *before* A — the over-wide volume marginal and the wrong-
   signed covariance both pre-date it. A nudged the marginal slightly toward real
   (0.00364→0.00345) and the slope trivially (0.214→0.240); it neither caused nor
   addressed the dispersion deviations.

## Decision

Team-scoring dispersion deviates on **three axes at once**, and the faithful fix is
the **coordinated** correction of all three — **not** a single knob and **not** a
make-value reweight. The eventual model PR inherits this scoped direction:

- **Restore the positive volume↔efficiency covariance AND narrow both over-wide
  marginals — PRIMARY, the model-PR target.** The engine's marginals are already too
  WIDE (Context §1: volume 2.6×, efficiency 2.0× real) and its covariance is wrong-
  signed (Context §2). Restoring real's correlation *alone* overshoots total
  dispersion ~2.3× (give the engine +0.193 at its current marginals →
  `Var(lnPF) ≈ 0.0076` vs real 0.00332), so the marginals must come **down** as the
  covariance sign is restored. **Optimize against total `Var(lnPF)` and the
  covariance sign** — the now-committed engine-side decomposition terms — **not** the
  scoring-on-volume slope, which is a diagnostic that can be "hit" by narrowing
  volume (lowering total dispersion, the wrong way).
- **Do NOT push a marginal WIDER — REJECTED.** ADR-0040 candidate **A** (real volume
  rates into the bucket composite) and candidate **B** (the +0xD90 Branch-B usage-
  shrink) are both volume-*marginal* modulators on the axis that is already too wide,
  and silent on the covariance; **neither is the dispersion fix**, and B is dropped
  from the dispersion track.

The **specific 5.60 coupling mechanism is not yet located in the decompile.** The
audit measured the anti-coupling and showed the engine's volume and make-value
paths are structurally independent (no path ties "good offense" to both more shots
and more points-per-shot), but deliberately does not name the dynamic — that RE is
the model PR's first task and must be measure-then-trace, not pre-judged. Asserting
a mechanism here would repeat the ADR-0040-style error of porting a guessed lever.

This ADR ships the **measurement instrument** (the harness's two-sided
decomposition) and records the direction; it ships **no model code**.

## Alternatives Considered

- **Keep ADR-0040's direction (port real volume rates / Branch-B)** — Rejected as
  the dispersion fix: candidate A was empirically null and the audit shows why (the
  engine volume marginal is already wider than real). ADR-0040's bucket-mechanism
  reading is not reopened; only its claim that the *volume marginal* is the
  dispersion lever is superseded here.
- **Treat efficiency as the lever (the prompting hypothesis #1)** — Rejected: on the
  full corpus efficiency and volume variances are ~even (`Var(lnPPS)/Var(lnFGA) ≈
  1.09`), not 2×; the engine over-disperses efficiency too. Efficiency is not a
  compressed channel.
- **Reweight `net` in `shot_value` / tune a dispersion constant** — Rejected
  (inherited from ADR-0040 §4 and reinforced here): both marginals are already too
  wide; pushing a marginal knob is the wrong axis.
- **Pre-name the coupling mechanism now** — Rejected: not yet evidenced in the
  decompile; the audit is explicit that locating it is the model PR's job.

## Consequences

- Positive: the model PR inherits a settled, evidence-grounded *axis* (restore the
  coupling) and a committed instrument that measures it directly (engine-side
  `Cov(lnFGA,lnPPS)` + scoring-on-volume slope), so progress is no longer judged by
  the era-confounded raw `pf_dispersion_ratio` alone.
- Positive: two volume-marginal candidates (A re-source, B usage-shrink) are
  closed out as the dispersion fix, ending that track.
- Negative: the faithful coupling mechanism is an open RE question; the model PR
  starts with a decompile trace, not a known port target.
- Neutral: the harness now carries eight decomposition terms (four per side) and
  two FGA-per-game `TeamStanding` fields; additive, existing outputs byte-stable.

## References

- `engine/docs/team-scoring-dispersion-channel-audit.md` — the full measurement +
  evidence (§1 the 2×2, §2 the slope, §3 why A was null, §4 structural RE, §5 verdict).
- `ibl5/docs/decisions/0040-team-offense-dispersion.md` — the ADR this reconciles
  with / supersedes on the dispersion lever.
- `ibl5/docs/decisions/0035-native-go-sim-engine.md` — the engine this scopes within.
- `engine/internal/calibrate/standings.go` — the instrument (`FidelitySummary`
  volume/efficiency block, `decomposeLogVariance`).
- `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
  — the committed full-corpus 2×2 artifact.
- The JSB decompile (`00_MASTER_REFERENCE.md`, `COMPOSITE_DOUBLES_TRACE.md`) lives
  outside this repo (`~/Downloads/jsb_560/decompiled/`).
- Memories: `reference_jsb_season_aggregate_verdict`, `reference_play_outcome_buckets`,
  `reference_jsb_corpus_completeness`, `reference_sco_fgm_is_2pt`,
  `reference_jsb_winshare_runs_artifact`.
