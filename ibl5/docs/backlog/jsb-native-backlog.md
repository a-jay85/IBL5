---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-20
---

# JSB Native-Engine Backlog

**Purpose:** Catalogue the remaining work to bring the Go engine (`engine/`) to cut-over fidelity with jumpshot 5.60, with an explicit **model-tier** on every item — separating what only Fable-class reasoning has cracked, what is Opus judgment, and what is now a Sonnet-executable recipe. Each open entry is a candidate for a `/plan`.

**Origin:** Advisor triage (2026-07-08), immediately after two static-RE closures refuted long-standing "requires live debugging" premises: the foul-divisor pin (2026-07-07, Fable session) and the CEngine runtime-doubles resolution (2026-07-08). Statuses verified against `$HOME/.claude/plans/`, `jsb-native/re-artifacts/`, and git log on 2026-07-08.

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
J17 game-state foul coupling (⬜, new 2026-07-10) — real 5.60 mechanism the engine lacks entirely
J21 gt=4 playoff-margin audit (✅ 2026-07-14 — no overshoot, engine under-disperses globally) · J22 per-player STL/TOV bundle wiring (✅ 2026-07-16) — cut-over-gate fidelity inputs to J13; NEITHER is a Cov(lnFGA,lnPPS) lever
J23 round-half-up + base_time re-center (✅, 2026-07-16, #1495) — coupled faithful fix deferred from J21; ADR-0085 records the hold finding; shipped round-half-up + baseTimeMid re-center 14.5→13.65 (J23)
  └─→ J24 possession-clock subsystem port (◑ Partial 2026-07-19) — step classes + jitter SHIPPED; steal split + faithful shotValue2pt/3pt (D80/D60/D64/DE8, blockMod, flow term wired) SHIPPED (j24-mix-fixes PR); matchupQuality Phase 3 matched-defender term (+0xDC8 DefAST48 vs LeagueAST48ByPos) SHIPPED, non-matched term (+0x350) SHIPPED (J25 2026-07-18); Phase 4 usage-dominance-flag infrastructure PLUMBED but INERT (J26 2026-07-19, jsb-phase4-33f0 — `computeUsageDominanceFlags` + accumulator wired into `matchupQuality`, but both call sites pass `[6]bool{}` so phase4 == 0; the numerator is an approximation of the unpinned `local_ac` and the Go sim has no faithful usage-flag analog, so a per-possession port is DEFERRED to a scoped follow-up per J26); FG% measured 46.42% (unchanged from J25 baseline); residuals: Phase 4 usage-flag port, CEngine+0x30 flag, baseTimeMid walkback to 16.0, U{0..2} step class
J18 composite fidelity ports (✅ 2026-07-13 — all divergences merged; f/shrink port declined as documented divergence) · J19 J6-residue RE (✅ 2026-07-12) — both spawned by J6
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy). Zeroing defQ moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever; that A/B stands as measurement. **But J6 (same day) overturned the static premise underneath it:** J5's "defQ ≡ 0" was a store-enumeration blindspot — 5.60 builds the player record on the STACK (FUN_004cfa50 → FUN_00405970 write-back), so +0xDD0 (STL/MIN×44), +0xDE0 (usage-shrunk TOV/48), and +0xDC8 (AST/48) are all **live**. The faithful foul coupling is therefore roster-VARYING (defQ = Σ defenders' STL/MIN×44; offQ = Σ offense TOV/48 − HCA, TOV-coupled not volume-neutral), and J2's "symmetric U[0,0.6) both sides" verdict plus the J15 program must be re-adjudicated against the live-composite semantics before any port ships. "Mapped carriers exhausted" stays refuted; the map had a foul-path hole — and a method hole (see J6's caveat).

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 4 |
| 📋 Planned | 0 |
| ◑ Partial | 1 |
| ✅ Implemented | 18 |
| 🚫 Declined | 1 |

---

## Entries

| # | Title | Status | Tier | Effort |
|---|-------|--------|------|-------:|
| J1 | Faithful foul-bucket pair port | ✅ Implemented | ⚙️ Sonnet | M |
| J2 | Count-axis carrier adjudication (post-J1) | ✅ Implemented | 🔮 Fable | L |
| J3 | Per-origin efficiency identifiability (IBL5.log) | ✅ Implemented | 🔮 Fable | M |
| J4 | Play-by-play extraction parser | ✅ Implemented | ⚙️ Sonnet | M |
| J5 | Unpinnable-claims sweep + static closures | ✅ Implemented | 🔮 Fable | M |
| J6 | Composite-scale pins (+0xD90/+0xDB0, `f`, full player formula map) | ✅ Implemented | 🔮 Fable | M |
| J7 | Turnover volume-coupling fidelity RE | ⬜ Open | 🧠 Opus | M |
| J8 | Transition trigger denominator 18 | ✅ Implemented | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ✅ Implemented | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ✅ Implemented | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ✅ Implemented | ⚙️ Sonnet | S |
| J12 | HCA re-homing to basis-scaled site-2 (absorbed into J15) | ✅ Implemented | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |
| J15 | Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor) | ✅ Implemented | 🧠 Opus | L |
| J16 | FUN_004e3860 net-advantage formula via objdump | ✅ Implemented | 🔮 Fable | S |
| J17 | Game-state foul coupling port (param_8 desperation + late-game fouling) | ⬜ Open | 🧠 Opus | M |
| J18 | Composite fidelity ports (bucketweights/teamquality vs the J6 formula map) | ✅ Implemented | 🧠 Opus | M |
| J19 | J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58) | ✅ Implemented | 🧠 Opus | M |
| J20 | Empty-FGA / within-possession restructure (Cov possession channel) | 🚫 Declined | 🧠 Opus | L |
| J21 | gt=4 playoff-margin overshoot audit (playoffNetMultiplier ×1.25) | ✅ Implemented | 🧠 Opus | S |
| J22 | Per-player rl_stl/rl_tov production-bundle wiring (PF dispersion) | ✅ Implemented | 🧠 Opus | M |
| J23 | round-half-up + base_time re-center (coupled pace faithful fix) | ✅ Implemented | 🧠 Opus | M |
| J24 | Possession-clock subsystem faithful port (step classes + jitter + arming) | ◑ Partial | 🧠 Opus | L |

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
**Location:** Engine turnover model vs 5.60; measured corr(volume, TOV/POSS) engine **+0.163** vs real **−0.176** (gap +0.339).
**Problem:** Real high-volume teams turn it over LESS per possession; the engine gives them MORE. An independent, sizeable fidelity bug — but the faithful fix RAISES FGA for inefficient teams and therefore **regresses** the count axis (deeper negative Cov). Filed as a bug, never as a count-axis fix.
**Direction:** RE 5.60's turnover generation for the coupling structure; sequence the port AFTER the J2 verdict so its wrong-direction pressure is priced in. A/B on the headline Cov required before shipping.
**Status (2026-07-08):** ⬜ Open. 🧠 Opus (RE + verdict); port likely ⚙️ Sonnet.

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
**Status (2026-07-13):** ⬜ Open — **bands sub-item VERIFIED post-J18** (`jsbcalibrate --mode gate` re-run on the post-J15+J18 engine, runs=20 stride=50: PASS, no literal change; provenance in `engine/internal/validate/bands.go` J18 block). **Leaders instrument (J13-2) SHIPPED** (2026-07-14, PR #1463 `6899910ea` — per-player leaders validation instrument); **cut-over ADR (J13-3) still open** — the only remaining J13 sub-item. Band derivation and per-player instrument are ⚙️-delegable; the acceptance judgment and cut-over ADR are 🧠.

### J14 AutoResearch eval-harness ADR (loop L9 companion)
**Location:** No harness exists; instrumentation groundwork (calibration walk ≈ 8 min full-corpus, freeze arms, channel-split tests) is merged. Cross-ref: [loop-engineering-backlog.md](loop-engineering-backlog.md) L9.
**Problem:** Engine iteration is human-paced despite an objective metric. The unresolved design tension — and why this is an ADR, not a script — is that a "perturb params, keep improvements" loop **conflicts with the faithfulness bar** (every shipped change must be RE-grounded in 5.60, not tuned to the corpus): the search space must be constrained to admitted stand-in constants and instrument-only measurements, never RE-pinned formulas.
**Direction:** ADR defining metric, legal parameter space (stand-ins only), acceptance rule, and how trial results feed RE prioritization rather than direct commits. Harness build afterward is ⚙️.
**Status (2026-07-08):** ⬜ Open. 🧠 Opus (ADR); ⚙️ Sonnet (harness).

### J15 Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor)
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` `defQuality`/`offQuality` + `possession.go` site-2 HCA + `engine/internal/validate` bands/goldens. Measured ground: `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md` §4/§6; faithful formulas: `jsb-J6-composite-scales-20260710.md`.
**Problem (re-scoped TWICE on 2026-07-10 — J2 then J6):** J2 proposed a *symmetric* program on J5's defQ ≡ 0 pin; **J6 overturned that pin**, so the faithful 5.60 pair is now statically known and roster-VARYING: **defQ = Σ five defenders' STL/MIN×44** (live +0xDD0), **offQ = Σ offense TOV/48 − HCA** (live +0xDE0 — TOV-coupled, so ADR-0061's `offQualityConstant = 1.575` and its volume-neutrality claim are unfaithful, and ADR-0082's shipped `defQuality` formula — floor1(OD)×0.25, neutral 8.21 — is the wrong composite entirely). Whether the away-side `≤ 0 → redraw` still dominates with live values is an EMPIRICAL question (the J2 corpus evidence — smooth winner-following FTA edge, no bimodality — bounds a deterministic arm but no longer proves it dead). What survives J2's A/B unconditionally: the FTA LEVEL must re-anchor to real 20.65 (37.8 shipped via A-relative gates), and HCA must re-home off the foul path (margin 3.44 → −0.06 under defQ0 while real = 4.12).
**Direction:** One program PR, now design-first: (1) port the faithful pair — defQ = Σ STL/MIN×44, offQ = Σ TOV/48 − HCA, in 5.60's units with the k-scale derived, not swept; (2) HCA re-homed to basis-scaled site-2 (J12 — target real margin 4.12; account for the `e88 → e90` and-one arm J16 identified); (3) FTA-level re-anchor against **real 20.65**, never the A-baseline; (4) band/golden re-derivation. **Prerequisite:** J19's escape-bound re-derivation with live AST/48 (J16's "unreachable" verdict is void until redone) and a static check of whether live defQ keeps the home arm non-positive. `/plan` with `auto_merge: false` (gate re-grounding is judgment).
**Risk if untouched:** every foul-share fidelity readout stays confounded, J2's final adjudication cannot run, and two shipped stand-ins (ADR-0061 offQ constant, ADR-0082 defQuality composite) keep wearing a faithfulness label J6 disproved.
**Status (2026-07-12):** ✅ Implemented — ADR-0084. Full detail in [archive](archive/jsb-native-backlog-archive.md).

### J16 FUN_004e3860 net-advantage formula via objdump
➜ J16 FUN_004e3860 net-advantage formula — ✅ Implemented (2026-07-10): formula + symmetry closures stand; reachability reopened under J19; see [archive](archive/jsb-native-backlog-archive.md).

### J17 Game-state foul coupling port (param_8 desperation + late-game fouling)
**Location:** `engine/internal/sim/possession.go:175` + `transition.go:137` — both `selectOutcome` call sites hardcode `shotClock=false`; 5.60's param_8 path (`+0x4c24 < 4` ⇒ outcomes restricted to {3pt, foul}, pinned in J5) is entirely unported, as is any late-game intentional-foul logic.
**Problem (found by J2 session 1's corpus instrument):** real 5.60's home/away FTA split (22.04/19.30) **follows the winner, not the side** — home-won games 23.25/18.03, visitor-won 20.28/21.15; margin-banded edge monotone −3.2 → +7.3. The bulk of the real FTA asymmetry is game-state-coupled (trailing teams foul late), a mechanism the Go engine lacks entirely. Also a candidate contributor to the residual FTADisp gap (1.51 vs ~1.0) and thus J2's residual Cov.
**Direction:** RE the game-state fouling surface (param_8 desperation is pinned; the late-game intentional-foul trigger is not), then port + wire real shot-clock/margin state into the two call sites. Acceptance: reproduce the corpus margin-banded FTA-edge curve; J4's per-quarter split is the sharper instrument once built. Sequence after J15 (foul bucket must be faithful first).
**Status (2026-07-10):** ⬜ Open — new (spawned by J2 session 1). 🧠 Opus (RE + verdict); port ⚙️ Sonnet.

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
**Status (2026-07-17):** ◑ Partial — the step-class subsystem SHIPPED (j24-possession-clock-port worktree PR): Phase 0 re-baselined the pre-port premise (corr −0.3722 at stride 4, n=4732 — replaces the stale June −0.42); Phase 0/1 binary-proved **u = CEngine+0x38 = 0.0** (prologue zero-stores; exhaustive modrm scan), so the FUN_004e4150 ratio is DEAD CODE and base_time is a per-matchup CONSTANT (16.0 ceiling) — the planned ratio port was correctly skipped and the additive stand-in retired; Phases 2–4 ported all three step classes (half-court jitter + {3..23} redraw; steal {0,1,2}s; DRB-push {2,3,4}s on the single-draw captured `transitionTriggers` gate, strategy_adj=0). **Phase 5 NO-GO** (archive smoke of record): the engine ARMS fast classes at ~29% of possessions vs real ~11.5%, so the faithful 16.0 center overshoots pace (114.68 poss/g) and the provisional `baseTimeMid` was re-centered 13.65→**17.7** (104.25 poss/g ≈ real 104.6); Var(lnPOSS) unmoved (0.000270 vs pre-port 0.000254; real 0.000721) and Cov(lnPOSS,lnPPS) still negative (−0.000055) — the class mix carries pace, not cross-team dispersion; the Var/Cov carrier remains unidentified. **Residual sub-steps (open):** (1) close the arming-share gap — likely gate the steal class like the transition run and/or re-derive the DRB gate rate; (2) trace the CEngine+0x30 forced-redraw flag writer; (3) pin `.lge +0x12c` strategy_adj; (4) walk `baseTimeMid` back to the faithful 16.0 once the share closes and re-run the Phase 5 gate. Full record: ADR-0085 J24 Update; instruments `basetimemid_sweep_archive_test.go` + `possessioncoupling_archive_test.go` J24 block. Per-class step-share counters (`FastClassAccum`, `engine/internal/sim/freeze.go`) and the archive diagnostic (`fastclass_share_archive_test.go`) added (j24-fast-class-instrument PR); status remains ◑ Partial pending arming-share gap closure.
**Status update (2026-07-18):** ◑ Partial — steal split (stealTurnoverScale 2.75e-5→1.69e-5 for steal-only rate; nonStealTurnover added for independent non-arming TOs) and faithful shot-value port (computeD64Base: D64 assembled at shot-time using twoPtBucketWeight as D90 — plan-architect misread D90 as 3GA/MIN×48, corrected; flow term +mq×250/D88 wired; blockMod with defBlkSum/leagueBlk48; shotValue3pt with per-player D80) SHIPPED (j24-mix-fixes-2-calibration PR). Archive result: steal 8.69% ✓ [8.0–9.0], indep-TO 4.83% ✓ [4.4–5.4], STL/g ✓, TOV/g ✓. FG% achievable 46.08% vs target [47.5, 48.9%] — gap is the flow term (mq≈0.1 with matchupQuality Phases 3/4 zeroed; closes automatically when +0x350/+0xDC8 defender loop and +0x33F0 strategy accumulator land in PR4/coaching). Phase 8 assertBand: steal/TOV/indep-TO gates pass; FG%, DREB, armed-fraction gates deferred. Residual open: (2) matchupQuality Phase 3/4 → FG% closure; (3) CEngine+0x30 forced-redraw flag; (4) .lge +0x12c strategy_adj; (5) baseTimeMid walkback to 16.0 post-share-closure; (6) **U{0..2} step-class taxonomy (open RE finding, unactioned):** `jsb-native/re-artifacts/jsb-J24-arming-share-RE-20260717.md` §1d argues the shipped U{0..2} class is 5.60's OREB quick-putback continuation, not steal — implying steal should merge into the code-7 U{2..4} path and a fourth step class (OREB putback, *excluded* from the code-7 share per §1d, so not itself a share lever) is unported. Verified ABSENT on master 2026-07-18 (`gameloop.go` step dispatch carries only steal / DRB-push / half-court; distinct from `putbackValue2pt` make-value, which IS on master). Reconcile against the §Problem THREE-class model before porting; bears on residual (1)'s steal-class share.
**Status update (2026-07-18c, +0x350 non-matched term SHIPPED — jsb-nonmatched-0x350 branch, J25):** residual (2) partially resolved — the Phase 3 NON-MATCHED `+0x350` term is now fully derived from the binary and live. The J16 "untraced setter family" caveat is retired: `FUN_00561c00`'s ten params (2–11) are pinned as UNGATED league per-48 rates over records 1–960 (season-class stat bank, writer `0x439d87`; p5 = FUN_0043bef0 mode-2 production composite/48), and the formula is `(A48 − p5) − B + C` — **+C**, correcting J16 §5's −C (raw FPU `de c1` FADDP is decisive over the Ghidra rendering). Full provenance: `jsb-native/re-artifacts/jsb-J25-nonmatched-0x350-20260718.md` (machine-local). Port: `engine/internal/backup/assemble.go` `computeNonMatchedTerm`/`computeLeagueNonMatchedParams` (+ hand-computed fixture test locking the +C sign). **Archive band result (`TestEndingMixBaseline`, `JSB_ARCHIVE_STRIDE=100 JSB_ARCHIVE_RUNS=4`, 30864 games): FG% = 46.42%, up from 46.19%, still OUTSIDE [47.5, 48.9]** (steal 8.70% ✓, indep-TO 4.84% ✓ unchanged). Honest read: +0x350 is faithful and live but its league-mean contribution is small — it is NOT the missing FG% lever alone; the remaining candidate is the Phase 4 `+0x33F0` accumulator (residual 4, `.lge +0x12c`). No constant was tuned toward the band.

**Status update (2026-07-18b, matchupQuality Phase 3/4 attempt — j24-matchupquality-phase34 branch):** ◑ Partial, residual (2) STAYS OPEN — this branch ported matchupQuality's Phase 3 MATCHED-defender term (`DefAST48` vs `LeagueAST48ByPos[slot]`, weight 0.8, +0xDC8/+0xDD0-class bundle fields, `bundle.go`/`plr.go`/`assemble.go`) and wired it into the live matched+non-matched accumulation loop in `matchup.go`, replacing the flat stub. By explicit scope decision the Phase 3 NON-MATCHED term (`+0x350`, `FUN_00561c00` setter chain, params 2–11) was **deferred to 0** — the RE artifact itself flags that setter chain as untraced/"optional J15 follow-up" (`jsb-native/re-artifacts/jsb-J16-fun004e3860-20260710.md` §5 L92-94) — and the Phase 4 accumulator stayed the pre-existing 0.0 stub (Branch B, `.lge +0x12c` unpinned). Unit tests (`go test ./internal/backup/ ./internal/sim/`) pass, confirming the matched-term wiring is correct in isolation. **Archive band result (`TestEndingMixBaseline`, `JSB_ARCHIVE_STRIDE=100 JSB_ARCHIVE_RUNS=4`, 30864 games): FG% = 46.19%, still OUTSIDE [47.5, 48.9]** (steal 8.70% ✓, indep-TO 4.82% ✓ both still pass). This is the expected result, not a bug: the matched arm `(DefAST48 − leagueAST48[slot])·0.8·fatigue` is mean-zero in expectation across defenders (deviation-2 comment in `matchup.go`), so it cannot materially move league-average FG% on its own — the actual FG%-moving lever is the deferred non-matched `+0x350` term, which stayed at 0. Residual (2) is therefore **not resolved**: FG% closure still requires porting `+0x350` (non-matched term) and/or the Phase 4 `.lge +0x12c` accumulator (residual 4/3, unchanged). No constant/weight was tuned to force the band shut, per this branch's explicit no-tune constraint.

**Status update (2026-07-19, Phase-4 usage-dominance-flag infrastructure PLUMBED but INERT — jsb-phase4-33f0 branch):** `computeUsageDominanceFlags` (per-team per-slot `[6]bool`, true = per-possession 2PA-rate ratio > 0.5) and the `matchupQuality` Phase-4 accumulator (raw positive `NonMatchedTerm` over usage-dominant defenders, gated off when the ball-handler is usage-dominant) are wired end-to-end, BUT both call sites (`possession.go`, `transition.go`) pass `[6]bool{}`, so `phase4 == 0` and there is no gameplay impact this phase. **Residual (4) is NOT resolved — it is reframed and DEFERRED** (see the J26 status update below): the numerator is a stated approximation of the still-unpinned `local_ac`, and the Go sim has no faithful analog of the per-slot usage flag, so activating it inline would ship an unfaithful term that the J26 ceiling probe shows overshoots the band. Archive run confirms steal ∈ [8.0, 9.0]% (8.70% ✓) and indep-TO ∈ [4.4, 5.4]% (4.84% ✓) still pass; FG% measured 46.42% (= J25 baseline, band [47.5, 48.9]% stays open). No constant tuned toward the band.

**Status update (2026-07-19, J26 — Phase 4 +0x33F0 gate PINNED, plan-slot premise REFUTED — jsb-phase4-33f0 branch):** residual (4)'s premise is **corrected, not resolved.** J26 (opcode re-trace of `jumpshot.exe` `4e45a0` + write-site enumeration of ALL twelve `0x6334` refs; `jsb-native/re-artifacts/jsb-J26-phase4-33f0-gate-20260719.md`, machine-local) establishes the Phase-4 accumulator `+0x33F0` is **NOT coaching-gated and NOT keyed on the `.lge +0x12c` strategy field**. The gate reads a per-slot **usage-dominance flag** `CEngine[+0x6334+slot*4] ∈ {0,4}` — its ONLY writers are `FUN_004e04e0` (shot-selection) `:96934`(=0)/`:96936`(=4); no writer ever sets 1/2/3, so the reader's `==3/==2/==1` branches are **DEAD** and `param_7` is inert. Flag=4 iff a player's per-possession usage ratio (2pt-attempt-weight ÷ Σ on-court `+0xD90`) > ~0.5 (outer 0.3 floor); the flag is coaching-INDEPENDENT and dynamic per-possession. So forced-neutral plans do NOT gate it, and the master-ref's "value 1-4 coaching strategy / `team*4` indexing" reading is refuted (master-ref §+0x33F0 corrected same day). **Verdict: LIVE but UNQUANTIFIED, NOT dead** — J26 ceiling probe (all defenders qualify) = FG% 49.25% vs 46.42% baseline (+2.83pp), OVERSHOOTS [47.5, 48.9], so the true term ∈ (0, +2.83pp] and Phase 4 IS a real FG% lever of unknown magnitude; **band [47.5, 48.9] stays OPEN.** **Not ported inline:** the Go sim has no analog that assembles the per-slot usage flag (`ballHandlerShare` uses static ratings, not the live usage ratio) — a faithful port is a per-possession subsystem threading a usage-flag array (both teams' slots) into `matchupQuality`, not the one-line accumulator the LIVE-case assumed. Deferred to a scoped follow-up `/plan` (`auto_merge: false`). `matchup.go` stays `phase4Accumulator = 0.0` (behavior unchanged; misleading "coaching-gated / +0x12c" comment corrected). No constant tuned toward the band; steal/indep-TO gates untouched. Residual (2) FG% closure now rests on the `+0x350` magnitude + this deferred usage-flag port; residual (4) reframed from "`.lge +0x12c` pin" to "per-possession usage-dominance-flag subsystem port."

**Status update (2026-07-19, gate-1 NO-GO synthesis — branch shelved at `c0ab7a8ef`):** Three disambiguation probes completed and closed. **(1) Marker ≡ Code7Push** bijectively (k=1.000, whole-binary byte sweep, zero undercount/overcount — `jsb-native/re-artifacts/jsb-J24-marker-code7-mapping-RE-20260719.md`). **(2) −1 formula byte-true** — FUN_0047f5a0 is 1-based (`inc %eax` @0x47f5bd); gate is `roll(1..18) ≤ TransOff − playoff + strategy_adj` with NO +1; shipped `TransOff+1` was a 0-based misread; strategy_adj ≡ 0 in IBL (FUN_005c5800 forces +0x12c=3; `jsb-native/re-artifacts/jsb-J24-transition-strategyadj-RE-20260718.md`). **−1 committed at `c0ab7a8ef`** (transition.go:76 only; NOT pushed, no PR). A/B result (`jsb-native/re-artifacts/jsb-J24-minus1-ab-20260719.md`): recent-era +1 → 13.92% (over, +0.95pp); −1 → 11.82% (under, −1.15pp vs real CI [12.80,13.14]). **(3) Poss/g denominator CLOSED** — real recent 05-08 = **216.58 ± 0.18 poss/g**, falls ~9.5 short of ≥226 threshold; corrects gap to **−0.707pp** (engine 11.82% vs corrected real 12.527%, CI floor 12.357% — `jsb-native/re-artifacts/jsb-J24-recent-poss-g-RE-20260719.md`). **Gate-1 NO-GO STANDS** (recent-era UNDER). Production-term decomposition (`jsb-native/re-artifacts/jsb-J24-minus1-decomp-20260719.md`): era slope carried entirely by mean picked on-court TransOff (3.967→5.658 → g_eff 0.2204→0.3146); armed fraction moves opposite (40.33→37.58%). Synthesis: `jsb-native/re-artifacts/jsb-J24-gate1-nogo-synthesis-20260719.md`. **Scoped next levers (J3 log measurement required):** (A) real recent armed fraction (engine 37.58%, needs ~41.1%); (B) real recent on-court-5 mean picked TransOff (engine 5.658, needs ~6.15 — branch-(a) residual, now inverted: real must sample HIGHER TO than uniform). By-design test failures deferred to future GO: `TestTransitionTriggers_Boundary` + `TestTransitionTriggers_PlayoffSpecialSub` (assert old `roll ≤ TransOff+1`) + TestGolden + two pace-pin tests (pre-existing).

**Status update (2026-07-20, PR4b decomposition complete — measurement-only, nothing committed):** E_time measured (instrumented sim rerun, reverted; 96 recent-era zips, stride 1, 4 runs, 442,668 games): **E_time = 5.6403**. Engine timing effect = E_time − E_armed = **−0.0026** (within ±0.02 noise) — engine arming is ~uniform over game-time. **Full gap decomposition (target 5.858 − E_armed 5.6429 = 0.2151):** real timing −0.0169 + minute-alloc +0.2346 + engine timing −0.0026 = **+0.2151** ✓. The +0.200 gap is entirely minute-allocation (R_time 5.8749 − E_time 5.6403 = 0.2346); no engine-timing lever. Artifact: `jsb-native/re-artifacts/jsb-J24-pr4b-rotation-quant-RE-20260719.md` (updated). **J24 implied-TO gap decomposition is COMPLETE.** Gate-1 NO-GO stands independently (armed% 37.58 vs 39.82% threshold); the implied-TO lever (PR4b = TransOff-aware rotation composition) is separately actionable but not sufficient alone for gate-1 GO.
