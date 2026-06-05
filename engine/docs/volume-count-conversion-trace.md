---
description: RE trace of how JSB 5.60 converts a team's offensive volume into shot COUNT (the make-coupled volume→count channel ADR-0042 named but did not pin). Refutes base_time pace (PPS-neutral) and the +0xD90 Branch-A bucket composite (shared structure; ADR-0040-A null) as the lever, and disambiguates the miss→ORB empty loop (level) from the wrong-signed covariance (dispersion, non-arm per the exhausted freeze lattice). LANDS A NAMED PRIME SUSPECT: the deferred Branch-B usage path — the only bucket-side 5.60/engine difference, team-quality-gated (TransOff × team DRB+AST), plausibly a half-court→transition volume allocation, upstream of the four exhausted arms so the lattice never saw it. Its net Cov sign is unmeasured; the build PR's first task is to implement it behind a freeze toggle and measure. Decision: ADR-0047.
last_verified: 2026-06-05
---

# Volume → shot-COUNT conversion — RE trace (ADR-0042 follow-on)

> **Type:** reverse-engineering faithfulness audit, measurement-first. **Ships docs
> only, no model code** (ADR-0040/0042 precedent). The deliverable is the *located
> candidate + the scoped, buildable fix-direction* for the downstream build PR — NOT a
> fix. The durable decision is **ADR-0047**.
>
> **Question (the user's chosen next step — measure-then-build).** ADR-0042 located the
> team-scoring defect as a *missing volume-rate→shot-COUNT pathway* (engine
> `Cov(lnFGA,lnPPS)` wrong-signed; slope `Cov(lnPF,lnFGA)/Var(lnFGA)` = **0.24** engine
> vs **1.20** in 5.60; 76% of engine FGA variance is "empty"/miss-driven) and refuted
> pace / fatigue / make-value. Two build attempts have *since* landed and both nulled the
> Cov flip: #966 (real per-48 rates into the +0xD90 bucket) and #974 (`offVolumeScale`
> into `teamBaseTime`). This trace answers: **what, concretely, in 5.60 makes a
> high-volume offense take more *makeable* shots — and why did both build attempts
> fail?** Measure-then-trace, not pre-judged.
>
> **One-line answer.** Two of the leading candidates are *refuted* with decompile +
> committed-artifact evidence: base_time pace is **PPS-neutral by construction** (so it
> can never generate the covariance — #974's failure explained), and 5.60's per-possession
> bucket normalization is **structurally identical** to the engine's (so the bucket
> *structure* cannot be the differentiator). The single bucket-side difference that
> remains is the **deferred Branch-B usage path** — a team-quality-gated (TransOff × team
> DRB+AST) modulation of the 2pt weight that the engine omits (it implements only the
> cold Branch A). Branch B is the **prime, unmeasured suspect**: it sits *upstream* of the
> four within-possession arms (all exhausted, residual ≈ baseline — ADR-0045), so the
> freeze lattice never saw it, and the same TransOff signal that gates it also gates the
> (higher-EV) transition path — so the half-court volume it shaves plausibly *reallocates*
> to make-coupled transition shots. Whether that nets a positive `Cov(lnFGA,lnPPS)` is an
> empirical question a static read cannot answer. **The build PR's first task is to
> implement Branch B behind a freeze toggle and measure its Cov effect — a single step
> that both tests the prime suspect and builds the non-arm instrument the verdict needs.**

---

## § Load-bearing assumptions

1. **`.sco` is jumpshot 5.60's own output** (the fidelity target), not real-NBA. So 5.60
   *demonstrably* produces slope 1.20 — the coupling exists in 5.60 and is portable.
2. **The fix must REPLACE a dispersion source, not add one.** The engine marginals are
   already *over*-wide (`Var(lnFGA)` 2.6× real, `Var(lnPPS)` 2.0× real — § Anchor). A
   channel that *adds* make-coupled volume on top (the #974 failure mode) widens
   `Var(lnFGA)` further. The faithful channel narrows it (removing empty/miss-driven FGA)
   **while** flipping the covariance sign.
3. **The slope is a diagnostic, not an optimization target** (gameable by narrowing
   volume). Optimize total `Var(lnPF)` and the covariance **sign**.
4. **Level ≠ dispersion.** Why FGA is 76% empty *on average* (level) is a different
   question from what generates the wrong-signed *cross-team* covariance (dispersion).

## § Anchor — the engine defect, reproducible (committed #972 artifact, regular gt2, N=484)

`engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`:

| term | engine | real (.sco) | ratio |
|------|--------|-------------|-------|
| `Var(lnFGA)` | 0.003447 | 0.001330 | 2.6× too wide |
| `Var(lnPPS)` | 0.002933 | 0.001451 | 2.0× too wide |
| `Cov(lnFGA,lnPPS)` | **−0.002621** | **+0.000269** | wrong sign |

Artifact-free slope `Cov(lnPF,lnFGA)/Var(lnFGA)` = **0.24** engine vs **1.20** real (the
"76% empty FGA" headline). Runs-stable (ADR-0042 §1) — structural, not a `--runs 1`
artifact.

## § Prong A — what 5.60 actually does (decompile, RESOLVED)

The 5.60 per-possession outcome is `play_outcome_selector` (`FUN_004e1ba0`), a weighted
pick over four bucket weights plus an independent turnover check (master ref
"Play Outcome Probabilities", RESOLVED 2026-05-30):

```
total = local_ac(2pt) + local_8c(3pt) + local_e90(and-one) + local_e80(foul-only)
roll  = rand_double(0, total)        // cumulative pick → outcome 1..4
turnover override: rand_int(1,1793) ≤ sqrt(local_44)   // local_44 = +0xDF8 [2,5] → ~0.1%, negligible
```

The four bucket inputs are the ball-handler's **copied per-game composite doubles**
(master ref "✅ RESOLVED — play-outcome bucket inputs", esp delta 0x60):
`local_ac`=`+0xD90` (2pt), `local_8c`=`+0xDB0` (3pt), `local_5c`→foul=`+0xDE0`,
`local_44`=`+0xDF8`. **`+0xDB0`, `+0xDE0`, and `+0xD78` are mathematically always 0**
(COMPOSITE_DOUBLES_TRACE.md §intro) — so the live buckets are **2pt (`+0xD90`)**, and-one
(matchup `net×0.25`), and foul-only (the 0.6 floor). This is the *same* live set the
engine builds (`bucketweights.go`: 3pt folded as `2pt×propensity`, foul floored to 0.6).

`+0xD90` is a **usage-gated two-branch** composite (`FUN_004cfa50`,
`jsb560_decompiled.c:91072-91099`; COMPOSITE_DOUBLES_TRACE.md §4):

```
usage = player[+0x1E8](TransOff) × (team[+0xDC0] DRB-rate + team[+0xDD0] AST-rate) × 0.2 × 0.04
if (usage ≤ 0 || +0xD90 ≤ 0):  BRANCH A  (cold start, no usage data)
    +0xD90 = D88 − (D88/(D70+D88))·DB8·((D88/(DB8+D88))·0.5 + 0.25)      // self-contained rate composite, NO team coupling
else:                          BRANCH B  (steady state, usage > 0)
    s = (ΣD − usage) / ΣD ;  +0xD90 ×= s ; +0xDB0 ×= s ; +0xDE0 ×= s ; +0xD78 ×= s   // usage modulation, team-quality-gated
```

base_time (the pace channel, `FUN_004e4150`, master ref L695-702): a clamped [13,16] ratio
of team offensive/defensive per-game aggregates → `possession_time`. **Neutral pace =
~99 possessions/team** (L702); possessions **alternate after scoring** (`+0x0048`),
counted per-team at `+0x4C0C` — counts are ~symmetric per game.

## § Prong B — engine-side: two channels refuted, one prime suspect

**B.1 — The bucket normalization is shared structure (so structure is not the
differentiator).** `selectOutcome` (`engine/internal/sim/outcome.go:88-118`) is `total =
Σ weights; roll = rand×total; cumulative pick` — **byte-for-byte 5.60's normalization**,
same live bucket set. The engine's `twoPtBucketWeight` (`bucketweights.go:130-151`) even
carries the volume signal (`d88 = per48Min(RealLifeFGA, MIN)`). So the bucket *structure*
and the cold composite are faithful; the only bucket-side difference is which **branch**
runs (B.4).

**B.2 — base_time pace is PPS-neutral by construction (refuted).** #974 added the
offensive side to `teamBaseTime` (`tempo.go:87-107`): higher offensive volume → shorter
base_time → more possessions. But possessions scale FGA **and** PF together → points-per-
shot unchanged → pace moves the *level*, never the *volume↔efficiency covariance*.
`gameloop.go:44` averages both teams into one shared `step` and possessions strictly
alternate (`gameloop.go:71`), so counts are symmetric (matching 5.60's ~99/team). The
committed `offVolumeScale` sweep (`tempo.go:38-66`) confirms it: 0.02→0.14 *monotonically
widens* `Var(lnFGA)` (0.00265→0.00392) and *deepens* the negative Cov (−0.00176→−0.00189)
— it **adds** a dispersion source instead of replacing one (assumption 2). Refuted
regardless of per-team vs shared application.

**B.3 — the miss→ORB→retry loop is a LEVEL story, not the dispersion generator.** A missed
shot + offensive rebound `continue`s the trip (`possession.go:141-144, 151-154`) → another
empty FGA; that explains the 76%-empty *level*. It does **not** generate the cross-team
covariance: the freeze lattice (`calibration-5.60-20260604-freeze-attribution.json`) puts
the ORB-split arm at collapseFrac **−0.033** and the make-value arm at **+0.021** — both
≈ 0. Do not target it as the Cov lever.

**B.4 — Branch-B is the only bucket-side difference, and the PRIME (unmeasured) suspect.**
The engine implements **only Branch A** (the cold composite — master ref L472: *"no team
coupling"*; `bucketweights.go:42`: *"this port implements only Branch-A"*); Branch B is
deferred. Decisive that Branch A is *not* the lever: ADR-0040-A (#966) fed *real* per-48
volume rates through the cold composite → **identical** output dispersion (CV 0.097
collapses to 0.034 downstream). What Branch A omits is exactly the **team coupling**:

- Branch B modulates `+0xD90` by `s = (ΣD − usage)/ΣD`, with `usage = player[+0x1E8]
  (TransOff) × team(DRB-rate + AST-rate) × 0.008` — a **team-quality-coupled** quantity
  (DRB+AST are the team-efficiency signals the slope-1.20 coupling needs).
- It sits **upstream of the four within-possession arms**, which are *exhausted* (residual
  ≈ baseline, ADR-0045) — so the freeze lattice has **never seen it**. That is precisely
  where the non-arm covariance must live.
- The same `TransOff` signal that drives the Branch-B shrink also gates the engine's
  **transition** trigger (`transition.go:45-54`, `rand(1,20) ≤ TransOff`), and transition
  is the engine's *only* higher-EV shot source (`transitionNet = 5.0 − TD`,
  `transition.go:85`). So 5.60's Branch-B plausibly **reallocates** half-court 2pt volume
  into make-coupled transition shots (a conserved allocation), whereas the engine — having
  only Branch A — runs transition **additively** (TransOff adds fast breaks without shaving
  the half-court side). An allocation tied to team quality is a *candidate positive-coupling
  channel*; an additive one is not.

Whether Branch B nets a **positive** `Cov(lnFGA,lnPPS)` is a team-season empirical question
a static formula read cannot settle (the earlier "usage penalty / wrong sign" reading was
exactly that over-reach). It is the prime suspect, **unmeasured** — not refuted.

## § Verdict + scoped fix-direction

Two candidates are dead ends (do not retry): **base_time pace** (PPS-neutral, B.2) and the
**+0xD90 cold composite / bucket magnitude** (shared structure, ADR-0040-A null, B.1). The
miss→ORB loop is a *level* story, not the dispersion generator (B.3). The wrong-signed
covariance is a **non-arm** phenomenon (freeze arms exhausted, ADR-0045), and the one named
mechanism that lives upstream of those arms and carries team-quality coupling is **Branch B**
(B.4) — the prime, unmeasured suspect.

**Buildable fix-direction for the downstream PR (ADR-0047):**
- **First task — test the suspect AND build the instrument in one step:** implement Branch B
  behind a freeze toggle (the inputs are already identified in `bucketweights.go:38-50`:
  `player[+0x1E8]`=`r_trans_off`, team DRB/AST rates from the .plr team-summary rows, the
  pinned 0.2/0.04 constants), run it through the freeze-lattice / season-aggregate
  harness, and **measure** its effect on `Cov(lnFGA,lnPPS)`, `Var(lnFGA)`, and the
  half-court↔transition volume split. This simultaneously tests the prime suspect and gives
  the engine the non-arm attribution the four exhausted arms cannot provide.
- **Do NOT** retry pace (PPS-neutral) or bucket-magnitude tuning (washes out).
- Whatever the measurement shows, the eventual channel must **REPLACE** the miss-driven
  empty-FGA dispersion (narrow `Var(lnFGA)` toward real) **while** flipping the covariance
  sign — optimize total `Var(lnPF)` + sign, never the slope.
- If Branch B measures *null or wrong-signed*, the search moves to the remaining non-arm
  residual (a season-level FGA-count↔make instrument); that is the only branch on which the
  verdict reverts to "structural, not yet located."

## § Negative findings (candidates proven NOT the positive-coupling lever)

1. **base_time pace** — PPS-neutral by construction; #974 `offVolumeScale` sweep adds
   `Var(lnFGA)` and deepens the negative Cov (`tempo.go:38-66`). Refuted.
2. **The +0xD90 cold composite (Branch A) / bucket magnitude** — shared normalization
   structure (`outcome.go` ≈ `FUN_004e1ba0`); ADR-0040-A fed real volume rates → identical
   output dispersion. Refuted as the dispersion lever.
3. **miss→ORB→retry loop** — explains the empty-FGA *level*, not the cross-team Cov; freeze
   ORB/Make arms ≈ 0. Refuted as the dispersion generator.

(Branch B is deliberately **not** a negative finding — it is the named prime suspect,
unmeasured. The earlier "usage penalty" static read is retracted: the shaved volume
plausibly reallocates to higher-EV transition, so the net Cov sign must be measured.)

## § Reproduce-the-evidence index

In-repo (CI-runnable):
- Engine defect anchor: `jq '.season_aggregates.fidelity[] | select(.game_type==2) | {engine_var_ln_fga, real_var_ln_fga, engine_cov_ln_fga_ln_pps, real_cov_ln_fga_ln_pps}' engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json`
- Freeze-lattice arms exhausted: `jq '{residual_frac_of_baseline, arms:[.arms[]|{arm,cov_pps_collapse_frac}]}' engine/internal/validate/testdata/calibration-5.60-20260604-freeze-attribution.json`
- Shared normalization: `engine/internal/sim/outcome.go:88-118` (`selectOutcome`).
- Volume in the bucket, Branch-A only: `engine/internal/sim/bucketweights.go:38-50, 130-151`.
- PPS-neutral pace + sweep: `engine/internal/sim/tempo.go:38-66, 87-107`.
- Shared step / alternation: `engine/internal/sim/gameloop.go:44, 71`.
- TransOff gates transition (the Branch-B reallocation target): `engine/internal/sim/transition.go:45-54, 85`.
- miss→ORB retry: `engine/internal/sim/possession.go:141-144, 151-154`.

Reference-only (decompile, outside the repo — not CI-runnable):
- `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` — Play Outcome Probabilities
  (`FUN_004e1ba0`), bucket-input resolution (esp delta 0x60), base_time L695-702.
- `~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` §4 — the +0xD90 cold composite
  + Branch-B usage modulation.
- `~/Downloads/jsb_560/decompiled/jsb560_decompiled.c:91072-91099` — the two-branch +0xD90.
