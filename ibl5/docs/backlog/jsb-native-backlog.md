---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-13
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
              └─→ J2 verdict: SHIPPABLE with residual → successor = J20 empty-FGA restructure (J4 ✅ 2026-07-12 feeds it) → J13 (unblocked)
J17 game-state foul coupling (⬜, new 2026-07-10) — real 5.60 mechanism the engine lacks entirely
J18 composite fidelity ports (◑ — formula divergences shipped; f/shrink pending) · J19 J6-residue RE (✅ 2026-07-12) — both spawned by J6
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy). Zeroing defQ moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever; that A/B stands as measurement. **But J6 (same day) overturned the static premise underneath it:** J5's "defQ ≡ 0" was a store-enumeration blindspot — 5.60 builds the player record on the STACK (FUN_004cfa50 → FUN_00405970 write-back), so +0xDD0 (STL/MIN×44), +0xDE0 (usage-shrunk TOV/48), and +0xDC8 (AST/48) are all **live**. The faithful foul coupling is therefore roster-VARYING (defQ = Σ defenders' STL/MIN×44; offQ = Σ offense TOV/48 − HCA, TOV-coupled not volume-neutral), and J2's "symmetric U[0,0.6) both sides" verdict plus the J15 program must be re-adjudicated against the live-composite semantics before any port ships. "Mapped carriers exhausted" stays refuted; the map had a foul-path hole — and a method hole (see J6's caveat).

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 8 |
| 📋 Planned | 0 |
| ◑ Partial | 1 |
| ✅ Implemented | 11 |
| 🚫 Declined | 0 |

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
| J8 | Transition trigger denominator 18 | ⬜ Open | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ✅ Implemented | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ⬜ Open | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ⬜ Open | ⚙️ Sonnet | S |
| J12 | HCA re-homing to basis-scaled site-2 (absorbed into J15) | ✅ Implemented | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |
| J15 | Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor) | ✅ Implemented | 🧠 Opus | L |
| J16 | FUN_004e3860 net-advantage formula via objdump | ✅ Implemented | 🔮 Fable | S |
| J17 | Game-state foul coupling port (param_8 desperation + late-game fouling) | ⬜ Open | 🧠 Opus | M |
| J18 | Composite fidelity ports (bucketweights/teamquality vs the J6 formula map) | ◑ Partial | 🧠 Opus | M |
| J19 | J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58) | ✅ Implemented | 🧠 Opus | M |
| J20 | Empty-FGA / within-possession restructure (Cov possession channel) | ⬜ Open | 🧠 Opus | L |

### J1 Faithful foul-bucket pair port
➜ J1 Faithful foul-bucket pair port — ✅ Implemented (2026-07-10): see [archive](archive/jsb-native-backlog-archive.md).

### J2 Count-axis carrier adjudication (post-J1)
➜ J2 Count-axis carrier adjudication — ✅ Adjudicated (2026-07-12): SHIPPABLE verdict; successor = J20 empty-FGA restructure (J4 ✅); see [archive](archive/jsb-native-backlog-archive.md).

### J3 Per-origin efficiency identifiability (IBL5.log)
➜ J3 Per-origin efficiency identifiability — ✅ Implemented (2026-07-09): study complete; J4 unblocked with spec; see [archive](archive/jsb-native-backlog-archive.md).

### J4 Play-by-play extraction parser
➜ J4 Play-by-play extraction parser — ✅ Implemented (2026-07-12): 23,714/23,714 games parsed, 100% sentence closure; feeds J20 + J17 instruments; see [archive](archive/jsb-native-backlog-archive.md).

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
**Location:** `engine/internal/sim/transition.go` `transitionTriggerDenom = 20` (comment admits "unpinned stand-in") vs asm-confirmed 5.60 value **18** (`push 0x12` before the rand_int call).
**Problem:** Engine under-fires transition ~11% with a ~10%-too-shallow TransOff slope. Like J7 this corrects fidelity while nudging the count-axis headline the WRONG way (more transition volume → more dilution).
**Direction:** One-constant port + A/B (expect a small negative headline nudge; ship on fidelity grounds with the A/B in the PR). Sequence after the J2 verdict.
**Status (2026-07-08):** ⬜ Open. ⚙️ Sonnet build + 🧠 Opus A/B sign-off.

### J9 League-baseline faithful port (FUN_004385f0)
➜ J9 League-baseline faithful port — ✅ Implemented (2026-07-12): PR #1437; see [archive](archive/jsb-native-backlog-archive.md).

### J10 `.plb` minutes reader + stamina=100 bundle fix
**Location:** `engine/internal/backup` bundle assembly — `DCMinutes` zeroed and stamina defaulted to 0 at assemble time (line refs drift; grep `assemble.go` for the two TODO sites).
**Problem:** Zeroed `dc_minutes` flattens rotation selection (the engine uses it in lineup quality scoring); stamina 0 is a degenerate energy ceiling where 5.60's faithful value is a uniform 100 (verified: `.plr` offset 546 constant across all players; no per-player stamina exists in JSB).
**Direction:** Add a Go `.plb` reader (32 team lines × 30 slots × 12 chars; slot→player via the ordinal formula in `ibl5/classes/PlrParser/PlrOrdinalMap.php`), wire `DCMinutes` into the bundle, set stamina ceiling to constant 100. One PR; recipe fully specified in the acceptance-bar record.
**Status (2026-07-08):** ⬜ Open. ⚙️ Sonnet.

### J11 Season-selection min-GP guard
**Location:** `engine/internal/calibrate` season selection (season.go) — picked TRUNCATED snapshots for 5/19 seasons (medGP 3–46 instead of ~82) when last measured.
**Problem:** Silently biases any committed standings gate that walks the archive.
**Direction:** Verify first — later corpus-completeness work (era-dependent complete-season counts, medGP normalization) may already cover it; if not, add the guard + a regression test. Measurement recipes in the re-artifacts assume it is still open.
**Status (2026-07-08):** ⬜ Open (verify-first). ⚙️ Sonnet.

### J12 HCA re-homing to basis-scaled site-2 (absorbed into J15)
**Location:** `engine/internal/sim/possession.go` `s2 + hca` (the site-2 2pt-composite nudge).
**Problem (re-scoped by J2 session 1):** the foul path was carrying ~ALL engine home margin (3.44 → −0.06 under defQ ≡ 0) — unfaithfully. 5.60's static HCA site-2 is `e88 += s·0.2`; Go applies raw ±0.2 on a ~16.5 basis (~1.2%). If site-2 is in raw bucket units like the foul pair was, the faithful proportional effect is ~8.6× larger (~10%) — ADR-0082's flagged under-scaling caveat, now load-bearing. Whether the foul-side `−s·0.2` also expresses depends on the live-composite home-arm question J6 reopened (J15 prerequisite) — the made-bucket nudge is live either way. Real corpus home margin: 4.12.
**Direction:** Executes inside J15 (the margin gate can't pass without it). The J6 composite-basis pins are now the faithful ground for the scale; corpus home-margin re-measure is the acceptance check. Win-share caveat stands: tune margin_gap, compare win share only at `--runs 1`.
**Status (2026-07-10):** ⬜ Open — absorbed into J15's program scope. 🧠 Opus.

### J13 Cut-over package: bands, leaders, decision
**Location:** `engine/internal/validate/bands.go` (placeholder ±15%, explicitly non-authoritative); per-player leaders validation (never built); the standings-residual gate (floor ≈ 3–5 wins / ~0 ppg); Var(lnPPS) sits ~2% under real as a monitor-only watch item.
**Problem:** Even with the dispersion blocker resolved, cut-over needs authoritative bands derived from the archive, a per-player sanity layer, and the actual go/no-go decision (env-flag swap of the jumpshot.exe invocation, `.sco` import path kept for one-command rollback; SHADOW as the live distributional check).
**Direction:** Gated on the J2 verdict. Band derivation and the leaders instrument are ⚙️-delegable; the acceptance judgment and the cut-over ADR are 🧠.
**Status (2026-07-12):** ⬜ Open — **unblocked** (J2 verdict in: SHIPPABLE with documented residual). Band derivation and per-player instrument are ⚙️-delegable; the acceptance judgment and cut-over ADR are 🧠.

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
**Status (2026-07-12):** ✅ Implemented — faithful defQ/offQ pair + basis-scaled HCA (margin → paired .sco 3.32) + FTA re-anchor (37.8 → paired .sco 21.32) + kept self-scaling bands + regenerated golden; ADR-0084. 🧠 Opus.

### J16 FUN_004e3860 net-advantage formula via objdump
➜ J16 FUN_004e3860 net-advantage formula — ✅ Implemented (2026-07-10): formula + symmetry closures stand; reachability reopened under J19; see [archive](archive/jsb-native-backlog-archive.md).

### J17 Game-state foul coupling port (param_8 desperation + late-game fouling)
**Location:** `engine/internal/sim/possession.go:175` + `transition.go:137` — both `selectOutcome` call sites hardcode `shotClock=false`; 5.60's param_8 path (`+0x4c24 < 4` ⇒ outcomes restricted to {3pt, foul}, pinned in J5) is entirely unported, as is any late-game intentional-foul logic.
**Problem (found by J2 session 1's corpus instrument):** real 5.60's home/away FTA split (22.04/19.30) **follows the winner, not the side** — home-won games 23.25/18.03, visitor-won 20.28/21.15; margin-banded edge monotone −3.2 → +7.3. The bulk of the real FTA asymmetry is game-state-coupled (trailing teams foul late), a mechanism the Go engine lacks entirely. Also a candidate contributor to the residual FTADisp gap (1.51 vs ~1.0) and thus J2's residual Cov.
**Direction:** RE the game-state fouling surface (param_8 desperation is pinned; the late-game intentional-foul trigger is not), then port + wire real shot-clock/margin state into the two call sites. Acceptance: reproduce the corpus margin-banded FTA-edge curve; J4's per-quarter split is the sharper instrument once built. Sequence after J15 (foul bucket must be faithful first).
**Status (2026-07-10):** ⬜ Open — new (spawned by J2 session 1). 🧠 Opus (RE + verdict); port ⚙️ Sonnet.

### J18 Composite fidelity ports (bucketweights/teamquality vs the J6 formula map)
**Location:** `engine/internal/sim/bucketweights.go` + `teamquality.go` — every divergence from the J6-pinned formulas, now enumerable against `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md` §2/§6.
**Problem:** J6 turned "unpinned stand-in" into "known-divergent." Confirmed divergences: (1) `bucketweights.go` comments (~:19, ~:180) assert "+0xDB0 is DEAD (always 0)" — false, it's usage-shrunk 3GA/MIN×48, so `threePtBucketWeight` needs re-derivation from the live composite (also a candidate for J2's residual: FTADisp 1.51, and the 3pt weight sits in the foul-share denominator); (2) `d70LeagueScalar = 1.0` (~:77) where 5.60 uses **S = (leaguePF48×5 − leagueTOV48×0.5)/(leagueFTA48×5)**, exactly computable from the FUN_004385f0 table the engine already models; (3) `d88` (~:159) uses `RealLifeFGA` where 5.60 uses **2PA** (and f-projected inputs); (4) the "+0xDE0 dead ⇒ foul floors" comment (~:85) premise is wrong; (5) `teamquality.go` faithful formulas per J15; (6) the `param_6` net-advantage foul shrink `e80 ×= 1 − param_6/(4·leagueTOV48)` (J16 exact identity, applied in 5.60 after the coupling and before the ≤0 redraw, both param_5 paths) is UNPORTED — the Go `netAdvantage` feeds shot_value only (found at J15 ratification, 2026-07-12; recorded in ADR-0084 Decision 2). Porting it changes foul share globally and re-opens the level/margin anchors, so it needs its own A/B. Open modeling question: whether to port the `f = (Confidence + rec[+0x18] + 95)/200` ±2% projection modulation and the pass-2 usage shrink, or accept them as documented divergences (they need Confidence + the +0x18 marker in the bundle).
**Direction:** Foul-adjacent pieces (2)(4)(5) execute inside J15. The shot-mix pieces (1)(3) and the f/shrink question are a follow-on A/B'd PR — each changes bucket weights globally, so headline Cov + Var gates must be re-measured per change, not batched blind. Comment corrections ride the first PR that touches each file.
**Update (2026-07-12):** All enumerated divergences ported/fixed, each individually A/B'd: (1) threePtBucketWeight = faithful 3GA/MIN×48 (PR #1438); (2) d70LeagueScalar = 0.6472241372826754, S computed exactly from the J9-validated league table (PR #1436); (3) d88 = 2PA/48 (PR #1439); (4) fixed in J15 (#1432); (5) in J15; (6, discovered during J16) :97164 mq foul shrink (PR #1435). Cov unchanged in every A/B — confirming the J2 s2 finding that shot-mix ports are fidelity work, not Cov levers. Follow-up shipped: foulBucketScale re-anchored 0.47 → 0.40 (PR #1440, its documented maintenance contract after the basis shrink); one more anchor check needed after the two stacks (#1436 vs #1437-40) merge. Ports live on two stacks and both edit `bucketweights.go` — expect a merge conflict.
**Status (2026-07-12):** ◑ Partial — all six formula divergences shipped (PRs #1435–#1440, stacked, pending merge); remaining: the f = (Confidence + rec[+0x18] + 95)/200 projection-modulation / pass-2 usage-shrink port-vs-document question (🧠 user decision — J19's rec[+0x18] evidence favors documenting as divergence).

### J19 J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58)
**Location:** `jsb-native/` decompile + PE; open items recorded in `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md` § still-open.
**Problem:** J6 left four bounded unknowns, two of them load-bearing: (1) **J16 escape-bound re-derivation with live AST/48** — blocks J15's design (is the home arm's `≤ 0 → redraw` still the dominant path with defQ = Σ STL/MIN×44 and a sign-varying matched term?); (2) **rec[+0x18] in-season semantics** — pinned = 100 at reset, but constant-vs-decay determines `f`'s real spread (±2% vs wider) and whether the f-port matters (J18); (3) energy-formula operand identities (slots 0x1c/0x64/ebx in asm 4d4711–4d4774); (4) +0xD58 — computed and stored (4d42df) but no reader found; confirm dead or find the reader. Also parked here: the transition-retention re-trace (+0xDA0/+0xDA8 live for 3pt shooters — the master ref's vestigial claim was premise-corrected but the downstream retention path was never re-walked).
**Direction:** Item (1) is arithmetic over already-pinned formulas + IBL5.plr distributions — do it first, it unblocks J15. Items (2)–(4) + the retention re-trace are one objdump session, precedented method. Escalate to 🔮 only if the asm hits the NaN/FPU-flag class.
**Status (2026-07-12):** ✅ Implemented — the load-bearing item (1), J16's escape-bound re-derivation with live AST/48, was resolved inside the J15 program (ADR-0084: the deterministic home arm is effectively-not-perfectly non-positive; live +0xDC8 AST48 shaved the barrier ~15%, residual 57.16 → 48.42, redraw still dominant for any realistic roster). Bounded residue spun forward as a follow-on objdump session — item (2) rec[+0x18] in-season decay semantics, (3) energy-formula operand identities (asm 4d4711–4d4774), (4) +0xD58 dead-vs-reader confirm, plus the +0xDA0/+0xDA8 transition-retention re-trace. 🧠 Opus (🔮 only if the asm hits the NaN/FPU-flag class).

### J20 Empty-FGA / within-possession restructure (Cov possession channel)
*(discovered 2026-07-12 during J2 session-2 adjudication)*
**Location:** Engine possession loop — the empty-FGA retry structure (`engine/internal/sim/gameloop.go` shot-decision path); evidence in `jsb-native/re-artifacts/jsb-J2s2-cov-adjudication-20260712.md` and the J4 corpus measurements (`jsb-native/re-artifacts/j4-parser-20260712/`, machine-local).
**Problem:** The possession-count channel carries **81% of real gt2 Cov** (+0.000498 of +0.000612) and is the only channel that can flip the engine's sign — the shot-mix channel is arithmetically capped at −0.000012 ≤ 0 even fully faithful (J2 s2), triple-confirmed by the J18 A/Bs (Cov unchanged in every port). The engine's empty-FGA retry loop over-disperses shots-per-possession and dilutes realized PPS on high-volume teams (corr −0.42) where 5.60 does not. Successor to ADR-0042's open item; the ADR-0054 possession budget constraint is generator-independent and binds any redesign.
**Direction:** Design against J4's real per-origin ground truth (initial/putback/transition FGA shares + per-origin efficiency): restructure how empty possessions/retries generate FGA so within-possession dispersion matches the corpus, without breaking the possession budget or the fta/margin anchors. Needs its own RE pass on 5.60's possession flow + a `/plan`; A/B gates = gt2/gt4 Cov, fta_per_g, margin. Sequence J7 (turnover coupling) with it — J7's faithful fix pressures Cov the wrong way and should be priced into the same adjudication.
**Status (2026-07-12):** ⬜ Open — unblocked (J4 ✅). 🧠 Opus design + adjudication; escalate 🔮 Fable (user-gated) only if the asm possession-loop derivation hits the refuted-premise class.
