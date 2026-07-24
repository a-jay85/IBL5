---
description: Adversarial statistical audit of the ~12.42 / 2-season CI floor ADR-0090 elected as the JSB cut-over bar (re-open criterion #2). Verdict A (construction DEFECT found?) = NO; Verdict B (cut-over authorized under criterion #2?) = NO. The ~12.42 bar has no recorded derivation (a PROVENANCE gap, corrected here to the actually-computed 12.374) but is recoverable as the √2-shrink of the reproduced 1-season CI and independently corroborated by a direct 2280-game bootstrap [12.418, 12.653]; every candidate defect (denominator uncertainty, reproduction-interval-as-gate, missing engine error bar, season-level clustering) is a NOT-A-DEFECT, PREFERENCE, or PROVENANCE finding that leaves master 12.37% below the bar. HOLD stands.
last_verified: 2026-07-24
---

# ADR-0094: Gate-1 cut-over CI — construction audit (ADR-0090 re-open criterion #2)

**Status:** Accepted
**Date:** 2026-07-23
**References:** ADR-0090 (cut-over go/no-go — HOLD), ADR-0088 (gate-1 re-denomination), ADR-0049 (band construction)

## Context

ADR-0090 HELD the JSB native-engine cut-over: master `DRBPushSharePct` = **12.37%** sits inside the between-season drift band [11.97, 12.54] but under the **era-matched 2-season CI floor ~12.42** (bootstrap CI [12.374, 12.698]) elected as the cut-over bar. Its re-open **criterion #2** authorizes cut-over if *"the 2-season bootstrap CI is recalibrated such that its lower bound covers 12.37%."*

This ADR answers the adversarial question that criterion poses: **is the elected ~12.42 / 2-season floor a construction DEFECT or a PREFERENCE?** A defect in the interval's own construction that moves its lower bound to ≤ 12.37% would satisfy criterion #2; a different, looser gating object would not.

**Method discipline (frozen before any recomputation).** The DEFECT-vs-PREFERENCE criterion and a bootstrap resolution floor were written down and hash-pinned in a machine-local note (`jsb-native/re-artifacts/adr-0092-criterion-freeze.md`, SHA-256 `703d686d…e2308`) **before** any number was recomputed, so no candidate could be reclassified after its number was seen. Two verdicts were pre-committed, either permitted to be NO. Base: master at HEAD `de3934d8d`; ADR-0090 Accepted and present on master; #1582 (seed-per-game archive calibration) an ancestor of HEAD.

**Frozen criterion (verbatim from the note):**
- **DEFECT** — an *internal method error in the cited CI itself*: a wrong resampling unit, an `n` that double-counts, an error term the estimator requires but the bootstrap does not propagate, or a CI method mismatched to the ratio estimator — that moves **that interval's own** lower bound.
- **PREFERENCE** — switching to a different, looser gating **object**: re-electing the drift band, substituting a two-sample overlap test, or injecting a variance component the original estimator never estimated. (ADR-0090 line 42 knowingly declined the drift band as "a fresh criterion-selection call.")
- **Resolution floor** — at B = 10000 the percentile lower bound is order statistic `s[250]`; a corrected bound covers 12.37% *decisively* only if it clears 12.37 by **more than** the `s[250]`→`s[251]` spacing. A bound landing within that spacing is "within resolution — not decisive," and the HOLD stands.

## Findings

All numbers are CLI-reproduced from the recorded scripts (§ Reproduce); scripts are machine-local (git-excluded), the numbers land here.

### 1. What the cited interval actually brackets (Phase 2)
`j24_armed_fraction.py` reproduces the cited interval **exactly**: `share 95% CI [12.3742%, 12.6978%]`, seed `random.Random(20240601)`, B = 10000. Its four descriptors: **unit = game** (a `(steal, dreb, trans)` game tuple is resampled), **n = 1140** (`[-1140:]`), **window ≈ one season-equivalent** (22,798 games / 20 chunks ≈ 1140), **estimator = the recent-tail real-world share 12.532%**, where `share = (arm_g/POSS_RECENT)·(t_g/arm_g) = t_g/POSS_RECENT` (the arming term cancels; POSS enters as a linear denominator). Self-consistency identity `38.51% × 0.3254 = 12.532%` and the monotone era trend (chunk 1 = 8.563%, chunk 20 = 12.532%) reproduce. **There is no 2-season object in the script at all.**

**Resolution floor (Phase 2c):** the `s[250]`→`s[251]` spacing is ≈ **0.0004pp** (neighbourhood-mean 0.00012pp; Monte-Carlo SE of the 2.5th percentile ≈ 0.0019pp) — an order of magnitude *smaller* than the 0.004pp gap between 12.37 and 12.374. So a corrected bound reaching ≤ 12.37 *would* be resolvable; none does.

*Footnote (not a finding):* the script also prints a stale hardcoded literal `Real recent: share=12.527% CI[12.357,12.697]`, disagreeing with the computed `[12.374, 12.698]`. It is a stale print literal, not an estimator defect; no argument here rests on it.

### 2. Provenance of "~12.42 / 2-season" (Phase 3)
An exhaustive search (`grep` over decisions/backlog/re-artifacts/engine + `git log -S'12.42'` + PR #1572 body) finds `12.42` / `2-season` **only in ADR-0090 and ADR-0088 prose** — no script, artifact, commit, or PR body derives it. ADR-0088 line 50 asserts the label "2-season CI floor ~12.42 (1-season point 12.53%, bootstrap CI [12.374, 12.698])" adjacent to the 1-season CI but records **no arithmetic** bridging the two.

The bridge is recoverable and corroborated:
- **√2-shrink** of the 1-season half-width: `12.536 − 0.1618/√2 = 12.4216% ≈ "~12.42"` (implies an iid-across-two-seasons assumption).
- **Direct 2280-game bootstrap** (the honest 2-season object): **[12.4181%, 12.6534%]** — lower bound 12.418%, essentially identical to the √2 number and **+0.044pp above** the 1-season 12.374%.

**Directionally load-bearing:** a genuine 2-season *game* bootstrap has more data ⇒ a *tighter* interval and a *higher* lower bound. Widening the window moves the bar **away from** authorization, not toward it. (Scope note: this holds only within the iid-game variance model; correcting the *unit* (Finding 7) moves the other way — the two are kept separate.)

**Classification: PROVENANCE gap, not a defect.** An elected bar with no recorded computation is a documentation failure in ADR-0090, not an internal error in [12.374, 12.698]. The correct repair is to replace "~12.42" with the number that *was* computed — the 1-season lower bound **12.374** — which is corroborated by the direct 2-season bootstrap (12.418). Per the disciplining arithmetic `12.37 < 12.374`, this repair **leaves the HOLD standing**. "The bar is un-derived, therefore pick another" is a criterion-selection PREFERENCE and is refused (§ Rejected levers).

### 3. Candidate (a) — unpropagated denominator uncertainty (Phase 4)
`POSS_RECENT = 216.58` is held fixed across all B replicates, yet ADR-0088 gives it a 95% CI **[216.22, 216.93]** (n = 2564). Since `share = t_g/POSS`, the denominator enters linearly. **Adjudication (before computing):** ADR-0088 records 216.58 as a *chosen era-matching conversion denominator* (a judgment call rejecting PBP ~226 and all-era 209.2), so the fixed treatment is a defensible normalization, not an omitted-error error. Numerator and denominator are drawn from overlapping corpora, so the faithful (paired) treatment is *narrower* than an independent draw.

**Computed anyway (report regardless of coverage):** relSE_POSS = 0.0836% adds ~0.0105pp in quadrature. The conservative **independent-draw** percentile CI = **[12.3729%, 12.6990%]** — lower bound **still +0.0029pp above 12.37%**, computed by the *same percentile rule* as ADR-0090's CI, so it clears the 0.0004pp resolution floor (Finding 1) decisively. (A symmetric normal-approx delta-method dips to 12.3695%, 0.0005pp under 12.37 — but that dip is an artifact of the delta-method itself: a symmetric Gaussian understates the lower tail of this right-skewed bootstrap, so it is **not** a faithful correction of a percentile CI. The percentile bound 12.373 above is the disciplining number; the delta-method is a cross-check, not the yardstick.) Propagation widens symmetrically; it does not shift the 12.532% centre toward master. **Classification: NOT-A-DEFECT**, and even the strict-estimate reading does not clear 12.37.

### 4. Candidate (b) — a reproduction interval repurposed as a one-sided gate (Phase 5)
The source script **deliberately excludes `share`** from its verdict-bearing `metrics` list (only `armed_frac`, `g_eff`, `impliedTO` get EXCEEDS/BELOW), with the inline comment that comparing `share` to the real value is *"a reproduction check … NOT given an EXCEEDS/BELOW verdict,"* and it had already removed `real_share`-derived thresholds as *circular* (they collapse onto the point estimate). ADR-0090 nonetheless elected this interval's lower tail as a one-sided floor.

**Classification: NOT-A-DEFECT (documentation gap in ADR-0090).** The interval is a correctly-constructed 95% percentile bootstrap; the script's refusal to attach an automated verdict is a statement about its closure-test harness, not about the interval's validity — and "the author did not intend this use" is none of the four DEFECT templates. The repair is the missing provenance citation, which this ADR supplies. **Direction check:** read as a two-sided reproduction tolerance, the same bootstrap gives ±0.162pp ⇒ **[12.370, 12.694]**; master 12.37 sits on the lower edge — a **tie at the bootstrap's own resolution, not coverage**. Either reading leaves the HOLD standing. ("Never meant as a threshold, therefore elect a different one" is a PREFERENCE — refused, § Rejected levers.)

### 5. Candidate — missing engine-side error term / asymmetry (Phases 6–7)
ADR-0090 gates a noisy engine point (12.37%) against a real-world CI carrying no engine-side error term. **Engine seed-spread measured (Phase 6, stride 8, 8 seeds, current master, 87 snapshots / 71.7M possessions):** mean **11.790%**, SD **0.012pp**, range **0.035pp**. (Binomial floor at 71.7M possessions ≈ 0.004pp; the measured 0.012pp is ~3× that, reflecting within-game seed correlation.) The default seed returns **11.814%** on current master vs the published 12.37% (commit `84ff51085`) — see § Measurement currency.

**Classification: PREFERENCE.** Criterion #2 is a claim about the *real-world CI's own lower bound*; an engine-side error term lives on the other side of the comparison. Adding it converts a one-sided floor into a two-sample **overlap test** — a different gating object. This also matches **ADR-0049's** own precedent: its noise floor is *corpus-side* (cross-season SD 3.82e-4 vs within-season seed SD ~1e-6, a 100–300× ratio), so propagating the corpus term and omitting the ~100× smaller engine-seed term is a *correct simplification*, not an oversight. **Quantitative disposal:** at the published 12.37 the measured engine SD (0.012pp) gives a 95% band [12.346%, 12.394%]; its upper tail crosses the CI lower bound 12.374 (an *overlap* at the CI's own resolution) but falls ~0.026pp short of 12.42. That crossing is exactly the two-sample-overlap object, not CI-lower-bound coverage — it does **not** move the CI's 12.374 lower bound, so criterion #2 is untouched. (At the current-master reading ~11.79% the band [11.766%, 11.814%] does not reach 12.374 at all.) The asymmetry argument fails on classification **and** leaves criterion #2 unmet either way — two independent grounds.

### 6. Candidate (c) — resampling unit ignores season-level clustering (Phase 7B)
The task's first-named candidate ("wrong resampling unit"), and the strongest legitimate route to a GO — audited at full strength. This is **not** the refused drift-band preference: it keeps the same estimand and gating object and corrects only *how the interval's variance was constructed*.

**Numbers:** the four re-denominated season chunks (ADR-0088: 04-05/05-06/06-07/07-08 = **[11.97, 12.47, 12.54, 12.53]**) have between-season SD **0.273pp**, range **0.57pp** — ≈ 3.3× the game-bootstrap SE; a variance decomposition puts ~92% of total spread between-season. Season-aware objects **do** fall below 12.37: season t-interval **[11.94%, 12.81%]**, crude n = 4 block bootstrap **[12.11%, 12.54%]**.

**Adjudication on the merits — NOT-A-DEFECT.** The full 20-chunk era split is **monotonic (8.563% → 12.981%)**: the between-season variation is a **secular trend (signal about which era is matched), not exchangeable sampling noise**. An iid-season bootstrap treats a trending series as exchangeable and converts trend into spurious variance — a misspecification, not a correction. The engine sims fixed recent rosters, so the estimand is "does the engine reproduce the recent era it sims"; the low draw driving the season interval below 12.37 is the *earliest* chunk (04-05 = 11.97), a different point on the trend. The three most-recent seasons [12.47, 12.54, 12.53] are tight (~12.5) and their floor is well above 12.37. A DEFECT here would have to rest on the *unit being wrong*, not on the n = 4 width — and the unit is not wrong: for a single fixed-era estimand, within-era game sampling is the appropriate variance; the season spread is between-*era* (trend). **The strongest GO route fails on the signal-vs-noise merits.**

## Classification table

| # | Candidate | Phase | Internal to [12.374, 12.698]? | Verdict | Effect on lower bound |
|---|---|---|---|---|---|
| 1 | "~12.42 / 2-season" has no recorded derivation | 3 | n/a — a *different* number | **PROVENANCE** | replace 12.42 → 12.374 (corroborated 12.418); still > 12.37 |
| 2 | Unpropagated `POSS_RECENT` denominator uncertainty | 4 | yes | **NOT-A-DEFECT** (era-matching constant) | widens ~0.01pp; corrected bound 12.373% > 12.37 |
| 3 | Reproduction interval repurposed as a one-sided gate | 5 | yes | **NOT-A-DEFECT** (doc gap) | two-sided edge ≈ 12.370 = tie within resolution |
| 4 | Missing engine-side error term (asymmetry) | 6, 7 | no — other side | **PREFERENCE** | none on the CI itself |
| 5 | Re-elect the drift band [11.97, 12.54] | 7d | no — different object | **PREFERENCE** | n/a |
| 6 | Two-sample overlap test in place of a floor | 7a, 7d | no — different object | **PREFERENCE** | n/a |
| 7 | Resampling unit ignores season-level clustering | 7B | yes — the variance model | **NOT-A-DEFECT** (drift is signal, not noise) | would widen, but iid-season is misspecified |

## Verdicts

**Verdict A — was a construction DEFECT found? NO.** No candidate is an internal error in the construction of [12.374, 12.698] under the frozen criterion. Candidate 1 is a real **provenance** gap (corrected here); candidates 2 and 3 are not-a-defect; 4–6 are preferences; 7 — the one candidate with the magnitude to move Verdict B — is a variance-model misspecification (iid-season treats a secular trend as noise), not a correction. The construction is **sound**.

**Verdict B — is cut-over authorized under re-open criterion #2? NO.** No corrected lower bound falls below 12.37% decisively:

```
master DRBPushSharePct              = 12.370%
cited / reproduced lower bound      = 12.374%   (+0.004pp)   percentile
corrected (denominator, percentile) = 12.373%   (+0.003pp)   percentile — disciplining
resolution floor (s[250]→s[251])    ≈ 0.0004pp  frozen 1d threshold; +0.003pp clears it decisively
```

Every **method-faithful** corrected bound — computed by the same percentile rule as ADR-0090's CI — lands strictly **above** 12.37%, by ≥0.0029pp, clearing the 0.0004pp resolution floor decisively (the reproduced 12.374 and the denominator-corrected 12.373). The only values at or below 12.37% are **non-faithful**: a symmetric delta-method approximation (12.3695, which structurally understates a right-skewed lower tail — Finding 3) and the **misspecified** iid-season treatment (12.11, 11.94 — candidate 7). Neither is a correction of the interval on its own percentile terms; both are refused on the merits. **Cell reached: (A = no, B = no) — construction sound; the bar stands. HOLD is upheld.**

## Rejected levers (do not re-argue)

- **"~12.42 is un-derived, so elect a different bar."** Criterion-selection PREFERENCE; the provenance repair is to cite the computed 12.374, not to swap gates.
- **"Master is inside the drift band, so elect the band."** ADR-0090 line 42 knowingly declined this; a wider *game* window yields a *tighter* floor, so the band is not the same kind of object.
- **"Both sides are noisy, so use a two-sample overlap test."** A different gating object; the engine-side term is ~100× smaller (ADR-0049) and quantitatively immaterial.
- **"Resample seasons (n = 4) to widen the interval below 12.37."** The between-season spread is a secular *trend* (signal); an iid-season bootstrap treats a trend as exchangeable noise and inflates illegitimately.

## Measurement currency

The engine seed sweep runs on **current master** (HEAD `de3934d8d`), not commit `84ff51085` where 12.37% was published. Every current-master reading lands **below** 12.37% and is **configuration-sensitive**: stride 8 (87 snapshots / 71.7M poss) gives ~11.79%, while an earlier stride-100 probe (8 snapshots / 6.7M poss) gave ~12.27%. The move is **confounded** across three independent shifts since `84ff51085` — engine #1536 (J17 game-state foul coupling: `possession.go`, `tempo.go`) changed possession endings; the archive snapshot set drifted (07-08 dir modified 2026-07-21, and the current archive yields fewer snapshots than the published 97); and stride/subsampling changes which snapshots enter the mean. It is therefore **not** cleanly attributable to any one cause and is not a clean −0.58pp engine regression number.

One thing is nonetheless robust and load-bearing: the **within-config seed SD** (0.012pp, single fixed stride, 8 seeds) — the engine error bar used above — is unaffected by the confound and is what Findings 5–6 rest on. The **direction** of the point-estimate move is by contrast only *suggestive, not firm*: every current-master reading sits *below* 12.37%, pointing further from GO, but the ~0.5pp stride swing (11.79 vs 12.27) shows subsample composition alone can move it materially, so a matched-config re-measurement is required before treating it as a settled engine shift — it is **not** relied on by either verdict. This is genuinely new evidence about a moved, config-sensitive measurement, **not** a re-reading of what ADR-0090 saw; **both verdicts are rendered against the published 12.37%**. A materially moved gate-1 point estimate is a separate, larger finding (it would mean ADR-0088's headline number needs a clean re-measurement on current master with a fixed archive/stride) and is flagged for J24 backlog housekeeping — it is **not** substituted into criterion #2, which would be the forbidden "a looser comparison clears master" move run in reverse.

## Consequences

- **Cut-over remains NOT authorized.** ADR-0090's HOLD stands; re-open criterion #2 is **not** met.
- ADR-0090's elected bar is corrected in provenance: the un-derived "~12.42" is the √2-shrink of the reproduced 1-season CI (12.4216), corroborated by the direct 2-season bootstrap [12.418, 12.653]; the honestly-computed 1-season lower bound is **12.374**, still above master 12.37.
- A status pointer to this audit is added to ADR-0090 § Re-open criteria.
- **No engine constant, parameter, code, or test was changed by this audit.** The only `engine/` path in this PR is the relocated J-series backlog doc (`engine/docs/backlog/jsb-native-backlog.md` — bookkeeping housekeeping of the audited criterion, not an engine change), so `git diff engine/` shows that doc alone. All analysis scripts are machine-local under `jsb-native/re-artifacts/`.

## Reproduce

Scripts are machine-local (git-excluded) under `jsb-native/re-artifacts/`; the numbers above are what they emit.

- `j24_armed_fraction.py` — reproduces the cited `[12.3742%, 12.6978%]` (seed 20240601, B = 10000).
- `adr0092_resolution.py` — resolution floor (2c), √2 + direct-2280 reconstructions (3b), denominator propagation (4c), season-aware objects (7B); artifact `adr-0092-resolution-20260723.txt`.
- Seed sweep — `TestFastClassArmingShareBaseline -tags archive`, `JSB_ARCHIVE_SEED` swept 20240601–08, `JSB_ARCHIVE_STRIDE=8`, `JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups`; artifact `adr-0092-seed-sweep-20260723.txt`.
- Frozen criterion: `adr-0092-criterion-freeze.md` (SHA-256 `703d686d…e2308`).
