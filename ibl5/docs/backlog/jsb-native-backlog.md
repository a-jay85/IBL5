---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-10
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
J1 faithful foul pair (✅ 2026-07-10, ADR-0082) ─→ J2 adjudication session 1 (◑ 2026-07-10): carrier NAMED
  └─→ J15 symmetric foul-bucket program (⬜ — THE next engine PR)
        ├─ absorbs J12 (HCA re-homes to basis-scaled site-2)
        ├─ J16 ✅ (2026-07-10): escape CLOSED (needs param_6 > 13.4, unreachable) — dead-arm conclusion STANDS
        └─→ J2 session 2 (re-measure) → residual hunt: J6 composite scales → J4 per-origin (spec ready) → J13
J17 game-state foul coupling (⬜, new 2026-07-10) — real 5.60 mechanism the engine lacks entirely
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — now has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy) with an invented opponent-OD coupling on top (the home arm J5 proved dynamically dead in 5.60). Zeroing defQ alone moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever. "Mapped carriers exhausted" is refuted; the map had a foul-path hole.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 11 |
| 📋 Planned | 0 |
| ◑ Partial | 2 |
| ✅ Implemented | 4 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Tier | Effort |
|---|-------|--------|------|-------:|
| J1 | Faithful foul-bucket pair port | ✅ Implemented | ⚙️ Sonnet | M |
| J2 | Count-axis carrier adjudication (post-J1) | ◑ Partial | 🔮 Fable | L |
| J3 | Per-origin efficiency identifiability (IBL5.log) | ✅ Implemented | 🔮 Fable | M |
| J4 | Play-by-play extraction parser | ⬜ Open | ⚙️ Sonnet | M |
| J5 | Unpinnable-claims sweep + static closures | ✅ Implemented | 🔮 Fable | M |
| J6 | 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60) | ◑ Partial | 🧠 Opus | M |
| J7 | Turnover volume-coupling fidelity RE | ⬜ Open | 🧠 Opus | M |
| J8 | Transition trigger denominator 18 | ⬜ Open | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ⬜ Open | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ⬜ Open | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ⬜ Open | ⚙️ Sonnet | S |
| J12 | HCA re-homing to basis-scaled site-2 (absorbed into J15) | ⬜ Open | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |
| J15 | Faithful symmetric foul-bucket program (defQ≡0 + HCA re-homing + level re-anchor) | ⬜ Open | 🧠 Opus | L |
| J16 | FUN_004e3860 net-advantage formula via objdump | ✅ Implemented | 🔮 Fable | S |
| J17 | Game-state foul coupling port (param_8 desperation + late-game fouling) | ⬜ Open | 🧠 Opus | M |

### J1 Faithful foul-bucket pair port
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` (ADR-0061's `offQualityConstant = 1.575` corpus stand-in). Plan: `$HOME/.claude/plans/jsb-faithful-foul-pair.md` (written 2026-07-08, `impl_model: sonnet`, `auto_merge: true`).
**Problem:** The stand-in is structurally unfaithful — it couples BOTH teams' foul weights to defense at ~0.38, where the statically-pinned 5.60 behavior is an asymmetric pair: HOME = deterministic defense-coupled weight `(defQ − (5/6)·teamDef)/5 + 0.2`; AWAY/NEUTRAL = a stochastic `U[0, 0.6)` redraw with zero coupling. This is also why ADR-0061's GATE-1 (±0.5 home margin) was proven unsatisfiable in the healthy foul range.
**Result:** Merged 2026-07-10 (PR #1395, ADR-0082, k = 8.6 pair). Count-axis effect ~3% of the Cov gap (gt2 −0.000807 → −0.000774); sign survived, arming J2.
**Caveat (found by J2 session 1, same day):** the plan predated J5's defQ ≡ 0 pin by one day and automouse ran it without integrating the refutation — the shipped deterministic home arm is **dynamically dead in 5.60** (defQ = 0 drives it non-positive; the faithful redraw fires ⇒ symmetric U[0,0.6) both sides). Also, the k-sweep's A-relative gates calibrated the pair to reproduce the pre-existing 1.8× FTA-level inflation (37.8 vs real 20.65/g). Both are corrected by J15.
**Status (2026-07-10):** ✅ Implemented — structure premise superseded same-day by J5+J2; see J15.

### J2 Count-axis carrier adjudication (post-J1)
**Location:** The wrong-signed Cov(lnFGA,lnPPS) — the sole remaining cut-over blocker (PF dispersion ≈ ½ real while level and offense-coupling are fixed).
**Problem:** The mapped search space is exhausted by measured NULLs: foulCompress, Branch-B, make-value variance and form (ADR-0053/0055), offVolumeScale and base_time form (ADR-0054 + RE), putback resolution, ORB intensity/level/retry-count (ADR-0056–0060, RE-faithful), transition gate (RE-faithful), the POSS channel (closed 2026-06-13 as a projection of the PPS-realization inversion, not a separable carrier), and TOV (an independent bug that goes the *wrong* direction). J1 spends the last mapped carrier. If the sign survives J1, naming the residual carrier — or ruling the model terminal-vs-shippable — is hypothesis generation over a refuted-premise space, the class Opus has repeatedly bounced off and Fable has cracked.
**Adjudication session 1 (🔮 Fable, 2026-07-10 — full record `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md`):** Both escape hatches failed post-J1 (sign survives at −0.000774; wins_resid_p50 7.75). Verdict: **not terminal — the exhausted-map premise is refuted.** The dominant carrier is a *mechanical* Cov injection from unfaithful foul share (PPS counts FT points while foul plays displace FGA), fed by two unfaithful sources: the 1.8× FTA level (legacy) and the invented OD-coupled home arm (dead in 5.60 per J5). Measured: defQ ≡ 0 full-corpus A/B moved gt2 Cov −0.000774 → **−0.000340** (real +0.000269; gt4 −0.001027 → −0.000365), FTA/g 37.8 → 23.3 (real 20.65), FTADisp 2.02 → 1.51 — while unmasking deficits the phantom FTA was paying for: margin 3.44 → −0.06 (real 4.12), level_gap_pf −2.27 → −4.05, Var(lnPPS)/Var(lnFGA) now UNDER real. New corpus instrument: real home/away FTA split (25,892 games) = 22.04/19.30 (1.142×), edge follows the *winner* (game-state fouling — J17); engine at master = 48.6/22.1 (2.20×). Explains ADR-0043's foul-only freeze arm carrying 47.6% of |Cov| and the inverted corr(realized PPS, roster FGP) = −0.14.
**Direction:** Land the J15 program (J16 ✅ 2026-07-10 — the only static escape from the dead-arm conclusion is closed), then **session 2**: re-run the channel split on the faithful engine. Residual (−0.00034) hunt order: J6 composite-scale pins (foul-share *denominator* dispersion — FTADisp still 1.51) → J4 per-origin decomposition → then terminal-vs-shippable if the sign still holds.
**Status (2026-07-10):** ◑ Partial — carrier named, measured, mechanism identified; final adjudication re-runs post-J15. 🔮 Fable.

### J3 Per-origin efficiency identifiability (IBL5.log)
**Location:** IBL5.log — 1.1 GB, **22,798 games** ≈ 19–20 seasons of 5.60 play-by-play (machine-local; earlier "23,714" was a miscount).
**Result (Fable session, 2026-07-09):** **Identifiable.** The stamp grammar is a *closed* 509-template table compiled into jumpshot.exe `.data` (va 0x6ac184–0x6b0a3c); a template matcher achieved **100.00% sentence closure over the full corpus (22,797 parsed games, 15.6M sentences, 0 unmatched)**. Load-bearing pins, all static: (1) transition possessions are marked **deterministically** (FUN_004ee320 first-trip guard; RNG selects phrasing only) — P(miss)=P(false)=0, ~24.5 markers/game ⇒ ~1,000 transition possessions/team-season; (2) rebound side is text-partitioned between the ORB/DRB handlers except one shared string (`"Rebounded by %s."`, ~2.6% of rebounds, resolved exactly by roster join); (3) putback = sequence rule (ORB → same-team shot; putback text is flag-gated, sufficient-not-necessary), matching engine origin semantics (`possession.go`/`transition.go`: transition tags all trips, putback = trip>0 half-court); (4) running score after every stamp (5,320,495 == 5,320,495) gives per-event ground-truth points. Power: se(PPS_transition) ≈ 0.033/team-season vs total lnPPS sd ≈ 0.038 → split-half disattenuation over ~500 team-seasons, se(corr) ≈ 0.046. Full study + **J4 build spec**: `jsb-native/re-artifacts/jsb-pbp-identifiability-J3-20260709.md` (parser `j3_study.py` + full-corpus output preserved alongside).
**Status (2026-07-09):** ✅ Implemented — study complete; J4 unblocked with spec.

### J4 Play-by-play extraction parser
**Location:** New machine-local tooling consuming IBL5.log per the J3 spec (`jsb-native/re-artifacts/jsb-pbp-identifiability-J3-20260709.md` § "J4 build spec"; the study parser `j3_study.py` next to it is ~80% of the matcher).
**Problem:** None — mechanical half of J3, now fully specified: segmenter (header regex + digit-boundary team/score split + season-by-date-rollback), 509-template matcher (gate: 100% closure — any unmatched sentence is a parser bug), roster-join attribution, possession state machine with engine-aligned origin rules.
**Direction:** Build to spec. Machine-verifiable gates: per-game Σ score-deltas == header final; **per-player per-game reconciliation against `ibl_box_scores`** (1988–2008, ~606K rows — stronger than the `.sco` season-total recon originally envisioned); Σ origin FGA == total FGA. Output: team-game per-origin {FGA,FTA,PTS,PPS} CSV → team-season demeaned couplings → J2's per-origin decomposition. **Spec addition (J2 session 1):** also emit home/away × per-quarter FTA — the clean instrument separating the static home-FTA component (~+1–2, confounded by FTA→margin reverse causality in box-score data) from game-state late-game fouling (J17), and the acceptance instrument for the static home-FTA channel — which J16 (2026-07-10) re-homed from param_6 (refuted: side-symmetric) to the site-2 HCA `e88 → e90` and-one feed.
**Status (2026-07-09):** ⬜ Open — **unblocked** (J3 ✅). ⚙️ Sonnet build; 🧠 Opus owns the J2 decomposition readout.

### J5 Unpinnable-claims sweep + static closures
**Location:** `jsb-native/jsb_560/` master reference + decompile + `jumpshot.exe` PE. Full record: `jsb-native/re-artifacts/jsb-J5-static-closures-20260709.md`.
**Result (Fable session, 2026-07-09):** **All four seed residuals closed statically — zero VM dependencies remain in them.** New load-bearing method: `llvm-objdump -d --start/--stop-address` on the PE recovers every operand Ghidra's `__ftol()`/`extraout` decompilation loses; `.rdata` doubles read via PE section parse. Closures: (1) HCA site-2 `s` = `2·(*(CEngine+0x33e4)) − 3` with +0x33e4 = offense team index, **home = teamIdx 2** (Go `hcaDelta` sign-match); `+0x4C18` is a ctor-set guard, ASG zeroes the magnitude not the guard. (2) `+0x68A8`/`+0x68D8` = league **STL/48** and **TOV/48** — the whole FUN_004385f0 league table is now identified (FGA/FTA/ORB/DRB/AST/STL/TOV/BLK/PF per 48). (3) `param_8` = shot-clock desperation flag (`+0x4c24 < 4`, restricts outcomes to {3pt, foul}); `param_6` = FUN_004e3860 return (internals → J16). (4) **Player `+0xDD0` has NO computed writer** (exhaustive `.text` store enumeration) ⇒ `FUN_004e3d90` (defQ) **≡ 0** — the :97163 foul coupling is roster-invariant in 5.60. Bonus: FUN_004cfa50 fully decoded (team rates = Σstat×`f`/D×48, D = ΣteamMIN/5, TOV/PF ×(2−f), STL ×44) — closes J6's `dVar60` fork and corrected ~15 master-ref rows (+0xDC8↔+0xDD0 swap, +0xDF0 = PF not PTS, +0xDA8 = 3PA not FTA).
**Spawned:** J15 (Go defQ port), J16 (FUN_004e3860 decode — ✅ closed 2026-07-10, which also folded in the five remaining `.rdata` pins: 0.625/1.4/1.25/2880.0/4.0).
**Status (2026-07-09):** ✅ Implemented.

### J6 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60)
**Location:** `engine/internal/sim/bucketweights.go` `twoPtBucketWeight`/`threePtBucketWeight` — admitted SHAPE stand-ins for 5.60's per-half composites (FUN_004cfa50, decompile ~91076–91110).
**Problem:** These weights are the foul-share DENOMINATOR and the largest unpinned formula surface left. Fork-A RE confirmed the *structure* faithful, but the composite SCALEs were never pinned — they gate any future share-level fidelity claim and feed J2's residual analysis. **Elevated (J2 session 1, 2026-07-10):** with the foul numerator symmetric and i.i.d. (J15), remaining foul-SHARE dispersion is denominator-driven — and foul-share dispersion mechanically injects negative Cov (the named carrier class). FTADisp sits at 1.51 vs real ~1.0 after defQ ≡ 0; these scale pins are the first suspect for J2's residual −0.00034, not just bookkeeping.
**Progress (J5, 2026-07-09):** The `dVar60` divisor fork is **closed** — D = ΣteamMIN/5 (asm :91005-06); **Go's MIN choice is faithful**. All team per-game-rate inputs to the composites are now identified with exact asm addresses, and the per-player projection structure (`f` good-stats / `2−f` TOV·PF) is decoded (`jsb-J5-static-closures-20260709.md` §5). Remaining: the +0xD90/+0xDB0 composite arithmetic itself and the `f` formula (localized to `4d3487–4d3553`) — both now objdump-tractable.
**Direction:** Decompile/objdump RE of the composite arithmetic (precedented Opus work); pin scale, then a ⚙️ Sonnet port PR if the Go values diverge.
**Status (2026-07-09):** ◑ Partial — divisor + input identities pinned; composite scales open. 🧠 Opus.

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
**Location:** `engine/internal/sim/shotdecision.go` `leagueBaseline` — a `.sco`-calibrated 250‰ stand-in whose comment repeats a wrong identity ("historical league-wide 2P%").
**Problem:** The 2026-07-08 closure pinned the real thing: 5.60 computes league 2PA/48 and the position 2P%/FG% tables per-dataset from `.plr` real-life stats (records 1–959, gate MIN > 2×GP), verified by exact recomputation to every displayed digit. The engine's stand-in is calibration-anchored (nothing is *broken*), but a faithful per-dataset computation removes a corpus dependency and makes quick-league behavior faithful automatically.
**Direction:** Comment fix rides along on any engine PR now; the actual swap is a small port with an A/B (calibration is anchored to the stand-in today). Full formula + inclusion rule are already written down — no RE remains.
**Status (2026-07-08):** ⬜ Open. ⚙️ Sonnet build + 🧠 Opus A/B.

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
**Location:** `engine/internal/sim/possession.go` `s2 + hca` (the site-2 2pt-composite nudge) — the only HCA carrier that survives the faithful dead foul arm.
**Problem (re-scoped by J2 session 1):** the foul path was carrying ~ALL engine home margin (3.44 → −0.06 under defQ ≡ 0) — unfaithfully. 5.60's surviving static HCA is site-2's `e88 += s·0.2`; Go applies raw ±0.2 on a ~16.5 basis (~1.2%). If site-2 is in raw bucket units like the foul pair was, the faithful proportional effect is ~8.6× larger (~10%) — ADR-0082's flagged under-scaling caveat, now load-bearing. (5.60's site-2 foul-side `−s·0.2` is erased by the dead arm's redraw — only the made-bucket nudge is live.) Real corpus home margin: 4.12.
**Direction:** Executes inside J15 (the margin gate can't pass without it). The J6 composite-basis pins are the faithful ground for the scale; corpus home-margin re-measure is the acceptance check. Win-share caveat stands: tune margin_gap, compare win share only at `--runs 1`.
**Status (2026-07-10):** ⬜ Open — absorbed into J15's program scope. 🧠 Opus.

### J13 Cut-over package: bands, leaders, decision
**Location:** `engine/internal/validate/bands.go` (placeholder ±15%, explicitly non-authoritative); per-player leaders validation (never built); the standings-residual gate (floor ≈ 3–5 wins / ~0 ppg); Var(lnPPS) sits ~2% under real as a monitor-only watch item.
**Problem:** Even with the dispersion blocker resolved, cut-over needs authoritative bands derived from the archive, a per-player sanity layer, and the actual go/no-go decision (env-flag swap of the jumpshot.exe invocation, `.sco` import path kept for one-command rollback; SHADOW as the live distributional check).
**Direction:** Gated on the J2 verdict. Band derivation and the leaders instrument are ⚙️-delegable; the acceptance judgment and the cut-over ADR are 🧠.
**Status (2026-07-08):** ⬜ Open — blocked on J2. 🧠 Opus.

### J14 AutoResearch eval-harness ADR (loop L9 companion)
**Location:** No harness exists; instrumentation groundwork (calibration walk ≈ 8 min full-corpus, freeze arms, channel-split tests) is merged. Cross-ref: [loop-engineering-backlog.md](loop-engineering-backlog.md) L9.
**Problem:** Engine iteration is human-paced despite an objective metric. The unresolved design tension — and why this is an ADR, not a script — is that a "perturb params, keep improvements" loop **conflicts with the faithfulness bar** (every shipped change must be RE-grounded in 5.60, not tuned to the corpus): the search space must be constrained to admitted stand-in constants and instrument-only measurements, never RE-pinned formulas.
**Direction:** ADR defining metric, legal parameter space (stand-ins only), acceptance rule, and how trial results feed RE prioritization rather than direct commits. Harness build afterward is ⚙️.
**Status (2026-07-08):** ⬜ Open. 🧠 Opus (ADR); ⚙️ Sonnet (harness).

### J15 Faithful symmetric foul-bucket program (defQ≡0 + HCA re-homing + level re-anchor)
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` `defQuality` (answers the FOLLOW-UP at ~:62) + `possession.go` site-2 HCA + `engine/internal/validate` bands/goldens. Measured ground: `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md` §4/§6.
**Problem (re-scoped 2026-07-10 — this is now THE next engine PR, not a one-liner):** J5 proved defQ ≡ 0 in 5.60 (`+0xDD0` never computed), which kills ADR-0082's deterministic home arm via the faithful `≤ 0 → redraw` — the true 5.60 foul bucket is **symmetric i.i.d. U[0,0.6)·scale on both sides**. But the J2 A/B shows a naive defQ→0 at k = 8.6 breaks the gates the phantom arm was propping up: home margin collapses to −0.06 (real 4.12), level_gap_pf deepens to −4.05, Var(lnPPS)/Var(lnFGA) drop under real. The payoff is the headline: Cov −0.000774 → −0.000340 and FTA/g 37.8 → 23.3 (real 20.65).
**Direction:** One program PR: (1) faithful symmetric bucket (defQuality → 0, home arm removed); (2) HCA re-homed to basis-scaled site-2 (J12 — target real margin 4.12); (3) FTA-level re-anchor — k re-swept against **real 20.65**, never the inflated A-baseline (the A-relative sweep methodology is what let 37.8 FTA/g ship); (4) band/golden re-derivation. **J16 verified 2026-07-10: the :97164 escape is closed** (shrink negative only at param_6 > 13.41 — unreachable; see J16) — the dead-arm conclusion stands and J15 can proceed on it. J16 also re-homes the residual static home-FTA candidate to the live site-2 `e88 → e90` and-one channel (param_6 itself is side-symmetric) — the HCA re-homing step (2) should account for the and-one arm explicitly. `/plan` with `auto_merge: false` (gate re-grounding is judgment, and gates themselves change meaning mid-PR).
**Risk if untouched:** every foul-share fidelity readout stays confounded by a phantom mechanism, and J2's final adjudication cannot run.
**Status (2026-07-10):** ⬜ Open — unblocked (J1 merged). 🧠 Opus (design + gate re-grounding); port mechanics ⚙️ Sonnet.

### J16 FUN_004e3860 net-advantage formula via objdump
**Location:** `jumpshot.exe` va 0x4e3860 — the play-outcome selector's `param_6` (net advantage). Ghidra fails to decompile it (`failed_decompile_004e3860_RAW.c` in the machine-local decompile dir).
**Problem:** The one remaining unknown *input* to the play-outcome formula. Its identity is pinned (J5); its internal arithmetic is not. **Elevated (J2 session 1, 2026-07-10) — now gates two live conclusions:** (a) the :97164 shrink factor `1 − param_6·0.25/(5·[+0x68d8]·0.2)` goes negative iff param_6 exceeds a large threshold — the ONLY static escape from J15's dead-arm/symmetric-bucket conclusion; (b) param_6 is the candidate mechanism for the residual static home-FTA component (~+1–2 FTA) the corpus split shows after game-state effects (J4's per-quarter decomposition is the acceptance instrument).
**Direction:** `llvm-objdump -d --start-address=0x4e3860` on the PE (the J5 method — Ghidra's failure is irrelevant to a direct disassembly read) → derive the formula → master-ref update; escalate only if the asm shows the NaN/FPU-flag class. Fold in the five remaining `.rdata` weight pins if they appear as operands. **Sequence: before or alongside J15.**
**Result (🔮 Fable session, 2026-07-10 — full record `jsb-native/re-artifacts/jsb-J16-fun004e3860-20260710.md`):** Formula fully traced; the prior master-ref reconstruction was structurally right but wrong on three load-bearing points (weight selector = *ball-handler-in-PG-slot* check, not "team==1"; an undocumented skip-self on the ball handler; matched-slot selector = the *pass-target* slot). **Both gating questions answered:** (a) escape **CLOSED** — the shrink is exactly `1 − param_6/(4·leagueTOV48)`, negative iff param_6 > **13.41** (IBL5.plr: leagueTOV48 = 3.3531), i.e. an advantage sum > 67; unreachable because player `+0xDC8` is dead-zero binary-wide (new pin, same enumeration as J5's `+0xDD0`) so the matched term is always ≤ 0, `−normalized ≤ 9.9`, and typical param_6 is O(1) (an O(13) value would force ~15% and-one share vs the corpus's few %; real home/away FTA 22.04/19.30 shows no deterministic-arm bimodality). J15's dead-arm/symmetric-bucket conclusion **stands**. (b) param_6 as the static home-FTA mechanism **REFUTED** — every input is side-symmetric; the surviving static channel is the site-2 HCA `e88 += s·0.2` feeding the and-one bucket (`e90 = param_6·0.25 + e88`), which the dead foul arm does not erase. Bonus closures: the five leftover J5 `.rdata` pins; `+0x350` = production-differential composite written once per depth-chart load (`FUN_00561c00` ← `FUN_0055f2a0`, the PLB handler — static per lineup, not per-game); `+0x6880` = league AST/48 position buckets (PG 13.30 … C 2.83); FUN_004e45a0's `+0x33F0` sum is positive-only with strategy ==4/==3/==2 paths. Master-ref corrected in place.
**Follow-up (optional, parked under J15):** an empirical `+0x350` distribution from IBL5.plr would bound the escape for *pathological* rosters too, but needs the `+0x250..+0x330` setter family (FUN_0050ad90..b200) replicated — not worth it unless J15's gates misbehave.
**Status (2026-07-10):** ✅ Implemented — closed statically same-day. 🔮 Fable.

### J17 Game-state foul coupling port (param_8 desperation + late-game fouling)
**Location:** `engine/internal/sim/possession.go:175` + `transition.go:137` — both `selectOutcome` call sites hardcode `shotClock=false`; 5.60's param_8 path (`+0x4c24 < 4` ⇒ outcomes restricted to {3pt, foul}, pinned in J5) is entirely unported, as is any late-game intentional-foul logic.
**Problem (found by J2 session 1's corpus instrument):** real 5.60's home/away FTA split (22.04/19.30) **follows the winner, not the side** — home-won games 23.25/18.03, visitor-won 20.28/21.15; margin-banded edge monotone −3.2 → +7.3. The bulk of the real FTA asymmetry is game-state-coupled (trailing teams foul late), a mechanism the Go engine lacks entirely. Also a candidate contributor to the residual FTADisp gap (1.51 vs ~1.0) and thus J2's residual Cov.
**Direction:** RE the game-state fouling surface (param_8 desperation is pinned; the late-game intentional-foul trigger is not), then port + wire real shot-clock/margin state into the two call sites. Acceptance: reproduce the corpus margin-banded FTA-edge curve; J4's per-quarter split is the sharper instrument once built. Sequence after J15 (foul bucket must be faithful first).
**Status (2026-07-10):** ⬜ Open — new (spawned by J2 session 1). 🧠 Opus (RE + verdict); port ⚙️ Sonnet.
