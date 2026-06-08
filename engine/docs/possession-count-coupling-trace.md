---
description: RE trace of JSB 5.60's possession-generation layer (the possession loop, the offensive-rebound continuation, base_time pace, and the independent fast-break gate), locating the missing positive volume↔efficiency coupling after the bucket-side search closed. ADR-0048 eliminated Branch-B — the last bucket-side lever — so the wrong-signed Cov(lnFGA,lnPPS) (engine −0.0013 vs real +0.0003) is confirmed structural/non-arm and the search moves UPSTREAM of the half-court shot-type pick FUN_004e1ba0. PR 1 of 2 (measure-then-build): ships a read-only possession-count instrument (the symmetric Dean-Oliver true-possession proxy FGA+0.44·FTA+TOV−ORB on both sides, plus the engine authoritative EventPossessionStart count as a level-validation diagnostic) that splits Cov(lnFGA,lnPPS) into a possession-COUNT term and a shots-per-possession term. PRIMARY MEASURED RESULT (confirms ADR-0042): the engine's wrong sign is DOMINATED by a shots-per-possession ANTI-coupling (engine ≈−0.00087, ~72% of the total) that 5.60 does not have (real ≈0) — the empty/miss-driven FGA loop. SECONDARY (sign-independent, trustworthy): the engine UNDER-disperses possession count, Var(lnPOSS) ~2.5× too narrow. The count-factor COVARIANCE gap (engine − vs real +) sits near the noise floor (~3e-4) and is reported but not leaned on. Lead PR-2 = REMOVE the empty-FGA shots-per-possession anti-coupling; a faithful off/def-ratio base_time pace is a secondary, conditional note (the count-covariance axis is distinct from the marginal/level axis ADR-0047 refuted, but needs a full-precision confirmation it clears the noise floor). No engine behavior change (golden byte-identical, no freeze toggle — counting an existing event is not a behavior). Decision: ADR-0049.
last_verified: 2026-06-07
---

# Possession-count / ORB-continuation / pace coupling — RE trace (ADR-0048 follow-on)

> **Type:** reverse-engineering faithfulness audit, measurement-first. **Ships a
> read-only instrument + docs, no model behavior change** (ADR-0040/0042/0047
> precedent). The deliverable is the *located factor + the scoped, buildable
> fix-direction* for the downstream build PR — NOT a fix. The durable decision is
> **ADR-0049**.
>
> **Question (the user's chosen next step — continue measure-then-build).** The
> bucket-side search is now CLOSED: all four within-possession freeze arms
> (TVR/Foul/ORB/Make, ADR-0043/0045) **and** Branch-B (ADR-0048, the ADR-0047 prime
> suspect — built, measured, REFUTED null: it *deepens* the negative Cov and
> regresses `Var(lnPPS)`) are exhausted. None flips the wrong-signed
> `Cov(lnFGA,lnPPS)`. So the defect is confirmed **structural / non-arm**, and
> ADR-0048 consequence #1 narrows the surviving suspect to the **possession-generation
> layer** — *upstream* of the half-court shot-type pick `FUN_004e1ba0` (which is fully
> refuted as an in-pick reroute). This trace answers: **how does 5.60 let team
> offensive quality generate more possessions / shots, and which factor —
> possession COUNT or shots-per-possession — carries the missing positive coupling?**
>
> **One-line answer (measured, § Anchor).** Decompose the volume identity `lnFGA =
> lnPOSS + ln(FGA/POSS)` so `Cov(lnFGA,lnPPS) = Cov(lnPOSS,lnPPS) +
> Cov(ln(FGA/POSS),lnPPS)`, on the SAME Dean-Oliver proxy both sides (apples-to-apples).
> The robust, dominant result **confirms** the ADR-0042 a-priori guess: the engine's
> wrong-signed total is ~72% a **shots-per-possession anti-coupling** (engine
> `Cov(ln(FGA/POSS),lnPPS)` ≈ −0.00087) that **5.60 does not have** (real ≈ 0) — the
> empty/miss-driven FGA loop, where teams that take more shots per possession score *less*
> per shot. Two secondary observations on the possession-COUNT factor: the engine
> **under-disperses** possession count (`Var(lnPOSS)` ~2.5× too narrow — sign-independent,
> trustworthy), and its count↔efficiency *covariance* is wrong-signed vs real, but that
> covariance gap (~3e-4) sits near the corpus noise floor and is reported, not leaned on.
> **Lead fix (PR 2): REMOVE the empty-FGA shots-per-possession anti-coupling** (a removal,
> not an addition — 5.60 has ≈0 coupling there). A faithful off/def-ratio `base_time` pace
> is a *secondary, conditional* candidate for the count factor — the cross-team
> `Cov(lnPOSS,lnPPS)` axis is genuinely distinct from the marginal/level axis ADR-0047
> refuted, but it must first clear the noise floor at full precision. **This trace ships
> the read-only instrument + the localized verdict.** No engine behavior changes — the
> engine already emits the possession event; counting it is not a behavior, so unlike
> Branch-B (ADR-0048) this needs no freeze toggle and the golden fixture is byte-identical.

---

## § Load-bearing assumptions

1. **`.sco` is jumpshot 5.60's own output** (the fidelity target), not real-NBA — so the
   positive coupling exists in 5.60 and is portable (carried from ADR-0047 §1).
2. **The fix must REPLACE a dispersion source, not add one.** The engine marginals are
   already *over*-wide (`Var(lnFGA)` ≈2.6× real, `Var(lnPPS)` ≈2.0× real). Branch-B
   failed precisely by *adding* dispersion (ADR-0048). The faithful channel narrows a
   marginal **while** flipping the covariance sign (carried from ADR-0047 §2).
3. **Level ≠ dispersion.** Why POSS/FGA sit where they do *on average* (the ADR-0045
   level story: eng POSS 99.8 vs sco 106.6, FGA/poss 0.74 vs 0.93) is a different
   question from what generates the wrong-signed *cross-team* covariance.
4. **`lnFGA = lnPOSS + ln(FGA/POSS)` is exact**, so `Cov(lnFGA,lnPPS) =
   Cov(lnPOSS,lnPPS) + Cov(ln(FGA/POSS),lnPPS)` is an identity (the split closes by
   construction; the instrument's unit tests and the committed artifact both assert it).
   POSS is one of the two multiplicative factors of FGA — the factor the bucket-side
   search never isolated.
5. **An offensive rebound is a shots-per-possession event, not a possession-count
   event.** It continues the SAME offensive trip (one more shot, same possession), so
   true possessions subtract ORB: `POSS = FGA + 0.44·FTA + TOV − ORB`. (This corrects the
   task framing's "ORB→extra-possession" shorthand — ORB extends a possession, it does
   not start one.)

## § Anchor — the engine defect, reproducible (committed possession-coupling artifact, regular gt2)

`engine/internal/validate/testdata/calibration-5.60-20260607-possession-coupling.json`
(the artifact this PR ships; the headline `Cov(lnFGA,lnPPS)` reproduces the ADR-0047
baseline, and the NEW possession-count split localizes the wrong sign):

Split computed on the SAME Dean-Oliver proxy `FGA + 0.44·FTA + TOV − ORB` on **both** sides
(apples-to-apples; the authoritative `EventPossessionStart` count is a separate
level-validation diagnostic, § B.4):

| term | engine | real (.sco) | reading |
|------|--------|-------------|---------|
| `Cov(lnFGA,lnPPS)` (headline) | **−0.001210** | **+0.000269** | wrong sign (the ADR-0042 defect; reproduces the ADR-0047 baseline exactly) |
| ├ `Cov(lnPOSS,lnPPS)` (count factor) | −0.000337 | +0.000241 | covariance gap near the noise floor (~3e-4) — reported, not leaned on |
| └ `Cov(ln(FGA/POSS),lnPPS)` (shots/poss factor) | **−0.000873** | +0.000027 | **the robust signal**: engine anti-coupling (~72% of total) that 5.60 lacks |
| `Var(lnPOSS)` | 0.000288 | 0.000721 | engine count-spread ~2.5× too narrow (var ratio 0.40; raw poss_dispersion 0.79) — sign-independent |

(Committed artifact: runs=20, stride=1 — full precision.) The two split terms sum to the
headline `Cov(lnFGA,lnPPS)` to float tolerance (the identity of assumption 4, asserted on
real data by the archive test: engine −0.000337 + −0.000873 = −0.001210; real +0.000241 +
+0.000027 = +0.000269). Both sides use the proxy; the engine authoritative count (mean
101.9/team) reconciles with the proxy (99.7) and with ADR-0045's ~99–106, validating it
(§ B.4).

**The split reads (primary, robust):** the engine's wrong-signed total is **dominated by a
shots-per-possession ANTI-coupling** — `Cov(ln(FGA/POSS),lnPPS)` = −0.000873 (≈72% of
−0.001210) — that **5.60 does not have** (real +0.000027 ≈ 0). Teams that take more shots
per possession score *less* per shot: the empty/miss-driven FGA loop (ADR-0042). This is
stable across configs (−0.001175 at runs=4 → −0.000971 at runs=8 → −0.000873 at runs=20,
same sign and ~72–83% dominance) and **confirms** the a-priori shots-per-possession guess.

**Two secondary observations on the possession-COUNT factor:** (1) the engine
**under-disperses** possession count — `Var(lnPOSS)` 0.000288 vs real 0.000721, ~2.5× too
narrow (sign-independent, so robust to noise); (2) the count↔efficiency *covariance* is
wrong-signed (engine −0.000337 vs real +0.000241), **but** both are ~3e-4, inside the
corpus noise band (the branchB-off artifact showed real `Cov(lnFGA,lnPPS)` ranging
−0.0004…+0.0003 across buckets), and the real side's count ≈ total is partly tautological
(real shots-per-possession ≈ 0 ⟹ count ≈ total by near-collinearity). So the count
*covariance* gap is reported, not leaned on; only the *dispersion* gap is trusted.

## § Prong A — what 5.60 does at the possession-generation layer (decompile, RESOLVED)

The possession-generation layer sits *upstream* of the half-court shot-type pick
`FUN_004e1ba0` (the bucket pick refuted by ADR-0047/0048). Three mechanisms set how many
possessions — and how many shots per possession — each team gets:

**A.1 — base_time pace is quality-symmetric (PPS-neutral).** `FUN_004e4150` (master ref
L695-702): `base_time` is a team off/def stat ratio **hard-clamped to [13.0, 16.0]**, then
`possession_time = (2.0 − factor) × base_time` (factor = `+0x63B8` = **1.0** in IBL →
identity), out-of-range → 24.0. At factor 1.0 this is neutral NBA speed, **~99
possessions/team**, and possessions **alternate after each score** (`+0x0048`), counted
per-team at `+0x4C0C`. Pace scales a team's FGA *and* its PF together → points-per-shot
unchanged → it moves the *level*, never the volume↔efficiency *covariance*. (This is the
#974 / ADR-0047 B.2 refutation, carried forward — pace is not the lever.)

**A.2 — the fast break (extra transition possessions) is DEFENSE-converted and gated
UPSTREAM of the offense's pick.** Master ref "Transition Mechanics — FULLY RESOLVED"
(four stages):
- **Stage 1** — the fast-break flag (`CEngine+0x4BE4`) is set by a **defensive rebound or
  a steal** (i.e. by the team that just *gained* the ball on defense), not by anything the
  offense's half-court rating does.
- **Stage 2** — at the *next* possession's start the break fires only if `random_roll ≤
  player.TO + coaching_mod` — a **TO (transition-offense) gate**, checked before and
  independent of the half-court bucket pick.
- **Stage 4** — the fast-break outcome is `net = 5.0 − defender_TD`: a **fixed 5.0
  regardless of the offense's quality**, modulated only by the defender's TD.

So extra transition possessions accrue to whoever converts *defense* into offense (good
defensive-rebounding / stealing teams), at an efficiency the offense's rating does not
raise. A good half-court *offense* does not generate more of its own possessions this way.
In the engine this is the independent upstream fast-break gate (`possession.go:114-121`,
`transitionTriggers`/`transitionStealSucceeds`), not an in-pick reroute — confirming
ADR-0048's "independent upstream fast-break gate" statement.

**A.3 — the offensive rebound is the one OFFENSE-quality-coupled possession-extender —
and it extends a possession, it does not start one.** Master ref "Offensive Rebound
Probability" (re-verified 2026-05-30, rebound_handler `FUN_004d6f00`): `P(OREB) =
off_rating/(off_rating+def_rating) × 0.5 + 0.25` (floor .25 / ceil .75); the sequential
two-roll structure (`team_rebound_strength` `FUN_004e22a0` decides the team, then the
secondary retention roll → `offensive_rebound` `FUN_004ed110`, `+0xBC` OREB counter++).
This **is** team-quality-coupled (off/(off+def)). But an ORB continues the SAME trip — a
putback or reset within one possession — so it adds a **shot**, not a **possession**. It
therefore lands in the `ln(FGA/POSS)` factor of the identity, not `lnPOSS`. In the engine
this is the inner `for trip` loop (`possession.go:124-130`), where an ORB `continue`s with
`origin = OriginOffReb` and **no** new `EventPossessionStart` is emitted — so the engine's
authoritative possession count already treats ORB-continuations correctly as
within-possession, and the .sco proxy's `−ORB` term matches.

## § Prong B — engine-side: the split localizes the defect (measured, § Anchor)

**B.1 (PRIMARY, robust) — the engine carries a large shots-per-possession ANTI-coupling
that 5.60 does not have: the empty-FGA artifact.** On the symmetric proxy the split puts
`Cov(ln(FGA/POSS),lnPPS)` at engine **−0.000873** (≈72% of the −0.001210 total) vs real
**+0.000027** (≈ decoupled). So the bulk of the engine's wrong-signed headline Cov is a
shots-per-possession anti-coupling with **no 5.60 counterpart**: in the engine, teams that
take more shots per possession score *less* per shot — the miss→ORB→retry empty-FGA loop
(ADR-0042's 76%-empty finding; `possession.go:124-130` inner-trip continuation). This is the
dominant, stable signal (−0.001175 at runs=4 → −0.000971 at runs=8 → −0.000873 at runs=20,
same sign and ~72–83% dominance) and it **confirms** the ADR-0042 shots-per-possession
a-priori guess. 5.60 has
essentially zero shots-per-possession coupling, so the faithful fix is to **remove** this
engine artifact, not add coupling here. (The committed ORB freeze arm being ≈0 on the Cov,
ADR-0047 B.3, is consistent: ORB is a shots-per-possession *level* contribution,
`share_oreb ≈ 0.25`, not the dispersion lever.)

**B.2 (SECONDARY) — the engine under-disperses possession count; its count↔efficiency
covariance is wrong-signed but near the noise floor.** Two distinct count-factor facts,
weighted differently:
- **Trustworthy (sign-independent):** `Var(lnPOSS)` engine 0.000288 vs real 0.000721 — the
  engine's team-to-team possession-count spread is ~2.5× too narrow. A variance gap does not
  depend on a sign clearing a noise floor, so this is a real secondary defect: the engine
  flattens possession count.
- **Reported, not leaned on:** `Cov(lnPOSS,lnPPS)` engine −0.000337 vs real +0.000241 — a
  wrong-signed count↔efficiency covariance, BUT both are ~3e-4, inside the corpus noise band
  (the branchB-off artifact showed real `Cov(lnFGA,lnPPS)` ranging −0.0004…+0.0003 across
  buckets), and the real side's count ≈ total is partly tautological (real shots-per-poss ≈ 0
  ⟹ count ≈ total). So the count *covariance* gap is not a load-bearing finding.

> **The count factor legitimately re-opens base_time pace on ONE axis — but as a
> conditional, not a headline.** `FUN_004e4150` computes base_time as a team **off/def stat
> ratio** (A.1), so it *could* couple possession count to team quality; the engine's
> `tempo.go` base_time is a **defense-composite placeholder** (ADR-0045) that would not. This
> is a different axis from ADR-0047's refutation — ADR-0047 B.2 refuted pace on the
> *marginal/level* axis (PPS-neutral *within* a team), which is distinct from the
> *cross-team* `Cov(lnPOSS,lnPPS)`. The distinction is real, but the count covariance it
> rests on is at the noise floor, so base_time is a **secondary, conditional** PR-2 idea: a
> full-precision run must first confirm the count factor clears the noise floor (and PR 2
> should re-measure #974's `offVolumeScale` with the split). Do NOT lead with it.

**B.3 — Branch-B (ADR-0048) is CLOSED — do not retry.** Built, measured, confirmed-engaged
null: deepens the negative Cov (−0.00121 → −0.00262), regresses `Var(lnPPS)` ≈3.2×. The
bucket-side reallocation hypothesis is refuted; the search has left the bucket pick.

**B.4 — methodology (symmetric proxy + count diagnostic).** The Cov split runs the SAME
Dean-Oliver proxy `FGA+0.44·FTA+TOV−ORB` on both sides (apples-to-apples — mixing a true
count against an FGA-derived proxy biases which factor absorbs the coupling, since the proxy
correlates with FGA by construction). The engine's authoritative `EventPossessionStart`
count rides alongside as a **level-validation diagnostic**: its mean (101.9/team) reconciles
with the proxy (99.7) and ADR-0045 (~99–106), confirming the proxy is a faithful
true-possession estimate. The count is kept OUT of the cross-side Cov split deliberately.

## § Verdict + scoped fix-direction

The wrong-signed `Cov(lnFGA,lnPPS)` is **non-arm / structural** (bucket arms + Branch-B all
exhausted), and the split now **localizes** it: it is two distinct defects on the two
multiplicative factors of FGA.

- **The instrument ships (this PR, ADR-0049):** the engine authoritative
  `EventPossessionStart` count + the .sco true-possession proxy `FGA+0.44·FTA+TOV−ORB`,
  threaded read-only through the season aggregate to emit `Var(lnPOSS)`,
  `Cov(lnPOSS,lnPPS)`, `Cov(ln(FGA/POSS),lnPPS)`, and `PossDispersionRatio`. No engine
  behavior change; the golden fixture is byte-identical with **no** freeze toggle (counting
  an event Simulate already emits is not a behavior — strictly cleaner than ADR-0048's
  `BranchBAccum`, which had to *run* a new behavior).
- **The localized defects (measured, § Anchor), in confidence order:**
  1. **PRIMARY (robust): a spurious shots-per-possession anti-coupling.** Engine
     `Cov(ln(FGA/POSS),lnPPS)` ≈ −0.00087 (≈72% of the wrong-signed total) vs real ≈ 0 —
     the empty/miss-driven FGA loop, an engine artifact with no 5.60 counterpart. Confirms
     ADR-0042; stable across configs.
  2. **SECONDARY (robust, sign-independent): possession-count under-dispersion.**
     `Var(lnPOSS)` engine 0.000288 vs real 0.000721, ~2.5× too narrow — the engine flattens
     team-to-team possession count.
  3. **REPORTED, not load-bearing: a count↔efficiency covariance gap** (engine − vs real +),
     ~3e-4, at the noise floor — needs full-precision confirmation before any build rests on it.
- **Buildable fix-direction for PR 2 (in priority order):**
  - **LEAD — remove the shots-per-possession anti-coupling** (defect 1): 5.60 has ≈0 coupling
    here, so the faithful target is to stop the engine's miss→ORB empty-FGA loop from
    anti-coupling shots-per-possession with efficiency — a *removal*, not an addition
    (assumption 2). This is the robust, dominant defect.
  - **SECONDARY / CONDITIONAL — possession-count dispersion + the off/def-ratio `base_time`
    idea.** Widen `Var(lnPOSS)` toward real (the trustworthy count-side gap). The off/def-ratio
    base_time candidate (5.60's base_time is a team off/def ratio, A.1; the engine's is a
    defense-composite placeholder, ADR-0045) targets the count↔efficiency covariance — but
    only pursue it AFTER a full-precision run confirms the count covariance clears the noise
    floor, and re-measure #974's `offVolumeScale` with the split first. It is a distinct axis
    from ADR-0047's marginal/level refutation, not a contradiction of it.
- **Success criterion (carried from ADR-0048 B7, plus the split constraint):** flip the
  headline `Cov(lnFGA,lnPPS)` sign and narrow `Var(lnFGA)` toward real WITHOUT regressing
  `Var(lnPPS)`; the dominant lever is removing the shots-per-possession anti-coupling.
  Optimize total `Var(lnPF)` + the sign, never the slope.
- **Do NOT** retry the fast break as an offense channel (A.2, defense-converted), Branch-B
  (ADR-0048, closed), or ORB-continuation magnitude alone (B.1, a shots-per-possession
  *level* contribution). Pace stays refuted on the marginal/level axis (ADR-0047); the
  count-covariance axis is a distinct, *conditional* re-opening gated on the precision run —
  not a headline.

## § Negative findings (candidates proven NOT the positive-coupling lever)

1. **base_time pace — on the MARGINAL/LEVEL axis only** — PPS-neutral *within* a team
   (A.1); the #974 `offVolumeScale` sweep added `Var(lnFGA)` and deepened the un-split
   negative Cov. Refuted (ADR-0047) **as a marginal/level lever**. The cross-team
   `Cov(lnPOSS,lnPPS)` axis is genuinely distinct and NOT covered by that refutation — but
   the count covariance the off/def-ratio base_time idea would target sits at the noise floor
   (B.2), so base_time is a *secondary, conditional* PR-2 candidate gated on a precision run,
   NOT a lead. Distinct axis; conditional verdict.
2. **the fast break as an OFFENSE-quality channel** — defense-converted (DRB/steal-set,
   TO-gated, fixed `5.0−TD` outcome), routed through an independent upstream gate (A.2).
   A good half-court offense generates none of its own; additive, not coupling.
3. **Branch-B usage-shrink** — built + measured, deepens the negative Cov, regresses
   `Var(lnPPS)` (ADR-0048). Closed; do not retry.
4. **ORB-continuation magnitude** — a shots-per-possession *level* contribution
   (`share_oreb ≈ 0.25`), freeze ORB arm ≈0 on the Cov (ADR-0047 B.3). Not the dispersion
   generator on its own — but the `ln(FGA/POSS)` factor it feeds is the surviving suspect
   the split measures.

## § Reproduce-the-evidence index

In-repo (CI-runnable):
- POSS split anchor: `bin/jsb-verdict engine/internal/validate/testdata/calibration-5.60-20260607-possession-coupling.json 'cov_ln_poss|var_ln_poss|shots_per_poss|cov_ln_fga_ln_pps'`
- Split-sum identity (unit): `go test ./engine/internal/calibrate -run DecomposePossCoupling`
- Engine authoritative count (unit): `go test ./engine/internal/validate -run 'AccumulatePossessions|ScoPossProxy'`
- Terse measure verdict line: `go run ./engine/cmd/jsbcalibrate --archive <dir> --mode measure` (the `Cov-split` + `Var(lnPOSS)` lines)
- No engine behavior change: `go test ./engine/internal/sim -run Golden` (passes with NO `-update`)
- Re-generate the anchor: `JSB_ARCHIVE_DIR=<dir> go test -tags archive ./engine/internal/calibrate -run PossessionCoupling`

Reference-only (decompile, outside the repo — not CI-runnable):
- `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` — "Transition Mechanics — FULLY
  RESOLVED" (four stages, `+0x4BE4`), "Offensive Rebound Probability" (`FUN_004d6f00` /
  `FUN_004e22a0` / `FUN_004ed110`), base_time L695-702 (`FUN_004e4150`, `+0x63B8`,
  possession count `+0x4C0C`, alternation `+0x0048`).
- `~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` — the bucket-pick / Branch-B
  context the search has now moved upstream of.
