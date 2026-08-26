---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-08-26
---

# JSB Native-Engine Backlog

**Purpose:** Catalogue the remaining work to bring the Go engine (`engine/`) to cut-over fidelity with jumpshot 5.60, with an explicit **model-tier** on every item — separating what only Fable-class reasoning has cracked, what is Opus judgment, and what is now a Sonnet-executable recipe. Each open entry is a candidate for a `/plan`.

**Origin:** Advisor triage (2026-07-08), immediately after two static-RE closures refuted long-standing "requires live debugging" premises: the foul-divisor pin (2026-07-07, Fable session) and the CEngine runtime-doubles resolution (2026-07-08). Statuses verified against `$HOME/claude-plans/`, `jsb-native/re-artifacts/`, and git log on 2026-07-08.

**Companion to** the other backlogs in [README.md](README.md); same status taxonomy. The AutoResearch loop item here (J14) is the engine-side companion of [loop-engineering-backlog.md](loop-engineering-backlog.md) L9.

> **Machine-local paths:** `jsb-native/…` references (decompile, master reference, re-artifacts, IBL5.log) are git-excluded and exist only on the primary dev machine — they are inputs to RE sessions, not repo deliverables.

---

## Taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

**Model tier** — per `.claude/rules/agent-tiering.md`; the tier named owns the item's *load-bearing reasoning* (mechanical sub-steps still delegate down):

- **🔮 Fable** — *gated: never self-selected; requires explicit user approval per run.* Reserved for the refuted-premise class: hypothesis generation over a search space where Opus has exhausted every mapped carrier with measured NULLs, and asm-level static derivations (NaN semantics, FPU-flag paths, encoded operands) that Opus-era sessions declared unrecoverable. Precedent: the 2026-07-07 foul-divisor static pin (`jsb-native/re-artifacts/jsb-foul-divisor-static-pin-20260707.md`) overturned an x32dbg runbook's core premise and directly produced the J1 plan.
- **🧠 Opus** — novel RE judgment, A/B verdicts, ADR authoring, ambiguous statistical interpretation.
- **⚙️ Sonnet** — fully-specified mechanical recipe with machine verification (the design is already resolved).
- **📇 Haiku** — enumeration / grep-and-format feeding a higher tier.

**Effort scale:** **S** — single PR, < 1 day. **M** — multi-step plan, 1–3 days. **L** — program-level, likely needs an ADR.

---

## Dependency spine (what unblocks whom)

```
J1 faithful foul pair (✅ 2026-07-10, ADR-0082) ─→ J2 adjudications (✅ 2026-07-12): SHIPPABLE verdict
  └─→ J6 composite-scale pins (✅ 2026-07-10, Fable): OVERTURNS the defQ≡0 / +0xDE0 / +0xDC8 dead-zero pins
        └─→ J15 faithful foul-bucket program (✅ 2026-07-12, ADR-0084 — live defQ = Σ STL/MIN×44, offQ = Σ TOV/48)
              ├─ absorbs J12 (HCA re-homing — corpus margin ground truth 4.12 unchanged) — ✅ absorbed
              ├─ prerequisite: J16 escape bound re-derived with LIVE AST/48 (J19) — ✅ J19 done
              └─→ J2 verdict: SHIPPABLE with residual → J20 🚫 void (within-possession lever cannot move Var(lnPOSS); pace/base_time dispersion is J23's domain) → J13 (unblocked)
J17 game-state foul coupling (✅ 2026-07-21, PR #1536) — param_8 + trailing-by-3 shotClock + leading-by-1-3 forcedMake ported; J17c doForcedMakeMax PINNED=10 (objdump); J17b +0x30 WRITER found, reader/port deferred → J24 residual (3)
J21 gt=4 playoff-margin audit (✅ 2026-07-14 — no overshoot, engine under-disperses globally) · J22 per-player STL/TOV bundle wiring (✅ 2026-07-16) — cut-over-gate fidelity inputs to J13; NEITHER is a Cov(lnFGA,lnPPS) lever
J23 round-half-up + base_time re-center (✅, 2026-07-16, #1495) — coupled faithful fix deferred from J21; ADR-0085 records the hold finding; shipped round-half-up + baseTimeMid re-center 14.5→13.65 (J23)
  └─→ J24 possession-clock subsystem port (◑ Partial) — step classes + jitter, steal split + faithful shotValue2pt/3pt, matchupQuality Phase-3 matched (+0xDC8) & non-matched (+0x350, J25) terms, FG% band CLOSED via +0xD58 penalty-minutes (#1544), and the §1d steal-gating partition (#1547) all SHIPPED. **Gate-1 code-7 arming share re-adjudicated WITHIN-NOISE (was "NO-GO vs floor 12.94" — a denomination artifact): 12.94 = markers/g 27.13 ÷ the all-era 209.2 poss/g, but the engine sims recent 05-08 rosters whose real poss/g is 216.58; re-denominated, master's 12.37% **[SUPERSEDED 2026-07-24 — current 12.4142%; conclusion unchanged]** is INSIDE the recent between-season drift band [11.97, 12.54]. NOT a clean GO — master is −0.05pp under the tightest 2-season floor ~12.42 (SEM caveat). Gate-1 decided by ADR-0090 (J13-3 FINAL). See ADR-0088/0090.** Open residuals: (7) 3P undershoot (◑ Partial — make-rate CLOSED 2026-07-22: harness artifact not engine defect, engine 40.16% ≈ sco 38.96%; attempt-share REFRAMED 2026-07-22: unit-mismatch in localise instrument pinned — `dec_rate_delta_e`/decision-frequency reading retired; correct engine-vs-sco: 3GA 73.0%/2GA 100.8% of sco; defect is 3pt-SHARE-specific; **OReb putback-3pt gate RE-ADJUDICATED 2026-07-23 and RESTORED — the 2026-07-22 removal was itself the misread (`FUN_004e1ba0`'s `param_6` is a `double`, so `param_3` = `local_15c` = the OReb flag and `:97195` DOES reject 3pt on a continuation); the +1.70pp it bought was a calibration gain from an unfaithful mechanism (ADR-0090), so the gap is back toward −4.46pp **[SUPERSEDED 2026-07-24 — measured −2.33pp]** pending re-measurement**; `local_15d` forced-3pt override REFUTED (INERT under Sim Game); Transition `allow3pt=false→true` **CLOSED 2026-07-23** — faithful port, +2.08pp 3PA/100poss (`gate_delta_c_raw` −0.01783→−0.00918, `SuppressionFrac` 0.237→0.1217); FTA side-effect −0.26/100poss ADR-0090 disclosure. **Denominator Σ-basket + +0xDB0 3pt-numerator magnitude RE'd NOT-A-LEVER 2026-07-23** (🔮 Fable asm RE); **no faithful lever confirmed for the remaining gap — may be INTRINSIC to 5.60** (engine 0.1776 IS 5.60's own share once w2+basket are faithful; a w1/w3/w4 bucket-VALUE divergence is an UNVERIFIED by-elimination candidate) — **routing/undershoot discrepancy RESOLVED 2026-07-25 (time-axis error — see residual (7)); INTRINSIC hypothesis DEMOTED — re-argue on 0.1776 vs 0.1959**), (8) constant-seed single-game harness artifact in `possession_archive_test` and `threept_attemptrouting_archive_test` (⬜ Open, surfaced 2026-07-22), (6) `.plb dc_minutes` wiring, (3) CEngine+0x30 reader (with J17b writer). See the J24 entry for the full current-state + NOT-A-LEVER trap list
J27 FTA undershoot ~−21% vs sco (⬜ Open 2026-07-24 — FTA-side gap; J24 faithful putback + transition ports disclosed partial cost; no mechanism confirmed; 🧠 Opus, M)
J29 TOV generator unfaithful in-tree (⬜ Open 2026-07-29, #1676 reverted) ── the split has two independently-blocked halves:
  ├─ steal side: `param` valued (`leagueSTL48 × 5.0`, RE 2026-07-29); now blocked on the unresolved call-gating fraction (discovered 2026-08-22 during jsb-j7-param-recovery) → J29 next-RE step (i)
  └─→ J30 non-steal side (⬜ Open) — the documented outcome-5 `sqrt(local_44)` path is falsified ~50× vs corpus; NEITHER half blocks the other, and either alone leaves the generator unfaithful
J31 play-outcome frame-trace residuals (⬜ Open — `local_e78`/`def_rating` offsets; same `FUN_0040b6a0` copy-map work as J30 candidate break 1, so cheaper if J30 runs first)
J32 master-reference audit sweep (◑ Partial — 8 drift sites + the doc-wide argument-binding caveat ✅ corrected 2026-07-29, applied straight to the reference since it is outside git and outside every CI gate, which is also WHY the drift survived; the 34-line proof-type sweep + the in-tree/out-of-tree decision remain open)
J18 composite fidelity ports (✅ 2026-07-13 — all divergences merged; f/shrink port declined as documented divergence) · J19 J6-residue RE (✅ 2026-07-12) — both spawned by J6
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy). Zeroing defQ moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever; that A/B stands as measurement. **But J6 (same day) overturned the static premise underneath it:** J5's "defQ ≡ 0" was a store-enumeration blindspot — 5.60 builds the player record on the STACK (FUN_004cfa50 → FUN_00405970 write-back), so +0xDD0 (STL/MIN×44), +0xDE0 (usage-shrunk TOV/48), and +0xDC8 (AST/48) are all **live**. The faithful foul coupling is therefore roster-VARYING (defQ = Σ defenders' STL/MIN×44; offQ = Σ offense TOV/48 − HCA, TOV-coupled not volume-neutral), and J2's "symmetric U[0,0.6) both sides" verdict plus the J15 program must be re-adjudicated against the live-composite semantics before any port ships. "Mapped carriers exhausted" stays refuted; the map had a foul-path hole — and a method hole (see J6's caveat).

---

## Entries

> **`00_MASTER_REFERENCE.md` line numbers below are volatile.** That file is machine-local,
> git-excluded, and edited in place, so every insertion shifts all citations after it (the 2026-07-29
> audit pass moved lines by up to ~+40). Any `00_MASTER_REFERENCE.md:NNNN` here is a *snapshot* —
> **match on the quoted anchor text, never seek by number.**

| # | Title | Status | Tier | Effort |
|---|-------|--------|------|-------:|
| J1 | Faithful foul-bucket pair port | ✅ Implemented | ⚙️ Sonnet | M |
| J2 | Count-axis carrier adjudication (post-J1) | ✅ Implemented | 🔮 Fable | L |
| J3 | Per-origin efficiency identifiability (IBL5.log) | ✅ Implemented | 🔮 Fable | M |
| J4 | Play-by-play extraction parser | ✅ Implemented | ⚙️ Sonnet | M |
| J5 | Unpinnable-claims sweep + static closures | ✅ Implemented | 🔮 Fable | M |
| J6 | Composite-scale pins (+0xD90/+0xDB0, `f`, full player formula map) | ✅ Implemented | 🔮 Fable | M |
| J7 | Turnover volume-coupling fidelity RE | ✅ Implemented | ⚙️ Sonnet | M |
| J8 | Transition trigger denominator 18 | ✅ Implemented | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ✅ Implemented | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ✅ Implemented | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ✅ Implemented | ⚙️ Sonnet | S |
| J12 | HCA re-homing to basis-scaled site-2 (absorbed into J15) | ✅ Implemented | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ✅ Implemented | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ✅ Implemented | 🧠 Opus | L |
| J15 | Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor) | ✅ Implemented | 🧠 Opus | L |
| J16 | FUN_004e3860 net-advantage formula via objdump | ✅ Implemented | 🔮 Fable | S |
| J17 | Game-state foul coupling port (param_8 desperation + late-game fouling) | ✅ Implemented | 🧠 Opus | M |
| J18 | Composite fidelity ports (bucketweights/teamquality vs the J6 formula map) | ✅ Implemented | 🧠 Opus | M |
| J19 | J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58) | ✅ Implemented | 🧠 Opus | M |
| J20 | Empty-FGA / within-possession restructure (Cov possession channel) | 🚫 Declined | 🧠 Opus | L |
| J21 | gt=4 playoff-margin overshoot audit (playoffNetMultiplier ×1.25) | ✅ Implemented | 🧠 Opus | S |
| J22 | Per-player rl_stl/rl_tov production-bundle wiring (PF dispersion) | ✅ Implemented | 🧠 Opus | M |
| J23 | round-half-up + base_time re-center (coupled pace faithful fix) | ✅ Implemented | 🧠 Opus | M |
| J24 | Possession-clock subsystem faithful port (step classes + jitter + arming) | ◑ Partial | 🧠 Opus | L |
| J26 | `fastClassShareArtifact` era/corpus field gap — all-era run logs recent-era band text (instrument hazard) | ✅ Implemented | ⚙️ Sonnet | S |
| J27 | FTA undershoot ~−21% vs sco (engine 152,874 / sco 194,185 = −21.27%; no confirmed mechanism) | ⬜ Open | 🧠 Opus | M |
| J28 | Cov(lnFGA,lnPPS): no positive lever (the −0.000062 J7-port regression is out of the tree with the #1676 revert; re-measure if the correct-form port lands) | ⬜ Open | 🧠 Opus | S |
| J29 | TOV generator is **unfaithful in-tree** — #1676 (total-TOV-rate share) reverted 2026-07-29, restoring the rating-for-stat `stealTurnoverScale = 1.69e-5` stand-in; in band but not 5.60's form. `param` is now valued (`leagueSTL48 × 5.0`, RE 2026-07-29) and the full steal formula is recovered; the port is now blocked on the **unresolved call-gating fraction** — the recovered formula gives ≈47.7% at band vs the 8.75% archive rate | ⬜ Open | 🧠 Opus | M |
| J30 | Non-steal turnover origin: the outcome-5 `sqrt(local_44)` path is quantitatively falsified (~50× short) — find the break in the chain or the second generation site | ⬜ Open | 🧠 Opus | M |
| J31 | Play-outcome frame-trace residuals: `local_e78` and `def_rating` source offsets unpinned (the master-ref closed-vs-open self-contradiction, item (3), ✅ adjudicated 2026-07-29) | ⬜ Open | 🧠 Opus | S |
| J32 | Master-reference audit sweep — 8 confirmed drift sites + both additive caveats ✅ corrected 2026-07-29; the 34-line proof-type sweep and the CI-invisibility decision remain | ◑ Partial | 🧠 Opus | S |

### J1 Faithful foul-bucket pair port
➜ J1 Faithful foul-bucket pair port — ✅ Implemented (2026-07-10): see [archive](archive/jsb-native-backlog-archive.md).

### J2 Count-axis carrier adjudication (post-J1)
➜ J2 Count-axis carrier adjudication — ✅ Adjudicated (2026-07-12): SHIPPABLE verdict; J20 🚫 void (pace/base_time dispersion is the real Cov carrier → J23); see [archive](archive/jsb-native-backlog-archive.md).

### J3 Per-origin efficiency identifiability (IBL5.log)
➜ J3 Per-origin efficiency identifiability — ✅ Implemented (2026-07-09): study complete; J4 unblocked with spec; see [archive](archive/jsb-native-backlog-archive.md).

### J4 Play-by-play extraction parser
➜ J4 Play-by-play extraction parser — ✅ Implemented (2026-07-12): 23,714/23,714 games parsed, 100% sentence closure; feeds J17 instruments (J20 🚫 void); see [archive](archive/jsb-native-backlog-archive.md).

### J5 Unpinnable-claims sweep + static closures
➜ J5 Unpinnable-claims sweep + static closures — ✅ Implemented (2026-07-09): closures 1–3 + team decode stand; closure 4 overturned by J6; see [archive](archive/jsb-native-backlog-archive.md).

### J6 Composite-scale pins (+0xD90/+0xDB0, `f`, full player formula map)
➜ J6 Composite-scale pins — ✅ Implemented (2026-07-10): every target pinned + mechanism discovered; spawned J15/J18/J19; see [archive](archive/jsb-native-backlog-archive.md).

### J7 Turnover volume-coupling fidelity RE
➜ J7 Turnover volume-coupling fidelity RE — ✅ Implemented (2026-07-26): `teamOffTOVShare` (TVR_rate/(shot_rate+TVR_rate)) replaces stealTurnoverScale×(100−TVR)×Σ(STL×fatigue); Cov regression −0.000062 disclosed as J28; PR #1676. See [archive](archive/jsb-native-backlog-archive.md). ⚠️ **The RE verdict stands; the port that shipped under it does not — see J29** (2026-07-29): the ported share is a *total*-TOV rate and is the complement of the fast-break conversion threshold the J7 RE explicitly ruled out as the generator, so STL/g runs 30.5 vs a 17.8±0.7 target. J7's status is unchanged because J7 was an RE deliverable ("No engine change. No worktree/PR" — its own artifact); the level defect belongs to J29. **#1676 reverted (2026-07-29):** `teamOffTOVShare` is no longer in the tree and `stealTurnoverScale = 1.69e-5` is restored along with its stand-in registration; the correct-form port is J29. J7's RE verdict is unaffected — what reverted is the port, not the finding.
[CORRECTED 2026-07-29] The denominator attribution in the 2026-07-20 artifact was wrong (offense/defense reversed). The cap formula and `param` identity STAND. Corrected formula: re-artifacts/jsb-J7-steal-probability-RE-20260729.md.
### J8 Transition trigger denominator 18
➜ J8 Transition trigger denominator 18 — ✅ Implemented (2026-07-13): PR #1433 (`transitionTriggerDenom` 20→18, asm-verified); see [archive](archive/jsb-native-backlog-archive.md).

### J9 League-baseline faithful port (FUN_004385f0)
➜ J9 League-baseline faithful port — ✅ Implemented (2026-07-12): PR #1437; see [archive](archive/jsb-native-backlog-archive.md).

### J10 `.plb` minutes reader + stamina=100 bundle fix
**Location:** `engine/internal/backup` bundle assembly — `DCMinutes` zeroed and stamina defaulted to 0 at assemble time (line refs drift; grep `assemble.go` for the two TODO sites).
**Problem:** Zeroed `dc_minutes` flattens rotation selection (the engine uses it in lineup quality scoring); stamina 0 is a degenerate energy ceiling where 5.60's faithful value is a uniform 100 (verified: `.plr` offset 546 constant across all players; no per-player stamina exists in JSB).
**Direction:** Add a Go `.plb` reader (32 team lines × 30 slots × 12 chars; slot→player via the ordinal formula in `ibl5/classes/PlrParser/PlrOrdinalMap.php`), wire `DCMinutes` into the bundle, set stamina ceiling to constant 100. One PR; recipe fully specified in the acceptance-bar record.
**Status (2026-07-12):** ✅ Implemented. Merged via PR #949 (`engine/internal/backup/plb.go` + `assemble.go`) — confirmed still live; archive regression re-run clean. ⚙️ Sonnet.

### J11 Season-selection min-GP guard
➜ J11 Season-selection min-GP guard — ✅ Verified already-implemented (2026-07-13): guard shipped in PR #975 (`minSeasonMedianGP = 70` + proxy-medGP skip, `engine/internal/calibrate/season.go`); see [archive](archive/jsb-native-backlog-archive.md).

### J12 HCA re-homing to basis-scaled site-2 (absorbed into J15)
**Location:** `engine/internal/sim/possession.go` `s2 + hca` (the site-2 2pt-composite nudge).
**Problem (re-scoped by J2 session 1):** the foul path was carrying ~ALL engine home margin (3.44 → −0.06 under defQ ≡ 0) — unfaithfully. 5.60's static HCA site-2 is `e88 += s·0.2`; Go applies raw ±0.2 on a ~16.5 basis (~1.2%). If site-2 is in raw bucket units like the foul pair was, the faithful proportional effect is ~8.6× larger (~10%) — ADR-0082's flagged under-scaling caveat, now load-bearing. Whether the foul-side `−s·0.2` also expresses depends on the live-composite home-arm question J6 reopened (J15 prerequisite) — the made-bucket nudge is live either way. Real corpus home margin: 4.12.
**Direction:** Executes inside J15 (the margin gate can't pass without it). The J6 composite-basis pins are now the faithful ground for the scale; corpus home-margin re-measure is the acceptance check. Win-share caveat stands: tune margin_gap, compare win share only at `--runs 1`.
**Status (2026-07-12):** ✅ Implemented — absorbed into J15's program scope, which shipped 2026-07-12 (ADR-0084); no independent J12 work remains. 🧠 Opus.

### J13 Cut-over package: bands, leaders, decision
**Location:** `engine/internal/validate/bands.go` (placeholder ±15%, explicitly non-authoritative); per-player leaders validation (never built); the standings-residual gate (floor ≈ 3–5 wins / ~0 ppg); Var(lnPPS) sits ~2% under real as a monitor-only watch item.
**Problem:** Even with the dispersion blocker resolved, cut-over needs authoritative bands derived from the archive, a per-player sanity layer, and the actual go/no-go decision (env-flag swap of the jumpshot.exe invocation, `.sco` import path kept for one-command rollback; SHADOW as the live distributional check).
**Direction:** Gated on the J2 verdict. Band derivation and the leaders instrument are ⚙️-delegable; the acceptance judgment and the cut-over ADR are 🧠.
**Status (2026-07-24):** ✅ Implemented — **bands sub-item VERIFIED post-J18** (`jsbcalibrate --mode gate` re-run on the post-J15+J18 engine, runs=20 stride=50: PASS, no literal change; provenance in `engine/internal/validate/bands.go` J18 block). **Leaders instrument (J13-2) SHIPPED** (2026-07-14, PR #1463 `6899910ea` — per-player leaders validation instrument); **cut-over ADR (J13-3) FINAL** — ADR-0090 Accepted (HOLD, corrected: 12.94 artifact overturned; master 12.37% inside drift band but −0.05pp under the era-matched 2-season floor ~12.42; #1572). Re-open when gate-1 DRBPushSharePct ≥ 12.42% or the 2-season CI recalibrates to cover 12.37% (ADR-0090 § Re-open criteria). **Criterion #2 (the 2-season CI recalibration path) was adversarially construction-audited — ADR-0094 (2026-07-23): Verdict A = NO (no construction defect in the elected ~12.42 floor), Verdict B = NO (cut-over not authorized); HOLD stands.** **Criterion #1 re-evaluated 2026-07-24 against the matched-config re-measurement — still NOT met.** `DRBPushSharePct` on current master (`77b9be48b`), matched config (98-zip recent-era 05-08 corpus, stride 1, 7 disjoint seed blocks) = **12.4142% ± 0.0210 (SE 0.0079)**, i.e. **−0.0058pp / −0.73 SE below the ≥12.42 bar**; on the current 100-zip corpus the seed-paired estimate is **12.4277** (+0.77 SE), also indistinguishable from the bar. ADR-0088's 12.37% is superseded by 12.4142% and the engine's +0.049pp move is attributed by bisect to the faithful ports #1590/#1593/#1595 (legitimate under ADR-0090's faithfulness rule) — but neither corpus clears the floor, so **HOLD stands and no new ADR is warranted**. Full measurement, corpus provenance, and bisect decomposition: J24 § matched-config re-measurement. Band derivation and per-player instrument are ⚙️-delegable; the acceptance judgment and cut-over ADR are 🧠.

### J14 AutoResearch eval-harness ADR (loop L9 companion)
➜ J14 AutoResearch eval-harness ADR (loop L9 companion) — ✅ Implemented (2026-07-20): see [archive](archive/jsb-native-backlog-archive.md).

### J15 Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor)
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` `defQuality`/`offQuality` + `possession.go` site-2 HCA + `engine/internal/validate` bands/goldens. Measured ground: `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md` §4/§6; faithful formulas: `jsb-J6-composite-scales-20260710.md`.
**Problem (re-scoped TWICE on 2026-07-10 — J2 then J6):** J2 proposed a *symmetric* program on J5's defQ ≡ 0 pin; **J6 overturned that pin**, so the faithful 5.60 pair is now statically known and roster-VARYING: **defQ = Σ five defenders' STL/MIN×44** (live +0xDD0), **offQ = Σ offense TOV/48 − HCA** (live +0xDE0 — TOV-coupled, so ADR-0061's `offQualityConstant = 1.575` and its volume-neutrality claim are unfaithful, and ADR-0082's shipped `defQuality` formula — floor1(OD)×0.25, neutral 8.21 — is the wrong composite entirely). Whether the away-side `≤ 0 → redraw` still dominates with live values is an EMPIRICAL question (the J2 corpus evidence — smooth winner-following FTA edge, no bimodality — bounds a deterministic arm but no longer proves it dead). What survives J2's A/B unconditionally: the FTA LEVEL must re-anchor to real 20.65 (37.8 shipped via A-relative gates), and HCA must re-home off the foul path (margin 3.44 → −0.06 under defQ0 while real = 4.12).
**Direction:** One program PR, now design-first: (1) port the faithful pair — defQ = Σ STL/MIN×44, offQ = Σ TOV/48 − HCA, in 5.60's units with the k-scale derived, not swept; (2) HCA re-homed to basis-scaled site-2 (J12 — target real margin 4.12; account for the `e88 → e90` and-one arm J16 identified); (3) FTA-level re-anchor against **real 20.65**, never the A-baseline; (4) band/golden re-derivation. **Prerequisite:** J19's escape-bound re-derivation with live AST/48 (J16's "unreachable" verdict is void until redone) and a static check of whether live defQ keeps the home arm non-positive. `/plan` with `auto_merge: false` (gate re-grounding is judgment).
**Risk if untouched:** every foul-share fidelity readout stays confounded, J2's final adjudication cannot run, and two shipped stand-ins (ADR-0061 offQ constant, ADR-0082 defQuality composite) keep wearing a faithfulness label J6 disproved.
**Status (2026-07-12):** ✅ Implemented — ADR-0084. Full detail in [archive](archive/jsb-native-backlog-archive.md).

### J16 FUN_004e3860 net-advantage formula via objdump
➜ J16 FUN_004e3860 net-advantage formula — ✅ Implemented (2026-07-10): formula + symmetry closures stand; reachability reopened under J19; see [archive](archive/jsb-native-backlog-archive.md).

### J17 Game-state foul coupling port (param_8 desperation + late-game fouling)
➜ J17 Game-state foul coupling port — ✅ Implemented (2026-07-21, PR #1536): param_8/clock-desperation + Q4 trailing-by-3 shotClock + Q4 leading-by-1-3 forcedMake ported; J17c doForcedMakeMax PINNED=10 (objdump); J17b CEngine+0x30 WRITER found, reader/port deferred → J24 residual (3); see [archive](archive/jsb-native-backlog-archive.md).
**Gate-1 neutrality (2026-07-22, all-era A/B, 705 snapshots, stride 1, runs 4, seed 20200101):** Δ DRBPushSharePct = **−0.00146pp** (A 11.69932% pre-#1536 → B 11.69786% master) — gate-1 neutral. **Caveat:** 705 snapshots = all-era corpus (11.70%) — NOT band-comparable to the recent-era 05-08 / 97-snapshot gate-1 figure (master 12.37% **[SUPERSEDED 2026-07-24 — 12.4142%]** vs 12.42% floor); ADR-0090's HOLD is untouched. Same A/B independently corroborates PR #1585's `OutcomeDiagAccum` non-perturbation claim at 584M-possession scale.

### J18 Composite fidelity ports (bucketweights/teamquality vs the J6 formula map)
➜ J18 Composite fidelity ports — ✅ Implemented (2026-07-13): six formula divergences merged (#1433, #1435–#1437, #1440, #1443, #1444); f/pass-2 usage-shrink port decided as a documented divergence (bundle lacks Confidence + `+0x18`; J19 bounds real `f` spread to ±2%); see [archive](archive/jsb-native-backlog-archive.md).

### J19 J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58)
**Location:** `jsb-native/` decompile + PE; open items recorded in `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md` § still-open.
**Problem:** J6 left four bounded unknowns, two of them load-bearing: (1) **J16 escape-bound re-derivation with live AST/48** — blocks J15's design (is the home arm's `≤ 0 → redraw` still the dominant path with defQ = Σ STL/MIN×44 and a sign-varying matched term?); (2) **rec[+0x18] in-season semantics** — pinned = 100 at reset, but constant-vs-decay determines `f`'s real spread (±2% vs wider) and whether the f-port matters (J18); (3) energy-formula operand identities (slots 0x1c/0x64/ebx in asm 4d4711–4d4774); (4) +0xD58 — computed and stored (4d42df) but no reader found; confirm dead or find the reader. Also parked here: the transition-retention re-trace (+0xDA0/+0xDA8 live for 3pt shooters — the master ref's vestigial claim was premise-corrected but the downstream retention path was never re-walked).
**Direction:** Item (1) is arithmetic over already-pinned formulas + IBL5.plr distributions — do it first, it unblocks J15. Items (2)–(4) + the retention re-trace are one objdump session, precedented method. Escalate to 🔮 only if the asm hits the NaN/FPU-flag class.
**Status (2026-07-12):** ✅ Implemented — item (1) resolved inside J15 program (ADR-0084); items (2)–(4) + retention re-trace spun forward as follow-on objdump session. Full detail in [archive](archive/jsb-native-backlog-archive.md).

### J20 Empty-FGA / within-possession restructure (Cov possession channel)
➜ J20 Empty-FGA / within-possession restructure — 🚫 Declined (2026-07-16): mechanism void — within-possession putback lever cannot move Var(lnPOSS); OReb continuations loop inside one `possession()` call without decrementing clock, so Var(lnPOSS) = f(base_time dispersion) only; per-origin shares already J4-faithful (putback 12.58% vs 12.65%); real Cov carrier is pace/base_time dispersion → J23's domain; evidence: `engine/internal/calibrate/possessioncoupling_archive_test.go:51-64` (2026-07-13); see [archive](archive/jsb-native-backlog-archive.md).

### J21 gt=4 playoff-margin overshoot audit
➜ J21 gt=4 playoff-margin overshoot audit — ✅ Implemented (2026-07-14): NO overshoot; engine under-disperses globally; no follow-on fix; see [archive](archive/jsb-native-backlog-archive.md).

### J22 Per-player rl_stl/rl_tov production-bundle wiring
➜ J22 Per-player rl_stl/rl_tov production-bundle wiring — ✅ Implemented (2026-07-16): PR #1490; real per-player STL/TOV composites feed defQ/offQ; rating stand-in retained as RealLifeMIN==0 fallback; see [archive](archive/jsb-native-backlog-archive.md).

➜ J23 round-half-up + base_time re-center — ✅ Implemented (2026-07-16, PR #1495): round-half-up (`int(pt+0.5)`) in `possessionTime` COUPLED with `baseTimeMid` re-centered 14.5→13.65; mean pace ~104.5 poss/g restored; four-term gate documented-null on Cov sign; ADR-0085 hold lifted; see [archive](archive/jsb-native-backlog-archive.md).

### J24 Possession-clock subsystem faithful port (step classes + jitter + FUN_004e4150 ratio)
**Location:** `engine/internal/sim/tempo.go` + `gameloop.go` (one deterministic per-game step, additive base_time stand-in) vs 5.60 `FUN_004e42e0`/`FUN_004e4150` — **both now fully pinned statically** (2026-07-16 Fable RE session, user-directed; artifact: `jsb-native/re-artifacts/jsb-J24-pace-dispersion-RE-20260716.md`).
**Problem:** 5.60's possession clock has THREE step classes the engine collapses into one constant: steal transition = `rand(3)` (0–2s); DRB push (code 7) = `rand(3)+2` (2–4s), gated per possession by `rand(18) ≤ TO rating (+0x1e8 = bundle r_trans_off) − (gt==4) + tempo-strategy adj` (team `.lge` strategy 1–5: ±1, half-prob at 2/4); half-court = round-half-up(pt/2 + U[0,pt)) with a {3..23} redraw when the rounded step hits trunc(pt). pt itself is a per-matchup RATIO of seven volume composites + league constants (all weights pinned: 2880/0.625/1.4/1.25/4.0; typical pt ≈ 15.3–15.5, effective mean step 13.8 = 2880/209.2 ✓ — the engine's `baseTimeMid=13.65` re-center silently absorbed the missing fast paths). This subsystem is the named carrier of the Var(lnPOSS) gap (0.000339 vs real 0.000721) and the wrong-signed Cov(lnPOSS,lnPPS) (−0.000095 vs positive; 81–89% of real Cov(lnFGA,lnPPS) per J2s2) — the cross-team dispersion enters via steal rate, TO ratings, and tempo strategy, channels the engine's clock never sees. Corrects two stale premises: ADR-0085's "rounds half-up" pin was radically incomplete (it rounds a JITTERED draw), and the June poss-channel closure (corr −0.42) predates the foul program and is re-opened.
**Direction:** Own `/plan`, `auto_merge: false`. Phases: (0) instrument — faithful-pt distribution across archive rosters + re-measure the −0.42 premise on current master; (1) faithful FUN_004e4150 ratio replacing the additive stand-in (composites live post-J22); (2) per-possession jittered step + redraw, retiring the per-game constant; (3) steal-transition 0–2s step; (4) DRB-push class on `r_trans_off` + tempo strategy (**bundle gap:** `.lge` strategy field needs offset pin + extraction); (5) retire `baseTimeMid`. Gates: mean pace ~104.6 preserved; Var(lnPOSS) → 0.000721; Cov(lnPOSS,lnPPS) sign; per-class shares vs J4 instrument.

**Current state — CONSOLIDATED 2026-07-21** (dated measurement log in [archive](archive/jsb-native-backlog-archive.md)). This backlog is the **single** authoritative record for J24. Merged-PR commit hashes are not stored here — resolve any PR number with `git log --all --grep '#<PR>'`.

**✅ Gate-1 code-7 arming share — re-adjudicated WITHIN-NOISE (2026-07-21; ADR-0088).** Measured on merged master (`84ff51085`; `TestFastClassArmingShareBaseline -tags archive`, recent-era 05-08, 97 snapshots, 85.77M possessions): **`DRBPushSharePct` = 12.37%** (10,608,830 code-7 / 75,158,396 half-court). The prior "band floor **12.94** → 0.57pp UNDER" NO-GO was a **denomination artifact**: 12.94 = real markers/g **27.13 ÷ the all-era 209.2** poss/g, but the engine sims recent 05-08 rosters whose real poss/g is **216.58** (box-score Dean-Oliver `FGA + 0.44·FTA + TOV − ORB`, n=2564g, CI [216.22, 216.93]). Re-denominated, the recent **between-season drift band is [11.97, 12.54]** (chunks 04-08 = [11.97, 12.47, 12.54, 12.53]) and master **12.37% is INSIDE**. **Verdict WITHIN-NOISE, NOT a clean GO** — master is −0.05pp under the tightest era-matched 2-season CI floor **~12.42** (1-season point 12.53%, bootstrap CI [12.374, 12.698]); this SEM caveat was decided by ADR-0090 (J13-3, Accepted 2026-07-21): **HOLD retained** (corrected — the 12.94 artifact is overturned, but master stays −0.05pp under the era-matched 2-season floor ~12.42, which is elected as the cut-over bar; re-open per ADR-0090 § Re-open criteria). **Criterion #2 adversarially construction-audited — ADR-0094 (2026-07-23): the un-derived "~12.42" is a *provenance* gap, not a defect — recoverable as the √2-shrink of the reproduced 1-season CI (12.4216) and corroborated by a direct 2-season game bootstrap [12.418, 12.653] (Verdict A = NO); the honestly-computed 1-season lower bound is 12.374, still above master 12.37%, so cut-over is not authorized (Verdict B = NO). Every candidate defect (denominator uncertainty, reproduction-interval-as-gate, missing engine error bar, season clustering) is a NOT-A-DEFECT, PREFERENCE, or provenance finding — see the ADR-0094 NOT-A-LEVER additions below. HOLD stands.** The residual −0.05pp is *optionally* closeable via armed-fraction + on-court-`TransOff` composition (real recent armed ~41.1% vs engine ~40.34% post-PR4b; on-court-5 mean picked TransOff ~6.15 vs ~5.658; `jsb-native/re-artifacts/jsb-J24-armed-transoff-RE-20260719.md`), but the fatigue-sub *timing* sub-path is CLOSED (ceiling 12.61%, see NOT-A-LEVER) and the remaining `selectStarters` composition change breaks lineup faithfulness — so it is NOT pursued to force the band. No constant has been tuned toward the band; scope is "legible," not forced-GO.

**✅ MATCHED-CONFIG RE-MEASUREMENT DONE (2026-07-24) — ADR-0088's 12.37% is SUPERSEDED by 12.4142%; the engine moved UP, and ADR-0090 re-open criterion #1 is still NOT met.** This closes the "needs a matched-config re-measurement" task ADR-0094 § Measurement currency opened (superseded paragraph retained below). **Config, recorded OUT-OF-BAND because the artifact JSON has no era/corpus field (J26):** `TestFastClassArmingShareBaseline` (`engine/internal/sim/fastclass_share_archive_test.go`, `-tags archive`) at master `77b9be48b`, **stride 1**, `runs=4`, corpus = **recent-era 05-08 minus the two 07-08 zips added after the ADR-0088 publish moment** (36 + 36 + 26 = **98 zips → 97 snapshots**, matching ADR-0088's 97). Corpus identity was VERIFIED, not inferred: `find … -newermt "2026-07-21 10:28:42"` *and* the `-newerct` variant each return exactly `07-08_27_reg-sim16.zip` + `07-08_28_reg-sim17.zip` (ctime agreeing rules out an in-place rewrite with preserved mtime).

**Result — 7 disjoint seed blocks** (`runs=4` consumes `seed..seed+3`, so blocks MUST be spaced ≥4 apart; a first replication attempt at 20240602/03/04 overlapped the baseline block by 3/2/1 draws and was discarded): 12.4437 / 12.4039 / 12.4146 / 12.4280 / 12.3767 / 12.4212 / 12.4112 → **mean 12.4142%, SD 0.0210, SE 0.0079** (blocks 20240601…20240625). Against the floors: **−0.0058pp (−0.73 SE) vs ADR-0090's ≥12.42** (3 of 7 blocks above), **−0.0074pp (−0.94 SE) vs ADR-0094's √2-shrink 12.4216** (2 of 7), −0.0038pp (−0.48 SE) vs the 2-season bootstrap LB 12.418 (3 of 7). **Re-open criterion #1 NOT met** — every estimate is within ±1 SE of the floor and the point estimate is *below* it. HOLD stands; ADR-0094 Verdict B untouched; no new ADR is warranted for a non-event. **On the CURRENT 100-zip corpus** (99 snapshots, n=4 blocks): raw mean 12.4361 — but that reads +1.67 SE over 12.4216 **only because blocks 01/05/09/13 happen to average +0.0065pp above the 7-block matched mean**. The seed-PAIRED corpus delta is essentially deterministic (+0.0134/+0.0133/+0.0133/+0.0139, SD 0.00031), so the correct current-corpus figure is the paired estimate **12.4277** (+0.77 SE over 12.4216) — also indistinguishable from the floor. Cite 12.4277, never the raw 12.4361.

**Direction claim in the superseded paragraph is AFFIRMATIVELY REFUTED, not merely stale:** its 11.81% (stride 8) and ~12.27% (stride 100) readings were **all-era** subsamples (720/8 = 87 and 720/100 = 8 snapshots), never band-comparable to a recent-era floor. The engine moved **UP** (12.3652 → 12.4142), not "below 12.37, further from GO."

**Attribution is MEASURED (bisect), not inferred.** Same matched config at `0df86dbdc` (#1585 — after the 3pt diagnostic instruments, before the first 3pt port; the test file is byte-identical to master's): **12.3652%**. Decomposition: `84ff51085`→`0df86dbdc` = **−0.0041pp** (#1536 J17 foul coupling, #1575/#1576/#1585 instruments, #1582 — ~0.2 seed-SD, i.e. neutral); `0df86dbdc`→`77b9be48b` = **+0.0490pp** to the 7-seed mean. The second window's only non-test `internal/sim` changes are `possession.go`/`transition.go`/`outcome.go`/`freeze.go` — exactly #1590 + #1593 (putback 3pt eligibility restore) and #1595 (transition `allow3pt=false` gate removal). #1594 touched only `internal/calibrate/standinregistry.go`, which `internal/sim` does not import. **So the entire gain is owned by faithful 5.60 ports and is legitimate under ADR-0090's faithfulness rule** (which forbids only gains from *unfaithful* mechanisms). Measurement-harness confound ruled out: `git diff 84ff51085..77b9be48b` on the test file is comments + one `t.Logf` band string, zero code change, and #1582's "seed-per-game" fix touched only `internal/calibrate` tests plus a docblock-only hunk in `sim/freeze.go`.

**⚠ Seed-SD correction — use 0.021pp, not 0.012pp, as the gate-1 noise floor.** ADR-0094 Phase 6 cites 0.012pp across 8 seeds; the 7 disjoint blocks here give **0.0210pp**. The two are statistically compatible at these n, BUT if Phase 6's 8 seeds were spaced <4 apart they shared draws (the exact overlap bug caught and discarded in this session), which would make 0.012 an underestimate. Prefer the larger, conservative value for any downstream seed-SD arithmetic. **✅ CONFIRMED 2026-07-24 — the conditional resolved YES.** Phase 6 swept seeds 20240601–**08** at the default `runs=4` (artifact header `stride=8 runs=4`), so adjacent blocks share 3 of 4 draws: its 8 readings are a sliding 4-wide window over just 11 distinct draws. Two compounding understatements — the ADR quoted the *population* SD 0.01189 rather than the sample SD 0.01271, and overlapping 4-windows deflate the sample SD ×0.781 vs disjoint blocks (400k-trial MC). De-overlapped, Phase 6's own data give **≈0.016pp**. ADR-0094 is stamped with a correcting § Addendum (2026-07-24); **both its verdicts stand** (Finding 5 is a PREFERENCE classification, which no SD magnitude touches), but its disposal margin narrows from 0.026pp to ~0.018pp, and the § Measurement currency claim that the seed SD is "unaffected by the confound" is corrected — true of the corpus/stride confound, false of block overlap. Do **not** merge 0.016 and 0.021: Phase 6 is all-era/stride-8, the 0.021 is matched recent-era/stride-1 — different estimands. **For recent-era gate-1 arithmetic keep using 0.021pp.** <!-- RETIRED-OK: this block IS the correction; 0.012pp/0.01189 appear here only as the retired values being replaced. -->

**NOT-A-LEVER corroboration:** the `84ff51085`→`0df86dbdc` window independently reproduces the prior "#1536 J17 foul coupling is gate-1-neutral" finding (−0.0041pp) on a *recent-era matched* corpus, where the earlier proof was all-era (−0.00146pp). Do not re-open foul coupling as a gate-1 lever.

**[SUPERSEDED 2026-07-24 by the block above — do NOT act on this paragraph]** **⚠ Gate-1 point estimate has MOVED on current master — flagged for clean re-measurement (2026-07-23, ADR-0094 § Measurement currency).** Distinct from residual (8)'s seed-artifact (which left ADR-0088/0090 unaffected), the default-seed `DRBPushSharePct` no longer reproduces the published 12.37% (`84ff51085`): on current master (`de3934d8d`+) it reads **11.81%** at stride 8 (87 snapshots / 71.7M poss) and ~12.27% at stride 100 (8 snapshots) — every reading *below* 12.37% but **configuration-sensitive** (~0.5pp stride swing) and **confounded** across engine #1536 (J17 foul coupling), archive-set drift (07-08 dir modified 2026-07-21; fewer than the published 97 snapshots), and stride/subsampling. It is therefore NOT a clean regression number and is **NOT substituted into criterion #2** — both ADR-0094 verdicts render against the published 12.37%. The *direction* (below 12.37, i.e. further from GO) is suggestive, not firm. **ADR-0088's headline 12.37% needs a matched-config re-measurement on current master (fixed archive + fixed stride) before it can be re-cited** — a separate task, not part of the criterion-#2 construction audit. The within-config seed SD is small and robust: **0.012pp** across 8 seeds (ADR-0094 Phase 6).

**SHIPPED sub-work** (PR numbers below; `git log --all --grep '#<PR>'` resolves each merged commit): step classes + half-court jitter + {3..23} redraw; the `FUN_004e4150` ratio retired (u=CEngine+0x38 binary-proved 0.0 → dead code, base_time a per-matchup constant); steal split (`stealTurnoverScale` 2.75e-5→1.69e-5 + non-arming `nonStealTurnover`, #1519) + faithful `shotValue2pt`/`shotValue3pt`; matchupQuality Phase-3 matched-defender term (+0xDC8, #1522) and non-matched `+0x350` term (#1527, J25); **FG% band CLOSED** via the `+0xD58` position-penalty base-minutes port (`penaltyBaseMinutes` = `dc>0?dc:MIN/GP`, #1544 — FG% 46.42→48.29% in [47.5,48.9], now a HARD assert); **§1d steal-gating partition** (#1547 — steal-armed possessions run the SAME single `transitionTriggers` gate as DRB via `gs.stealPushFired`; survivors merge into the code-7 `DRBPushClass`; ungated `StealClass` deleted → code-7 is now one band-comparable quantity on master).

**Open residuals (NOT the cut-over blocker):**
- **(7) 3P undershoot** vs the real-life 3P baseline — separate from gate-1 (missed shots rebound OFFENSIVELY, so FG% closure does NOT route through DREB arming). **✅ MAKE-RATE HALF CLOSED 2026-07-22 — it was a TEST-HARNESS ARTIFACT, not an engine defect.** The instrument's sim loop passed a **constant** `seed` to `SimulateWith` over *single-game* bundles, so every game restarted the PCG stream from state 0 and only ever drew overlapping **prefixes** of one fixed sequence; that prefix's bias was amplified rather than averaged away (measured 3pt make-roll mean **565.7** vs uniform 500.5). Fixing the seed to vary per game (`seed+gi`) collapses the make residual **9.10pp → −0.19pp**: engine 3P% **31.05% → 40.16%**, matching its own recon `E[sv]/1000` 39.97% and box 40.16% (made-vs-box gap 0.000pp). **Decisive proof it is the 40.16% that is correct, not merely less biased:** `P(make)` is *linear* in effective shot value, so for uniform independent rolls the realized rate is **forced** to equal `E[sv]/1000` — no distribution shape can bend it. 31.05% violated that identity, which is what identifies the estimator (not the model) as the error. **Consequence: the sign flips** — against the sco comparator 38.96%, the engine is now mildly *above* real (`pop_dpct` **+1.20pp**), not −7.92pp below, so "3P undershoot" is a misnomer for the make axis. **Both artifacts are committed, side by side, deliberately:** `calibration-5.60-20260721-3pt-undershoot.json` is preserved unchanged as the *contaminated* record (31.05% / dpct −7.92) that the superseded narrative below cites, and `calibration-5.60-20260722-3pt-undershoot.json` is the corrected post-seed-fix record (40.16% / dpct +1.20 **as first written**; that file has since been re-run and now holds the **post-putback-3pt-port** record — see the port block below). Do not "reconcile" them — the disagreement between the two files IS the finding. **The attempt-share half is now the WHOLE of residual (7), and it is roughly TWICE as large as previously recorded — ✅ adjudicated 2026-07-22.** The old headline (`dRate −2.41pp`, engine 16.47% vs sco 18.88%) was computed off the *contaminated* engine 3GA count. The seed artifact biased **both** halves, in **opposite** directions: it depressed the make rate (31.05% vs the true 40.16%) *and* inflated attempts (`engine_sum_3ga` **176,795 → 147,470** against sco's 202,086 — engine 3GA falls from 87.5% of real to **73.0%**). Corrected, `pop_drate_per100poss` goes **−1.93 → −4.16 per 100 possessions**. Both figures are already committed side by side in `calibration-5.60-20260721-…` (contaminated) and `calibration-5.60-20260722-…` (corrected); the corrected one shipped un-adjudicated and this entry is that adjudication, not a new measurement. The seed-clean `threept_attemptrouting_archive_test.go` agrees independently: sim **0.052** 3PA/min vs real **0.110** — the engine takes 3s at **47% of the real per-minute rate**, and the Kitagawa–Oaxaca split puts essentially all of it in the **per-player rate effect** (B −0.0582) with **minute allocation a non-factor** (A −0.0002). **Reframe: "3P undershoot" is real but it is entirely an ATTEMPT-RATE defect, not a make-rate one** — the engine shoots threes about as well as real players once it takes them, and simply does not take enough. Do not scope make-model work against it; scope the attempt basket (`+0xDB0` 3GA/48 feed and its `selectOutcome` consumer). Minute allocation is **NOT-A-LEVER** here (measured A ≈ −0.0002, two orders of magnitude below B). **Superseded — do NOT act on:** the prior "make residual is **entirely** the net + block modifier MEANS on 3pt attempts" direction, and the "re-RE the net feed if the net-mean is implausibly large" follow-on. Both were inferences from the biased 31.05%; there is no make-rate residual left for them to explain. Historical detail of the superseded decomposition follows for provenance only — **localisation instrument shipped** (`TestRealArchive_ThreePtUndershoot`, `//go:build archive`, recent-era 05-08 corpus, 97 snapshots) + committed decomposition artifact `engine/internal/validate/testdata/calibration-5.60-20260721-3pt-undershoot.json`. Authoritative gap decomposed: **dPct −7.92pp** make-rate (engine 3P% 31.05% vs sco 38.96%) + **dRate −2.41pp** attempt-share (engine 16.47% vs sco 18.88%) — corpus-dependent, larger than the prior ~2.8pp J3-corpus headline. Both FORMULAE RE'd **FAITHFUL** (attempt basket = faithful `+0xDB0` 3GA/48 + plain-sum `selectOutcome` consumer, no static 3pt coefficient; make-model `shotValue3pt` formula + d80 feed faithful, real-3GA-weighted base 38.99% ≈ sco 38.96%, confirming `ToBundle` wires faithful `D80`). **Confound-free cut (advisor 2026-07-21):** the sim-3GA-weighted d80 = **40.38%** (the base the sim actually shoots against) sits *above* the real-weighted 38.99% (routing signal −1.39pp), so **shot ROUTING is exonerated** — the sim mildly favors better shooters, not weaker ones. Realized 31.05% is therefore **−9.33pp below the sim's own faithful base** → the make residual is **entirely the net + block modifier MEANS on 3pt attempts**. (The pooled-2P% "shared-modifier" check is **confounded** — 2pt-only guaranteed/boosted makes + asymmetric d80/D64 bases — and did NOT discriminate; the sim-weighted-d80 cut is authoritative.) So the make FORMULA + feed are faithful and routing is exonerated, but the gap is **NOT a clean pure-sim-realized close: the net-advantage FEED magnitude and the block-mean on 3s are NOT yet re-excluded** — a static sign/scale defect there would produce exactly this. **Downgraded from "static-formula-pinnable" to "needs sim A/B on the modifier means + attempt routing, with the net-advantage feed still an OPEN static candidate."** **NOT-A-LEVER for the make-rate — do NOT re-chase shot composition / routing-to-weak-shooters:** measured-refuted, the sim-3GA-weighted d80 (40.38%) sits *above* the real-3GA-weighted d80 (38.99%) over 97 snapshots (routing signal −1.39pp — the sim mildly favors *better* shooters), so mis-routing 3PA toward low-d80 players is NOT the −9.33pp make drag; the 40.38%-above-38.99% cut restated in this sentence **is** the discriminating proof (the 2026-07-21 make-rate RE artifact it was originally cited to no longer exists on disk — dead pointer removed 2026-07-30). Follow-on: instrument realized net-mean + block-mean on 3pt attempts (split the −9.33pp), re-RE the net feed if the net-mean is implausibly large, and instrument the minutes/attempt-routing behind −2.41pp. (The two 2026-07-21 attempt-rate/make-rate RE artifacts this follow-on was cited to no longer exist on disk — dead pointers removed 2026-07-30; the live successors are the localization instrument and the −2.33pp re-measurement recorded below.) Fix is a follow-on plan. **Status — attempt-rate localization instrument SHIPPED 2026-07-22 (this PR):** the seed-clean per-decision instrument `TestRealArchive_ThreePtLocalize` (`//go:build archive`, recent-era 05-08 corpus, 98/98 snapshots valid) + committed artifact `engine/internal/validate/testdata/calibration-5.60-20260722-3pt-localize.json` partition the −0.058 rate effect across the four candidates (feed / denominator / gate-suppression / closure-residual) via a new accumulation-only `OutcomeDiagAccum` (byte-identical to `Simulate`; DRBPushSharePct untouched — `TestOutcomeDiagAccum_NonPerturbationAndReachability` + `TestOutcomeDiagAccum_DRBPushShareUntouched`). The run reproduces the adjudicated effect exactly: sim **0.05200** vs real **0.10990** 3PA/min, TotalGap **−0.05790** (vs the K-O rate effect B −0.0582). **Measured carrier: `inconclusive_d`** (ResidualFracOfGap **0.718** > 0.15; GateDeltaCExcess **−0.01107** = 19% of gap, DenomDeltaB **−0.00507** = 9%, FeedDeltaA **−0.00020** = 0.4%). No single named carrier clears the 60% dominance bar, so **closure did not resolve to a single carrier (ResidualFracOfGap 0.718 > 0.15) — deeper +0xDB0/selectOutcome RE scheduled as the follow-on.** **UNIT MISMATCH PINNED (2026-07-22) — decision-frequency localization retired.** The prior claim ("`DecRateDeltaE` −0.04146 matches `ClosureResidualD` to four decimals → localized to shot-DECISION-FREQUENCY") is an artifact of the wrong benchmark target. Verified at `engine/internal/calibrate/threept_localize_archive_test.go:184-232`: `real3GAByPID`/`realMINByPID`/`realFGAByPID` populate from `p.RealLife3GA`/`p.RealLifeMIN`/`p.RealLifeFGA` — `.plr` real-life NBA stats, not `.sco`; inner-join basis (simMIN>0 AND realMIN>0) deliberate and documented in-code (all-players 0.076 → rotation-only 0.110 3PA/min). `real_pa_per_min = 0.1099` benchmarks **engine-vs-reality**, not engine-vs-sco. `total_gap` −0.0579 conflates (engine↔jsb) with (jsb↔reality); `dec_rate_delta_e` −0.0415 is largely a cross-reference artifact. Corroboration: `ideal_pa_per_min` 0.0684 ≈ sco's own ~0.072 3PA/min. **Correct engine-vs-sco measurement — PRE-PORT BASELINE** (97 recent-era snapshots, games_cap 60, stride 1, seed 20240601). Provenance note: this table was read off `calibration-5.60-20260722-3pt-undershoot.json` when that file held the pre-port engine; the file now tracks the **live (ported)** engine, so the counts below live in git history at `origin/master`. The 98-snapshot re-run of the same pre-port behavior is committed as `...-3pt-undershoot-ab-suppress.json` and reproduces the share (14.424%) but not the raw counts (98 vs 97 snapshots):

| | engine | sco | ratio |
|---|---|---|---|
| 3GA | 147,470 | 202,086 | **72.97%** |
| 2GA | 875,496 | 868,372 | **100.82%** |
| FGA total | 1,022,966 | 1,070,458 | 95.56% |
| 3PA share of FGA | **14.42%** | **18.88%** | **−4.46pp** **[SUPERSEDED 2026-07-24 — measured −2.33pp; see J24 re-measurement block]** |

**Conclusion: total shot volume at near-parity; defect is 3pt-SHARE-specific, not a shot-decision-frequency deficit.** Live levers were: gate/eligibility suppression (`SuppressionFrac` **0.237** vs ~**0.09** real fast-break reference; `GateDeltaCExcess` −0.01107, 19% of gap) and 3pt-share denominator (`MeanThreeShareFull` **0.179** vs `RealShareFGA` **0.196**; `DenomDeltaB` −0.00507, 9% of gap).

**❌ GATE/ELIGIBILITY LEVER RE-ADJUDICATED 2026-07-23 — the 2026-07-22 removal was itself the misread; the OReb putback-3pt gate IS faithful and is RESTORED.** The 2026-07-22 paragraph below correctly showed that ADR-0055's *citation* for divergence (2) was bad — `jsb560_decompiled.c:94022-94024` is the OReb loop-back, not a 3pt→2pt re-roll. It then wrongly concluded that `FUN_004e1ba0`'s reject-retry has **no OReb branch**. It has one: the reject-retry's *first* clause is it. The error is an argument-binding misread — Ghidra declares `__thiscall FUN_004e1ba0(int,int,char,int,int,double,int,char)` and **`param_6` is a `double`**, consuming two stack slots, so the naive slot→param mapping comes out one short. Derived from the pushes at the selector's sole call site `0x4d8f61-0x4d8f82`: `param_3` = `edi` = `[esp+0x18]` = **`local_15c`** (the OReb continuation flag), `param_7` = `local_154` (the coach play call), `param_8` = `[esp+0x54]` = `local_120` (shot clock). `[esp+0x18]` is pinned as `local_15c` independently by `0x4d8f1d-0x4d8f2c` (`mov al,[esp+0x18]; cmp al,1; jne; mov byte [esp+0x54],0`) — exactly the decompile's `if ((char)local_15c == '\x01') { local_120 = local_120 & 0xffffff00; }`. So `:97195`'s `(param_3 == '\x01') && ((iVar8 == 2 || (iVar8 == 4)))` **rejects outcomes 2 (3pt) and 4 (foul) on an OReb continuation** — a putback is restricted to `{1,3}` and is never 3pt, as ADR-0055 originally concluded. The 2026-07-22 “positive counter-evidence” inverts too: the shot-clock **clear** at `0x4d8f2c` is a **livelock guard** (with `param_8 == 1` forcing `{2,4}` and `param_3 == 1` rejecting `{2,4}`, every outcome would be rejected and the retry loop would never terminate), not a widened bucket set. Restored: `threePtW = 0` returns to `possession.go`'s half-court trip loop as the **default**. The A/B knob survives — its decoupling of 3pt eligibility from the putback make-value was genuinely useful — renamed and re-polarised to **inverted-polarity** `FreezeConfig.UnfaithfulPutback3pt` (default `false` = faithful = suppressed), matching the `UnfaithfulOreb` convention. `UnfaithfulPutback` is unchanged and still gates the make-value site only. Adjudication + full disassembly citations (incl. the byte-level proof that `0x4d8f82` is the **only** reference to `0x4e1ba0` image-wide): `jsb-native/re-artifacts/jsb-J24-transition-3pt-ADJUDICATION-20260723.md`. **Consequence for residual (7): the attempt-share gap is LARGER than the recorded −2.76pp**, and the +1.70pp in the superseded table is a calibration gain from an **unfaithful** mechanism — exactly what ADR-0090 forbids as a basis for shipping. The measurement is not disputed; its interpretation is reversed. Superseded record follows — do NOT act on it.

**[SUPERSEDED 2026-07-23 — do NOT act on this paragraph]** **✅ GATE/ELIGIBILITY LEVER PARTIALLY CLOSED 2026-07-22 — the OReb putback-3pt gate was UNFAITHFUL and is REMOVED (mechanism port, not a tune).** ADR-0055 divergence (2) ("5.60 re-loops a 3pt outcome on the OReb flag, forcing a 2pt — putbacks are never 3pt", cited to `jsb560_decompiled.c:94022-94024`) is a **decompile misread**, now reversed. `local_15c` is the OReb **continuation** flag — the condition of the possession `do{...}while` at `:94379`, assigned from the rebound routine `FUN_004d6f00` one line above the cited `goto` (`:94019`) — so `LAB_004dadd9` (`:94375`) is the **loop-bottom** label and that jump runs the next possession iteration *after* the shot is already resolved. It is not a 3pt→2pt re-roll. Positive counter-evidence: 5.60 sets the shot-clock restriction only when `local_15c == '\0'` (`:93251-93253`) and **clears** it on a continuation (`:93278-93280`), so an OReb putback gets the **full four-bucket set**; and `FUN_004e1ba0`'s reject-retry (`:97194-97196`) is exhaustive with **no OReb branch** and nothing zeroing `local_8c` (the `+0xDB0` 3pt bucket weight). Port: the `threePtW = 0` zero in `possession.go`'s half-court trip loop is deleted; the old behavior survives only behind a new **normal-polarity** `FreezeConfig.SuppressPutback3pt` (default `false` = live) used by the A/B. `UnfaithfulPutback` keeps inverted polarity and now gates the **make-value site only** — decoupled deliberately so the 3PA attribution is clean (the `suppress` arm reproduces the shipped engine's 14.424% exactly, proving the make-value site contributes **nothing** to 3PA). Measured, recent-era 05-08, 98 snapshots, seed 20240601, games_cap 60 (`engine/internal/calibrate/threept_undershoot_archive_test.go`, arm via `JSB_3PT_AB`):

| arm | 3PA share of FGA | dRate/100poss | engine 3P% |
|---|---|---|---|
| `suppress` (pre-port baseline) | 14.424% | −4.165 | 40.12 |
| `""` (ported, live) | **16.125%** | **−2.502** | 39.70 |
| sco target | 18.888% | 0 | 38.95 |

**+1.70pp of the −4.46pp closed (38%); `dRate/100poss` 40% closed.** 3P% moves *toward* sco as well (dPct +1.17pp → +0.75pp), so this is not trading attempts for accuracy. Not over-firing: ORB lands at **+0.47%** vs sco (pre-port −0.36%) and TOV/poss are ~neutral. **Disclosed cost: FTA worsens −19.65% → −21.83%** **[SUPERSEDED 2026-07-24 — current −21.27%; engine 152,874 / sco 194,185, sha 575c52350, 99 snapshots]** — putback 3s replace putback 2s and draw fewer shooting fouls, against an already-open ~−20% FTA gap. Per ADR-0090 that is not a reason to re-add an unfaithful gate; it is a real cost of a faithful mechanism, filed against the separate FTA gap. Artifacts (all 98-snapshot, same corpus/seed): `engine/internal/validate/testdata/calibration-5.60-20260722-3pt-undershoot.json` (ported, live — this file was re-run in place and no longer holds the pre-port record) + `...-3pt-undershoot-ab-suppress.json` (pre-port baseline, isolated) + `...-3pt-undershoot-ab-putback.json` (the entangled `UnfaithfulPutback` arm from the first pass, kept as the decoupling record: 3GA 168,610 vs the ported 167,450 — the make-value site moves 3PA by 0.7%, second-order via rebound feedback only); full trace `jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md`; ADR-0055 carries the correction. Side effects on pinned tests were re-baselined with dated rationale, not suppressed: `origin_share_pin_test.go` (oreb share 0.137482 → 0.165285 — putback 3s convert lower, so more putback misses feed further continuations) and `golden.json` (regenerated with `-update`, the sanctioned mechanism).

**Residual after the 2026-07-23 restore — the 2026-07-23 PREDICTION IS RETIRED.** [SUPERSEDED 2026-07-24 — it predicted a residual LARGER than the −2.76pp recorded on 2026-07-22, "back toward −4.46pp"; measured **−2.33pp**, i.e. SMALLER.] The prediction was correct in sign but missed that the transition `allow3pt=false→true` port (+2.08pp forward) was already live in the same codebase; reconciliation: −4.46 + 2.08 = **−2.38pp expected** vs **−2.33pp measured**. **RE-MEASURED 2026-07-24 (sha 575c52350, stride 1, 99 snapshots, games_cap 60, seed 20240601):** 3GA 173,698/206,962 = **83.93%**; 2GA 875,093/888,270 = **98.52%**; 3PA share engine/sco **16.56%/18.90%** → **−2.33pp**. Make-rate **CLOSED** to 0.005pp (recon/roll/box agree; engine 39.97% / sco 38.94%). Engine **overshoots sco on 3P% by +1.03pp** (unexpected sign pattern for a 3PA undershoot — instrument selected **INSPECT**, NOT Branch-A/Branch-B; do NOT upgrade to a conclusion). FTA: engine 152,874 / sco 194,185 = **−21.27%** (see J27). **New leading open sub-lever — the TRANSITION gate.** The same argument-binding derivation that reinstated the putback gate also refutes the 2026-07-22 claim that transition `allow3pt=false` is faithful. `param_7` is `local_154` = `CEngine+0x63ac`, the **interactive coach play call** — written by exactly two sites in the image: `FUN_00435120` @ 0x435120, an `LB_GETCURSEL` handler on `CBBallCoachView` (strings @ 0x2aa698: `Post`/`Drive`/`Outside`/`Three`/`Auto`; index 1 → 5 = “Three”, no selection/`LB_ERR` → 6 = “Auto”), and a constructor at `0x4cf5bd` writing constant 1. It is **not** a fast-break code; the fast break is `local_c4 == 7`, a different variable never passed to the selector. Under `Sim Game` `local_154` is 6, and no re-pick site writes 5, so `(param_7 == 5 && iVar8 == 2)` at `:97195` is **unreachable**. A code-7 possession skips `FUN_004e04e0` and then runs the standard four-bucket selector with `local_154` re-picked into {1,2,3} and the ballhandler's own `+0xdb0` 3pt composite unmodified — **3PA fully eligible on a fast break**. No other code-7 suppression exists (complete `local_c4` census: the arm, the `0x4d8758` gate, a post-selection shot-*difficulty* branch at `0x4d9320`, bookkeeping at `0x4dae10`). So `transition.go`'s `allow3pt=false` / `threePtWeight: 0` has **no 5.60 warrant** and is a live unfaithful carrier. Measured ceiling from the existing instrument (`calibration-5.60-20260722-3pt-localize.json`): `gate_delta_c_raw` **−0.01783** of `total_gap` −0.05790 = **30.8%** of the gap. **CLOSED 2026-07-23 (this PR) — faithful port: `allow3pt=true`.** Measured via A/B (`SuppressTransition3pt`): transition gate contributed **+2.08pp 3PA/100poss** (suppress arm 13.78% → default arm 15.86%; `gate_delta_c_raw` −0.01783→−0.00918, `SuppressionFrac` 0.237→0.1217). **FTA side-effect: −0.26 FTA/100poss** — transition 3pt attempts replace transition 2pt attempts, drawing fewer shooting fouls; per ADR-0090 this is a faithful mechanism's downstream cost, not grounds to restore the gate; disclosed in PR. Updated artifacts: `calibration-5.60-20260722-3pt-undershoot.json` (live post-port engine) + `calibration-5.60-20260722-3pt-localize.json`. A/B arm: `JSB_3PT_AB=suppress_transition` → `SuppressTransition3pt=true` → 14.42% 3PA/FGA (pre-port baseline, ±0.1pp confirmed). Proof: `jsb-native/re-artifacts/jsb-J24-transition-3pt-ADJUDICATION-20260723.md`. **Denominator Σ-basket + +0xDB0 3pt-numerator magnitude RE'd NOT-A-LEVER 2026-07-23** (🔮 Fable asm RE, `jsb-native/re-artifacts/jsb-J24-3pt-denom-RE-20260723.md`): the 5.60 selector's denominator is the same 4-bucket Σ (`MeanThreeShareFull` 0.1776 is the correct analog; `MeanThreeShare2` 0.1888 has no 5.60 counterpart), NOT smaller than the engine's, and the +0xDB0 3pt weight reaches the selector raw (player-index passed, not a weight; copy-ctor verbatim; slot 0xe10 never written in the selector). **No faithful lever is CONFIRMED for the remaining −0.05004 share gap.** Since 3pt-share = `w2/(w1+w2+w3+w4)` with BOTH the +0xDB0 numerator (w2) and the 4-bucket basket faithful, the engine's `MeanThreeShareFull` 0.1776 **IS** 5.60's own share — so unless engine w1/w3/w4 (2pt/and-one/foul) VALUE stand-ins diverge from 5.60, the 0.1959-vs-0.1776 undershoot is **INTRINSIC to 5.60** and residual (7) has no faithful lever at all. Whether such a w1/w3/w4 divergence exists is **UNVERIFIED** — this RE did NOT target it; its incidental §3 divergences are **inert** (hca leg ×`[esi+0x18b58]`=0 in IBL) or **negligible** (and-one floor). So w1/w3/w4 bucket-VALUE fidelity is a **by-elimination candidate, NOT a confirmed lever**; pick the next lever from the leverage report (`ls -t jsb-native/re-artifacts/leverage-*.txt`), not this reasoning. Discovered follow-on — **PORTED 2026-07-26 (#1675, `jsb-shotclock-w4-foul-rescale`):** the shot-clock `w4 ← w4·w2/w1` foul rescale (@0x4e1e93–0x4e1ebf), mode-scoped off the default half-court draw, now lives in `selectOutcome` (shot-clock gated) behind the inverted-polarity `SuppressW4Rescale` A/B arm; golden unchanged (the single fixture game hits no flipping shot-clock draw), 64/500 richBundle games move under the rescale. **RESOLVED 2026-07-25 — time-axis bookkeeping error, not a real instrument disagreement.** The 2026-07-24 note compared the routing instrument against the undershoot instrument using different estimands and references, and anchored "no movement" to the PRE-port routing baseline (2026-07-22) instead of the POST-port one (2026-07-23).

Three-date routing record (artifacts `calibration-5.60-20260722/23/24-3pt-attemptrouting.json`):

| Date | snaps | sim_pa_per_min | total_gap | Notes |
|------|------:|---------------:|----------:|-------|
| 2026-07-22 | 98 | 0.051997 | −0.057899 | PRE transition-port baseline |
| 2026-07-23 | 98 | 0.059905 | −0.050039 | POST port (+15.21% sim rate) |
| 2026-07-24 | 99 | 0.059958 | −0.050047 | No engine sim commit (CI chore only) |

07-23→07-24 is flat because `git log --oneline 575c52350..HEAD -- engine/` returns exactly one commit (`a7e53c97d` — CI-path chore `#1671`). Corpus went 98→99 snapshots; that is the only change. **Cross-instrument corroboration:** routing +15.21% (sim 0.051997→0.059905) vs undershoot +15.02% (3GA 72.97%→83.93% of sco). 0.19pp apart on a 15% move — convergence, not discrepancy.

**Benchmark trap — `real_pa_per_min` 0.1100 is engine-vs-REALITY, not engine-vs-sco** (`.plr` real-life NBA feed, same wrong reference the unit-mismatch finding retired on 2026-07-22). The engine-vs-sco comparator in the same artifact is `ideal_pa_per_min` 0.0687 (≈ sco's ~0.072 3PA/min); engine 0.0600 is ~87% of it, consistent with the undershoot instrument's 84%. Do NOT anchor routing-gap reasoning to `real_pa_per_min` 0.1100.

**INTRINSIC hypothesis — DEMOTED to UNSUPPORTED.** Its prior support rested on the routing instrument's un-moved −0.05004, which was a PRE-port baseline misread. The hypothesis is not refuted; it must be re-argued on the share figures alone: engine `MeanThreeShareFull` 0.1776 vs `real_share_fga` 0.1959. The w1/w3/w4 bucket-VALUE divergence remains an UNVERIFIED by-elimination candidate.

**Standing prohibition LIFTED.** residual (7) stays ◑ Partial (denominator + numerator closed; no confirmed remaining lever; INTRINSIC hypothesis DEMOTED — pending re-argument on 0.1776 vs 0.1959).
- **(6) permanent `.plb dc_minutes` wiring** into the archive test's minutes input (MPG-fallback 48.29 → exact faithful 48.49; deferred only because the `.plb` reader isn't production-tested yet).
- **(3) CEngine+0x30 forced-redraw READER** — the writer is found (J17b #1536: three `movb $imm8,0x30(esi)` sites); the reader is pace territory, port it WITH the writer, not alone.
- **(5) `baseTimeMid` walkback 17.7→16.0** — **✅ DONE 2026-07-24 (J25).** Shipped ahead of the share close, on FAITHFULNESS grounds alone rather than on a pace target: with `u = CEngine+0x38 = 0.0` the `FUN_004e4150` composite `base_time` ratio is dead code, so `base_time` collapses to the constant ceiling of the faithful [13,16] band — `baseTimeMid` coincides with `baseTimeHigh` **by construction**, which is the u=0 proof's conclusion, not a coincidence. The original "once the share closes" precondition was never a fidelity requirement; it was a pace-calibration one, and it is **superseded by ADR-0085** (fidelity over calibration fit). The fast-class arming-share gap is **still open** and this constant is **not** a lever on it — see the NOT-A-LEVER entry below, which stands unchanged. Re-baselined with it: the two `possession_pace_pin_test.go` characterization pins (Pin A step-mean 17.46→15.8072, support [3,27]→[3,24], pre-redraw floor 9→8, drained bucket 17→16; Pin B per-team possessions 97.9000→109.0000), the `steal_transition_test.go` step-range unions, the `base_time_mid` stand-in sweep bracket, and the golden snapshot. All GATE-class assertions held **unchanged** (total possessions/game [160,300], per-team ratio ≤1.25, injuries/game [0.15,0.30], non-degeneracy, the 90% coverage floor). Archive coupling figures at 16.0 are **pending re-measurement** — no valid post-2026-07-22 corrected reading exists at the live center and none was invented.
- **(8) Constant-seed harness artifact in the sibling archive tests** — **✅ FIXED + RE-MEASURED 2026-07-22** (surfaced the same day by the residual-(7) close). Both sites now vary the seed per game (`possession_archive_test.go` `20240601+uint64(gi)`, `threept_attemptrouting_archive_test.go` `seed+uint64(gi)`). Measured A/B, same corpus and caps, only the seed expression changed:

  | Quantity | Constant seed | Per-game seed | sco (real) |
  |---|---:|---:|---:|
  | `PossessionAccounting` 3PT% | 29.6% | **38.3%** | 37.9% |
  | `PossessionAccounting` 2PT% | 54.0% | 54.0% | 50.4% |
  | `PossessionAccounting` POSS/team | 95.5 | **92.5** | 107.5 |
  | `PossessionAccounting` dPOSS | −12.0 | **−15.0** | — |
  | `PossessionAccounting` DRB/team | 30.3 | **27.2** | 34.7 |
  | `AttemptRouting` sim 3PA/min | 0.062302 | **0.051997** | 0.109896 |
  | `AttemptRouting` total gap | −0.047754 | **−0.057899** | — |
  | `AttemptRouting` rate-effect (B) | −0.046966 | **−0.058213** | — |
  | `AttemptRouting` minute-effect (A) | −0.000816 | −0.000203 | — |

  **Independent corroboration of the residual-(7) make-rate close:** `PossessionAccounting` is a *different* test on a *different* sample (13 zips, not the 97-snapshot recent-era corpus) and it moves 3PT% 29.6% → 38.3% against sco 37.9% — engine now **+0.4pp above** real, agreeing in both sign and magnitude with the undershoot test's independent +1.20pp. Two unrelated harnesses reaching the same corrected value is much stronger evidence than either alone. **2PT% did not move at all** (54.0% both), which matches the earlier control triple (3pt shared-stream roll mean 565.7 vs 2pt 510.7): the over-sampled prefix happened to bias the 3pt roll positions hard and the 2pt ones barely. That asymmetry is itself a signature of the artifact and would be inexplicable under any real engine defect.

  **Consequence — the pace/possession figures were contaminated too, and are WORSE than recorded.** `dPOSS` moves −12.0 → **−15.0** and DRB/team 30.3 → **27.2** (vs real 34.7). Any residual-#4 (`base_time` pace) reasoning derived from this test's pre-2026-07-22 numbers is understated and must be re-derived from the corrected column. Tier: ⚙️ Sonnet for the edits, 🧠 Opus to adjudicate the re-measured numbers. Two diagnostics still loop over *single-game* bundles with a fixed seed, so each game replays an overlapping prefix of one PCG stream and every rate they assert is biased by that prefix (not just 3pt): `internal/calibrate/possession_archive_test.go:141` (`sim.Simulate(sub, 20240601)`) and `internal/calibrate/threept_attemptrouting_archive_test.go:119` (`sim.Simulate(sub, seed)`). Fix is the same one-liner — vary the seed per game (`seed+uint64(gi)`) — but each fix must be **re-measured**, since it moves every number those tests report; the attempt-share `dRate −2.41pp` in residual (7) is the headline figure to re-derive. **Blast radius is bounded and no SHIPPED conclusion is invalidated:** the ADR-0088 arming-share figure comes from `TestFastClassArmingShareBaseline` (`internal/sim/fastclass_share_archive_test.go:96`), which calls `SimulateWith(b, seed+uint64(run), …)` — the **whole** bundle `b`, not a single-game `sub`, *and* a per-run seed. Clean on both counts, so **ADR-0088 and ADR-0090 are unaffected**. Same for `internal/calibrate/research.go:173` (`SimulateWith(b, seed+uint64(run), …)`) and `internal/calibrate/freeze.go:317` (`SimulateWith(b, baseSeed+uint64(r), opts)`) — both whole-bundle **and** per-run seed. (An earlier revision of this entry cited `internal/sim/freeze.go:317`; that was a wrong package prefix — `internal/sim/freeze.go` contains no `Simulate` call site at all. Corrected 2026-07-22 by a repo-wide census of every `Simulate`/`SimulateWith` call, which found exactly the two dirty sites named above and no others. `internal/validate/harness.go:440` is worth noting explicitly: it *does* use a single-game `sub`, but varies the seed per run (`baseSeed+uint64(run)`), so the main validation harness is clean.) The bug needs *both* halves (single-game bundle **and** fixed seed), which is why it is confined to the two inline diagnostic loops above. Each of these was read directly — not inferred from a sibling. Tier: ⚙️ Sonnet for the edits, 🧠 Opus to adjudicate the re-measured numbers.

**Do NOT re-open — settled / NOT-A-LEVER (each with its discriminating proof).** *(The "< 12.94 floor" comparisons in the fatigue-sub bullet reference the superseded all-era 209.2 denomination; re-denominated to 216.58 the floor is the recent drift band [11.97, 12.54] (ADR-0088), but each lever's conclusion — it cannot move `DRBPushSharePct` — is denominator-independent and STANDS. The bullets below are preserved verbatim as historical proof.)*
- **OReb putback 3pt suppression IS a 5.60 mechanism — keep it; do NOT remove it again** (2026-07-23, reversing the 2026-07-22 bullet that said the opposite). Discriminating proof is an argument-binding derivation from the disassembly, not a fit: `FUN_004e1ba0`'s `param_6` is a **`double`** (two stack slots), so the decompile's parameter numbering was misbound. From the pushes at the sole call site `0x4d8f61-0x4d8f82`, `param_3` = `edi` = `[esp+0x18]` = `local_15c` (the OReb continuation flag, pinned independently by the shot-clock clear at `0x4d8f1d-0x4d8f2c`), so the reject-retry's first clause at `:97195` rejects outcomes 2 and 4 on a continuation — putbacks are restricted to `{1,3}`. The 2026-07-22 counter-evidence inverts: the shot-clock clear is a **livelock guard** (`param_8==1` forces `{2,4}`, `param_3==1` rejects `{2,4}`), not a widened bucket set. The +1.70pp 3PA-share gain from removing it is real but is a calibration gain from an unfaithful mechanism (ADR-0090) — **not** grounds to remove it again. `jsb-native/re-artifacts/jsb-J24-transition-3pt-ADJUDICATION-20260723.md`; superseded: `jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md`.
- **Transition `allow3pt=false` gate — CLOSED 2026-07-23 (this PR); do NOT re-add** — the gate had no 5.60 warrant (proof: `jsb-native/re-artifacts/jsb-J24-transition-3pt-ADJUDICATION-20260723.md`). A/B measurement (`SuppressTransition3pt`) confirmed contribution: +2.08pp 3PA/100poss (`gate_delta_c_raw` −0.01783→−0.00918, `SuppressionFrac` 0.237→0.1217). `allow3pt=true` is the faithful behavior; restoring `allow3pt=false` would be an ADR-0090 violation (unfaithful suppression). FTA side-effect −0.26/100poss is a faithful mechanism's downstream cost — not grounds to re-gate. `SuppressTransition3pt=true` in `FreezeConfig` is the A/B arm preserved for measurement, not a re-gate path.
- **Fast-break forced-3pt override (`local_15d` / `:93281-93283`) is INERT — do NOT port** (2026-07-23). Discriminating proof is an exhaustive write census: the override fires only when `local_154 == 5`, and `local_154` (`CEngine+0x63ac`) has exactly two writers in the image — `FUN_00435120` @ 0x435120, an `LB_GETCURSEL` handler on the interactive `CBBallCoachView` listbox (index 1 → 5 = “Three”; no selection/`LB_ERR` → 6 = “Auto”), and a constructor at `0x4cf5bd` writing constant 1. IBL runs `Sim Game`, so `local_154` inits to 6 and no re-pick site (`4d8bbc/4d8bd4/4d8be2`, `4d8e6c/4d8e7c/4d8e8a`) writes 5 — 5 never reaches `0x4d8f48`. `local_15d` itself is `FUN_004e04e0`'s play-success flag (only writes `4e1a9d`→0, `4e1aa7`→1) and stays 0 on code-7 possessions because the `0x4d8758` gate skips that call. Effect on 3PA share: **0**. Ghidra's `:96874-96882` render of the set site is a **misread** (asm `4e1514` targets `param_8`, not `param_6`) — do not re-derive from the C alone. `jsb-native/re-artifacts/jsb-J24-local15d-forced3pt-RE-20260722.md`.
- **3pt make model — net-mean / block-mean / `shotValue3pt` / fatigue / tail-clamp are NOT levers** (2026-07-22). There is no make-rate residual for them to move: with a per-game seed the engine realizes 40.16% against its own `E[sv]/1000` of 39.97% (−0.19pp). Discriminating proof is an *identity*, not a fit — `P(make)` is linear in effective shot value, so any unbiased-roll engine must realize exactly `E[sv]/1000` regardless of the modifier distribution's shape. Individually refuted at the real `rollMake` call site over the recent-era corpus: tail clamp (clamp_loss 0.000 / clamp_gain 0.002, **zero** attempts at sv≥1000), box crediting (made-vs-box gap 0.000pp), fatigue (mean 1.0000 — `fatigueFactor` caps at 1.0 and stamina never drives it below), and sv reconstruction (recon−sv gap 0.000pp). The old −9.33pp "modifier means" target was an artifact of the constant-seed harness (residual (7)). Do NOT re-open the net-advantage feed **on 3pt make-rate grounds**; a net-feed defect may still exist for other reasons, but this metric cannot evidence it.
- **3pt ATTEMPT-rate — the real-3PA feed into the bundle is NOT a lever** (2026-07-22). Refuted on its own explicit discriminator, not merely by falling under a dominance bar: the sim-minute-weighted rate implied by what the bundle actually feeds is **0.10969** 3PA/min against the real basis **0.10990** — a **0.2%** agreement, `FeedDeltaA` **−0.00020**, i.e. **0.4%** of the −0.05790 gap — and the stand-in path that could have diluted it does not exist in this corpus at all (`PctRealMinZeroDecisions` **0.00%**: zero shot decisions taken by a player with `RealLifeMIN == 0`). So the players the engine actually plays are fed real-life 3PA volumes that reproduce the real attempt rate to within a fifth of a percent; the −0.058 3PA/min shortfall is manufactured **downstream of the feed**, inside the sim. Do NOT re-open `RealLife3GA` ingestion, per-player rate feeding, or roster/stand-in substitution **on 3pt attempt-rate grounds**. Measured by `TestRealArchive_ThreePtLocalize` over 98/98 recent-era 05-08 snapshots; artifact `engine/internal/validate/testdata/calibration-5.60-20260722-3pt-localize.json`. **Scope — this bullet covers the FEED ONLY.** The other two named carriers are deliberately NOT listed here and are NOT refuted: gate/eligibility suppression is *material* (`SuppressionFrac` **0.237** vs the ~**0.09** real fast-break reference — the sim suppresses ~2.6× more than real; `GateDeltaCExcess` −0.01107, 19% of gap) and the 3pt-share denominator is *live* (`MeanThreeShareFull` **0.179** vs `RealShareFGA` **0.196**; `DenomDeltaB` −0.00507, 9% of gap). Both remained open levers — their absence from this list was conservatism, not clearance. **Update 2026-07-22:** the gate/eligibility lever is now **partially resolved** — its OReb half was an unfaithful engine-side gate, removed. **Update 2026-07-23:** the transition half is also **CLOSED** — `allow3pt=true` is the faithful behavior (this PR); `SuppressionFrac` 0.237→0.1217 (residual = faithful OReb putback + bucket-weight differences on the break). **Update 2026-07-23 (denominator RE):** the **denominator Σ-basket is NOT-A-LEVER** AND the **+0xDB0 3pt-numerator magnitude is faithful** — both byte-cited by 🔮 Fable asm RE (`jsb-native/re-artifacts/jsb-J24-3pt-denom-RE-20260723.md`; see the dedicated NOT-A-LEVER bullet below). **No faithful lever is CONFIRMED for the remaining −0.05004 gap:** with w2 and the basket faithful, the engine's 0.1776 IS 5.60's own share, so the 0.1959 undershoot is **INTRINSIC to 5.60** unless engine w1/w3/w4 VALUE stand-ins diverge — which is **UNVERIFIED** (this RE did not target it; its incidental §3 divergences are inert/negligible in IBL). w1/w3/w4 bucket-VALUE fidelity is a by-elimination candidate, not a confirmed lever.
- **3pt-share DENOMINATOR (Σ-basket composition) + the +0xDB0 3pt-numerator MAGNITUDE are NOT levers** (2026-07-23, 🔮 Fable asm RE). Discriminating proof is byte-cited static disassembly of `FUN_004e1ba0`, not a fit: (a) the 5.60 selector's per-draw denominator is the sum of the **same four buckets** the Go `selectOutcome` sums — `Σ = w1(+0xd90,2pt) + w2(+0xdb0,3pt) + w3(and-one) + w4(foul)` @0x4e1ec3–0x4e1ed9, exactly four terms — so `MeanThreeShareFull` (0.1776, 4-bucket) is the correct engine analog and `MeanThreeShare2` (0.1888, 2-bucket) has **no** 5.60 counterpart; the 5.60 denominator is NOT smaller than the engine's, so basket composition cannot be raised to close the 0.1959 gap. The reject-retry ≡ up-front-restriction equivalence claim (`outcome.go:67-73`) is **VERIFIED** (all reject edges re-enter after the Σ store @0x4e1ee1, redrawing over a fixed Σ = renormalization over the accepted subset; termination guaranteed by the floors). (b) The `+0xdb0` 3pt weight reaches the selector RAW — the caller passes a player INDEX not a weight (×0xe28 stride @0x4e1bdc), the copy-ctor copies +0xdb0 verbatim (@0x40cda2), and slot 0xe10 is never written in the selector body; `threePtBucketWeight` = `per48Min(3GA,MIN)` unscaled is exactly what 5.60 consumes. So if the remaining −0.05004 share gap has any faithful lever it can only be **w1/w3/w4 bucket-VALUE fidelity** (2pt/and-one/foul stand-in magnitudes) — but this RE did NOT establish that engine w1/w3/w4 diverge from 5.60 (its incidental §3 divergences are **inert** — hca leg ×`[esi+0x18b58]`=0 in IBL — or **negligible** — and-one floor). If w1/w3/w4 are also faithful, the engine's 0.1776 IS 5.60's own share and the 0.1959 undershoot is **INTRINSIC to 5.60** (no faithful lever exists). The w1/w3/w4 direction is therefore an **UNVERIFIED by-elimination candidate, not a confirmed lever.** `jsb-native/re-artifacts/jsb-J24-3pt-denom-RE-20260723.md`. **Discovered follow-on — PORTED 2026-07-26 (#1675, `jsb-shotclock-w4-foul-rescale`):** the `param_8` shot-clock branch rescales the FOUL bucket `w4 ← w4·(w2/w1)` @0x4e1e93–0x4e1ebf before the {3pt,foul} redraw — now replicated in `selectOutcome` (shot-clock gated, applied when `in.threePtWeight>0 && in.twoPtWeight>0`; the `twoPtWeight>0` term is a deliberate Go divergence from 5.60's `w2>0`-only gate, guarding the x87 `fdiv` +Inf→NaN path when `twoPtBucketWeight` returns 0). A mode-scoped foul-weight change confined to clock-expiry/`lateGameForcing` windows that does NOT touch the default half-court draw the calibration baseline measures. Inverted-polarity `SuppressW4Rescale` A/B arm (`JSB_3PT_AB=suppress_w4_rescale`) restores the pre-port no-rescale baseline (3pt share `w2/(w2+w4)`). Golden unchanged (single fixture game hits no flipping shot-clock draw); 64/500 richBundle games move under the rescale.
- **+0x4be4 fast-break outlet pick** is byte-exact uniform over the on-court five and TransOff-blind (draw reads only const 5; TransOff read AFTER the pick, only in the gate) → the non-uniform-pick lever (branch (a)) is REFUTED in either +1/−1 sign; the +0.200 mean-picked-TO gap is minute-allocation only. `jsb-native/re-artifacts/jsb-J24-4be4-pick-disambig-RE-20260720.md`.
- **Fatigue-substitution energy-threshold tuning (minute-allocation lever B via sub *timing*)** cannot close gate-1 — the no-fatigue-sub *ceiling* is the hard max of on-court-`TransOff` (`E_time`), and it yields `DRBPushSharePct` = **12.61%**, still 0.33pp UNDER the 12.94 floor. Measured ceiling-first (`JSB_FATIGUE_ENERGY_THRESHOLD` sweep, recent-era, ~32M armed poss, reverted): thr 0 → 12.37% / `E_time` 5.870; no-sub ceiling → 12.61% / `E_time` 5.994 (≈ RE 5.984; displaced 30.5% ≈ RE 30.4%). The *most*-subbing config has the LOWEST `E_time`, so no-sub MAXIMIZES `E_time` ⇒ 12.61% is the absolute ceiling regardless of interior threshold, and share is invariant to the threshold (the armed-fraction coupling that surprised PR4b is refuted here). NB the plan's `{0,−3,…,−15}` sweep was INERT: energy is *seconds-drained* (~17s/possession, unfloored; a never-subbed starter floors near Stamina−1440), so the disabling ceiling needs a threshold below ~−1400, not single digits. PR4b (`bestFatigueBackup`) already consumed the recoverable minute-allocation gap; the residual on-court-`TransOff` path now needs a `selectStarters` composition change (breaks lineup faithfulness), NOT sub timing. Durable log: machine-local `project_j24_arming_share_nogo.md`.
- **U{0..2} / OREB quick-putback reclassification** — IMPLEMENTED and refuted on shelved branch `531a3e677` (unified arm-flag + `orebTripClock`); its own Phase-7 measured code-7 15.41% and diagnosed the excess as the ending-share (arming population), NOT the clock reclass. Done, not a gate-1 lever (2026-07-18). The make-value `putbackValue2pt` IS on master; only the non-lever clock-step is unported.
- **OREB-rate trim** moves armed share the WRONG way (+0.76pp) — trimming OREB converts continuations into DREB endings, which arm at ~94%.
- **+1/−1 transition basing** — SETTLED byte-true: master's `transition.go` gate is `roll(1..denom) ≤ TransOff − specialSub`, NO spurious +1; strategy_adj ≡ 0 in IBL (FUN_005c5800 forces +0x12c=3). The shelved +1 was its unified-arming rewrite, not master. `jsb-native/re-artifacts/jsb-J24-transition-strategyadj-RE-20260718.md`.
- **marker ≡ Code7Push** bijective (k=1.000, whole-binary byte sweep, zero over/undercount) → the log marker and the engine code-7 event are the same thing; the recent-era band is legitimate. `jsb-native/re-artifacts/jsb-J24-marker-code7-mapping-RE-20260719.md`.
- **Var/Cov** PASS under the recent-era re-spec (the all-era ceiling was biased — the same flaw the Var/Cov gates carried). Gate-1 is the ONLY blocker.
- **`baseTimeMid` walk (17.7→16.0)** cannot close gate-1 — share is flat-to-rising as btm drops. (The walk itself SHIPPED 2026-07-24 as J25 residual (5), on faithfulness grounds; that does **not** retire this trap. The measurement stands and the gap remains open at 16.0 — do not re-open `baseTimeMid` as a share lever.)
- **Phase-4 usage-dominance flag (+0x33F0)** is NOT an FG% lever — numerator objdump-pinned to +0xD90 = `twoPtBucketWeight`; the faithful flag fires 0.0005%, FG% +0.01pp (#1541, corrected for faithfulness only, INERT). `jsb-native/re-artifacts/jsb-fgpct-phase4-numerator-pin-20260720.md`.
- **Phase-3 matched-defender term** is NOT an FG% lever — `(DefAST48 − leagueAST48[slot])·0.8` is mean-zero across defenders; ported faithfully it moved FG% only 46.08→46.19%.
- **`dec_rate_delta_e` / `named_carrier: inconclusive_d` from the localise instrument are NOT the attempt-rate carrier** (2026-07-22): the instrument's `real_pa_per_min` benchmarks engine-vs-reality (`.plr` real-life 3PA rates, not `.sco`); `total_gap` −0.0579 conflates (engine↔jsb) with (jsb↔reality), so `DecRateDeltaE` −0.0415 is an artifact of the wrong reference point. Discriminating proof: `engine/internal/calibrate/threept_localize_archive_test.go:184-232` (source verification that the reference is `.plr` real-life stats) + the 2GA-vs-sco parity in the undershoot artifacts (pre-port `...-3pt-undershoot-ab-suppress.json` 884,686/878,321 = **100.7%**; post-port `...-3pt-undershoot.json` 870,986/878,321 = **99.2%**) — total shot volume at near-parity either side of the port rules out a decision-frequency deficit as the carrier. Do NOT re-open the shot-decision-frequency deficit as the gap carrier.
- **The J7 *share* TOV port** is NOT a count-axis fix — the share port REGRESSES Cov(lnFGA,lnPPS) by **−0.000062** (measured 2026-07-26; pre-impl −0.000226 → post-impl −0.000288; further from real +0.000269). **Rescoped 2026-07-29:** the port that produced this measurement (#1676) was **reverted** — it was the wrong quantity, not merely mis-scaled (J29) — so the trap now reads: do not reach for the `TVR_rate/(shot_rate+TVR_rate)` **share form** as a Cov or count-axis lever. It says nothing about the RE-pinned correct form (no shot-rate denominator), which is unmeasured on this axis. See J28, J29.
- **Master pre-partition fastclass counters** — the 9.48%/18.44% DRB-only / steal-union split, and the shelved-branch 13.92%/11.82% at +1/−1 — are NOT band-comparable and are now MOOT post-#1547; the only band-comparable quantity is the merged `DRBPushSharePct` (12.37% **[SUPERSEDED 2026-07-24 — 12.4142%]**). Do NOT resurrect them.
- **The elected ~12.42 / 2-season CI floor is NOT a construction defect — do NOT re-elect a looser bar** (2026-07-23, ADR-0094). Adversarial statistical audit of ADR-0090's criterion #2: the un-derived "~12.42" is a *provenance* gap (√2-shrink of the reproduced 1-season CI = 12.4216; direct 2280-game 2-season bootstrap = [12.418, 12.653]), not an internal error in `[12.374, 12.698]`. "~12.42 is un-derived, therefore elect a different bar" is a criterion-selection **PREFERENCE**, not a defect — the repair is to cite the computed 12.374, never to swap gates. `jsb-native/re-artifacts/adr0092_resolution.py` + ADR-0094.
- **"Master is inside the drift band [11.97, 12.54], so elect the band" is NOT-A-LEVER** (2026-07-23, ADR-0094). ADR-0090 § Decision knowingly declined this; a wider *game* window yields a *tighter* floor (the direct 2-season bootstrap lower bound RISES, moving the bar away from GO), so the band is a different kind of object, not a looser-but-equivalent gate. Criterion-selection PREFERENCE.
- **"Both sides are noisy → use a two-sample overlap test" is NOT-A-LEVER** (2026-07-23, ADR-0094). A different gating object. The engine-side error term is measured small — seed SD **≈0.016pp** (ADR-0094 Phase 6 as corrected 2026-07-24 for seed-block overlap; the ADR's original 0.012pp was deflated, and the matched recent-era measurement gives 0.021pp) against a real-world SE of ≈0.083pp (the CI [12.374, 12.698] half-width 0.162pp ÷ 1.96) — **~5×, or ~4× at 0.021pp**, NOT the "~100× smaller than the real-world CI half-width" this bullet previously claimed. That figure was wrong twice over: it was ADR-0049's corpus-vs-seed ratio misapplied here, and it compared a 1-SD spread against a ~1.96-SE half-width (a unit mismatch — compare SE to SE). **The NOT-A-LEVER verdict does not rest on the magnitude at all** — Finding 5 is a PREFERENCE classification about *which gating object* criterion #2 names, which no ratio touches. The quantitative disposal survives independently: even at the conservative 0.021pp the 95% upper tail reaches only 12.411, short of 12.42; the SD needed to touch it is 0.0255pp. <!-- RETIRED-OK: 0.012pp appears here only as the retired value this bullet corrects. -->
- **"Resample seasons (n=4) to widen the CI below 12.37" is NOT-A-LEVER** (2026-07-23, ADR-0094). The between-season spread is a secular **TREND** (signal — monotonic 8.56%→12.98% across the 20-chunk era split), not exchangeable sampling noise; an iid-season bootstrap treats a trend as noise and inflates the interval illegitimately (variance-model **misspecification**, not a correction). ADR-0094 § Rejected levers.

**Residual (1) status:** partition complete (#1547); gate-1 re-adjudicated **WITHIN-NOISE** (re-denominated 209.2 → 216.58; master 12.37% **[SUPERSEDED 2026-07-24 — 12.4142%]** inside drift band [11.97, 12.54]; −0.05pp under the tightest 2-season floor ~12.42, NOT a clean GO — ADR-0088). **RESOLVED by ADR-0090** (J13-3 cut-over ADR is FINAL — HOLD-corrected: hold retained, re-open per ADR-0090; #1572). **Criterion #2 construction-audited — ADR-0094 (2026-07-23): Verdict A = NO (no construction defect), Verdict B = NO (cut-over not authorized); the elected ~12.42 floor is sound and cut-over remains unauthorized.**

### J26 `fastClassShareArtifact` era/corpus field gap (instrument hazard)

*(Numbered J26, not J25: "J25" is already in use in this doc as the informal label for the `+0x350` non-matched matchupQuality term shipped under J24 in #1527.)*

**Location:** `engine/internal/sim/fastclass_share_archive_test.go` — `fastClassShareArtifact` struct (line 40) and header comment (lines 9–10) + final `t.Logf` (line 140).

**Problem:** `fastClassShareArtifact` has no `era` or `corpus` field — only `Snapshots` distinguishes an all-era run (705) from a recent-era run (97). The file's header comment (lines 9–10) and its final `t.Logf` (line 140) **hard-code recent-era band text** (`DRBPushSharePct ∈ [11.97, 12.54] @216.58 poss/g`), which prints verbatim on an all-era run regardless of snapshot count. That is a live misreading trap: an all-era 11.70% gets logged alongside a recent-era band it is not comparable to, and will silently appear to pass/fail a gate it cannot validly address. Demonstrated by the all-era A/B run (705 snapshots, stride 1, runs 4, seed 20200101) that produced the J17 gate-1 neutrality measurement on 2026-07-22: the log correctly showed 11.70% but the band text was wrong for that corpus.

**Direction:** Add an `era` or `corpus` field to the `fastClassShareArtifact` struct and make the `t.Logf` band text conditional on that field (or derive it from `Snapshots` with an explicit guard). ⚙️ Sonnet recipe; one PR.

**Status (✅ Implemented, 2026-07-24 — this branch `jsb-backlog-hygiene-j26-fta`).** `Era string` (json:`"era"`) and `Corpus string` (json:`"corpus"`) fields added to `fastClassShareArtifact`. Era is derived at runtime from the set of distinct 5-char season prefixes in the zip list: if all prefixes are in {04-05, 05-06, 06-07, 07-08} → `"recent (04-08)"`, else `"all"`. The label says **04-08**, matching both the classifying set and the band's own derivation window (ADR-0088 chunks 04-05/05-06/06-07/07-08); do NOT relabel it "05-08" to match the narrower *engine reference* corpus — that is a different object, and the mismatch was the exact misread J26 exists to kill. The exact season subset scanned is also logged (`seasons: …`) so a 04-05-containing corpus is visibly distinguishable from the 05-08 reference. The final `t.Logf` band text is now conditional on `art.Era`: recent-era prints the [11.97, 12.54] band and master reference; any other era prints `"no comparable band for era=<era>"`. The ⚠ header block (formerly "THIS ARTIFACT DOES NOT RECORD ITS CORPUS (backlog J26)") and the stale NOTE comment in the struct are removed. Verified: `go vet -tags archive ./internal/sim` clean; `make fmt-check && make vet && make test` all pass.

---

### J27 FTA undershoot ~−21% vs sco (no confirmed mechanism)

*(Numbered J27: next available after J26.)*

**Current measurement (2026-07-24):** engine FTA 152,874 / sco FTA 194,185 = **−21.27%** (sha 575c52350, stride 1, 99 snapshots, games_cap 60, seed 20240601; `TestRealArchive_ThreePtUndershoot`). The 0.56pp move from the prior disclosed −21.83% is within corpus-size variance (99 vs ~97–98 snapshots) and is **NOT evidence of improvement**.

**Disclosed contributors to the current magnitude** (these explain *part* of the gap's history — the mechanism of the underlying structural gap is UNKNOWN):

1. **OReb putback-3pt restore (2026-07-23):** FTA worsened −19.65% → −21.83% (98-snapshot arm, per the SUPERSEDED 2026-07-23 block in J24 residual (7)). The putback-3pt gate IS faithful (ADR-0090) — its FTA cost is not grounds to restore an unfaithful suppression.
2. **Transition `allow3pt=false→true` port (2026-07-23):** FTA side-effect −0.26 FTA/100poss (ADR-0090 disclosure, J24 NOT-A-LEVER bullet). Faithful mechanism's downstream cost; not grounds to reverse.

**J15 distinction:** J15 was the OPPOSITE-signed FTA problem — before ADR-0082, the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g), causing a massive OVERSHOOT. J15 was ✅ Implemented 2026-07-12 (ADR-0084). J27 is the current UNDERSHOOT (~−21%); it is **NOT owned by J15**. Do NOT re-open J15 for this gap.

**Open:** mechanism is unattributed. No confirmed lever exists. Do NOT claim a mechanism from J27's numbers alone; pick a lever from the leverage report (`ls -t jsb-native/re-artifacts/leverage-*.txt`) first.

**Status (⬜ Open, filed 2026-07-24).** Tier: 🧠 Opus (novel RE — mechanism attribution requires tracing the foul-draw path, no confirmed lever yet). Effort: M (multi-step investigation + faithful port + re-measurement if a lever is found).

### J28 Cov(lnFGA,lnPPS) regression from J7 TOV-coupling port

*(Numbered J28: next available after J27.)*

**Mechanism:** J7 faithful port (`teamOffTOVShare`) regresses `Cov(lnFGA,lnPPS)` by **−0.000062** (pre-impl −0.000226 → post-impl −0.000288, 2026-07-26 archive run, 15 seasons). Further from real +0.000269. Faithfulness cost per ADR-0090; not grounds to withhold the J7 port.

**Why it regresses:** High-volume lineups have larger `total_shot_rate` denominators → `P(TO) = TVR_rate/(shot_rate+TVR_rate)` is diluted **relative to** low-volume lineups → their possessions convert to shots at a higher relative rate, without a proportional PPS rise → Cov(lnFGA,lnPPS) is pushed more negative. This is a **cross-sectional dilution gradient**; it is the same FGA anti-coupling amplification that makes J7 necessary. **Level-direction correction (2026-07-29, J29):** an earlier version of this line stated that the port makes TOV count *fall* and FGA/g *rise* in aggregate. Measured, the levels moved the **opposite** way — TOV/g **rose** 43.0% (27.77 → 39.72) and FGA/g **fell** 5.5% (199.17 → 188.25), `ibl5/backups` (example) stride 100 / runs 4 / seed 20240601. The gradient claim and the −0.000062 Cov measurement stand; only the aggregate level direction was wrong. The level defect itself is **J29**.

**Measurement (2026-07-26):** `TestRealArchive_PossessionCoupling`, worktree `jsb-j7-tov-coupling-port`. Pre-impl baseline: −0.000226; post-impl: −0.000288; Δ = −0.000062.

**Status (2026-07-26, ⬜ Open; rescoped 2026-07-29):** Disclosed; no positive Cov lever confirmed. Do NOT re-open J7 **for the Cov axis** — on that axis J7 faithfulness is the cause, not the fix, and a positive Cov fix must come from the FGA anti-coupling (see J20 declined rationale) or a future count-axis improvement. **Scope correction (J29):** this bar was written on the premise that the #1676 port is faithful, and that premise is falsified **for the level** — the steal branch fires at the total-TOV rate, STL/g 30.5 vs a 17.8±0.7 target, and the archive band `steal share% ∈ [8.0, 9.0]` fails at 14.69%. That defect is tracked as **J29** and is a legitimate re-open of the ported mechanism; this bar never covered it. **Revert correction (2026-07-29):** #1676 was reverted, so the −0.000062 regression and its dilution-gradient mechanism are **out of the tree** — Cov is *expected* back at the pre-port −0.000226 and this entry no longer carries a live regression. **Not re-measured:** the revert PR did not re-run `TestRealArchive_PossessionCoupling`, and this branch also carries #1675 (the w4 foul-bucket rescale, which landed after #1676), so treat −0.000226 as the inferred value, not a fresh reading. The mechanism explanation above is also **specific to the reverted form**: it turns on `shot_rate` appearing in the denominator, and the RE-pinned correct form has no shot-rate denominator, so it does **not** transfer to the re-port — J29's port must be Cov-re-measured from scratch rather than assumed to reproduce −0.000062. What stays open here is only the standing "no positive Cov lever confirmed" disclosure. Tier: 🧠 Opus. Effort: S (disclosure only until a positive lever is identified).

### J29 #1676 steal branch fires at the total-TOV rate (level defect; RE cannot resolve the split)

*(Numbered J29: next available after J28.)*

**Defect (as shipped in #1676; reverted 2026-07-29 — the code cited below is no longer in the tree,
and `teamOffTOVShare` / `tovCarelessRate48` no longer exist as symbols).**
PR #1676 (J7 port) replaced the steal branch's calibrated constant with an unnormalized
share: `steal.go:59` read `prob := gs.turnoverProb(teamOffTOVShare(offense.players))`, and
`freeze.go:665-690` applies **no scaling** (`p := share`, clamped at 0.9). The share's numerator is
a **total**-turnover rate — `tovCarelessRate48 = RealLifeTVR/RealLifeMIN×48`, and `bundle.go:112`
documents `rl_tvr` as `TOV/MIN×48` with `assemble.go:176` summing it into the league total-turnover
accumulator `sumTOV`. So the steal-only branch fires at the team's total turnover rate, while
`nonStealTurnover` (`steal.go:84`, scale `0.00175`, untouched by #1676) adds non-steal turnovers on
top of turnovers already counted.

**Measured (`ibl5/backups` (example), stride 100, runs 4, seed 20240601 — 8 snapshots, 35,896 games, ~7.4M
possessions per end; instrument byte-identical at both ends: `endingmix_share_archive_test.go` blob
`e3f0802a1`, `endingmix.go` blob `e96231713`):**

| Quantity | pre-#1676 (`45080c1cf`) | post-#1676 (`619fee3f2`) | master tip `1c3220456` | target |
|---|---|---|---|---|
| steal endings % | 8.76 | 14.69 | 14.69 | band **[8.0, 9.0]** ❌ |
| indep-TO endings % | 4.78 | 4.47 | 4.47 | band [4.4, 5.4] ✅ |
| STL / game | 17.97 | 30.46 | 30.5 | 17.88 (gate 17.8±0.7) ❌ |
| TOV / game | 27.77 | 39.72 | 39.7 | ~27.8 |
| FG % | 48.41 | 48.27 | 48.22 | band [47.5, 48.9] ✅ |
| FTA / game | 33.8 | 31.9 | 31.6 | band [29.5, 34.2] ✅ |

**Post-revert re-measure (2026-07-29, same instrument / population / seed — `TestEndingMixBaseline`
PASS):** steal endings **8.75%** ✅, indep-TO **4.78%** ✅, DREB 32.31%, made-FG 44.95%, FT-seq 9.20%,
FG% **48.37** ✅, FTA/g **33.5** ✅, STL/g **18.0**, TOV/g **27.8**, poss/g 205.1, armed 39.13%,
implied code-7 @0.300 **11.74%** (was 12.94% post-#1676 — back under ADR-0090 re-open criterion #1's
≥12.42 bar; reported, not gated here). The revert carries #1675 (w4 foul-bucket rescale, which landed
*after* #1676) along with it, so this end is pre-#1676 J7 **plus** #1675 — that is the source of the
small drift from the `45080c1cf` column (FTA/g 33.8 → 33.5, FT-seq 9.29 → 9.20, made-FG 44.92 →
44.95). All four bands hold.

`619fee3f2^` = `45080c1cf`. **Exactly one** assertion fails, at `endingmix_share_archive_test.go:340`:
`steal share% = 14.69%, want within [8.00%, 9.00%]`. The control held — `indep-TO share%` is the
untouched `nonStealTurnoverScale` branch and stays in band, so the run is not population- or
config-contaminated. Pre-#1676 reproduces the checked-in
`calibration-5.60-20260723-ending-mix.json` steal share of 8.758 to 3 dp. **Era caveat:** the share
bands are ratios and transfer across populations; the per-game targets (17.88 STL/g, 209.2 poss/g,
67.58 DREB/g) are all-era J3 figures and are **not** era-safe (ADR-0088 retired all-era denominators
for gate-1).

**ADR-0085 does not shield this.** The violated target is the port's **own** fidelity target — STL
≈ 17.8±0.7/g both teams, named in the comment of the very constant #1676 deleted
(`stealTurnoverScale = 1.69e-5`). A scale error inside a faithfulness port is not a
fidelity-vs-fit tradeoff.

**Headline RE finding — the ported quantity is the wrong quantity, not merely the wrong scale.**
`teamOffTOVShare = TVR_rate/(shot_rate + TVR_rate)` is the **near-complement** of
`00_MASTER_REFERENCE.md:436-443`'s fast-break steal-**success** threshold
`total_shot_rate/(total_shot_rate + TVR_rate)`. <!-- RETIRED-OK: corrects "exact complement" -->
**Correction (2026-07-29):** an earlier version of this line said *exact* complement. It is not exact
— 5.60's `total_shot_rate = +0xDA8 + +0xD88 + +0xD70` (3P + 2P + **FT composite**) while Go's
`shot_rate` is `Σ fgaRate48` and omits the FT term. The identification of the quantity stands; only
"exact" was wrong, and nothing in this entry turns on exactness. That quantity is fast-break-scoped
(`:436`), governs whether an
*already-created* turnover's transition **converts** rather than whether a turnover happens, and is
**stateful** — `DA0` decays by 2.0 (floor 2.0) after each break. `jsb-J7-tov-coupling-RE-20260720.md`
§1 explicitly rules it out as the generator ("correcting an earlier draft"). The pinned generator is
instead `Σ_5(offensive_turnover_stat × fatigue) / (param × 1.5)` — the offense's own per-48 TOV-rate
composite over an **engine constant**, with no shot-rate denominator and no defense-side term.

**Why this is NOT fixed here, and why a fitted factor is refused.** Neither candidate repair has an
RE basis. *Renormalize* (restore a calibrated scale in front of the same share) preserves a
fast-break-scoped, decaying, wrong-quantity anchor. *Partition* (steal branch takes the
steal-sourced portion) requires a steal/non-steal attribution step that no RE has located. Two
independent stop conditions:

1. **`param` is never named or valued.** `00_MASTER_REFERENCE.md` § Steal Probability pins the form
   and stops at "Total capped at `param × 1.5`". The generator's **level** — the whole of this
   defect — is not derivable from RE. <!-- RETIRED-OK -->
2. **5.60's non-steal turnover path is unlocated, and the RE's account of it is falsified.** J7 RE
   §1 asserts the only independent per-possession check is the `+0xDF8` energy param ∈ [2,5] →
   ~0.1%/poss ≈ **0.2 TOV/g**; the corpus carries indep-TO endings 5.16% ≈ **10 TOV/g** — a ~50×
   discrepancy. Taken literally, RE's structure ("under ⇒ steal") makes 5.60 emit steals ≈ *all*
   turnovers, against a corpus 64%. The RE's stated structure cannot produce the observed split.

**Live readings (three; `+0xDD0 = STL/MIN×44` is CLOSED — it is steals *gotten*, a defensive stat,
while the generator sums the OFFENSE's five careless-ball-handler weights):**

- **(a) Unfound attribution step** — the roll creates a turnover; a later step credits a steal (~64%)
  or not (~36%). Then the Go bug is that `nonStealTurnover` is *additive* to a gate that already
  counts non-steal turnovers, instead of a partition of it. Needs: the credit step's offset.
- **(b) Unfound second generation site** — supplies ~10 TOV/g, since the `+0xDF8` check is ~50× too
  small. Then the steal generator is steal-only and must fire at a steal-sourced rate. Needs: the site.
- **(c) Level-set-by-`param`** — the structure is complete and the split is emergent from `param`.
  Then the faithful port is `Σ_5(tovCarelessRate48 × fatigue) / CAP`, which **drops the shot-rate
  denominator** and is neither audit candidate. Needs: `param`'s value.

**Next RE (in order):** (i) read the guard at `0x4da86b` (the single call site of `FUN_004d7a10`) to establish the gating fraction; (old-i, done) pin `param` at the steal-roll call site in the possession handler — trace the caller that supplies the cap operand into the `× 1.5` [CORRECTED 2026-07-29 — done: param = leagueSTL48 × 5.0; formula recovered in re-artifacts/jsb-J7-steal-probability-RE-20260729.md]; (ii) locate 5.60's non-steal
turnover origin by working back from the `.sco`/IBL5.log ending classes that produce indep-TO
endings; (iii) only then choose between (a)/(b)/(c). Data is **not** the blocker — J22 (✅) already
wires per-player `rl_stl`/`rl_tov`; the blocker is asm.

**#1676 WAS reverted (2026-07-29), reversing this entry's original "do NOT revert" directive.**
That directive was written on a fix-forward premise and is superseded by an explicit user decision.
The trade it makes: the pre-#1676 form is genuinely **unfaithful** (rating-for-stat plus a
defense-side Σ STL pool, severing the offense-rate self-coupling, `jsb-J7…RE` §1) and restores the
`stealTurnoverScale = 1.69e-5` stand-in and its ADR-0087 registration — but it is *in band*, whereas
#1676's form is the **wrong quantity** and out of band on a hard gate. With `param` unrecovered there
is no faithful form available to ship, so carrying a known-wrong quantity past a hard band (and into
gate-1's `DRBPushSharePct`, which the steal-rate error inflates via the merged code-7 arming) is the
worse of the two. The revert restores a known, disclosed approximation instead of an unlocated one.
J29 now tracks the **correct-form port**, not the removal.

**Do NOT** (applies to the re-port): apply a flat ×0.64 or any factor fitted to make the band pass;
re-introduce the share form under a calibrated scale (that keeps a fast-break-scoped, decaying,
wrong-quantity anchor and is not what the RE pins); sweep or register the re-introduced share as a
stand-in (it would be pinned by omission under ADR-0087 §2 either way); or merge a
`TOV/MIN×48` careless-rate term into `tovRate` (that docblock forbids it — J6/J16 offQuality foul
coupling). **Also do NOT** re-open J7's RE verdict on the strength of the revert — the revert removed
a port, not a finding.

**Related.** J28 carries the Cov(lnFGA,lnPPS) regression from the same port — that is a separate
axis and its "do not re-open J7" bar is scoped to Cov, not to this level defect. The hard band that
would have caught this is dark (`//go:build archive`, skips without `JSB_ARCHIVE_DIR` — "always 0 on
CI", test docblock `:30`); that is the meta-tooling **unwired test** disposition, due at the
2026-10-06 cull, and is deliberately **not** bundled here. Machine-local inputs:
`jsb-native/re-artifacts/pr1676-tov-fidelity-audit-20260729.md`,
`jsb-native/re-artifacts/jsb-J7-tov-coupling-RE-20260720.md`,
`jsb-native/jsb_560/decompiled/00_MASTER_REFERENCE.md`.

**Status (2026-07-29, ⬜ Open — rescoped to the re-port).** Filed from a first-hand reproduction at
master tip `1c3220456` (no engine changes between `85d245cb5` — the planned measurement SHA — and
`1c3220456`, confirmed via `git log 85d245cb5..HEAD -- engine/internal/sim/` returning empty; the A/B
ends are unaffected). **#1676 has since been reverted** (this entry's PR), so the defect is out of the
tree and no band is red; what remains open is the **correct-form port**, which is blocked on RE, not
on implementation.

**The single blocker is `param`.** The faithful generator is
`P = Σ_5(TOV/MIN×48 × fatigue) / (param × 1.5)` (`jsb-J7-tov-coupling-RE-20260720.md:52-54`) — **no
shot-rate denominator**, and pace-invariant. `param` is never named or valued anywhere in the RE:
`00_MASTER_REFERENCE.md:1604-1609` § Steal Probability is bare prose with **no `FUN_` address and no
✅ VERIFIED stamp**, unlike every block around it. So recovering it is an RE task with an *unlocated
starting point*, not a lookup — it wants its own `/plan`, not a fold-in to the port.

**Follow-up owed, not bundled here:** re-measure gate-1 `DRBPushSharePct` post-revert. #1676 inflated
`implied_code7_pct` to 12.94% (over ADR-0090 re-open criterion #1's ≥12.42 bar) via the merged code-7
arming; the revert reads 11.74%, so any gate-1 reading taken between #1676 and this revert is
defect-contaminated. Do **not** re-derive the gate-1 band or re-express it pace-invariantly off those
numbers — that is an ADR-level decision and the number that prompted it was defect-driven.

Tier: 🧠 Opus (novel RE — asm work to pin `param` and locate the non-steal path). Effort: M (RE
session + faithful port + archive re-measure at both ends + gate-1 re-read).

### J30 Non-steal turnover origin — the outcome-5 `sqrt(local_44)` path is quantitatively falsified
(discovered 2026-07-29 during jsb-re-audit-backlog)

**Why now.** J29's stop condition (2) says 5.60's non-steal turnover path is *unlocated*. It is not
unlocated — the master reference documents it, at `00_MASTER_REFERENCE.md:1394`. What is wrong is
that the documented chain **does not reproduce the corpus**, by ~50×, and no line in the reference
says so. This entry owns that gap; it is the concrete work behind J29's live reading (b), "an
unfound second generation site."

**The chain, end to end** (each link re-read against `jsb560_decompiled.c` on 2026-07-29, not quoted
from memory):

1. `FUN_004e1ba0` tail: `iVar10 = FUN_0047f5a0(0x701);` then
   `if (((float10)iVar10 <= SQRT((float10)local_44)) && (param_3 == '\0') && (0 < *(int *)(param_1 + 0x171d0))) iVar8 = 5;`
   — outcome 5 is the turnover, rolled *after* the bucket loop and outside it.
2. `local_44` is declared `int` and never assigned in the function body; it is a field of the
   ball-handler record copied in by `FUN_0040b6a0` at entry (`00_MASTER_REFERENCE.md:1480`).
3. The 2026-07-09 esp accounting (delta `0x60`) pins `local_44` @ `esp0+0xE58` → player `+0xDF8`
   (`:1499-1504`, roll-up `:4522`).
4. `+0xDF8` is the dc_minutes energy parameter, **clamped `[2,5]`** (`:415`, `:509`, `:1816-1826`).
5. ⇒ `sqrt(local_44) ∈ [1.41, 2.24]` against `rand_int(1,1793)` ⇒ **≈0.12%/possession ≈ 0.25 TOV/g.**

Corpus says independent (non-steal) turnovers are **5.16% of endings ≈ 10 TOV/g** (J29's own
measured ending mix). The chain is off by ~40–50×, which is the same ~50× J29 records — so J29's
gap and this chain's gap are **the same defect**, not two.

**Four candidate breaks, in the order they should be tested** (this is the RE, not a menu to
pick from by taste):

1. **`+0xDF8` is not the same field in the copy as in the player array.** `:1457` already suspects
   `FUN_0040b6a0` repacks regions — the same paragraph shows the uniform-`0x68` map failing on
   `local_8c`. If the copy is not same-offset throughout, step 3 is unsound and `local_44` is some
   other double. **Discriminating test:** disassemble `FUN_0040b6a0`'s copy loop over the
   `0xDE8–0xE60` source window and read the destination offsets directly, rather than inferring a
   delta from two fitted points.
2. **`+0xDF8` holds something else at call time.** `:415` carries both readings at one offset (int
   energy param *and* the play-outcome turnover value) with an explicit `⚠️`. If per-half setup
   (`FUN_004cfa50`) overwrites `+0xDF8` with a per-game double after the PLR-load int is written,
   the `[2,5]` clamp is simply not what the selector reads. **Test:** enumerate every write to
   `+0xDF8` on the player array including esp-relative stores in the stack-built record — the exact
   blindspot class that overturned `+0xDD0`/`+0xDE0`/`+0xDC8` (J6, `:505`).
3. **`FUN_0047f5a0(0x701)` is not `rand_int(1,1793)`.** The magnitude conclusion is entirely a
   function of this range. **Test:** read `FUN_0047f5a0`'s body; confirm the interval and whether
   the comparison is inclusive.
4. **There is a second, unlocated generation site.** `turnover_logic` (`FUN_004ec760`, 224 lines) is
   in the function inventory (`:844`) and is recorded as generating no randoms of its own — but that
   is a *randomness* claim, not a *turnover-attribution* claim, and it has never been walked.
   **Test:** trace every caller path that increments a TOV stat, not just the ones that roll.

**What this does NOT license.** Do not close J29 or fit `stealTurnoverScale` on the strength of a
resolution here — that inherits J29's whole "Do NOT" list unchanged. This entry buys the *location*
of the second path; the port is still J29's, and still blocked on `param` for the steal side.

**Ordering vs J29.** Independent. J29's next-RE step (i) pins `param` at the steal call site; this
entry attacks the other half of the split. Neither blocks the other, and either alone leaves the
generator unfaithful.

Tier: 🧠 Opus (novel asm RE; three of the four tests are negative-proof shaped, which
`.claude/rules/agent-tiering.md` keeps off Sonnet). Effort: M.

### J31 Play-outcome frame-trace residuals (`local_e78`, `def_rating`) + a master-ref self-contradiction
(discovered 2026-07-29 during jsb-re-audit-backlog)

Three leftovers from the play-outcome frame trace, small enough to share one session:

1. **`local_e78` source offset — still OPEN, and correctly refuses to guess**
   (`00_MASTER_REFERENCE.md:1559`). It is caller-supplied (`CONCAT44(local_c0,local_c4)`), lands at
   `esp0+0xDD8`, and sits **below** the `FUN_0040b6a0` copy region, so the in-copy `slot−0x60` map
   does not apply. By role it is the made/offensive base — the "made" half of "made + fouled" — and
   it multiplies straight into the and-one bucket at `:1552`. Needs a direct disasm read of the
   value landing at `004e1ba0`'s `esp0+0xDD8` at the `jsb560_decompiled.c:93293` call setup.
2. **`def_rating` source offset — unpinned** (`:1598`). `defender_selector` (`FUN_004e2850`) weights
   each defender `(2.0 − fatigue) × def_rating`, `×0.6667` in foul trouble. `def_rating` is a
   per-game double from the same `FUN_0040b6a0` copy, and the reference flags the offset as
   "not yet pinned (validation-phase)". Same copy-map work as (1), so do them together.
3. ✅ **RESOLVED 2026-07-29 — the reference's self-contradiction on whether the bucket inputs are
   closed.** `:4522` recorded `local_ac/8c/5c/44` as ✅ **CLOSED** with all four offsets pinned while
   `:4541`, in the same roll-up, stated "the only open formula item is play-outcome
   `local_8c`/`local_5c`". **Adjudicated: the CLOSED row is right, the "Net" line is stale** —
   `local_8c`=`+0xDB0` and `local_5c`=`+0xDE0` were pinned by the delta-`0x60` esp accounting on
   2026-05-30. Both the "Net" line and the "ALL per-possession formulas are now closed" claim now
   carry a `[CORRECTED 2026-07-29 …]` marker restating the real open scope as items (1) and (2) of
   this entry plus J29/J30. Neither loser was deleted (see J32 on why the reference keeps superseded
   text).

**Note.** `FUN_004e1ba0`'s argument binding is **not** on this list — it was re-verified
2026-07-29 from the caller-side stack-slot trace and is correct as documented (see J32, which
records the stamp).

Tier: 🧠 Opus (frame-offset RE; (3) is an adjudication between two dated pins). Effort: S.

### J32 Master-reference audit sweep — superseded conclusions printed unmarked, and zero CI coverage

**The structural finding, first, because it is the cause and not a symptom.**
`jsb-native/jsb_560/decompiled/00_MASTER_REFERENCE.md` is 4,596 lines, is the single source of truth
for every 5.60 runtime-behavior fact the port depends on, and is **invisible to every gate the repo
has**. It lives under `jsb-native/`, which is git-excluded (`.git/info/exclude:20`), so: it does not
materialize in worktrees, no PR can carry a correction to it, `bin/check-docs` never scans it (it is
outside every in-scope glob in `.claude/rules/doc-freshness.md`), and its retired-figure rule — the
one mechanism the repo has for exactly this failure — cannot fire on it. Every drift below survived
because nothing could catch it. Deciding what to do about that (leave it, or bring a scanned copy
in-tree) is part of this entry, not a precondition for it.

**Status 2026-07-29: the two bounded halves of this entry are DONE; the open half is the sweep.** (discovered 2026-07-29 during jsb-re-audit-backlog)
Applied the same day the entry was filed, directly to the reference (which is why no PR carries them
— see the structural finding above): every row of the confirmed-drift table below now carries a
`[CORRECTED …]` / `[SUPERSEDED …]` marker, and both additive edits landed — the doc-wide
argument-binding caveat is now a new `## ⚠ Method caveats` section at the top of the file (it also
promotes the esp-store blindspot from an inline aside to a stated *class*, and records the file's
CI-invisibility in the file itself), and `FUN_004e1ba0`'s binding carries its ✅ VERIFIED 2026-07-29
stamp with the full slot table. The line numbers in the table below are **pre-edit** and have since
shifted downward by up to ~+40 (the shift grows with depth, since each inserted block pushes
everything after it) — re-grep on the quoted anchor text, do not seek by number. **What remains open: the
34-line proof-type sweep, plus the structural decision** (leave the reference outside git, or bring a
scanned copy in-tree so `bin/check-docs` can fire on it).

**Confirmed drift sites** — every row dated 2026-07-29 is ✅ CORRECTED (each re-read that day; line numbers as of *before* those edits). Rows added later carry their own status in the *Overturned by* cell — do not read the 2026-07-29 blanket stamp as covering them:

| Site | Stale claim | Overturned by |
|---|---|---|
| `:99` | "Steal/block/assist/foul mechanics — COMPLETE" | J29: the steal/non-steal split is unresolved and `param` is unvalued |
| `:429-430` | "handler NEVER keeps the ball in consecutive transition plays" / "the transition ball-retention mechanism is entirely disabled" — printed unmarked *below* the corrected premise at `:428` | J6 (2026-07-10): `+0xDA8`/`+0xDA0` are live for any 3-point shooter, so retention can fire |
| `:767` | `CEngine+0x30` redraw flag — "writer unidentified — bounded unknown" | J17b / PR #1536: three `movb $imm8,0x30(esi)` writer sites found (backlog J24 residual (3)) |
| `:1267`, `:1301` | "player `+0xDC8` ≡ 0 (dead)" ⇒ matched term always ≤ 0 | J6 (2026-07-10), recorded 15 lines away at `:1260`: `+0xDC8` is live AST/MIN×48; the matched term is a sign-varying differential |
| `:1388` | bucket origins "declared-but-never-assigned … exact assembly pending the cross-function frame trace" | the trace completed — `:1459`, `:1465-1468`, `:4484` |
| `:1450-1457` | the pre-2026-07-09 bucket table (delta `0x68`, `+0xDA8??`/`TBD`/`+0xDF0`, "expected 3P rate `+0xD70`") printed **unmarked above** the pinned table | `:1463-1468` (delta `0x60`; `+0xD90`/`+0xDB0`/`+0xDE0`/`+0xDF8`) |
| `:415`, `:1472` | "JSB turnovers are therefore overwhelmingly **steal-driven**" | J29 + J30: the ~0.1% independent-TO figure that conclusion rests on is falsified by the corpus at ~50× |
| `:1384` vs `:1420` | two unqualified `param_7` tokens 35 lines apart naming **different functions'** parameters (`FUN_004e1ba0`'s play_type vs `FUN_004e45a0`'s inert flag) | not a wrong claim — a live misreading trap |
| `:466` | the § *Misreading trap* callout still routes the reader to "§ Steal Probability (steal side, **blocked on `param`**)" | J7 RE 2026-07-29 (`re-artifacts/jsb-J7-steal-probability-RE-20260729.md`): `param = leagueSTL48 × 5.0` is valued and § Steal Probability now carries a `✅ VERIFIED 2026-07-29` stamp, so the reference self-contradicts. **⬜ NOT corrected** — surfaced 2026-08-26 during #1964, which is a worktree PR and cannot reach a git-excluded file (this entry's own structural finding) |

**The larger sweep, which is the actual work.** `grep -nE 'dead\b|never read|vestigial|dead-zero|always 0|≡ 0'` returns **34 lines**. Two of those dead-zero pins have already been overturned by
one method fix each (J6's esp-store blindspot; the 2026-07-23 Ghidra `double`-argument misread). Each
of the remaining 34 needs its **proof type** classified — exhaustive-scan negative, enumeration
negative (suspect: that is the class J6 broke), or inference — and the enumeration-negatives re-run.
This is the per-claim judgment that makes the entry Opus and not a marker sweep.

**Two additive edits owed regardless of the sweep's outcome — both ✅ APPLIED 2026-07-29:**

- A **doc-wide argument-binding caveat.** `grep -n 'param-binding|argument-binding'` returns **zero
  hits**: the hazard that produced the 2026-07-23 OReb misread — Ghidra emitting a bare
  `FUN_xxx()` at the call site while a `double` parameter silently consumes two stack slots — is
  documented nowhere as a class, only as one incident. The reference already carries the companion
  caveat for the esp-store blindspot at `:487`; this is its missing twin.
- A ✅ **VERIFIED stamp on `FUN_004e1ba0`'s binding** (`:1378-1387`), which was re-derived
  2026-07-29 and is **correct**. Caller-side stack-slot trace at `jsb560_decompiled.c:93285-93293`:
  `-0x194`→`param_2`, `-0x190`=`local_15c`→`param_3` forced_make, `-0x18c`=`param_1+0x58`→`param_4`,
  `-0x188`=`*(byte*)(param_1+0x4c18)`→`param_5` HCA, `-0x184` (8 bytes) = `FUN_004e3860()`→`param_6`
  **double**, `-0x17c`=`local_154`→`param_7` play_type, `-0x178`=`local_120`→`param_8` shot_clock.
  Three independent cross-checks hold: `local_154 == 5` is the steal guard eight lines above the
  call; `local_15c==1 ⇒ local_120 &= ~0xff` (forced-make and shot-clock are mutually exclusive);
  `local_120 |= 1` when `param_1+0x4c24 < 4` (clock low). Stamping this **closes** the re-audit
  rather than opening one — record it so the next session does not re-derive it.

**Do NOT:** delete superseded text. The reference deliberately keeps it behind
`<details><summary>superseded original text</summary>` (`:1392`) for provenance; the defect is
missing *markers*, not surviving history. Use the `bin/check-docs` marker vocabulary
(`[CORRECTED …]`, `[SUPERSEDED …]`, `**Superseded by:**`, `RETIRED-OK`) even though no gate reads
this file — so that it stays gate-compatible if the structural question above is ever answered by
bringing it in-tree.

Tier: 🧠 Opus (the 34-line sweep is proof-type classification, and per
`.claude/rules/agent-tiering.md` a negative-existence claim is not Sonnet work). Effort: M.
