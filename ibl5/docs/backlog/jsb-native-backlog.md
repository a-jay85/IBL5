---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-21
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
J17 game-state foul coupling (✅ 2026-07-21, PR #1536) — param_8 + trailing-by-3 shotClock + leading-by-1-3 forcedMake ported; J17c doForcedMakeMax PINNED=10 (objdump); J17b +0x30 WRITER found, reader/port deferred → J24 residual (3)
J21 gt=4 playoff-margin audit (✅ 2026-07-14 — no overshoot, engine under-disperses globally) · J22 per-player STL/TOV bundle wiring (✅ 2026-07-16) — cut-over-gate fidelity inputs to J13; NEITHER is a Cov(lnFGA,lnPPS) lever
J23 round-half-up + base_time re-center (✅, 2026-07-16, #1495) — coupled faithful fix deferred from J21; ADR-0085 records the hold finding; shipped round-half-up + baseTimeMid re-center 14.5→13.65 (J23)
  └─→ J24 possession-clock subsystem port (◑ Partial) — step classes + jitter, steal split + faithful shotValue2pt/3pt, matchupQuality Phase-3 matched (+0xDC8) & non-matched (+0x350, J25) terms, FG% band CLOSED via +0xD58 penalty-minutes (#1544), and the §1d steal-gating partition (#1547) all SHIPPED. **Gate-1 code-7 arming share re-adjudicated WITHIN-NOISE (was "NO-GO vs floor 12.94" — a denomination artifact): 12.94 = markers/g 27.13 ÷ the all-era 209.2 poss/g, but the engine sims recent 05-08 rosters whose real poss/g is 216.58; re-denominated, master's 12.37% is INSIDE the recent between-season drift band [11.97, 12.54]. NOT a clean GO — master is −0.05pp under the tightest 2-season floor ~12.42 (SEM caveat). Gate-1 decided by ADR-0090 (J13-3 FINAL). See ADR-0088/0090.** Open residuals: (7) 3P undershoot ~2.8pp, (6) `.plb dc_minutes` wiring, (3) CEngine+0x30 reader (with J17b writer), (5) baseTimeMid walkback. See the J24 entry for the full current-state + NOT-A-LEVER trap list
J18 composite fidelity ports (✅ 2026-07-13 — all divergences merged; f/shrink port declined as documented divergence) · J19 J6-residue RE (✅ 2026-07-12) — both spawned by J6
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy). Zeroing defQ moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever; that A/B stands as measurement. **But J6 (same day) overturned the static premise underneath it:** J5's "defQ ≡ 0" was a store-enumeration blindspot — 5.60 builds the player record on the STACK (FUN_004cfa50 → FUN_00405970 write-back), so +0xDD0 (STL/MIN×44), +0xDE0 (usage-shrunk TOV/48), and +0xDC8 (AST/48) are all **live**. The faithful foul coupling is therefore roster-VARYING (defQ = Σ defenders' STL/MIN×44; offQ = Σ offense TOV/48 − HCA, TOV-coupled not volume-neutral), and J2's "symmetric U[0,0.6) both sides" verdict plus the J15 program must be re-adjudicated against the live-composite semantics before any port ships. "Mapped carriers exhausted" stays refuted; the map had a foul-path hole — and a method hole (see J6's caveat).

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 0 |
| 📋 Planned | 0 |
| ◑ Partial | 2 |
| ✅ Implemented | 21 |
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
| J7 | Turnover volume-coupling fidelity RE | ◑ Partial | 🧠 Opus | M |
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
**Location:** Engine turnover model vs 5.60; measured `corr(volume, TOV/POSS)` engine **+0.163** vs real **−0.176** (gap +0.339). RE artifact: `jsb-native/re-artifacts/jsb-J7-tov-coupling-RE-20260720.md` (machine-local, git-excluded).
**Mechanism (pinned 2026-07-20 — primary source master-ref :436-443, :478, :505-506):**
- **5.60:** `P(turnover)` = offense's own per-48 TOV-rate composite (`+0xDD8` = `TOV/MIN×48`), applied per-possession as a **volume-normalized share**: `TVR_rate / (total_shot_rate + TVR_rate)`. High shot-volume grows the denominator without touching the numerator → `P(TO)` diluted → `TOV/POSS` falls as volume rises → real **−0.176**. The per-48 normalization cancels in the ratio; what governs is the pure share, not rates. The fast-break conversion path (`:436-443`: `total_shot_rate / (total_shot_rate + TVR_rate)`) is a **secondary, same-signed channel** (high-volume teams also *convert* fast breaks more / lose fewer) — corroborated at :443, but it is NOT the turnover generator.
- **Go (`steal.go:82`):** `prob = stealTurnoverScale × (100−TVR_rating) × Σ(defender STL × fatigue)` — absolute product, no shot_rate in the denominator, no share. The offense's TOV-rate self-coupling is severed and replaced with a rating×opponent-steal-pool product that carries no roster shot/TOV anti-correlation → residual nets **+0.163**.
**Verdict:** Sign flip = **stat-vs-rating + self-vs-opponent** substitution in the turnover-probability anchor — the same `offQ`/`defQ` divergence pattern at `00_MASTER_REFERENCE.md:1488`. This is a **normalization-kind** mismatch, not a mechanical-competition difference (steal-before-shot competition exists in both engines — confirmed; not the discriminator). An independent, sizeable fidelity bug. Confirms and quantifies the June closure (`jsb-poss-channel-RE-20260613.md:77-80`).
**A/B (estimated):** A faithful port **regresses** the headline `Cov(lnFGA,lnPPS)` by ≈ **−0.0001..−0.0002** (from engine −0.000807 toward ≈ −0.0009..−0.0010, further from real +0.000269) — it raises FGA for high-volume/low-PPS teams, compounding with the empty-FGA anti-coupling. Confirm A/B on current master (post-J22/J24) before shipping. Filed as bug, never as count-axis fix.
**Port:** ⚙️ Sonnet from the pinned mechanism; sequence after J13 (count-axis fix must be in place before this regression is acceptable).
**Status (2026-07-20):** ◑ Partial — RE and verdict complete (artifact above). Port is a ⚙️ Sonnet follow-on, sequenced after J13.

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
**Status (2026-07-21):** ✅ Implemented — **bands sub-item VERIFIED post-J18** (`jsbcalibrate --mode gate` re-run on the post-J15+J18 engine, runs=20 stride=50: PASS, no literal change; provenance in `engine/internal/validate/bands.go` J18 block). **Leaders instrument (J13-2) SHIPPED** (2026-07-14, PR #1463 `6899910ea` — per-player leaders validation instrument); **cut-over ADR (J13-3) FINAL** — ADR-0090 Accepted (HOLD, corrected: 12.94 artifact overturned; master 12.37% inside drift band but −0.05pp under the era-matched 2-season floor ~12.42; #1572). Re-open when gate-1 DRBPushSharePct ≥ 12.42% or the 2-season CI recalibrates to cover 12.37% (ADR-0090 § Re-open criteria). Band derivation and per-player instrument are ⚙️-delegable; the acceptance judgment and cut-over ADR are 🧠.

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

**✅ Gate-1 code-7 arming share — re-adjudicated WITHIN-NOISE (2026-07-21; ADR-0088).** Measured on merged master (`84ff51085`; `TestFastClassArmingShareBaseline -tags archive`, recent-era 05-08, 97 snapshots, 85.77M possessions): **`DRBPushSharePct` = 12.37%** (10,608,830 code-7 / 75,158,396 half-court). The prior "band floor **12.94** → 0.57pp UNDER" NO-GO was a **denomination artifact**: 12.94 = real markers/g **27.13 ÷ the all-era 209.2** poss/g, but the engine sims recent 05-08 rosters whose real poss/g is **216.58** (box-score Dean-Oliver `FGA + 0.44·FTA + TOV − ORB`, n=2564g, CI [216.22, 216.93]). Re-denominated, the recent **between-season drift band is [11.97, 12.54]** (chunks 04-08 = [11.97, 12.47, 12.54, 12.53]) and master **12.37% is INSIDE**. **Verdict WITHIN-NOISE, NOT a clean GO** — master is −0.05pp under the tightest era-matched 2-season CI floor **~12.42** (1-season point 12.53%, bootstrap CI [12.374, 12.698]); this SEM caveat was decided by ADR-0090 (J13-3, Accepted 2026-07-21): **HOLD retained** (corrected — the 12.94 artifact is overturned, but master stays −0.05pp under the era-matched 2-season floor ~12.42, which is elected as the cut-over bar; re-open per ADR-0090 § Re-open criteria). The residual −0.05pp is *optionally* closeable via armed-fraction + on-court-`TransOff` composition (real recent armed ~41.1% vs engine ~40.34% post-PR4b; on-court-5 mean picked TransOff ~6.15 vs ~5.658; `jsb-native/re-artifacts/jsb-J24-armed-transoff-RE-20260719.md`), but the fatigue-sub *timing* sub-path is CLOSED (ceiling 12.61%, see NOT-A-LEVER) and the remaining `selectStarters` composition change breaks lineup faithfulness — so it is NOT pursued to force the band. No constant has been tuned toward the band; scope is "legible," not forced-GO.

**SHIPPED sub-work** (PR numbers below; `git log --all --grep '#<PR>'` resolves each merged commit): step classes + half-court jitter + {3..23} redraw; the `FUN_004e4150` ratio retired (u=CEngine+0x38 binary-proved 0.0 → dead code, base_time a per-matchup constant); steal split (`stealTurnoverScale` 2.75e-5→1.69e-5 + non-arming `nonStealTurnover`, #1519) + faithful `shotValue2pt`/`shotValue3pt`; matchupQuality Phase-3 matched-defender term (+0xDC8, #1522) and non-matched `+0x350` term (#1527, J25); **FG% band CLOSED** via the `+0xD58` position-penalty base-minutes port (`penaltyBaseMinutes` = `dc>0?dc:MIN/GP`, #1544 — FG% 46.42→48.29% in [47.5,48.9], now a HARD assert); **§1d steal-gating partition** (#1547 — steal-armed possessions run the SAME single `transitionTriggers` gate as DRB via `gs.stealPushFired`; survivors merge into the code-7 `DRBPushClass`; ungated `StealClass` deleted → code-7 is now one band-comparable quantity on master).

**Open residuals (NOT the cut-over blocker):**
- **(7) 3P undershoot ~2.8pp** vs the real-life 3P baseline (robust across the J3 corpus AND population frames) — the next frontier FG% lever, separate from gate-1 (missed shots rebound OFFENSIVELY, so FG% closure does NOT route through DREB arming).
- **(6) permanent `.plb dc_minutes` wiring** into the archive test's minutes input (MPG-fallback 48.29 → exact faithful 48.49; deferred only because the `.plb` reader isn't production-tested yet).
- **(3) CEngine+0x30 forced-redraw READER** — the writer is found (J17b #1536: three `movb $imm8,0x30(esi)` sites); the reader is pace territory, port it WITH the writer, not alone.
- **(5) `baseTimeMid` walkback 17.7→16.0** once the share closes — housekeeping, and NOT a share lever (below).

**Do NOT re-open — settled / NOT-A-LEVER (each with its discriminating proof).** *(The "< 12.94 floor" comparisons in the fatigue-sub bullet reference the superseded all-era 209.2 denomination; re-denominated to 216.58 the floor is the recent drift band [11.97, 12.54] (ADR-0088), but each lever's conclusion — it cannot move `DRBPushSharePct` — is denominator-independent and STANDS. The bullets below are preserved verbatim as historical proof.)*
- **+0x4be4 fast-break outlet pick** is byte-exact uniform over the on-court five and TransOff-blind (draw reads only const 5; TransOff read AFTER the pick, only in the gate) → the non-uniform-pick lever (branch (a)) is REFUTED in either +1/−1 sign; the +0.200 mean-picked-TO gap is minute-allocation only. `jsb-native/re-artifacts/jsb-J24-4be4-pick-disambig-RE-20260720.md`.
- **Fatigue-substitution energy-threshold tuning (minute-allocation lever B via sub *timing*)** cannot close gate-1 — the no-fatigue-sub *ceiling* is the hard max of on-court-`TransOff` (`E_time`), and it yields `DRBPushSharePct` = **12.61%**, still 0.33pp UNDER the 12.94 floor. Measured ceiling-first (`JSB_FATIGUE_ENERGY_THRESHOLD` sweep, recent-era, ~32M armed poss, reverted): thr 0 → 12.37% / `E_time` 5.870; no-sub ceiling → 12.61% / `E_time` 5.994 (≈ RE 5.984; displaced 30.5% ≈ RE 30.4%). The *most*-subbing config has the LOWEST `E_time`, so no-sub MAXIMIZES `E_time` ⇒ 12.61% is the absolute ceiling regardless of interior threshold, and share is invariant to the threshold (the armed-fraction coupling that surprised PR4b is refuted here). NB the plan's `{0,−3,…,−15}` sweep was INERT: energy is *seconds-drained* (~17s/possession, unfloored; a never-subbed starter floors near Stamina−1440), so the disabling ceiling needs a threshold below ~−1400, not single digits. PR4b (`bestFatigueBackup`) already consumed the recoverable minute-allocation gap; the residual on-court-`TransOff` path now needs a `selectStarters` composition change (breaks lineup faithfulness), NOT sub timing. Durable log: machine-local `project_j24_arming_share_nogo.md`.
- **U{0..2} / OREB quick-putback reclassification** — IMPLEMENTED and refuted on shelved branch `531a3e677` (unified arm-flag + `orebTripClock`); its own Phase-7 measured code-7 15.41% and diagnosed the excess as the ending-share (arming population), NOT the clock reclass. Done, not a gate-1 lever (2026-07-18). The make-value `putbackValue2pt` IS on master; only the non-lever clock-step is unported.
- **OREB-rate trim** moves armed share the WRONG way (+0.76pp) — trimming OREB converts continuations into DREB endings, which arm at ~94%.
- **+1/−1 transition basing** — SETTLED byte-true: master's `transition.go` gate is `roll(1..denom) ≤ TransOff − specialSub`, NO spurious +1; strategy_adj ≡ 0 in IBL (FUN_005c5800 forces +0x12c=3). The shelved +1 was its unified-arming rewrite, not master. `jsb-native/re-artifacts/jsb-J24-transition-strategyadj-RE-20260718.md`.
- **marker ≡ Code7Push** bijective (k=1.000, whole-binary byte sweep, zero over/undercount) → the log marker and the engine code-7 event are the same thing; the recent-era band is legitimate. `jsb-native/re-artifacts/jsb-J24-marker-code7-mapping-RE-20260719.md`.
- **Var/Cov** PASS under the recent-era re-spec (the all-era ceiling was biased — the same flaw the Var/Cov gates carried). Gate-1 is the ONLY blocker.
- **`baseTimeMid` walk (17.7→16.0)** cannot close gate-1 — share is flat-to-rising as btm drops.
- **Phase-4 usage-dominance flag (+0x33F0)** is NOT an FG% lever — numerator objdump-pinned to +0xD90 = `twoPtBucketWeight`; the faithful flag fires 0.0005%, FG% +0.01pp (#1541, corrected for faithfulness only, INERT). `jsb-native/re-artifacts/jsb-fgpct-phase4-numerator-pin-20260720.md`.
- **Phase-3 matched-defender term** is NOT an FG% lever — `(DefAST48 − leagueAST48[slot])·0.8` is mean-zero across defenders; ported faithfully it moved FG% only 46.08→46.19%.
- **J7 faithful TOV port** is NOT a count-axis fix — the faithful share port REGRESSES Cov(lnFGA,lnPPS) by ≈ −0.0001..−0.0002 (away from real +0.000269); file as an independent fidelity bug, sequence after J13.
- **Master pre-partition fastclass counters** — the 9.48%/18.44% DRB-only / steal-union split, and the shelved-branch 13.92%/11.82% at +1/−1 — are NOT band-comparable and are now MOOT post-#1547; the only band-comparable quantity is the merged `DRBPushSharePct` (12.37%). Do NOT resurrect them.

**Residual (1) status:** partition complete (#1547); gate-1 re-adjudicated **WITHIN-NOISE** (re-denominated 209.2 → 216.58; master 12.37% inside drift band [11.97, 12.54]; −0.05pp under the tightest 2-season floor ~12.42, NOT a clean GO — ADR-0088). **RESOLVED by ADR-0090** (J13-3 cut-over ADR is FINAL — HOLD-corrected: hold retained, re-open per ADR-0090; #1572).
