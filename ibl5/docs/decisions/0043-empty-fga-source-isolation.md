---
description: A counterfactual freeze lattice isolates which within-possession mechanism (ORB-continuation, turnover, foul-only, or make-value) generates the engine's wrong-signed Cov(lnFGA,lnPPS); the criterion is pre-registered before the archive run, and the named lever grounds the ADR-0042 follow-on Lever-2 fix.
last_verified: 2026-06-03
---

# ADR-0043: Isolating the empty-FGA source by counterfactual freeze lattice

**Status:** Accepted
**Date:** 2026-06-03

## Context

ADR-0042 located the team-scoring coupling defect as a missing shot-volume-rate →
shot-COUNT pathway: the engine's `Cov(lnFGA,lnPPS)` is wrong-signed because output
FGA variance is miss-dominated ("empty" FGA) rather than make-coupled. PR #974 built
the volume→count channel (`tempo.go`, `offVolumeScale=0.02`) but **could not flip the
sign**: the channel ADDS a dispersion source instead of REPLACING one. ADR-0042's
*bounded open item* was therefore: **which within-possession mechanism carries the
empty/miss-driven FGA**, so the follow-on Lever-2 fix can CUT (or RE-WIRE) it.

The existing by-origin telemetry (`decomposeByOrigin`, `covOriginPPS`) cannot answer
this. It is **size-dominated** — initial FGA is ≈68% of the total, so each origin's
covariance tracks its FGA *size*, not its intrinsic emptiness — and it inherits the
**shared-term artifact** (`decomposeLogVariance` takes `lnPPS = lnPF − lnFGA`, so any
single `Cov(lnFGA,lnPPS)` headline is contaminated by the shared `lnFGA` term).

This ADR records the *instrument* that names the lever and the *criterion* applied to
its output. The discipline is the lesson of ADR-0040 (which tuned a guessed lever and
measured null): **the decision criterion is pre-registered here, before the archive
run.**

## Decision

**Instrument — a counterfactual freeze lattice** (`engine/internal/calibrate/freeze.go`,
built on `engine/internal/sim/freeze.go`). Four within-possession mechanisms ("arms")
are freezable at their **derived-rate output point** (never at a shared rating input,
which would spill into a sibling mechanism):

1. **ORB** — offensive-rebound continuation probability (`orebProbability`)
2. **TVR** — turnover threshold (`turnoverThreshold`)
3. **Foul** — foul-only bucket weight (`foulBucketWeight`)
4. **Make** — 2pt make-value (`shotValue2pt`; the 3pt make-value is a team-invariant
   constant, so only the 2pt channel carries Make variance)

The 4th arm is essential: the negative covariance pre-exists the channel, baked into
the miss→ORB→miss loop, and may unify with ADR-0041's 2×-wide `Var(lnPPS)`.

Per season bucket a no-freeze **baseline** pass harvests each arm's per-season league
mean (`sim.FreezeAccum`); then all 2⁴ = 16 freeze configs re-run with those means
substituted. Per config the engine scoring spread is read through the **reused**
`decomposeLogVariance`. Each arm's **Shapley marginal** (exact over the 16 configs) is
reported for **three sub-deltas** — `ΔVar(lnFGA)`, `ΔCov(lnFGA,lnPF)`,
`ΔCov(lnFGA,lnPPS)` — never the shared-term `Cov(lnFGA,lnPPS)` alone. The signature
distinguishes the follow-on action: a large **−ΔVar(lnFGA)** ⇒ **CUT** that empty-FGA
source; a large **+ΔCov(lnFGA,lnPF)** ⇒ **RE-WIRE** that FGA to score.

The harness is engine-only (frozen-engine vs baseline-engine); it bypasses
`ValidateCorpus`, leaving the validate package untouched. The **no-freeze config is its
own reference** — every Δ is self-consistent regardless of the absolute Cov, which is
not a fixed constant (it shifts with runs/stride/season-selection). The verdict is
scoped to the **regular-season** bucket.

**Controls (instrument validation — both required for the per-arm Δ to be
interpretable):**

- **Control A:** the no-freeze config reproduces a **negative** `Cov(lnFGA,lnPPS)` of
  order ~1e-3 (a sanity band, not a literal).
- **Control B:** freezing **all four** arms reduces `|Cov|` relative to baseline. The
  covariance that *survives* is the **non-arm residual** (pace / shot-mix / FT /
  rebound-count) — reported, never asserted to zero. A large residual is itself a
  verdict: the defect lives outside the four arms.

### Pre-registered decision criterion (fixed BEFORE the run)

> - **Dominant lever** = one arm collapses `|Cov(lnFGA,lnPPS)|` by **≥50%** of baseline
>   **AND ≥2×** the next arm. Its sub-delta signature picks **CUT** (large −ΔVar(lnFGA))
>   vs **RE-WIRE** (large +ΔCov(lnFGA,lnPF)).
> - If **make-prob/shot-value dominates** AND its freeze also collapses the 2×-wide
>   `Var(lnPPS)` → declare FGA-side and PPS-side **ONE defect** (ADR-0041 unification,
>   one lever / three axes); follow-on = shot-value dispersion fix.
> - If a **PAIR dominates** → report the interaction (e.g. TVR×ORB: turnover rate gates
>   how many possessions reach the ORB loop); follow-on targets both.
> - **"No single dominant lever"** (all arms <50%, roughly uniform, OR a large non-arm
>   residual) is a **legitimate terminal verdict** → escalate to a different RE axis
>   (pace / shot-mix), documented. Do **not** force-rank a winner.

### Verdict

Full archive run, regular bucket — 18 seasons, 8 runs/game, seed 20240601
(`calibration-5.60-20260603-freeze-attribution.json`). Both controls hold:
baseline `Cov(lnFGA,lnPPS) = −0.002676` (negative, ~1e-3 — **Control A**); all-frozen
`−0.001279`, i.e. `|Cov|` below baseline (**Control B**).

| Arm | collapseFrac of \|baseline Cov\| | ΔVar(lnFGA) | ΔCov(lnFGA,lnPF) | ΔCov(lnFGA,lnPPS) |
|-----|---:|---:|---:|---:|
| **Foul** | **+0.476** | **−0.001105** | +0.000168 | +0.001272 |
| TVR | +0.069 | −0.000723 | −0.000537 | +0.000185 |
| ORB | −0.017 | +0.000043 | −0.000001 | −0.000044 |
| Make | −0.006 | +0.000018 | +0.000002 | −0.000016 |
| **non-arm residual** | **+0.478** | — | — | — |

**The foul-only rate is the dominant within-possession arm — but the defect is split
roughly half-and-half with a non-arm residual.** Foul collapses **47.6%** of baseline
`|Cov|`, **6.9× the next arm** (TVR +6.9%) and **91% of all arm-attributable
collapse** (ORB and Make are null/negative). Its sub-delta signature is **CUT** — a
large `−ΔVar(lnFGA)` with a near-zero `ΔCov(lnFGA,lnPF)` — so the foul-only-rate
*dispersion* is what generates the wrong-signed covariance, not a make-value
mis-wiring. The corroborating mech panel agrees independently: `foul_only` has by far
the strongest coupling to both axes (`Cov(rate,lnFGA) = −0.00142`,
`Cov(rate,lnPPS) = +0.00133`) — teams that draw more foul-only trips take *fewer* FGA
(0-FGA possessions) at *higher* PPS (free throws), teams that draw fewer take more FGA
(more misses) at lower PPS: exactly the negative `Cov(lnFGA,lnPPS)`. The foul-bucket
divisor (`foulBucketWeight`, the team-quality `offQ/defQ` term) is what over-disperses
that rate.

**Against the pre-registered criterion, this is a hybrid, reported as the numbers
read — not rounded up.** Foul **just misses** the ≥50%-of-baseline dominance bar
(47.6%), because the **non-arm residual is co-equal at 47.8%** — the covariance that
survives freezing all four arms (pace / shot-mix / FT / rebound-COUNT — the
output-point freeze fixes per-event ORB *probability*, not the number of misses that
feed the loop). So the criterion's two branches fire **together**: Foul is the
unambiguous dominant *arm* (the ≥2× sub-bar is met 6.9×), **and** the large residual
triggers the escalation branch. Two prior hypotheses are **refuted**: the
make-prob/shot-value arm is null (−0.006 — no ADR-0041 FGA/PPS unification via
make-value), and the ORB-continuation arm is null (−0.017 — the miss→ORB→retry loop
is *not* the dispersion lever, despite being ADR-0042's narrative candidate).

**Suppression alone is sign-insufficient — a critical constraint on the follow-on.**
The all-frozen `Cov(lnFGA,lnPPS) = −0.001279` is **still negative**, while the real
target is **+0.00027** (ADR-0041). Freezing *every* within-possession arm walks the
covariance only toward the residual (≈−0.00128), never to the real *positive* value.
So narrowing the foul-only-rate dispersion reaches `Cov ≈ 0` **at best**, not
`+0.0003`. The foul anti-coupling (free-throw points inflate PPS while contributing
zero FGA) is largely *physiological* — real teams have it too and overcome it with a
**positive volume→efficiency coupling the engine lacks**. That coupling is exactly
ADR-0042's volume→count channel, shipped at the conservative `offVolumeScale = 0.02`.

**Follow-on (the Lever-2 PR this unblocks) is therefore a PAIR of a specific shape:**

1. **Narrow the foul-only-rate dispersion** — the dominant, named *negative* driver. RE
   the 5.60 foul/free-throw frequency model and reduce the engine `foulBucketWeight`
   team-quality (`offQ/defQ`) sensitivity that over-disperses the foul rate. This
   removes the wrong-signed contribution but, by the all-frozen evidence above, **cannot
   by itself flip the sign**.
2. **Build the positive coupling** that reaches a *positive* `Cov` — scale up ADR-0042's
   volume→count channel beyond `offVolumeScale = 0.02` (the mechanism the engine lacks),
   the only lever that can move `Cov` past zero. The ~48% non-arm residual (which the
   all-frozen number *is*) is the headroom this must come from; pace / possession-count
   and shot-type mix and rebound *count* are its RE axes.

A single-arm "≥50%, CUT and done" verdict would have been the convenient story. The
instrument says the foul rate is the lead *negative* driver but only half the defect,
and that no amount of suppression flips the sign — pre-registration plus the all-frozen
control are what keep that honest and keep the next PR from chasing `Cov → 0`.

## Alternatives Considered

- **Analytical attribution only** (extend the by-origin `decomposeByOrigin` /
  `covOriginPPS`). Rejected as the *lead* instrument: size-dominated (initial FGA 68%)
  and shared-term-contaminated. Retained as the cheap *corroborating* mechanism-rate
  panel (folded from baseline events), never the tiebreaker.
- **Thread `sim.Options` through `ValidateCorpus`.** Rejected: the freeze lattice is
  engine-vs-engine and needs no `.sco` pairing; threading would ripple the
  `ValidateFunc` signature through `season.go`/`walk.go` and risk the green validate
  tests. The standalone engine-only harness reuses the zip-walk helpers and `backup.*`
  primitives with zero validate changes.
- **Hard-assert the known engine `Cov ≈ −0.0034`.** Rejected: that figure is a
  stride-thinned-sample value at one scale, not a constant, and this harness sims the
  full schedule (not the `.sco`-matched subset). The no-freeze config is the
  self-reference instead (the PR #887 "imagined expected value" anti-pattern, avoided).
- **Clamp rating inputs to freeze an arm.** Rejected: clamping e.g. rebound ratings
  spills into *defensive* rebounding (`selectRebounder`) — a cross-mechanism confound.
  Injection is at the derived-rate output, one mechanism at a time.

## Consequences

- ADR-0042's bounded open item is closed: the empty-FGA lever is **named** (see
  Verdict), grounding the follow-on Lever-2 PR with a pre-registered target rather than
  a guess.
- The freeze-override infrastructure (`sim.SimulateWith`, `sim.FreezeConfig/FreezeMeans/
  FreezeAccum`) is permanent instrumentation: the follow-on PR reuses it to A/B a
  candidate fix against the same lattice. Zero-Options `Simulate` is byte-identical
  (golden-stable).
- The committed artifact is the reproducible record; the archive diagnostic is
  build-tag gated (`archive`), never run in CI.
- **This PR ships no model/scale change.** It is measurement-only; the lever fix is the
  next PR.

## References

- `engine/internal/sim/freeze.go`, `engine/internal/calibrate/freeze.go` — the
  instrument.
- `engine/internal/validate/testdata/calibration-5.60-20260603-freeze-attribution.json`
  — the committed artifact (18 seasons × 8 runs, the verdict above).
- [ADR-0042](0042-team-scoring-coupling-mechanism.md) — the missing volume→count
  pathway; this ADR closes its bounded open item.
- [ADR-0041](0041-team-scoring-dispersion-channel.md) — the three-axis defect; the
  Make-arm verdict tests its FGA-side/PPS-side unification.
- [ADR-0040](0040-team-offense-dispersion.md) — the null-lever lesson that motivates
  pre-registration.
