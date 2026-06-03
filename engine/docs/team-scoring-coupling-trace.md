# Team-Scoring Coupling — RE Trace (volume→points)

> **Type:** reverse-engineering faithfulness audit, measurement-first. **Ships
> docs only, no model code** (ADR-0040 precedent). The deliverable is the *located
> mechanism class* (§2), its corroboration (§3), the proven-NOT findings (§4), and
> the bounded set + scoped fix-direction it hands the downstream model-fix PR (§5).
> The durable decision is **ADR-0042**. Every engine claim is grep-reproducible
> against `engine/`; the decomposition terms come from the committed #972 artifact
> `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`;
> the rating correlations are reproducible via `bin/db-query` (§ Reproduce index).
>
> **Question (ADR-0041's scoped next step).** #972 measured the deficit — engine
> team-scoring dispersion deviates on three axes, and *the deficit is the
> covariance sign* (real `Cov(lnFGA,lnPPS) = +0.00027`, engine `−0.00262`),
> artifact-free slope `Cov(lnPF,lnFGA)/Var(lnFGA)` = **1.20 real vs 0.24 engine**.
> #972 deliberately did not name the mechanism. This trace answers: **what, in the
> engine, generates the negative sign — and what does 5.60 do that the port
> dropped?** Measure-then-trace, not pre-judged.
>
> **One-line answer.** The engine has **no shot-volume-rate → shot-COUNT pathway.**
> A team's shot-volume rates (`r_fga/r_3ga/r_fta`) — which *are* positively coupled
> to its efficiency in the inputs (`corr(VOL-rating, FGP) = +0.265`) — feed only
> shot-*type*, 3pt-propensity, and transition-rate, never *how many* shots the team
> takes. Output FGA ≈ the (defense-paced, ~0.8%-spread) possession count plus the
> miss→rebound→retry loop. So the positive input coupling never reaches output
> volume, and team-to-team FGA variance is **miss-dominated, not make-dominated**:
> of the engine's FGA variance only **24% converts to points (slope 0.24); 76% is
> "empty" FGA** (missed shots that don't score). In 5.60 FGA varies because good
> offenses take more *makeable* shots, so FGA tracks PF (slope 1.20). That missing
> volume→count channel is the mechanism class; the exact empty-FGA source split
> (ORB cap, TVR, foul-draws) is the bounded part the fix PR calibrates.

---

## §1 — The finding, stated artifact-free (lnPF / lnFGA only)

`PPS := PF/FGA` is a derived quotient, so `lnPPS = lnPF − lnFGA` shares the `lnFGA`
term; `Cov(lnFGA,lnPPS)` and its correlation are **not** headlined here (the
shared-term artifact #972 set aside). Everything below is in the two box-summed
variables `lnPF` and `lnFGA`, where no shared term inflates it. From the committed
artifact (regular season, `game_type 2`, N = 484), with
`Cov(lnPF,lnFGA) = Var(lnFGA) + Cov(lnFGA,lnPPS)`:

| metric | formula | REAL (.sco) | ENGINE |
|--------|---------|-------------|--------|
| `Var(lnFGA)` | — | 0.00133 | 0.00345 |
| `Cov(lnPF,lnFGA)` | `Var(lnFGA)+Cov(lnFGA,lnPPS)` | +0.00160 | +0.00083 |
| **scoring-on-volume slope** | `Cov(lnPF,lnFGA)/Var(lnFGA)` | **1.20** | **0.24** |
| volume share of scoring var | `Cov(lnPF,lnFGA)/Var(lnPF)` | 48% | 73% |
| **"empty" FGA fraction** | `1 − slope` | **−20%** | **+76%** |

**The legible statement:** in the engine, **76% of team-to-team FGA variance
produces zero points** — slope 0.24 means shoot 10% more → score only 2.4% more.
In 5.60 the "empty" fraction is *negative* (−20%): shoot 10% more → score 12% more,
because the extra volume arrives *with* extra efficiency (good offenses take more
makeable shots). The engine's extra shots are extra *misses*. Equivalently,
`Cov(lnFGA,lnPPS) = −0.00262 = −0.76 × Var(lnFGA)`: the negative sign is exactly
the empty-FGA fraction. The slope is **runs-stable** (#972: engine ≈ 0.21 vs real
≈ 1.45 at `--runs 10`), so this is structural, not a `--runs 1` artifact.

**The slope is a diagnostic, NOT an optimization target** (inherited from #972): it
can be "hit" by *narrowing* `Var(lnFGA)`, which lowers total dispersion — the wrong
direction. The fix optimizes total `Var(lnPF)` and the covariance **sign**.

---

## §2 — The located mechanism class: no shot-volume-rate → shot-COUNT pathway

The engine's two scoring inputs are structurally independent (#972 §4: the volume
composite and the make-value path share no inputs). The question is *why output FGA
variance is empty*. Tracing the volume-determining path shows the answer is an
**absence**, not an inversion knob:

**(a) Half-court FGA ≈ the possession count, and the possession count is
defense-paced and nearly flat.** `gameloop.go:29-32` computes ONE shared
`step = possessionTime(avg(teamBaseTime(visitor), teamBaseTime(home)))` per game;
possessions strictly alternate and the clock decrements by that shared `step`
(`gameloop.go:45-60`), so **both teams get the same possession count**.
`teamBaseTime` (`tempo.go:21-39`) maps only the **defensive** composite
(`OD+DD+PD+TD`) onto the `[13,16]`s clamp. Measured spread: team defensive
composite sd ≈ 1.38 on 36 → base_time varies ±0.12s on ~15s = **~0.8%**. Pace is a
minor volume driver and is *defense*-keyed, not offense-keyed.

**(b) The shot-volume rates never set shot COUNT.** `r_fga/r_3ga/r_fta` feed only:
shot-*type* via the 2pt bucket composite (`bucketweights.go`, `twoPtBucketWeight`
reads `p.FGA`), 3pt propensity (`possession.go:32-38`, `r_3ga/(r_fga+r_3ga)`), and
the transition trigger rate (`transition.go:29-35`, `resetTransitionShotRate` =
Σ`(FGA+TGA+FTA)`). **None of these change how many shots the team attempts.** A
half-court possession yields ~1 attempt regardless of `r_fga`.

**(c) What is left driving output FGA variance is the miss/rebound/turnover
process.** Within a trip (`possession.go:73-139`): a **make ends the possession**
(one FGA, `return false`); a **miss → offensive rebound → `continue`** (a second
attempt, ≥2 FGA); a turnover ends it with **zero** FGA. So FGA-per-possession rises
with *misses-that-are-rebounded* and falls with *makes* and *turnovers* — a process
mechanically **decoupled from**, and partly inverse to, scoring.

**Conclusion (the mechanism class):** because the positively-coupled volume rates
(§3) have **no channel to output shot count**, the residual source of FGA variance
is the miss/rebound/turnover loop, which does not score. That is why FGA variance is
empty (slope 0.24) instead of make-coupled (slope 1.20). **The dropped 5.60
behavior is a volume-rate → shot-count pathway**: 5.60 lets a good, high-volume
offense actually take more (makeable) shots — via its per-team `base_time` (a team
**offensive/defensive** stat ratio, `00_MASTER_REFERENCE.md` L690-702, FUN_004e4150,
re-verified 2026-05-30; the port kept only the defensive side, `tempo.go:14-20`) and
its volume rates — so volume co-moves with scoring.

---

## §3 — Corroboration (three independent lines)

1. **The input ratings ARE positively coupled — the engine discards it.** Across the
   28 active IBL teams (minutes-weighted team composites, `bin/db-query`):
   `corr(VOL-rating, FGP) = +0.265`, `corr(VOL-rating, O-composite) = +0.416`,
   `corr(O-composite, FGP) = +0.320`. IBL teams that shoot more *are* more
   efficient — the same positive volume↔efficiency structure real teams have
   (real `Cov(lnFGA,lnPPS) > 0`). The volume-rate CV is **8.6%** (wider than the
   engine's 5.9% output FGA sd), so the coupling is present and large in the inputs;
   the engine's negative output sign is therefore generated by the *transform* (the
   missing volume→count channel), not by the rating structure.

2. **ADR-0040 candidate A corroborates the absence.** #966 routed the *real* per-48
   volume rates into the 2pt-bucket composite and was **null on dispersion**
   (0.346→0.345). That is expected here: the bucket is a shot-*type* allocator, not a
   shot-*count* channel — feeding real volume rates into shot-type cannot move a
   count that nothing reads. A's null is direct evidence the volume→count pathway is
   the one that is missing.

3. **The level deficit shares the root.** The engine scores ~23.5 ppg/team low,
   driven by ~25 fewer FGA/team (`reference_jsb_season_aggregate_verdict`). Same
   cause: with no volume-rate → count channel, the engine cannot reproduce
   high-volume offenses' attempt totals. Building the channel is expected to narrow
   the level deficit *and* restore the covariance sign — one root, two symptoms.

> **Measurement provenance.** The decomposition terms are the full 484-team-season
> archive (#972 artifact). The rating correlations are a current-roster snapshot
> (28 teams, dev DB) characterizing IBL roster construction, which is structurally
> stable across seasons (`reference_jsb_season_rating_stability`). The two are
> consistent but not the same corpus; the correlations establish *direction and
> magnitude of the input coupling*, not a re-derivation of the decomposition.

---

## §4 — Negative findings (candidates proven NOT the generator)

1. **Defense-inverted pace is NOT the generator — REFUTED by data.** The a-priori
   parsimonious hypothesis ("`teamBaseTime ∝ +D` ⇒ FGA `∝ −D`; if O and D correlate
   positively, that alone generates `Cov < 0`") was tested and fails twice: (i)
   through PPS's *dominant* channel, `corr(D-composite, FGP) = −0.117` (≈ 0, slightly
   negative) — so defense-inverted pace would push the sign *positive*, not negative;
   (ii) magnitude — pace varies only ~0.8% (§2a), far too small to source the
   engine's 2.6×-too-wide `Var(lnFGA)`. Pace is a minor, defense-keyed volume term;
   it is not the empty-FGA generator.

2. **Fatigue/energy is NOT the generator — structurally inert.** The natural suspect
   (more possessions → fatigue → lower efficiency) is ruled out in code: live energy
   is "inert under current curve" (`possession.go:85`, `transition.go:106`) and
   FG-make fatigue is "≈ 1.0 anyway" (`possession.go:152-153`). It cannot produce the
   covariance.

3. **A make-value (`net`) reweight is NOT the fix — REJECTED (inherited).** 5.60's
   make value is net-light by design and the port matches it exactly (ADR-0040 §4);
   both marginals are already too wide (#972), so pushing a make-value/marginal knob
   is the wrong axis.

---

## §5 — Measurement limit, bounded set, verdict, scoped fix-direction

**Measurement limit (recognized, not a gap).** Cross-team rating correlations
decisively located the mechanism *class* (the missing volume→count channel) and
refuted two named candidates, but they **cannot attribute the empty-FGA variance
among its within-possession sources** — the offensive-rebound continuation (cap
`maxOffensiveRebounds = 8`, `possession.go:7`), the TVR turnover rate, and
foul-draws diverting attempts to FT are not linear in team ratings; separating them
needs sim instrumentation, which is out of scope for this docs-only trace (no
ablation rig, per the inherited direction). Recognizing this limit **is** the
terminal state.

| Item | Verdict |
|------|---------|
| **Missing shot-volume-rate → shot-COUNT pathway** | ✅ **LOCATED — mechanism class.** Output FGA variance is miss-dominated (76% empty) because the positively-coupled volume rates never reach shot count; corroborated by ADR-0040-A's null and the level deficit. |
| Empty-FGA source split (ORB cap, TVR, foul-draws) | ◻ **BOUNDED — fix-PR calibration.** The within-possession contributors to empty FGA; attributable only with sim instrumentation. |
| Defense-inverted pace | ✖ **REFUTED** (§4.1). |
| Fatigue/energy | ✖ **REFUTED — inert** (§4.2). |
| Make-value `net` reweight | ✖ **REJECTED — inherited** (§4.3). |

**Scoped fix-direction for the downstream model-fix PR (ADR-0042).** Build a
**shot-volume-rate → shot-COUNT pathway** so team FGA varies via coupled
makes/volume rather than misses — restoring 5.60's positive volume↔scoring slope.
This is faithful to 5.60's per-team `base_time` offensive/defensive stat-ratio pace
plus its volume-rate mechanics, and it addresses the level deficit at the same root
(§3.3). It is explicitly **NOT** "add offense to the pace clamp" (pace is too minor
a volume lever, §2a/§4.1) and **NOT** a make-value reweight (§4.3). Optimize against
total `Var(lnPF)` and the covariance **sign**, never the slope (§1). Because the
input coupling already exists (§3.1) and the marginals are *over*-wide (#972),
routing volume through a count channel must *replace* a dispersion source, not add
one — building the channel should narrow `Var(lnFGA)` toward real while flipping the
sign.

This audit ships **no model code**; the fix is a separate PR scoped by §5. The
decision is recorded in **ADR-0042**.

---

## Reproduce-the-evidence index

| Claim | Command |
|-------|---------|
| §1 decomposition terms (both sides) | `jq '.season_aggregates.fidelity[0]' engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json` |
| §1 slope / empty-FGA | from those four terms: `Cov(lnPF,lnFGA)=Var(lnFGA)+Cov(lnFGA,lnPPS)`; slope `=/Var(lnFGA)`; empty `=1−slope` |
| §2a shared, defense-paced step | `engine/internal/sim/gameloop.go:29-60`, `engine/internal/sim/tempo.go:21-39` |
| §2b volume rates feed type/transition, not count | `grep -nE 'p\.(FGA\|TGA\|FTA)' engine/internal/sim/bucketweights.go engine/internal/sim/possession.go engine/internal/sim/transition.go` |
| §2c make ends / miss→ORB→retry | `engine/internal/sim/possession.go:73-139` |
| §3.1 input coupling (28 teams) | `bin/db-query "SELECT teamid, SUM(dc_minutes*(r_fga+r_3ga+r_fta))/SUM(dc_minutes) v, SUM(dc_minutes*r_fgp)/SUM(dc_minutes) f FROM ibl_plr WHERE active=1 AND teamid BETWEEN 1 AND 32 AND dc_minutes>0 GROUP BY teamid"` → `corr(v,f)=+0.265` |
| §4.1 defense-inverted pace refuted | same query with `(od+dd+pd+td)` as D-composite → `corr(D,FGP)=−0.117`; pace spread `≈ (1.38/36)·3 / 15 ≈ 0.8%` |
| §4.2 energy inert | `grep -n 'inert under current curve\|≈1.0 anyway' engine/internal/sim/possession.go engine/internal/sim/transition.go` |

## Source-of-record

- `engine/internal/sim/gameloop.go`, `tempo.go`, `possession.go`, `transition.go`,
  `bucketweights.go`, `netadvantage.go` — the volume/efficiency paths traced (§2, §4).
- `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
  — the #972 full-corpus 2×2 artifact (§1).
- `engine/docs/team-offense-dispersion-audit.md` (ADR-0040) — the make-value /
  bucket reading this builds on (§3.2, §4.3).
- `engine/docs/team-scoring-dispersion-channel-audit.md` (ADR-0041) — the three-axis
  decomposition and the sign-is-the-deficit framing this trace answers (§1).
- `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` — base_time / tempo
  (L690-702, FUN_004e4150), transition (L878-930) (§2).
- Memories: `reference_jsb_season_aggregate_verdict`, `reference_play_outcome_buckets`,
  `reference_jsb_season_rating_stability`, `reference_sco_fgm_is_2pt`,
  `reference_jsb_winshare_runs_artifact`.
