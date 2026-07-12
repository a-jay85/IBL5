---
description: Faithful 5.60 foul buckets from live per-player composites (defQ = Σ STL/MIN×44, offQ = Σ TOV/48 − HCA), HCA re-homed to the basis-scaled site-2 effect, and the foul level re-anchored to the paired .sco FTA — superseding the ADR-0061 offQ-constant and ADR-0082 defMatchupQuality fidelity claims.
last_verified: 2026-07-12
---

# ADR-0084: Faithful 5.60 foul buckets from live composites, HCA re-homing, and FTA-level re-anchor

**Status:** Accepted
**Date:** 2026-07-12

## Context

Two prior ADRs shipped foul-bucket inputs that live reverse-engineering of jumpshot 5.60 has now shown to be the wrong composites:

- **ADR-0061** shipped the offensive-quality foul divisor as a volume-neutral constant (`offQualityConstant = 1.575`).
- **ADR-0082** replaced the foul model with an asymmetric pair whose defensive input was `defMatchupQuality = Σ floor1(OD)×0.25 → compressQuality(8.21, 0.45) → cap 7.5`.

Static + live-AST RE of 5.60 (machine-local artifacts `jsb-J6-composite-scales-20260710`, `jsb-J19-escape-bound-rederivation-20260710`, `jsb-J2-adjudication-20260710`, `jsb-J16-fun004e3860-20260710`; decompile `FUN_004e1ba0`, `jsb560_decompiled.c:97116-97173`) established:

- **Faithful defensive quality is live, not dead:** `defQ = Σ (five defenders) STL/MIN × 44` (binary field +0xDD0, live per J6 §2/§5 — overturning the prior "defQ ≡ 0 / dead +0xDD0" pin), capped at `1.5×5×leagueSTL48`. NOT `floor1(OD)×0.25 → compress(8.21,0.45)`.
- **Faithful offensive quality is roster-varying and TOV-coupled:** `offQ = Σ (offense) TOV/48 − HCA` (binary field +0xDE0, live per J6 §5) — refuting ADR-0061's volume-neutral `1.575` constant (a constant provably cannot track a roster's turnover profile).
- **The foul coupling is live and INCREASING in defQ:** `e80 ×= 1 + (defQ − (5/6)·5·leagueSTL48)/offQ` (verbatim decompile :97163; PE constant `0x66D3A0 = 5/6`). J6 §5's inline paraphrase `1 − defQ·(5/6)/offQ` was a mis-transcription (the C1 correction) — the faithful factor is league-baselined and increasing in defQ, on a deterministic base `e80 = (2.0 − fatigue)·TOV48_bh` (:97126), not a stochastic draw.
- **J19 escape-bound re-derivation:** the deterministic HOME foul arm is **effectively — not perfectly — non-positive.** The away-side redraw dominates for any realistic roster, but the live +0xDC8 AST48 matched term shaved the algebraic barrier ~15% (residual 57.16→48.42), so the arm can **occasionally express**. The verdict rests on two premise-independent corroborators (§5.1 and-one-share bound; §5.2 J2's smooth measured 22.04/19.30 home/away FTA split), not a perfect static dead-arm. The `w ≤ 0` redraw guard is therefore kept **live and reachable**, not asserted-dead.
- **The two foul RATIOS sit on opposite sides of 1.0 by design.** The faithfulness oracle is the decompile arithmetic, not the .sco aggregate. The per-possession foul bucket is faithfully **anti-home** (home draws slightly FEWER fouls; ratio ~0.91), because the home MARGIN is a SCORING phenomenon carried by the made-shot/and-one legs, not a foul-drawing one. The real .sco **pro-home** FTA split (~1.14) is an emergent, home-lead-driven late-game fouling effect that this per-possession bucket does not model. Both are correct simultaneously.
- **HCA basis mismatch:** the prior HCA was a raw ±0.2 addend on the ~16.5 2pt bucket basis (~1.2%), whereas the faithful site-2 effect (`e88 += s·0.2`, J16, where e88 is O(1)) is a proportional ~10% effect that also propagates through the and-one arm `e90 = param_6·0.25 + e88`.

## Decision

Port the faithful asymmetric foul pair and re-home HCA in the Go engine:

1. **Composites (teamquality.go).** Replace `defMatchupQuality` with `defQ = Σ STL/MIN×44` (cap `1.5×5×leagueSTL48`) and re-create `offQ = Σ TOV/48 − HCA` as a TOV-coupled aggregator (not a constant), both in 5.60 units on the faithful CEngine per-48 basis (`leagueTOV48 = 3.353143`, `leagueSTL48 = 1.834`). The per-player STL/TOV counting inputs remain documented corpus-deferred stand-ins until the production bundle wires `rl_stl`/`rl_tov` (follow-on).
2. **Coupling (bucketweights.go).** Rewire `foulBucketWeight` to the live deterministic base and coupling factor — `w = ((2.0 − fatigue)·TOV48_bh − s·hca) · (1 + (defQ − (5/6)·5·leagueSTL48)/offQ)` (decompile :97126/:97160/:97163) — keeping the `w ≤ 0` redraw guard live and reachable (J19: effectively-not-perfectly dead). **Documented divergence:** 5.60 additionally multiplies `e80 ×= 1 − param_6/(4·leagueTOV48)` (the J16-pinned net-advantage shrink, applied after the coupling and before the redraw check, both param_5 paths) — NOT ported here; the Go engine's `netAdvantage` feeds shot_value only. Consequence: the engine's redraw fires only on `factor ≤ 0`, rarer than 5.60's (where `param_6 > 13.41` occasionally triggers it). Porting it re-opens the level/margin anchors, so it is filed as J18 divergence (6) to be A/B'd on its own.
3. **HCA re-homing — four legs, split scaled/raw (gametype.go, possession.go).** Re-home HCA to the four modeled half-court legs at the play-outcome selector (decompile :97157-97164, param_5==1):
   - **Leg A** (site-2 e88 made-shot): `+delta·hcaSite2BasisScale` — pro-home scoring.
   - **Leg D** (e90 and-one): inherits e88's `+delta·hcaSite2BasisScale`.
   - **Leg B** (site-2 e80 foul base): `−raw delta` before the factor — anti-home, DOMINANT.
   - **Leg C** (site-3 offQ divisor, per player): `−raw delta`, so the coupling factor moves off 1 toward `sign(defQ−baseline)` — pro-home only for a strong-steal defense, anti-home otherwise.

   Legs A/D use the tuned `hcaSite2BasisScale = 2.85` (the raw ±0.2 e88 units are O(1); the engine bucket basis is O(10s), so the scale preserves the proportion across the basis change). Legs B/C use the **RAW 0.2**, because offQ and the foul base are already built on the faithful CEngine per-48 basis where the decompile's raw 0.2 is in-basis — scaling them ~2.85× would over-apply HCA and drive the foul ratio absurd. The transition path (param_5==0) is fully symmetric and receives 0 for every leg.
4. **Level + margin anchors — paired comparators, not the pooled corpus.** Both level dials are tuned to the **paired .sco value measured on the SAME games** the archive harness runs, following the J15 program's paired-comparator principle (judge an instrument WITHIN its own sample):
   - **Margin:** `hcaSite2BasisScale = 2.85` → engine gt=2 home margin **3.332** vs paired .sco **3.319** (gap +0.014). The target is the paired .sco margin (~3.32), NOT the pooled-corpus **4.12** — which has no runnable instrument on the archive sample.
   - **FTA:** `foulBucketScale = 0.47` → engine FTA/g **21.36** vs paired .sco **21.32** (gap +0.04; 1-D search 0.50→22.43, 0.45→20.67, 0.47→21.36). The target is the paired .sco FTA (~21.32), NOT the pooled **20.65**. The retired 37.8 FTA/g A-baseline (a 1.8× inflation) is never a target.

   The two dials are only weakly coupled — sweeping `foulBucketScale` moved the gt=2 margin non-monotonically (3.332/3.304/3.287 for 0.50/0.45/0.47), i.e. within the ±0.03 Monte-Carlo noise floor of the 20-run harness, so the FTA re-anchor did not disturb the Phase-5 margin lock.
5. **Emergent Cov, never tuned toward.** The `Cov(lnFGA,lnPPS)` sign move is captured as an emergent diagnostic, never a tuning target (ADR-0041/0044 forbid gaming toward it).
6. **Validation bands + golden.** Re-derive the sim golden against the faithful distribution; keep the `validate` bands (see Consequences — they self-scale and empirically pass).

## Consequences

**Superseded fidelity claims.** ADR-0061's `offQualityConstant` volume-neutrality and ADR-0082's `defMatchupQuality` composite are superseded (status lines flipped; the forward reference lives in this ADR's `## Lineage`).

**Faithful-enough acceptance — Opus verdict, and the reason `auto_merge: false` holds.** The acceptance criterion (bands/golden) is itself re-derived by this program, so a human signs off. The following are recorded as **open Opus judgment calls**, surfaced for that sign-off rather than silently closed:

- **gt=2 `Cov(lnFGA,lnPPS)` sign is FLIP-NEEDED but moved the RIGHT direction, un-gamed.** Engine −0.000423 vs real +0.000612 — still the wrong sign, but improved from the defQ≡0 baseline −0.000774 as a *side effect* of the faithful composites, never tuned toward (ADR-0041/0044). `Var(lnPF)` ratio ≈0.22 (engine under-disperses PF) remains a known stand-in limit of the per-player STL/TOV stand-ins, closing only when the production bundle wires `rl_stl`/`rl_tov`.
- **Validation bands KEPT, not re-tightened.** The `max(AbsFloor, RelPct×engineMean)` bands self-scale on the current engine mean, so they never froze to the 37.8 era. `jsbcalibrate --mode gate` on the faithful distribution passes overall (min-rate 0.9) with NO stat failing either game type; `pf` in-band 0.94 (gt 2) / 0.959 (gt 4) — the "robust to occasional home-arm expression" headroom the plan asked `pf` to keep, so `pf` is not re-tightened despite being a named target. Re-deriving would only tighten toward the p95 proposal (the over-tighten the plan warns against). The gate is reference-only (no CI workflow invokes `jsbcalibrate`), so this is a low-harm reference calibration, not a live per-PR gate. **One known-stale floor:** `tov` AbsFloor was sized to a defunct ~30 TOV mean; refreshed at ratification to the calibrate proposal (gt2 9, gt4 11 — Ratification D2 below).
- **gt=4 playoff margin OVERSHOOTS (4.655 vs 3.082) — pre-existing, out of scope.** The overshoot is the `playoffNetMultiplier` (×1.25) amplifier, which is isolated to `netAdvantage`/shot_value (netadvantage.go:32) and is a SEPARATE addend from the `hcaScaled` bucket weight — no double-count. It predates this PR and is not addressed here.

**Open follow-ons.** The `rl_stl`/`rl_tov` production-bundle wiring, and the J18 3pt-weight / d70Scalar / d88 composite-fidelity items.

## Ratification (2026-07-12, delegated Fable session)

Each open decision was re-derived from the primary source (decompile `FUN_004e1ba0`, `jsb560_decompiled.c:97116-97173` — the faithfulness oracle), not deferred to the implementing session. Rulings:

- **D1 — paired-comparator targets STAND** (`hcaSite2BasisScale = 2.85`, `foulBucketScale = 0.47`). The archive harness (`measure_baseline_archive_test.go`, runs=20/stride=50) measures engine and `.sco` **on the same sampled games** — the `.sco` columns it prints ARE the paired values (margin 3.319, FTA 21.32). No instrument for the pooled-corpus values (4.12 / 20.65) exists on this sample; tuning this sample's engine output toward pooled targets would deliberately miscalibrate it against the very games it simulates. No re-tune.
- **D2 — `tov` validation-band AbsFloor refreshed to the calibrate proposal: gt2 22 → 9, gt4 21 → 11** (bands.go). The floors were sized to a defunct ~30 TOV mean; at the faithful ~14.3 mean they were AbsFloor-dominated, so the band carried no fidelity signal — a known-stale number in a shipped file. Proposal measured on this branch (`jsbcalibrate --mode calibrate`, runs=20, stride=50, coverage 0.95): gt2 AbsFloor=9 / in-band 0.975, gt4 AbsFloor=11 / in-band 0.977; RelPct kept at the committed wider values (the proposal's 0.596/0.600 would only tighten — the over-tighten the plan warns against). The gate is reference-only (no CI wires `jsbcalibrate`), so the refresh is low-risk and reversible.
- **D3 — SHIP.** J15 is a faithfulness port; the gt=2 `Cov(lnFGA,lnPPS)` improvement (−0.000774 → −0.000423) is an emergent side effect, never a tuning target, and the sign flip is explicitly J2 session 2's job downstream. Holding J15 would keep two disproven fidelity claims (ADR-0061/0082) in the shipped engine.
- **Ratified against the decompile, line-by-line:** leg A/D scaled ×2.85 vs legs B/C raw 0.2 (legs A/B at :97157-97161 add raw `s·0.2` to the O(1) e88 and e80; the Go 2pt basis is O(10s), so the proportional scale is the faithful transposition, while base/offQ are already on the CEngine per-48 basis); the coupling factor `1 + (defQ − (5/6)·5·leagueSTL48)/offQ` verbatim at :97163 with legs applied BEFORE the factor; the `≤ 0 → U[0, 0.6)` redraw at :97170; transition (param_5==0) receiving no legs; the anti-home 0.91 foul ratio as the direct sign of leg B (`e80 −= s·δ`, s=+1 home); the gt=4 margin overshoot isolated to `playoffNetMultiplier` (netadvantage.go feeds shot_value only — grep-verified no foul-path coupling).
- **Corrections applied during ratification:** the C1 mis-transcription (`1 − defQ·(5/6)/offQ`) fixed in this ADR's Context/Decision (the code always implemented the correct :97163 form); the unported `param_6` net-advantage foul shrink `e80 ×= 1 − param_6/(4·leagueTOV48)` surfaced and recorded as J18 divergence (6) (see Decision item 2).

## Supersedes

Supersedes the fidelity claims of **ADR-0061** (offQualityConstant volume-neutrality) and **ADR-0082** (defMatchupQuality composite). Grounded in RE artifacts J6/J19/J2/J16 (machine-local, git-excluded). Absorbs backlog **J12** (HCA re-homing — corpus margin ground truth unchanged); unblocked by **J19** (escape-bound re-derivation).
