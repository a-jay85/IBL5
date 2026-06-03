# Team-Scoring Dispersion — Channel Audit (volume vs efficiency vs coupling)

> **Type:** reverse-engineering faithfulness audit, measurement-first. **Ships
> instrument + docs, no model fix.** The deliverable is the 2×2 measurement (§1),
> the lever it identifies (§2), and the go/no-go it forces on the inherited
> candidates (§5). The durable decision is **ADR-0041**. Every number is
> reproducible from the committed artifact
> `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
> via the harness (`--mode calibrate --selection season --runs 1`); every engine
> claim is grep-reproducible against `engine/`.
>
> **Question.** ADR-0040 concluded 5.60 disperses team offense through per-48
> shot-VOLUME rates and shipped candidate **A** (source the real rates into the
> bucket composite). A was implemented (PR #966) and was **null on dispersion**
> (`pf_dispersion_ratio` 0.346→0.345, memory `reference_jsb_season_aggregate_verdict`).
> So the live question is no longer "is it volume or efficiency" in the abstract —
> it is **what, measured on the full archive, actually produces the real
> team-scoring spread, and which part of that does the engine fail to reproduce.**
>
> **One-line answer.** The engine compresses TOTAL team scoring (`Var(lnPF)` =
> 0.34× real) but **deviates on three axes at once**, and not one of them is "the
> marginals are too narrow": (1) the volume marginal is **2.6× too WIDE**
> (`Var(lnFGA)`), (2) the efficiency marginal is **2.0× too WIDE** (`Var(lnPPS)`),
> and (3) the volume↔efficiency **covariance is wrong-signed** — real teams
> reinforce (`+`, shoot more *and* convert better), the engine anti-couples (`−`),
> and that cancellation is what collapses the total. The legible diagnostic: a team
> that shoots 10% more scores **~12%** more in 5.60 (scoring-on-volume slope ≈
> **1.20**) but only **~2.4%** in the engine (slope ≈ **0.24**) — which is what
> `pf_corr ≈ 0` looks like at the mechanism level. **Matching real dispersion
> therefore requires BOTH restoring the positive covariance AND narrowing the two
> over-wide marginals** — fixing the covariance alone overshoots total dispersion
> ~2.3× (§2). The slope is a diagnostic, *not* an optimization target (it can be
> hit by narrowing volume, which moves total dispersion the wrong way). Candidates
> A and B move the *volume marginal* and push it WIDER — the wrong direction on
> axis (1) and silent on axis (3); that is why A was null (§3).

---

## §1 — The 2×2 measurement (the new instrument)

The calibrate harness now decomposes team scoring on **both** sides of the
comparison. For each (season, team) it records points-for/game `PF` and total
field-goal attempts/game `FGA` (= the validate-layer `"fga"` stat, already
2pt+3pt summed — see `fgaFor`), and pools by game type. Per side it computes the
exact log-variance identity, demeaned **within season** so the corpus's 24→26→28-
team era pace shifts (memory `reference_jsb_corpus_completeness`) do not leak into
the volume term:

```
Var(lnPF) = Var(lnFGA) + Var(lnPPS) + 2·Cov(lnFGA, lnPPS),   PPS := PF/FGA
```

Regular season (`game_type 2`, **N = 484** team-seasons), from the committed
artifact `season_aggregates.fidelity[0]`:

| term | REAL (.sco) | ENGINE |
|------|-------------|--------|
| `Var(lnPF)`            | 0.00332 | 0.00114 |
| `Var(lnFGA)` (volume marginal)     | 0.00133 | **0.00345** |
| `Var(lnPPS)` (efficiency marginal) | 0.00145 | 0.00293 |
| `Cov(lnFGA, lnPPS)`    | **+0.00027** | **−0.00262** |

**The engine's marginals are not compressed — they are WIDER than real.** The
engine's team-to-team volume spread is 2.6× real (`Var(lnFGA)` 0.00345 vs 0.00133;
the raw-space `volume_dispersion_ratio` is 0.879 only because raw pooling re-folds
the era pace structure the demean strips) and its efficiency spread is 2.0× real
(`efficiency_dispersion_ratio` = **1.659** > 1). Yet total `Var(lnPF)` is **0.34×**
real. The compression lives entirely in the **covariance sign**: real teams
**reinforce** (high-volume teams are also more efficient, `Cov > 0`); engine teams
**anti-couple** (`Cov < 0`), and the cancellation collapses total scoring even
though both inputs are over-dispersed.

The same pattern holds in the playoff bucket (`game_type 4`, N = 194): engine
`efficiency_dispersion_ratio` 1.406 (> 1), real slope 0.82 vs engine 0.36 (§2).

---

## §2 — The lever, in directly-observed variables (artifact-free)

`PPS := PF/FGA` is a *derived* quotient, so `lnPPS = lnPF − lnFGA` shares the
`lnFGA` term and `Cov(lnFGA, lnPPS) = Cov(lnFGA, lnPF) − Var(lnFGA)`. The headline
must therefore be stated in the two variables **summed straight from box scores**,
`lnPF` and `lnFGA`, where no shared-term artifact can inflate it. From the four
terms above, `Cov(lnPF, lnFGA) = Var(lnFGA) + Cov(lnFGA, lnPPS)`:

| metric (regular season) | formula | REAL | ENGINE |
|---|---|------|--------|
| **scoring-on-volume slope** | `Cov(lnPF,lnFGA)/Var(lnFGA)` | **1.20** | **0.24** |
| volume share of scoring var | `Cov(lnPF,lnFGA)/Var(lnPF)`  | 48% | 73% |
| efficiency share            | `1 − volume share`           | 52% | 27% |

**The legible statement:** *shoot 10% more shots → score 12% more in 5.60, but
only 2.4% more in the engine.* That single number is what `pf_corr ≈ 0.018`
(scoring not tracking team quality) looks like at the mechanism level: the engine
barely lets shot volume translate into points, and volume is ~half of what varies
across real teams. The slope is **runs-stable** — re-measured at `--runs 10` over a
stride-sampled set it held at engine ≈ 0.21 vs real ≈ 1.45 (so the collapse is not
a `--runs 1` artifact).

**The slope is a DIAGNOSTIC, not the optimization target.** `slope =
Cov(lnPF,lnFGA)/Var(lnFGA)`, so a model PR could "hit" slope ≈ 1.2 by *narrowing*
`Var(lnFGA)` — which lowers total `Var(lnPF)`, the opposite of the fidelity goal.
The thing to optimize is the **covariance sign and the total `Var(lnPF)`**; the
slope is exposition. And the covariance is not the *whole* fix either: the engine's
marginals are already too wide (§1), so **restoring real's correlation alone
overshoots.** Give the engine real's correlation (+0.193) at its current wide
marginals: `Cov = 0.193·√(0.00345·0.00293) = 0.00061`, total `Var(lnPF) = 0.00345 +
0.00293 + 2·0.00061 = 0.0076 ≈ 2.3×` real's 0.00332. **Matching real dispersion
needs the positive covariance AND both marginals brought down toward real** — three
coordinated moves, not one knob.

**Hypothesis #1 is refuted as stated.** The prompting one-season dump suggested
"scoring varies ~2× more through efficiency than volume." On the full corpus the
real efficiency/volume variance ratio is `Var(lnPPS)/Var(lnFGA)` ≈ **1.09** —
essentially **even**, not 2×, and the *covariance-attribution* split is 52/48. The
real driver is not an efficiency channel that out-varies volume; it is that both
channels **co-move** with team quality. The one-season figure was era/sample
structure the within-season demean removes.

---

## §3 — Why candidate A (and B) are not the dispersion fix

ADR-0040 candidate A wired the real per-48 volume rates into the +0xD90 2pt-bucket
composite (`bucketweights.go`). That composite is a **within-possession shot-TYPE
allocator** (memory `reference_play_outcome_buckets`): it decides how a possession's
shot is distributed across the 2pt/3pt/and-one buckets. It is **not** the team's
total shot-volume knob and **not** an efficiency knob. A moved (at most) the shape
of the volume marginal — the **one axis that is already too WIDE** (§1) — and did
nothing to the wrong-signed covariance (axis 3). It pushed the volume marginal in
the **wrong direction** and was silent on the actual coupling defect; that is the
structural reason it was null (0.346→0.345).

**Measured directly: A was null, not harmful, and the three deviations all
pre-date it.** Re-running this instrument against the **pre-A** engine (revert
`e6f1f18fc`, same `--runs 1` corpus pass) gives engine `Var(lnFGA)` = **0.00364**
(2.7× real) and slope **0.214** — already over-wide, already decoupled, *before* A.
A nudged the volume marginal slightly toward real (0.00364→0.00345) and the slope
up trivially (0.214→0.240), nowhere near real's 1.20. So A neither caused the
volume over-dispersion nor the covariance sign; it was a genuine no-op on the
dispersion axes. This **reconciles with ADR-0040 rather than contradicting it**:
0040's bucket-mechanism reading stands; its claim that the volume *marginal* was the
dispersion lever does not.

Candidate **B** (the +0xD90 Branch-B usage-shrink) is likewise a volume-marginal
modulator reading the same season aggregates (ADR-0040 §3). Same verdict: it acts on
an already-too-wide marginal and is silent on the covariance. **B is therefore
dropped from the dispersion track.**

---

## §4 — Structural RE: where the engine decouples volume from scoring

The measurement says the coupling is severed; the engine source says *why it is
free to be*. The two determinations are structurally independent:

```
# make-value (PPS) path reads no volume input:
grep -nE 'FGA|ORB|volume|bucket' engine/internal/sim/shotdecision.go engine/internal/sim/netadvantage.go | grep -v '//'   # → none
# the 2pt volume composite reads no make-quality input:
grep -nE 'FGP|OO|DriveOff|PO|net' engine/internal/sim/bucketweights.go | grep -v '//'   # → only andOneBucketWeight:168 (the minor and-one bucket), never twoPtBucketWeight
```

Combined with ADR-0040 §1 (the four offense-quality channels — ball-handler share
is scale-invariant, shot-type routing is weak, the net make-value term is
±17‰/±3.8% and defense-canceling, the foul-bucket divisor is minority), **no path
in the engine makes a team's shot volume and its scoring efficiency rise together
in proportion to team quality.** Volume comes from the volume-rate composite;
make value is FGP-dominated and league-uniform; nothing ties "this is a good
offense" to *both* more shots *and* more points-per-shot the way real basketball
does.

**What this audit does NOT claim.** It has *measured* a strongly negative engine
`Cov(lnFGA, lnPPS)` (an active anti-coupling, not mere independence), but it has
**not located in the decompile the specific 5.60 mechanism that makes good real
offenses convert volume into points**, nor the engine dynamic that inverts it.
That trace — *what couples volume to scoring in jumpshot 5.60* — is the scoped
next RE step (it is the model PR's target, per ADR-0041), and must be
measure-then-trace, not pre-judged. Stating a literal "tradeoff mechanism" here
would repeat the #957/ADR-0040-style error of asserting a mechanism ahead of the
evidence.

---

## §5 — Verdict and negative findings

| Candidate | Verdict | Rationale |
|-----------|---------|-----------|
| **(A)** source real volume rates into the bucket composite | ✖ **NO-GO as the dispersion fix** | §3: acts on the volume marginal — the axis already too WIDE — and pushes it wider; silent on the covariance. Empirically null (0.346→0.345); over-dispersion pre-dates A. |
| **(B)** port the +0xD90 Branch-B usage-shrink | ✖ **NO-GO as the dispersion fix** | §3: another volume-marginal modulator on the same aggregates; same wrong axis. |
| **(fix)** restore the positive volume↔efficiency covariance **and** narrow both over-wide marginals toward real | ✅ **the faithful direction — model-PR target** | §1-§2: three coordinated moves (covariance sign + two marginals); covariance alone overshoots ~2.3×. Optimize total `Var(lnPF)` / covariance sign — **not** the slope (a diagnostic). RE target per §4. |

### Negative findings (paths proven NOT the fix / claims NOT made)

1. **The defect is NOT a compressed marginal — REJECTED.** The intuitive reading
   ("the engine flattens team scoring, so widen the spread") is falsified: both
   engine marginals are *wider* than real (§1). Any fix that *adds* marginal
   dispersion (A, B, or a constant tune) pushes the wrong knob; the faithful fix
   **narrows** both marginals toward real while restoring the positive covariance —
   three coordinated moves, not one (§2 overshoot).

2. **Hypothesis #1 (efficiency out-varies volume ~2×) is NOT supported on the full
   corpus.** `Var(lnPPS)/Var(lnFGA) ≈ 1.09`; covariance-attribution 52/48. The
   one-season dump's 2× was sample/era structure removed by the within-season
   demean (§2).

3. **The coupling mechanism is NOT yet located in the decompile.** §4 measures the
   anti-coupling and shows the engine's two paths are structurally independent, but
   deliberately does not name the dynamic. That RE is the model PR's job.

### Reconcile the two dispersion totals (so a reviewer is not tripped)

The long-standing headline `pf_dispersion_ratio = 0.373` is **raw-pooled** across
all (season,team) rows; it carries cross-era pace structure. The figure the
decomposition explains is the **within-season-demeaned** log-space PF ratio
`sqrt(Var(lnPF)_eng / Var(lnPF)_real) = sqrt(0.00114/0.00332) = 0.586`. Both
describe the same compression; the decomposition (§1) is stated against 0.586, and
0.373 = 0.586 plus the era-pace structure the demean strips. The within-season
demean is correct and is kept.

This audit ships **no model code**; the dispersion fix (restore the covariance,
narrow both marginals) is a separate model PR scoped by §5. The decision is
recorded in **ADR-0041**.

---

## Reproduce-the-evidence index

| Claim | Command |
|-------|---------|
| §1 2×2 terms (both sides) | `jq '.season_aggregates.fidelity[0]' engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json` |
| §1/§2 slope + shares | derive from the four `*_var_ln_*` / `*_cov_ln_fga_ln_pps` terms: `Cov(lnPF,lnFGA)=Var(lnFGA)+Cov(lnFGA,lnPPS)`; slope `=/Var(lnFGA)`; share `=/Var(lnPF)` |
| §2 runs-stability | `jsbcalibrate --mode calibrate --selection season --runs 10 --sample-stride 12 --archive ibl5/backups` → engine slope ≈ 0.21 |
| §1 identity closes | `engine/internal/calibrate/standings_test.go` `TestCollectFidelitySummaries_EngineSideCouplingSign` (real & engine identities to 1e-9) |
| §4 make-value reads no volume | `grep -nE 'FGA\|ORB\|volume\|bucket' engine/internal/sim/shotdecision.go engine/internal/sim/netadvantage.go \| grep -v '//'` |
| §4 volume composite reads no make-quality | `grep -nE 'FGP\|OO\|DriveOff\|PO\|net' engine/internal/sim/bucketweights.go \| grep -v '//'` |

## Source-of-record

- `engine/internal/calibrate/standings.go` — the instrument: `FidelitySummary`
  volume/efficiency block, `decomposeLogVariance`, `decompRows`.
- `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
  — the full-corpus 2×2 artifact.
- `engine/docs/team-offense-dispersion-audit.md` — the prior (volume) audit this
  supersedes on the dispersion lever.
- `ibl5/docs/decisions/0040-team-offense-dispersion.md` — the ADR being reconciled.
- `ibl5/docs/decisions/0041-team-scoring-dispersion-channel.md` — the decision.
- Memories: `reference_jsb_season_aggregate_verdict`, `reference_play_outcome_buckets`,
  `reference_jsb_corpus_completeness`, `reference_sco_fgm_is_2pt`,
  `reference_jsb_winshare_runs_artifact`.
