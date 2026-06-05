---
description: RE-trace verdict on the volume→shot-COUNT conversion mechanism (ADR-0042 follow-on, PR 1 of 2). Refutes pace (PPS-neutral) and the +0xD90 cold/Branch-A bucket composite (shared structure; ADR-0040-A null) as the lever, and disambiguates the miss→ORB empty loop (level) from the wrong-signed covariance (dispersion, non-arm per the exhausted freeze lattice). Names a PRIME suspect: the deferred Branch-B usage path — the only bucket-side 5.60/engine difference, team-quality-gated (TransOff × team DRB+AST), plausibly a half-court→transition allocation, upstream of the four exhausted arms. Its net Cov sign is unmeasured; the build PR's first task is to implement it behind a freeze toggle and measure.
last_verified: 2026-06-05
---

# ADR-0047: Volume → shot-COUNT conversion mechanism

**Status:** Accepted
**Date:** 2026-06-05

## Context

ADR-0042 located the team-scoring defect as a *missing volume-rate→shot-COUNT pathway*:
the engine's `Cov(lnFGA,lnPPS)` is wrong-signed (engine −0.00262 vs real +0.00027), the
scoring-on-volume slope is 0.24 vs 5.60's 1.20, and 76% of engine team-to-team FGA
variance is "empty"/miss-driven. It named the mechanism *class* but left the exact 5.60
mechanism "named, not traced to buildable detail" and refuted pace / fatigue / make-value.

Two build attempts have since landed and **both nulled the Cov flip**: #966 (ADR-0040-A:
real per-48 volume rates fed into the +0xD90 bucket) and #974 (ADR-0042: `offVolumeScale`
into `teamBaseTime` pace). The user directed a **measure-then-build** next step: RE-trace
the 5.60 volume→count mechanism *before* a third build attempt. This ADR records that
trace's verdict. Full evidence: `engine/docs/volume-count-conversion-trace.md`.

## Decision

**The trace refutes two mechanical candidates as the positive-coupling lever and names a
single prime, unmeasured suspect — the deferred Branch-B usage path — whose Cov effect the
build PR must measure first.** The verdict stays "candidate, not yet confirmed" (no engine
change here measures Branch B), but it is a *named, testable* candidate, not an open shrug.

**Refuted (dead ends — do not retry), trace § Prong B:**

1. **base_time pace** — PPS-neutral by construction (more possessions scale FGA and PF
   together → points-per-shot unchanged → no volume↔efficiency covariance). The committed
   `offVolumeScale` sweep confirms it *adds* `Var(lnFGA)` and *deepens* the negative Cov
   rather than flipping it. 5.60's pace is symmetric-in-count (~99/team), so per-team
   application would not change this.

2. **The +0xD90 cold composite (Branch A) / bucket magnitude** — `selectOutcome` is
   byte-for-byte 5.60's `FUN_004e1ba0` weighted pick with the same live bucket set
   (+0xDB0/+0xDE0 dead in both), and `twoPtBucketWeight` already carries the per-48 FGA-rate
   volume signal — yet ADR-0040-A fed *real* rates through it to **identical** output
   dispersion (input CV 0.097 collapses to output CV 0.034 downstream). Shared structure +
   the cold composite cannot explain 0.24 vs 1.20.

Disambiguation: the **miss→ORB→retry loop** explains the empty-FGA *level* (76% empty on
average) but is **not** the cross-team *dispersion* generator — the freeze lattice
(ADR-0043/0045) puts the ORB-split and make-value arms at ≈ 0, and all four within-possession
arms are exhausted (residual-frac ≈ 1.03). The wrong-signed covariance is **non-arm**.

**Prime suspect (unmeasured), trace § Prong B.4:** the deferred **Branch-B** usage path is
the *only* bucket-side difference between 5.60 and the engine. Branch B modulates the 2pt
weight by `s = (ΣD − usage)/ΣD` with `usage = player[+0x1E8](TransOff) × team(DRB+AST) ×
0.008` — a **team-quality-coupled** quantity, sitting **upstream of the four exhausted arms**
(so the freeze lattice never saw it), gated by the *same* `TransOff` that drives the engine's
higher-EV transition path (`5.0 − TD`). So 5.60's Branch B plausibly **reallocates**
half-court 2pt volume into make-coupled transition shots (a conserved, quality-gated
allocation), where the engine — Branch-A-only — runs transition additively. Whether that nets
a **positive** `Cov(lnFGA,lnPPS)` is empirical; the earlier "usage penalty / wrong sign"
static read is retracted.

**Scoped fix-direction for the downstream build PR (PR 2 of 2):**
- **First task — implement Branch B behind a freeze toggle and MEASURE.** Inputs are already
  identified (`bucketweights.go:38-50`): `player[+0x1E8]`=`r_trans_off`, team DRB/AST rates
  from the .plr team-summary rows, the pinned 0.2/0.04 constants. Run it through the
  freeze-lattice / season-aggregate harness and read its effect on `Cov(lnFGA,lnPPS)`,
  `Var(lnFGA)`, and the half-court↔transition split. This single step tests the prime suspect
  **and** builds the non-arm instrument the verdict needs.
- **Do NOT** retry pace (PPS-neutral) or bucket-magnitude tuning (washes out).
- The eventual channel must **REPLACE** the miss-driven empty-FGA dispersion (narrow
  `Var(lnFGA)` toward real) **while** flipping the covariance sign; optimize total
  `Var(lnPF)` + sign, never the slope.
- If Branch B measures null/wrong-signed, the search reverts to the remaining non-arm
  residual (a season-level FGA-count↔make instrument).

This is PR 1 of a 2-PR sequence; PR 2's exact build is unknowable until the Branch-B
measurement lands.

## Alternatives Considered

- **Headline "the bucket normalization is the channel."** Rejected: the engine ports 5.60's
  normalization faithfully and ADR-0040-A nulled the cold-composite path — this would hand
  PR 2 the same guess that failed.
- **Declare Branch-B "refuted / wrong sign" on the static formula read.** Rejected (and
  retracted from an earlier draft): the shaved half-court volume plausibly reallocates to the
  TransOff-gated higher-EV transition path, so the net Cov sign is a measurement, not a
  formula reading. Branch B is the prime suspect, not a dead end.
- **Pre-name PR 2's exact build (per-team possessions, wider bucket spread, etc.).**
  Rejected: unestablished; pre-naming violates the no-unresolved-decisions gate (ADR-0040
  precedent). The decided next action is the Branch-B measurement.

## Consequences

- The build PR is spared a third nulled attempt: pace and the cold-composite/bucket-magnitude
  are recorded as dead ends, not knobs.
- The covariance fix has a concrete, testable first step (Branch B behind a freeze toggle)
  that doubles as the non-arm instrument — not an open-ended "instrument more."
- The "usage penalty" mischaracterization of Branch B is corrected to a quality-gated
  half-court→transition allocation hypothesis, pending measurement.
- No engine model code changes in this PR (docs only).

## References

- ADR-0042 (coupling mechanism class), ADR-0043 (empty-FGA freeze lattice), ADR-0045
  (turnover fidelity + the full-precision freeze re-run).
- `engine/docs/volume-count-conversion-trace.md` (this trace), `engine/docs/team-scoring-coupling-trace.md` (ADR-0042 trace).
- `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
  (engine slope 0.24 / Cov −0.00262), `engine/internal/validate/testdata/calibration-5.60-20260604-freeze-attribution.json` (arms exhausted).
- `engine/internal/sim/outcome.go`, `bucketweights.go`, `tempo.go`, `gameloop.go`,
  `transition.go`, `possession.go` (the engine paths traced).
- `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md`,
  `COMPOSITE_DOUBLES_TRACE.md` §4, `jsb560_decompiled.c:91072-91099` (5.60 reference).
