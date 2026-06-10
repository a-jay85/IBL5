---
description: ADR-0049 PR-2 (the LEAD measure-then-build spike on the shots-per-possession anti-coupling). The ADR-0049 instrument localized ~72% of the engine's wrong-signed Cov(lnFGA,lnPPS) to a shots-per-possession ANTI-coupling Cov(ln(FGA/POSS),lnPPS) ≈ −0.000873 that 5.60 lacks (real ≈ +0.000027) — the empty/miss-driven FGA putback loop. This PR implements two origin-scoped decoupling arms behind the freeze.go seam (MakePutback full / MakePutbackHalf blend), routing OriginOffReb 2pt make-value to the per-season league mean to strip the team-quality variance feeding the putback efficiency↔volume coupling, plus a Phase-1 made-FG-by-origin efficiency instrument. Off by default → golden byte-identical. Measured as a 3-config A/B (OFF / full / half) over the full backup archive (705 zips, stride 1, runs 20). VERDICT — measured NULL: neither arm flips the headline Cov(lnFGA,lnPPS) (OFF −0.001210 → full −0.001201 / half −0.001205, real +0.000269), and the shots-per-poss FACTOR barely moves (−0.000873 → −0.000869 / −0.000870, ~0.5%). So the putback make-VALUE variance is NOT the dominant carrier of the factor — the anti-coupling is structural to the miss→ORB→retry VOLUME loop, not the make-value spread. The arms DO narrow Var(lnPPS) (1.570e-3 → 1.496e-3, toward real 1.451e-3) and Var(lnFGA) slightly toward real without regression — a small marginal-fidelity side effect, not the covariance fix. Both arms ship OFF as permanent measurement seams (golden unchanged, no regen), recorded null per the ADR-0048 Branch-B precedent. The count-residual axis (Cov(lnPOSS,lnPPS) ≈ −0.000337) and base_time pace remain the explicitly out-of-scope secondary axis.
last_verified: 2026-06-10
---

# ADR-0053: Shots-per-possession decoupling — origin-scoped putback make-value arms (measure-then-build)

**Status:** Accepted
**Date:** 2026-06-10

## Context

ADR-0049 (PR #1005, the measure half of this two-PR program) shipped a read-only
possession-count instrument that splits the engine's wrong-signed `Cov(lnFGA,lnPPS)`
(the ADR-0042 team-scoring defect: engine ≈ −0.00121 vs real ≈ +0.00027) into a
possession-**count** factor and a **shots-per-possession** factor, using the symmetric
Dean-Oliver true-possession proxy `FGA + 0.44·FTA + TOV − ORB` on both sides
(`engine/docs/possession-count-coupling-trace.md` §B.1):

| Cov(lnFGA,lnPPS) split (regular bucket) | engine | real | note |
|---|---|---|---|
| `Cov(lnPOSS,lnPPS)` (count factor) | −0.000337 | +0.000241 | covariance gap near the ~3e-4 noise floor — reported, not leaned on |
| `Cov(ln(FGA/POSS),lnPPS)` (shots/poss factor) | **−0.000873** | +0.000027 | **the robust signal**: an engine anti-coupling (~72% of the total) that 5.60 lacks |

The shots-per-possession factor is the **empty/miss-driven FGA loop**: a missed shot can be
offensive-rebounded and retried within the same possession (`possession.go:124-130`), so a
team that takes *more* shots per possession is, in the engine, scoring *less* per shot — the
inverted coupling 5.60 does not have. ADR-0049 named the LEAD for PR-2: **remove the
empty-FGA shots-per-possession anti-coupling.** It explicitly did NOT pin the exact code
change — the trace ruled out every obvious lever (literal ORB-continuation removal,
`maxOffensiveRebounds`, Branch-B, pace), leaving the `ln(FGA/POSS)` make-value path as the
surviving suspect. So PR-2 is a **measure-then-build spike**: diagnose, then A/B a small named
set of candidate decouplings behind the existing measurement seam, and either promote a winner
(a real behavior change, golden regen) or record a measured null.

This is the **ADR-0048 Branch-B precedent applied deliberately**: a candidate is built behind a
freeze toggle, measured over the full archive, and ships as a permanent off-by-default
measurement seam with a recorded null when it fails the gate. **A documented null is a
first-class outcome of this PR.**

## Decision

### The mechanism — why an origin-scoped make-value decoupling is structurally valid

The putback continuation (`OriginOffReb`, `possession.go:128-130`) routes a team's own
make-quality through the **same** `makeValue2pt` → `rollMake` path as the initial attempt
(`freeze.go:212`, `possession.go:225`); `origin` was label-only. So the putback attempt's
make/miss carries the *same* cross-team make-value variance as the initial shot — and because
a putback only exists *after* a miss (a low-efficiency event), that variance feeds the
efficiency↔volume **anti**-coupling. Routing **only** `OriginOffReb` 2pt make-value through
the league-mean make-value removes that team-quality variance from the putback efficiency
without touching the initial attempt — a structural decoupling, not a magnitude tweak.

### Two A/B arms, spanning decoupling STRENGTH (winner undetermined at write time)

`Var(lnPPS)` is ~2× too wide, so neutralizing putback efficiency *narrows* it toward real —
until it overshoots below real. The discriminating axis is therefore strength:

- **`MakePutback`** (full): putback 2pt make-value → the full per-season league mean
  (`FreezeMeans.MakeVal2pt`).
- **`MakePutbackHalf`** (blend): putback 2pt make-value → the halfway blend
  `(live + mean)/2` — the hedge if the full substitution over-narrows `Var(lnPPS)` below real
  or under-flips the headline.

Both reuse the harvested `FreezeMeans.MakeVal2pt` (no new accumulator) and are **off by
default**, so a zero `Options` stays byte-identical to `Simulate` (the golden fixture is
unchanged unless a winner is promoted).

### Implementation (one PR, the freeze.go seam the spike turns on)

- **Phase-1 instrument** (`compare.go` `OriginFGA`, `harness.go` `accumulateOriginFGA`):
  the by-origin FGA path is extended to tally **made** field goals by origin
  (`EventShotMake`, which `creditMadeFieldGoal` already emits with `Origin`), so per-origin
  shooting efficiency (made/attempts) — and the empty-loop signature `OrebMade == 0` while
  `Oreb > 0` — is directly observable. Counting an existing event changes no engine behavior
  (golden byte-identical). This is DISTINCT from the season-aggregate
  `EngineCovLnShotsPerPossLnPPS` factor and must not be conflated with it.
- **The arms** (`freeze.go` `FreezeConfig.MakePutback`/`MakePutbackHalf`, `makeValue2pt`
  gains an `origin` parameter, `validate()` rejects an arm with an unset mean): the sole
  caller `possession.go:154` passes `origin`; the transition path passes `OriginTransition`
  (the arm is `OriginOffReb`-scoped, so fast-break shots keep their live make-value).
- **Threading**: the four `validate.*With` signatures (`ValidateCorpusWith`,
  `ValidateUnscheduledWith`, `validateGame`, `simulateGameMeans`) are unified onto a single
  `sim.Options` passthrough, replacing the two positional `branchB bool, accum` parameters
  (avoids signature creep — a 4th positional bool — and carries the harvest `Accum` + arm
  `Means` the arms need). A zero `sim.Options{}` is byte-identical to the pre-PR path
  (`TestValidateCorpusWith_OffDefaultUnchanged`).
- **Per-season-bucket Means harvest** (`season.go` `validateWithArms`): because the arms
  consume the era-specific league-mean make-value, the real default closure runs a per-bucket
  **two-pass** — a harvest pass (a *fresh* `sim.FreezeAccum`, allocated inside the closure body
  so each season harvests its OWN mean, never a cross-era global) then a frozen pass at the
  SAME seed (so the arm perturbs the same realized games the mean was harvested from). This
  mirrors `CollectFreezeAttribution`'s two same-seed passes; it is the genuinely novel seam
  (Branch-B has no Means; the freeze-attribution lattice has Means but bypasses
  `CollectSeasonReports`/`FidelitySummary`).
- **CLI** (`jsbcalibrate`): `--makePutback` / `--makePutbackHalf` flags; the existing
  `--mode measure` `writeMeasureVerdict` PASS key is reused, not rebuilt.

## The gate (machine-checkable)

For an arm to WIN and be promoted, on the regular-season bucket (game type 2):

1. **Headline** `Cov(lnFGA,lnPPS)` (`EngineCovLnFGALnPPS`) sign FLIPS to match real
   (`writeMeasureVerdict` prints `sign=MATCH`; currently `FLIP-NEEDED`). **AND**
2. `Var(lnPPS)` NOT regressed (`|ON−real| ≤ |OFF−real|`). **AND**
3. `Var(lnFGA)` narrowed toward real.

**Decision rule:** exactly one arm clears → that arm wins (promote, golden regen). Both clear →
prefer the gentler (Half) if it satisfies the gate without over-narrowing `Var(lnPPS)`.
Neither clears the headline → the null branch (both arms stay as permanent off-by-default
measurement seams, golden unchanged).

### The pre-registered likely outcome

The PASS keys on the **headline** sign, but this lead targets only the shots-per-poss
**factor**. Zeroing the −0.000873 factor leaves the headline at the out-of-scope count
residual `Cov(lnPOSS,lnPPS)` ≈ −0.000337 — **still negative → still FLIP-NEEDED**. For the
headline to PASS a candidate must push its own factor not to 0 but to **≥ +0.000337** (a tall
order for a pure decoupling). So the documented **null is the likely outcome**, and the
measurement is run at BOTH the factor and headline level so the ADR can distinguish "mechanism
worked at the factor level, headline gated on the separately-scoped count axis" from
"mechanism failed." A count/`base_time` candidate to force the flip is the **secondary axis,
explicitly out of scope** (see Out of Scope).

## Results — measured verdict

The 3-config A/B (OFF / `MakePutback` / `MakePutbackHalf`) is measured over the full local
`.plr` backup archive (705 zips, stride 1, runs 20) via
`internal/calibrate/makeputback_archive_test.go`:

```
cd engine && JSB_ARCHIVE_DIR=…/ibl5/backups JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
  go test -tags archive ./internal/calibrate -run MakePutbackDecoupling -v -timeout 12h
```

It emits the committed artifacts `calibration-5.60-20260610-makePutback-{off,on}.json` and
`calibration-5.60-20260610-makePutbackHalf-on.json` into `internal/validate/testdata/` and logs
the factor + headline readout per arm. The walk completed in ~41 min (2435 s, 5 walk-equivalents
— OFF + each ON config's per-season harvest+frozen two-pass) over 21 qualifying seasons.

### Measured verdict — a NULL on the gate (regular bucket, engine vs real)

| metric | OFF | `MakePutback` (full) | `MakePutbackHalf` | real |
|---|---|---|---|---|
| **HEADLINE** `Cov(lnFGA,lnPPS)` | −0.001210 | −0.001201 | −0.001205 | **+0.000269** |
| **FACTOR** `Cov(ln(FGA/POSS),lnPPS)` | −0.000873 | −0.000869 | −0.000870 | +0.000027 |
| residual `Cov(lnPOSS,lnPPS)` | −0.000337 | −0.000332 | −0.000335 | +0.000241 |
| `Var(lnPPS)` | 0.001570 | 0.001496 | 0.001532 | 0.001451 |
| `Var(lnFGA)` | 0.001798 | 0.001797 | 0.001796 | 0.001330 |

Per-arm verdict signals: `headline_flipped=false`, `factor_flipped=false`,
`pps_regressed=false`, `var_fga_narrowed=true` for BOTH arms. Neither clears gate criterion 1
(headline sign flip), so **neither arm is promoted** — the null branch. The `var_fga_narrowed`
/ `Var(lnPPS)` improvements do NOT constitute a partial win: the gate keys on the covariance
SIGN, and a marginal-variance nudge that leaves the sign wrong is not the defect's fix.

**This is a CONFIRMED-ENGAGED null, not a never-engaged no-op** (the ADR-0048 engagement-proof
discipline). The OFF and ON passes run identical seeds, games, and order, and `SimulateWith` is
deterministic, so any OFF≠ON delta is 100% attributable to the arm. `Var(lnPPS)` moved
1.570e-3 → 1.496e-3 (≈4.7%) — the arm demonstrably engaged and removed real putback make-value
variance. The null is therefore a genuine measured result: the arm did its job and the
covariance simply did not respond. (The arm carries no separate engagement counter like
Branch-B's `BranchBAccum`; the deterministic-A/B marginal delta IS the engagement proof.)

**Harness self-validation (the Control-A analog).** The OFF factor (−0.000873) and headline
(−0.001210) reproduce ADR-0049's published `Cov(lnFGA,lnPPS)` split *exactly*, which proves the
new `sim.Options` passthrough and the per-season harvest+frozen two-pass closure
(`validateWithArms`) are sound end-to-end — the OFF self-reference lands where ADR-0049's
independent instrument put it, so the ON deltas are measured against a trustworthy baseline.

### Diagnosis — why this null is sharper than the pre-registered "likely null"

The pre-registered likely null was "the factor moves as designed but the headline stays gated
on the out-of-scope count residual." The measurement is **stronger than that**: the FACTOR
itself **barely moves** (−0.000873 → −0.000869, ≈0.5%) while the `Var(lnPPS)` **marginal** moves
≈4.7%. The clean reading of that split: putback make-value variance feeds the per-shot-efficiency
**marginal** (`Var(lnPPS)`), but **not its COUPLING with volume** (`Cov(lnFGA,lnPPS)`). So the
result is not "mechanism worked, headline gated on the count axis" — it is **the putback
make-VALUE variance is NOT the dominant carrier of the shots-per-possession anti-coupling.**
Substituting the league-mean make-value on `OriginOffReb` shots removes real efficiency spread
(the 4.7% marginal move) but that spread is largely uncorrelated with a team's shot VOLUME, so
the efficiency↔volume covariance is untouched. A make-value decoupling is the wrong lever for
this factor: the anti-coupling lives in the *volume* a low-efficiency team accrues by missing and
retrying, not in the *make-value* spread on those retries.

The narrowing of `Var(lnPPS)` toward real (1.570e-3 → 1.496e-3, real 1.451e-3) and `Var(lnFGA)`
without regression is a real but orthogonal marginal-dispersion improvement — not the covariance
SIGN fix the gate requires.

### Decision

Both arms ship **OFF by default as permanent measurement seams** (the golden fixture is
**unchanged** — no `make golden-update`), exactly as ADR-0048 landed the refuted Branch-B arm.
The committed `calibration-5.60-20260610-makePutback-*` artifacts are the durable evidence. The
shots-per-possession anti-coupling remains an open defect, but the lever is **not** a make-value
decoupling, and **not** the ORB-continuation loop itself (ADR-0049 ruled that faithful to 5.60 —
Out of Scope #2). The next lead is the deliberately-deferred secondary axis: the count-residual
`Cov(lnPOSS,lnPPS)` and a faithful off/def-ratio `base_time` pace (trace §B.2) — pending the
full-precision confirmation it clears the ~3e-4 noise floor.

## Out of Scope

- **`base_time` off/def-ratio pace** and any possession-count *dispersion* work — the
  SECONDARY, conditional axis (trace §B.2). This PR does NOT add a count/`base_time` candidate
  to force the headline flip even though the gate arithmetic shows the headline likely stays
  negative without it; forcing it would smuggle in the secondary axis.
- **Literal removal of the ORB-continuation loop** (`possession.go:124-130`) — trace §A.3: the
  engine's offensive-rebound continuation is FAITHFUL to 5.60; deleting it breaks a correct
  mechanism.
- **Lowering `maxOffensiveRebounds`** — an ORB-continuation *magnitude* (level) contribution,
  not the dispersion lever (negative finding #4).
- **Retrying Branch-B** (ADR-0048, closed null) or the fast break as an offense channel
  (defense-converted, trace §A.2).
- **PHP / DB / web / migration** — single Go module; no SQL, HTTP endpoint, auth route, or
  user-facing rendering, so there is no security surface to audit.
