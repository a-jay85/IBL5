---
description: J14 — the AutoResearch eval-harness is a faithfulness-CONSTRAINED search loop, not a "perturb params, keep improvements" hill-climb. It perturbs only allowlisted stand-in constants (everything not registered is frozen — safe-by-omission), scores distributional distance to the archive target bands, and emits a per-stand-in × per-term LEVERAGE report that ranks RE prioritization for humans — it never auto-commits, because a stand-in that improves corpus fit can MASK a fidelity bug (the ADR-0085 truncation-compensating-base_time precedent). Records rejection of loop L9's naive acceptance rule; harness build is the ⚙️ follow-up.
last_verified: 2026-07-20
---

# ADR-0087: AutoResearch eval-harness — a faithfulness-constrained search loop, not a corpus hill-climb

**Status:** Accepted
**Date:** 2026-07-20

## Context

Engine iteration is human-paced despite an objective metric already existing.
Every J-series refinement to date has been a hand-run A/B: a human picks a
stand-in constant, sweeps it, reads the four-term Cov/Var decomposition
(ADR-0049) and the ending-mix bands off the archive, and decides. The
instrumentation groundwork is merged — the calibration walk is ≈ 8 min
full-corpus (~23,714 games), the freeze arms (`engine/internal/sim/freeze.go`)
pin seasons, and ~15 archive-tagged calibrate tests
(`engine/internal/calibrate/*archive*_test.go`, e.g.
`basetimemid_sweep_archive_test.go`, `possessioncoupling_archive_test.go`,
`leaders_archive_test.go`, `measure_baseline_archive_test.go`) each measure one
axis against the corpus. What's missing is the loop that would run hundreds of
these overnight instead of one an afternoon.

Loop L9 (`loop-engineering-backlog.md`) sketched that loop as: *perturb engine
params in a worktree, sim N seasons, score distribution error, **keep only
improvements**, log each trial.* The last clause is the problem, and it is why
J14 is an ADR and not a script.

**The faithfulness bar forbids a corpus hill-climb.** Every shipped engine
change must be RE-grounded in jumpshot 5.60's decompiled arithmetic — *no
constant tuned toward the band.* A loop whose acceptance rule is "lower
distributional loss wins" is definitionally a corpus tuner: it will happily
drive a provisional stand-in to whatever value minimizes error, and that value
is not evidence of 5.60 fidelity — it is evidence of overfit. Worse, it can be
actively misleading. ADR-0085 is the standing precedent: the possession
clock-step truncation (a confirmed 5.60 infidelity) was *accidentally
compensating* a too-slow `base_time` stand-in; the config with the better corpus
fit was the one hiding two bugs that cancelled. A "keep improvements" loop would
have locked that pairing in and reported success.

So the design tension is not "how do we automate the sweep" — the sweeps are
already automated (that's what the 15 archive tests are). It is: **how do we
automate the sweep without letting the objective metric become the acceptance
criterion.** This ADR answers that; the harness build is the ⚙️ Sonnet
follow-up.

> **Framing note.** "AutoResearch" is *our* engine's automated self-improvement
> loop (the L9 companion) — it is **not** a subsystem inside jumpshot 5.60 to be
> reverse-engineered. There is no 5.60 AutoResearch to be faithful to; the
> harness's own fidelity is validated against *our* known-good manual A/Bs
> (see § Harness self-validation).

## Decision

Build AutoResearch as a **faithfulness-constrained search loop** with four
load-bearing constraints. It generalizes the existing archive instruments — it
does not replace the metric or invent a new one.

### 1. Metric — distributional distance to the archive target bands (unchanged)

The harness scores each trial against the *same* ground truth the manual A/Bs
use, so a harness result and a hand-run result are directly comparable:

- **Ending-mix bands** (per-game rate targets): FG% ∈ [47.5, 48.9], steals
  ∈ [8, 9], independent TO ∈ [4.4, 5.4], pace ≈ 104.6, plus scoring-margin
  spread.
- **Four-term Cov/Var decomposition** (ADR-0049): the *sign and magnitude* of
  Cov(lnPOSS, lnPPS) and its companions — the structural terms that caught the
  ADR-0085 masking, which a scalar band-fit alone would have missed.

Ground truth is the frozen real-corpus archive (~23,714 games); the freeze arms
make each trial reproducible for a fixed seed. A per-trial **scalar fitness** is
computed and logged for ranking convenience — but see constraint 3: the scalar
is a *summary*, never the acceptance criterion.

### 2. Legal parameter space — an allowlist, default-frozen (safe-by-omission)

**Only explicitly registered stand-in constants may be perturbed. Everything
else is frozen.** This is the crux. In the engine source a pinned literal and a
provisional one are indistinguishable to a machine (`= 18 // asm-verified` reads
the same as `= 17.7 // provisional re-center`). A *denylist* of frozen values is
unsafe: the day someone RE-pins a new constant and forgets to add it to the
denylist, the loop perturbs a faithful value — a silent fidelity breach with no
error.

The registry is therefore an **allowlist with default-deny semantics**: a
constant is perturbable **iff** it is registered as an admitted stand-in
(provisional re-centers, validation-phase placeholders, instrument-only knobs).
Anything not registered — every RE-pinned formula and constant — is frozen by
omission. Adding a stand-in is a deliberate, reviewable act; forgetting to
freeze something is impossible because freezing is the default.

The registry is machine-readable (a manifest the harness loads, or struct tags
it reflects over — mechanism deferred to the ⚙️ build). This ADR pins the
**property** (default-deny allowlist), not the encoding. A CI gate should assert
that every perturbable entry cites its stand-in justification, so a pinned value
can never quietly enter the search space.

### 3. Acceptance rule — a leverage report for RE prioritization, never an auto-commit

**The harness's primary output is a per-stand-in × per-term leverage table, not
a "keep the lowest-loss config" verdict.** For each registered stand-in it
reports how much moving that stand-in shifts each metric term — a ranked map of
*where fidelity work has the most leverage.* That is its deliverable: it tells a
human *which RE question is worth a Fable session next*, ordered by measured
impact.

It does **not** decide what ships. The ADR-0085 masking story is the empirical
reason: a config with lower corpus loss can be the one hiding two cancelling
fidelity bugs. A scalar fitness as the *acceptance criterion* reintroduces
exactly the tune-to-corpus temptation the faithfulness bar forbids; a scalar as
a per-trial *summary line* is fine. So:

- Trial results feed **RE prioritization** — "moving stand-in X by Δ shifts
  Cov(lnPOSS,lnPPS) most; that's the highest-leverage RE target."
- A candidate stand-in value the loop surfaces is a **hypothesis for human
  review**, routed through `/plan` with `auto_merge: false` — never a direct
  commit. It ships only once a human RE-grounds it in 5.60 arithmetic (or
  explicitly re-blesses it as a provisional stand-in with a recorded reason).
- **Sub-noise deltas are reported as null.** Any leverage smaller than sampling
  noise is "within sampling noise" (ADR-0085 discipline), not a signal — the
  report must state the noise floor for the trial's season count and suppress
  anything under it.

### 4. Harness self-validation — reproduce a known manual A/B before it is trusted

Because there is no external AutoResearch to be faithful to, the harness earns
trust by **reproducing the numbers of a manual instrument it is meant to
generalize.** Its acceptance test is:

- Re-run the **J23 `base_time` sweep** (the `basetimemid_sweep_archive_test.go`
  axis, whose coupled fix shipped in PR #1495) through the harness and confirm
  the leverage it reports matches the hand-run sweep within the noise floor.
- Re-run the **J26 ceiling probe** (zeroing a Phase-4 term moved FG% +2.83pp in
  the manual prototype) and confirm the harness recovers the same lever
  magnitude. *(Steal/non-steal turnover-scale arm: automated by the J14 harness —
  `steal_turnover_scale` and `non_steal_turnover_scale` are registered stand-ins in
  `StandInRegistry()` (`engine/internal/calibrate/standinregistry.go`); the sweep
  runs automatically via `jsbcalibrate --mode research`.)*

Seed/determinism is controlled via the freeze arms so these are exact
comparisons, not eyeballed. A harness that cannot reproduce a known A/B is not
trusted to rank unknown ones.

### Non-goals

- **Never perturb a pinned value.** Default-deny allowlist; a pin entering the
  search space is a bug, not a config choice.
- **Never auto-commit a trial result.** Output is a prioritization report +
  human-review hypotheses (`/plan`, `auto_merge: false`).
- **Never report a sub-noise delta as signal.** State the noise floor; suppress
  below it.
- **Not a new metric.** Reuses the ADR-0049 decomposition and the ending-mix
  bands; adds no target the manual A/Bs don't already measure against.
- **Not a replacement for RE.** It ranks *where to point* reverse-engineering;
  it does not substitute for grounding a value in 5.60 arithmetic.

## Consequences

- **What we gain:** the leverage measurement that today costs a human an
  afternoon per axis runs hundreds of times overnight, producing a ranked RE
  backlog instead of a single hand-picked sweep. The faithfulness bar is
  preserved *by construction* — the loop cannot tune to the corpus because it
  has no authority to commit and no pinned value in its reach.
- **What we pay:** the stand-in registry is new maintenance surface — every
  stand-in must be registered with a justification, and the CI gate that
  enforces "perturbable ⇒ cited stand-in" is one more gate to keep green
  (weighed against the meta-tooling bar at build time; the allowlist manifest is
  the cheap form, a bespoke `bin/` script the expensive one). The self-
  validation suite must be kept in lockstep with the J23/J26 instruments it
  mirrors.
- **Why the ⚙️ build is separable:** this ADR fixes the *contract* (metric,
  legal space, acceptance rule, self-validation). The Sonnet follow-up
  implements the loop, the manifest encoding, and the report format against that
  contract — no further design judgment is delegated.

## Lineage

- **Companion to loop L9** (`loop-engineering-backlog.md`) — this ADR is the
  design L9 asked for and **records the rejection of L9's "keep only
  improvements" acceptance rule** as incompatible with the faithfulness bar.
- **Extends ADR-0049** — reuses the four-term Cov/Var decomposition as the
  structural half of the metric; the harness is an automation layer over that
  instrument, not a new one.
- **Grounded in ADR-0085** — the truncation-accidentally-compensating-`base_time`
  masking is the empirical argument for why a better corpus fit cannot be the
  acceptance criterion. This ADR generalizes that single incident into a
  standing constraint.
- **Supersedes nothing.** Backlog: J14 (`jsb-native-backlog.md`).
