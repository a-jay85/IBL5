---
description: Possession-count decomposition of the ADR-0042 wrong-signed Cov(lnFGA,lnPPS), the instrument PR (1 of 2) succeeding ADR-0048 (Branch-B, REFUTED). ADR-0048 closed the bucket-side search, so the defect is structural/non-arm and the search moved UPSTREAM of the half-court pick. Ships a read-only possession-count instrument — the symmetric Dean-Oliver true-possession proxy FGA+0.44·FTA+TOV−ORB on both sides, plus the engine authoritative EventPossessionStart count as a level-validation diagnostic — splitting Cov(lnFGA,lnPPS) via lnFGA=lnPOSS+ln(FGA/POSS) into a possession-COUNT term and a shots-per-possession term. PRIMARY measured verdict (confirms ADR-0042): the engine's wrong sign is DOMINATED by a shots-per-possession ANTI-coupling (engine −0.000873, ~72% of the −0.001210 total) that 5.60 lacks (real +0.000027 ≈ 0) — the empty/miss-driven FGA loop. SECONDARY (sign-independent, trustworthy): the engine under-disperses possession count, Var(lnPOSS) ~2.5× too narrow. The count-factor COVARIANCE gap (engine −0.000337 vs real +0.000241) sits at the corpus noise floor and is reported, not leaned on. Lead PR-2 = REMOVE the empty-FGA shots-per-possession anti-coupling; a faithful off/def-ratio base_time pace is a secondary, conditional idea for the count factor (a distinct axis from the marginal/level one ADR-0047 refuted, gated on a precision run confirming the count covariance clears the noise floor). No engine behavior change — counting an already-emitted event needs no freeze toggle, so the golden fixture is byte-identical (cleaner than #1004). ADDENDUM 2026-06-10: a full-precision per-season sweep (20 seasons × 5 seeds) CONFIRMS the count-covariance gap clears the noise floor (17/18 seasons negative, mean −0.000609, t≈6.8), so the base_time secondary axis is no longer noise-gated — still secondary to the lead empty-FGA-removal fix; tautology hedge (real count≈total) stands.
last_verified: 2026-06-10
---

# ADR-0049: Possession-count coupling channel — instrument + localized verdict (PR 1 of 2)

**Status:** Accepted
**Date:** 2026-06-07

## Context

ADR-0048 (PR #1004) built and measured the JSB Branch-B usage-shrink — the ADR-0047 prime
suspect and the last untested bucket-side lever — and recorded a confirmed-engaged **null**:
Branch-B *deepens* the wrong-signed `Cov(lnFGA,lnPPS)` (−0.00121 → −0.00262) and regresses
`Var(lnPPS)` ≈3.2×. With the four within-possession freeze arms (ADR-0043/0045) **and**
Branch-B all exhausted, ADR-0048 consequence #1 declared the defect **structural / non-arm**
and narrowed the surviving suspect to the **possession-generation layer** — *upstream* of the
half-court shot-type pick `FUN_004e1ba0`, which is fully refuted as an in-pick reroute.

This ADR is **PR 1 of 2** (measure-then-build, the ADR-0047 cadence): it builds the
instrument that localizes the wrong sign, and records the verdict. PR 2 builds the channel.

The decomposition rests on the exact identity `lnFGA = lnPOSS + ln(FGA/POSS)`, so

```
Cov(lnFGA, lnPPS) = Cov(lnPOSS, lnPPS) + Cov(ln(FGA/POSS), lnPPS)
```

splitting the ADR-0042 defect into a **possession-COUNT** factor and a
**shots-per-possession** factor. POSS is one of the two multiplicative factors of FGA — the
one the entire bucket-side search never isolated.

Full RE trace + evidence: `engine/docs/possession-count-coupling-trace.md`.

### A primary-source correction folded in (the load-bearing pin)

The plan premised that offensive rebounds are "unrecoverable" on the real side (the compared
`TeamStat` collapses to total `REB`), and proposed an ORB-omitting proxy `FGA+0.44·FTA+TOV`.
That is **wrong and was corrected**: the raw `.sco` box carries `ORB` directly
(`ScoBox.ORB`), as `calibrate/possession_archive_test.go` already exploits. So the proxy is
the **true Dean-Oliver possession estimate** `FGA + 0.44·FTA + TOV − ORB`, computed at the
harness layer from the raw box on both sides. The `−ORB` term is load-bearing, not cosmetic:
an offensive rebound *extends* a possession (an extra shot in the same trip), so it belongs
in the shots-per-possession factor — omitting it would misattribute ORB-continuations into
the possession-count factor, corrupting the very split the instrument exists to make. The
engine side confirms the framing: `EventPossessionStart` fires once per offensive trip and an
ORB `continue`s the SAME trip (no re-emit), so the engine's authoritative count is already
true possessions.

## Decision

**The instrument ships read-only; the split localizes the engine's wrong-signed
`Cov(lnFGA,lnPPS)` primarily to a shots-per-possession ANTI-coupling 5.60 lacks (the
empty-FGA loop — confirming ADR-0042), with possession-count under-dispersion as a
sign-independent secondary defect.**

### Cleaner than #1004 — no behavior toggle (the key contrast)

Branch-B (ADR-0048) needed a `FreezeConfig.BranchB` toggle because *measuring* it required
**running a new engine behavior** (the usage-shrink), so the golden fixture had to be guarded.
The possession-count metric needs **no toggle**: it counts `EventPossessionStart`, an event
`Simulate` already emits, and the .sco proxy is computed from the box at the calibrate/validate
layer. Counting an existing event is not a behavior change. `sim` is untouched,
`golden_test.go` is byte-identical with no `-update`, and there is nothing to revert. The
measurement seam lives entirely on the harness + season-aggregate side
(`validate.GameReport.{Engine,Sco}PossPerG`, `calibrate.FidelitySummary`'s POSS terms,
`jsbcalibrate --mode measure`'s diagnostic line).

### The measured verdict (regular bucket, committed artifact — runs=20, stride=1)

`engine/internal/validate/testdata/calibration-5.60-20260607-possession-coupling.json`, the
split run on the SAME Dean-Oliver proxy `FGA+0.44·FTA+TOV−ORB` on both sides (apples-to-apples;
the authoritative count is a level diagnostic, not a split input):

| term | engine | real (.sco) | reading |
|------|--------|-------------|---------|
| `Cov(lnFGA,lnPPS)` | −0.001210 | +0.000269 | wrong sign (reproduces the ADR-0047 baseline exactly) |
| ├ `Cov(lnPOSS,lnPPS)` | −0.000337 | +0.000241 | covariance gap at the noise floor (~3e-4) — reported, not leaned on |
| └ `Cov(ln(FGA/POSS),lnPPS)` | **−0.000873** | +0.000027 | **robust**: engine anti-coupling (~72% of total) that 5.60 lacks |
| `Var(lnPOSS)` | 0.000288 | 0.000721 | engine count-spread ~2.5× too narrow — sign-independent |

The split closes on real data (engine −0.000337 + −0.000873 = −0.001210; real +0.000241 +
+0.000027 = +0.000269). The authoritative count (mean 101.9/team) reconciles with the proxy
(99.7) and ADR-0045's ~99–106, validating the proxy.

**The verdict — primary finding CONFIRMS ADR-0042, it does not reverse it:**

1. **PRIMARY (robust): a spurious shots-per-possession anti-coupling.** Engine
   `Cov(ln(FGA/POSS),lnPPS)` = −0.000873 — ~72% of the wrong-signed total — vs real +0.000027
   (≈ 0). The empty/miss-driven FGA loop (ADR-0042's 76%-empty): teams taking more shots per
   possession score *less* per shot, a coupling 5.60 lacks. Stable across configs
   (−0.001175→−0.000971→−0.000873 at runs 4/8/20).
2. **SECONDARY (robust, sign-independent): possession-count under-dispersion.** `Var(lnPOSS)`
   engine 0.000288 vs real 0.000721 (~2.5× too narrow) — the engine flattens team-to-team
   possession count. A variance gap needs no sign to clear a noise floor, so it is trusted.
3. **REPORTED, not load-bearing: the count↔efficiency covariance gap** (engine −0.000337 vs
   real +0.000241) is wrong-signed but ~3e-4, inside the corpus noise band (the branchB-off
   artifact's real `Cov(lnFGA,lnPPS)` ranged −0.0004…+0.0003 across buckets); the real
   count ≈ total is partly tautological (real shots-per-possession ≈ 0). Not a build target on
   its own.

### What ships

- The read-only POSS instrument: `validate.GameReport.{Engine,Sco}PossPerG` (the symmetric
  proxy split inputs) + `EnginePossCountPerG` (the authoritative-count diagnostic) +
  `accumulatePossessions`/`possProxy` (harness), the `TeamStat.ORB` field (raw-box ORB for the
  proxy), the `calibrate.FidelitySummary` POSS terms + `decomposePossCoupling`, and the
  `jsbcalibrate --mode measure` diagnostic Cov-split line.
- The committed anchor artifact at **runs=20, stride=1** (full precision; the directional
  verdict is config-robust — the shots-per-possession dominance held at runs 4/8/20).
- `engine/docs/possession-count-coupling-trace.md` — the full RE trace.
- **No golden regen, no revert, no behavior change, no security surface.**

## Consequences

1. The surviving suspect is now **instrumented and localized**. PR 2's **lead** target is the
   robust defect: **remove the engine's shots-per-possession anti-coupling** (the empty-FGA
   loop; 5.60 has ≈0 coupling there, so the faithful fix is a *removal*, not an addition).
2. PR 2's **secondary** target is the possession-count under-dispersion (`Var(lnPOSS)` ~2.5×
   narrow — sign-independent, trusted). A **faithful off/def-ratio `base_time`** is a
   *conditional* candidate for the count↔efficiency covariance: 5.60 computes base_time as a
   team off/def stat ratio, the engine's is a defense-composite placeholder (ADR-0045). This
   is a genuinely distinct axis from ADR-0047's *marginal/level* refutation (PPS-neutral
   within a team ≠ the cross-team `Cov(lnPOSS,lnPPS)`), but the count covariance it targets is
   at the noise floor — so pursue it only after a precision run confirms the count factor
   clears the floor, and re-measure #974's `offVolumeScale` with the split first. **Not the
   lead.**
3. The faithful **true-possession proxy** (`−ORB`) and the authoritative engine count are now
   wired and reconciled against each other, available for PR 2 without re-deriving them.

## Addendum (2026-06-10): full-precision noise-floor verdict

The PR-1 body parked the count↔efficiency *covariance* gap (engine −0.000337 vs real
+0.000241) as "near the corpus noise floor (~3e-4), reported not leaned on," and gated the
`base_time` secondary axis (consequence #2) on **a precision run confirming the count factor
clears the floor.** That run is now done.

**Method — per-season sweep, not a pooled high-runs run.** The floor is *corpus-side* (the
across-season spread of the covariance), so more engine `--runs` cannot shrink it and a single
pooled estimate has nothing to compare against. Instead: `jsbcalibrate --mode measure` run
**per season-dir across the whole archive × seeds {1..5} at runs=50**. The cross-**season** SD
of the count-factor covariance gap *is* the noise floor; the cross-**seed** SD is the engine
sampling-noise band.

**Verdict — the gap CLEARS the floor.**

| metric | value |
|---|---|
| seasons with a regular snapshot | 18 / 20 (06-07, 07-08 are floor-70 incomplete — the 28-team era) |
| seasons with a **negative** gap (engine count-cov < real) | **17 / 18** (only 88-89 marginally +6.1e-5) |
| engine poss-cov sign | negative in **all 18** seasons; real positive in 16/18 |
| cross-season mean gap | **−0.000609** (replicates the pooled PR-1 −0.000578) |
| cross-season SD | 3.82e-4 ⇒ SE-of-mean ≈ 9.0e-5 ⇒ **t ≈ 6.8** from zero |
| within-season seed SD | ~1e-6 (100–300× below the cross-season spread — measurement is precise) |

The PR-1 "near the floor" hedge was **too conservative**: at full precision the
count-covariance gap is a systematic, consistently wrong-signed defect, distinguishable from
the corpus floor by ~7 SE. It is a *second* count-factor defect alongside the trusted
sign-independent `Var(lnPOSS)` under-dispersion.

**Two caveats — do not over-read the verdict:**
- **Tautology hedge stands.** Real count ≈ total by near-collinearity (real shots-per-poss
  ≈ 0), so the real-side positive covariance is partly mechanical. Statistical reality ≠ proof
  that *building faithful `base_time` pace* is the fix — it only removes the "it's just noise"
  objection to pursuing that axis.
- **Floor estimated on 24/26-team eras only.** The two dropped seasons are the 28-team era; the
  most recent / highest-team-count corpus is unrepresented in the floor.

**Consequence.** ADR-0049 consequence #2's `base_time` candidate is **no longer
noise-gated** — promoted from "parked, pursue only after a precision run" to a *legitimate*
secondary axis. It remains **secondary** to the lead PR-2 fix (remove the empty-FGA
shots-per-possession anti-coupling, consequence #1), which is unaffected by this run and still
needs the engine-side RE work.

Committed artifacts (full-archive `-tags archive` re-run, all six RealArchive tests green;
engine confirmed race-clean under `go test -race -count=20`):
`calibration-5.60-20260610-branchB-{off,on}.json`, `-freeze-attribution.json`,
`-possession-coupling.json`.

## Reproduce

```
go test ./internal/validate ./internal/calibrate ./cmd/jsbcalibrate   # unit (incl. split identity + golden)
go test ./internal/sim -run Golden                                    # byte-stability (no -update)
JSB_ARCHIVE_DIR=<dir> go test -tags archive ./internal/calibrate -run PossessionCoupling   # regenerate the artifact
bin/jsb-verdict engine/internal/validate/testdata/calibration-5.60-20260607-possession-coupling.json 'cov_ln_poss|var_ln_poss|shots_per_poss'
```

## Reference

- `engine/docs/possession-count-coupling-trace.md` — the RE trace (Prong A decompile pins:
  Transition Mechanics four stages, Offensive Rebound Probability, base_time `FUN_004e4150`).
- `ibl5/docs/decisions/0048-branch-b-usage-modulation.md` — the Branch-B null this succeeds.
- `ibl5/docs/decisions/0047-volume-count-conversion-mechanism.md` /
  `0045-turnover-model-fidelity.md` / `0042-team-scoring-coupling-mechanism.md` — the prior chain.
- `engine/internal/validate/testdata/calibration-5.60-20260607-possession-coupling.json` — the
  committed anchor artifact.
