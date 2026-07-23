---
description: Faithful JSB 5.60 putback (OReb-continuation, OriginOffReb) shot resolution shipped UNCONDITIONALLY ON as the default live engine. The RE faithfulness audit found two decompile-verified divergences in how the engine resolves a half-court putback: (1) make-value — 5.60 (jsb560_decompiled.c:93880-93883) computes the putback 2pt make as player[+0xD60] × 1.3333 (net-advantage-free, 4/3-boosted), but the engine routed putbacks through the normal net-coupled shotValue2pt(net,fgp,false); (2) 3pt suppression — 5.60 (94022-94024) re-loops a 3pt outcome on the OReb flag forcing a 2pt, so putbacks are never 3pt, but the engine let outcome3pt be selected. Both are fixed via a net-free boosted putbackValue2pt helper in makeValue2pt and a threePtW=0 zero in the half-court trip loop. The net-coupled un-boosted putback make-value is the suspected carrier of the empty-FGA shots-per-possession anti-coupling (ADR-0049 Cov(ln(FGA/POSS),lnPPS) ≈ −0.00087, real ≈ 0): low-efficiency teams miss putbacks more → more miss→ORB→retry loops → extra empty FGA → wrong-signed factor. INVERTED-POLARITY escape hatch FreezeConfig.UnfaithfulPutback (default false = faithful = production) restores master's coupled behavior for the archive A/B's OFF walk ONLY. Golden bytes change BY DESIGN (regenerated with -update); TestDeterminism stays green. Measured as a 2-config A/B (OFF=UnfaithfulPutback / ON=faithful) over the full backup archive (705 zips, stride 1, runs 20). VERDICT — SUCCESS per the pre-registered gate: the FACTOR Cov(ln(FGA/POSS),lnPPS) moves toward real (OFF −0.000873 → ON −0.000800, real +0.000027) with NO Var(lnPPS) regression — Var(lnPPS) in fact improves to ≈ real (0.001570 → 0.001458, real 0.001451), and headline/count-residual/both variances all move the correct direction. Magnitude caveat: the factor gap closes ~8% and the headline does not flip (−0.001210 → −0.001120, real +0.000269) — the faithful form is A carrier, not THE dominant one, refining ADR-0053's null (make-value VARIANCE is not the carrier; the make-value FORM is, but the bulk stays structural to the empty-FGA volume loop, left to the OOS count axis ADR-0054 / offVolumeScale #974). Ships as faithful production behavior on RE-verified merits independent of magnitude. CORRECTION 2026-07-22 — divergence (2) is REVERSED: the 3pt-suppression claim rests on a decompile MISREAD. local_15c is the OReb *continuation* flag (the possession do-while's loop condition at 94379), assigned from the rebound routine at 94019, so the goto at 94024 is the loop-back for the next iteration, NOT a 3pt→2pt re-roll. 5.60 in fact CLEARS the shot-clock restriction on an OReb continuation (93278-93280), guaranteeing the full four-bucket set, and FUN_004e1ba0's reject-retry (97194-97196) has no OReb branch at all — putback 3pt IS reachable in 5.60. The threePtW=0 zero is removed; a new NORMAL-polarity FreezeConfig.SuppressPutback3pt reproduces the old behavior for the A/B only. Divergence (1) (make-value) and UnfaithfulPutback's gating of it are UNCHANGED and still faithful. Proof + A/B: jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md.
last_verified: 2026-07-22
---

# ADR-0055: Faithful putback shot resolution

**Status:** Accepted — **divergence (2) REVERSED 2026-07-22** (see Correction)

**Date:** 2026-06-10

## Correction (2026-07-22): divergence (2) was a decompile misread

Everything below about the **make-value** (divergence (1)) stands. The **3pt-suppression**
half (divergence (2)) does not: it was read off `jsb560_decompiled.c:94022-94024` as "5.60
re-loops a 3pt outcome on the OReb flag, forcing a 2pt." It does not.

- `local_15c` is the **OReb continuation flag**, not a shot-outcome flag — it is the
  condition of the possession `do{...}while` (`:94379 } while ((char)local_15c == '\x01');`)
  and is assigned from the rebound routine `FUN_004d6f00` one line above the cited `goto`
  (`:94019`). `LAB_004dadd9` (`:94375`) is the **loop-bottom** label. So the `goto` runs the
  next possession iteration for the putback, after the shot is already resolved.
- Positively, 5.60 **widens** the bucket set on an OReb continuation: it sets the
  shot-clock flag only when `local_15c == '\0'` (`:93251-93253`) and **clears** it when
  `local_15c == '\x01'` (`:93278-93280`). Inside `FUN_004e1ba0` that flag is what
  restricts outcomes to `{3pt, foul}`; cleared, the full four-bucket set applies.
- `FUN_004e1ba0`'s reject-retry (`:97194-97196`) is exhaustive and has **no OReb branch**.
  Nothing zeroes `local_8c` (the `+0xDB0` 3pt bucket weight) on it.

**Putback 3pt is reachable in 5.60.** The `threePtW = 0` zero in the half-court trip loop
is therefore removed from the live engine; a new **normal-polarity**
`FreezeConfig.SuppressPutback3pt` (default `false` = live) reproduces the old behavior for
the A/B baseline only. `UnfaithfulPutback` keeps its inverted polarity and now gates the
**make-value site only** — the two were deliberately decoupled so the 3PA attribution is
clean. Transition `allow3pt=false` (`transition.go`) is untouched and **is** faithful
(`param_7 == 5 && iVar8 == 2` is a genuine 5.60 rejection).

Measured on the J24 recent-era 05-08 corpus (98 snapshots, seed 20240601, 60 games each):
3PA share of FGA **14.424% → 16.125%** against sco's 18.888%, closing **+1.70pp of the
−4.46pp** J24 residual-(7) attempt-share gap (38%); `dRate/100poss` −4.165 → −2.502.
Engine 3P% moves *toward* sco too (+1.17pp → +0.75pp), and ORB lands at **+0.47%** vs sco
— the engine is not over-producing continuations. Disclosed cost: FTA worsens −19.65% →
−21.83% (putback 3s draw fewer shooting fouls than putback 2s) against an already-open
−20% FTA gap; per ADR-0090 that is not a reason to re-add an unfaithful gate.

Full trace, negative evidence and A/B tables:
`jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md`. Backlog: J24
residual (7).

## Context

ADR-0049 (PR #1005) localized ~72% of the engine's wrong-signed `Cov(lnFGA,lnPPS)`
(the ADR-0042 team-scoring defect) to a **shots-per-possession anti-coupling**
`Cov(ln(FGA/POSS),lnPPS)` ≈ −0.00087 that 5.60 lacks (real ≈ +0.00003): a team that
takes more shots per possession scores *less* per shot in the engine — the inverted
coupling of the miss→ORB→retry loop (`possession.go` trip loop).

ADR-0053 (the LEAD measure-then-build spike) attacked the make-value **variance**:
two origin-scoped freeze arms (`MakePutback` / `MakePutbackHalf`) routed the
`OriginOffReb` 2pt make-value to the per-season league mean, stripping team-quality
variance. The verdict was a **measured NULL** — neither arm moved the factor (−0.000873
→ −0.000869, ~0.5%). Conclusion: the anti-coupling is **not** carried by the make-value
*spread*; it is structural to the miss→ORB→retry **volume** loop.

This ADR attacks a different lever found by the RE faithfulness audit: the make-value
**form** itself. Two decompile-verified divergences exist in how the engine resolves a
half-court putback (`OriginOffReb`, trip > 0):

1. **Make-value (`jsb560_decompiled.c:93880-93883`).** 5.60 computes the putback 2pt
   make-value as `player[+0xD60] × 1.3333` — **net-advantage-free** and **4/3-boosted**,
   where `+0xD60` is the player's 2P% field (master-ref L399/L1900, distinct from `+0xD64`
   base_2pt). The engine instead routed putbacks through the normal net-coupled
   `shotValue2pt(net, fgp, false)` = `net×500/baseline + base2pt(fgp)`.
2. ~~**3pt suppression (`jsb560_decompiled.c:94022-94024`).** 5.60 re-loops a 3pt outcome
   when the OReb-continuation flag is set, forcing a 2pt — **putbacks are never 3pt**. The
   engine let `outcome3pt` be selected for a putback.~~ **REVERSED 2026-07-22 — misread;
   see Correction above. Putback 3pt IS reachable in 5.60.**

### Mechanism

The engine's net-coupled, un-boosted putback make-value is the suspected carrier of the
anti-coupling: low-efficiency (low-net) teams miss putbacks more → more miss→ORB→retry
loops → extra empty FGA → wrong-signed `Cov(ln(FGA/POSS),lnPPS)`. The faithful form
(net-free + 4/3 boost) decouples putback efficiency from the matchup net advantage and
raises the make rate, which should shorten the retry loop and shrink the factor toward
real ≈0. The 3pt-suppression fix removes a second source of unfaithful empty putback FGA.

## Decision

Ship the faithful putback resolution **unconditionally ON** as the default live engine —
this is a faithfulness fix that *becomes* production behavior, NOT a default-off
measurement seam (unlike the ADR-0053 arms or ADR-0048 Branch-B). The golden fixture
changes by design and is regenerated with `-update`; `TestDeterminism` stays green.

1. **Make-value** — `makeValue2pt` (`freeze.go`) computes the `OriginOffReb` 2pt
   make-value from a new named `putbackValue2pt(fgp)` helper (`shotdecision.go`) =
   `base2pt(fgp) × shotClock2ptMult` (the engine's `+0xD60 × 1.3333` stand-in; the SAME
   assembled form as the `shotValue2pt` `shotClock==true` branch, named for its distinct
   concept). Computed BEFORE the accum capture so the ADR-0053 freeze arms freeze against
   the NEW (faithful) baseline — the no-cross-confound property is preserved.
2. ~~**3pt suppression** — the half-court trip loop (`possession.go`) zeroes the 3pt bucket
   weight (`threePtW = 0`, the same mechanism `transition.go` uses for `allow3pt=false`)
   for `OriginOffReb` before `selectOutcome`, so `outcome3pt` is unreachable for a putback.
   `outcome3pt` stays reachable for `OriginInitial`. RNG draw count at the selection site
   is unchanged (one `Float64` + one `IntN` regardless of bucket weights).~~ **REVERSED
   2026-07-22 (see Correction).** The zero is removed; `outcome3pt` is reachable for
   `OriginOffReb`. The old behavior survives only behind the normal-polarity
   `FreezeConfig.SuppressPutback3pt` A/B arm. RNG-draw invariance is unaffected.

### Inverted-polarity escape hatch

`FreezeConfig.UnfaithfulPutback` (default `false` = faithful = production) is the ONLY
`FreezeConfig` flag whose zero value is NOT "live engine." It is set `true` ONLY by the
archive A/B's OFF walk to RESTORE master's old net-coupled, 3pt-reachable putback as the
diagnostic baseline. (As of the 2026-07-22 Correction it gates the **make-value site
only** — the 3pt-eligibility site moved to the separate normal-polarity
`SuppressPutback3pt`.) It gated BOTH divergences with the same polarity at both sites,
consumes no `FreezeMeans` (`validate()` ignores it, mirroring `BranchB`), and rides the
existing `gs.freeze` threading — zero new threading. Production never sets it.

### Scope

`OriginOffReb` (half-court) ONLY. Transition putbacks are deliberately tagged
`OriginTransition` (`transition.go`), keep the fast-break origin and make-value, and
already exclude 3pt — transition putback make-value faithfulness is an explicit,
rationale-backed follow-up (see Out of Scope), not a silent drop. The measured
anti-coupling and the ADR-0049 split instrument are half-court-only.

## Pre-registered success criterion

Written BEFORE the archive run completed (the gate the Results are judged against):

> - **SUCCESS:** Cov(ln(FGA/POSS),lnPPS) moves from engine ≈−0.00087 toward real ≈0 (a
>   material fraction of the gap closed) WITHOUT regressing Var(lnPPS) toward real;
>   headline Cov(lnFGA,lnPPS) moves toward + (need not fully flip).
> - **PARTIAL:** the shots-per-poss factor moves materially but Var(lnPPS) regresses, or
>   only one of the two moves → record as a real-but-incomplete fix; re-measure
>   offVolumeScale (#974) + count axis (ADR-0054) with the freed Var(lnFGA) budget.
> - **NULL / TERMINAL:** the faithful form does NOT move the shots-per-poss factor → a
>   legitimate measured result that re-opens the RE audit (Branch B revisited: the
>   divergence is real but not the carrier). DO NOT tune to force the metric.

## Results

Measured as a 2-config A/B (`OFF` = `UnfaithfulPutback` master baseline; `ON` =
zero-Options faithful production) over the backup archive at `JSB_ARCHIVE_RUNS=20
JSB_ARCHIVE_STRIDE=1`. Artifacts:
`engine/internal/validate/testdata/calibration-5.60-20260610-putback-faithful-{off,on}.json`.

Full archive (705 zips, stride 1, runs 20, seed 20240601), regular bucket, engine:

| Channel (regular bucket, engine) | OFF (master) | ON (faithful) | real | movement |
|---|---|---|---|---|
| **FACTOR** Cov(ln(FGA/POSS),lnPPS) | −0.000873 | **−0.000800** | +0.000027 | toward real (~8% of the gap; ~8.4% of the engine anti-coupling magnitude) |
| HEADLINE Cov(lnFGA,lnPPS) | −0.001210 | **−0.001120** | +0.000269 | toward + (~6% of the gap; not flipped) |
| residual Cov(lnPOSS,lnPPS) | −0.000337 | −0.000319 | +0.000241 | slightly toward real (count axis OOS) |
| Var(lnFGA) | 0.001798 | 0.001727 | 0.001330 | narrowed toward real |
| Var(lnPPS) | 0.001570 | **0.001458** | 0.001451 | improved to ≈ real (gap 1.19e-4 → 7e-6) |

Verdict signals: `factor_moved_toward_real=true  headline_moved_toward_plus=true
pps_regressed=false  var_fga_narrowed=true`.

**OFF reproduces master to the digit.** The OFF (`UnfaithfulPutback`) walk matches the
prior published baselines exactly — FACTOR −0.000873 (ADR-0053/0049), HEADLINE −0.001210
(ADR-0053), residual −0.000337 (ADR-0049), Var(lnPPS) 0.001570 (ADR-0053). This is the
corpus-level proof that the escape hatch is leak-free and the A/B OFF arm is a true
master baseline: a leaky hatch would have shifted OFF off the known baseline. (The unit
tests prove the hatch at the call site; this proves it across the whole archive.)

**Verdict: SUCCESS** (per the pre-registered gate: factor moved toward real AND
Var(lnPPS) did NOT regress). Both gate conditions hold; notably Var(lnPPS) did not
merely avoid regression but improved to essentially real (0.001458 vs 0.001451), and
every channel — factor, headline, count residual, both variances — moved the correct
direction with no regression. The faithful putback make-value form is therefore a real,
regression-free carrier of the shots-per-possession anti-coupling.

**Magnitude caveat (honest):** the factor gap closes by only ~8% (and the headline does
not flip), so the faithful form is *a* carrier, not *the* dominant one. This is fully
consistent with ADR-0053's measured null — which proved the make-value *variance* is not
the carrier — and refines it: the make-value *form* (net-decoupling + the 4/3 boost,
which raises the putback make rate and shortens the miss→ORB→retry loop) moves the
factor where freezing the variance could not, but the bulk of the anti-coupling remains
structural to the empty-FGA volume loop. The residual is left to the out-of-scope count
axis (ADR-0054) / `offVolumeScale` (#974), exactly as the PARTIAL branch foresaw — but
because there is no Var(lnPPS) regression here, this lands as a clean SUCCESS, not a
PARTIAL. This fix ships as faithful production behavior on its own merits (RE-verified
faithfulness) independent of the magnitude.

## Consequences

- The golden fixture (`engine/internal/sim/testdata/golden.json`) changes by design.
  `TestDeterminism` and the full non-archive suite stay green.
- The ADR-0053 freeze arms (`MakePutback` / `MakePutbackHalf`) now harvest and freeze
  against the faithful putback distribution. Their existing origin-scoped semantics are
  unchanged; only the baseline they substitute against moves.
- Unit coverage: `engine/internal/sim/putbackfaithful_test.go` (make-value origin scope,
  new-baseline harvest, escape-hatch master reproduction, 3pt suppression + reachability,
  RNG-consumption invariance). The archive A/B lives in
  `engine/internal/calibrate/putbackfaithful_archive_test.go` (`//go:build archive`).

## Out of scope

- **Transition-putback make-value faithfulness.** A putback within a fired fast break is
  tagged `OriginTransition`, keeps the fast-break origin and `transitionNet(def)`
  make-value, and already excludes 3pt. This is a deliberate origin separation aligned to
  where the ADR-0049 instrument reads (half-court only). Revisit only if this ADR's
  verdict is PARTIAL/NULL and the residual localizes to transition FGA.
- **offVolumeScale / count-axis re-measurement (#974, ADR-0054).** If the PARTIAL branch
  fires, re-measure these with the freed Var(lnFGA) budget — a separate PR.
