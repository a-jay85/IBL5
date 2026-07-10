---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-09
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
J1 faithful foul pair (📋, Sonnet impl)
  └─→ J2 count-axis adjudication (🔮)  ←─ J4 pbp extraction (⚙️, spec ready) ←─ J3 identifiability design (✅ 2026-07-09)
        └─→ J12 HCA magnitude · J13 cut-over package (🧠)
J5 unpinnable-claims sweep (✅ 2026-07-09) ─→ closed all 4 residuals; spawned J15 (defQ≡0 port) + J16 (FUN_004e3860); J6 now ◑
```

The single cut-over blocker is the wrong-signed count-axis covariance Cov(lnFGA,lnPPS) (engine ≈ −0.0008 vs real +0.0003); every fidelity axis besides dispersion is resolved (level −2.5 ppg, offense-coupling corr 0.673). J1 is the last *mapped* carrier; J2 decides what happens if the sign survives it.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 12 |
| 📋 Planned | 1 |
| ◑ Partial | 1 |
| ✅ Implemented | 2 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Tier | Effort |
|---|-------|--------|------|-------:|
| J1 | Faithful foul-bucket pair port | 📋 Planned | ⚙️ Sonnet | M |
| J2 | Count-axis carrier adjudication (post-J1) | ⬜ Open | 🔮 Fable | L |
| J3 | Per-origin efficiency identifiability (IBL5.log) | ✅ Implemented | 🔮 Fable | M |
| J4 | Play-by-play extraction parser | ⬜ Open | ⚙️ Sonnet | M |
| J5 | Unpinnable-claims sweep + static closures | ✅ Implemented | 🔮 Fable | M |
| J6 | 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60) | ◑ Partial | 🧠 Opus | M |
| J7 | Turnover volume-coupling fidelity RE | ⬜ Open | 🧠 Opus | M |
| J8 | Transition trigger denominator 18 | ⬜ Open | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ⬜ Open | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ⬜ Open | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ⬜ Open | ⚙️ Sonnet | S |
| J12 | HCA magnitude calibration vs archive | ⬜ Open | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |
| J15 | Faithful defQ ≡ 0 port (drop the Go OD-coupled stand-in) | ⬜ Open | 🧠 Opus | M |
| J16 | FUN_004e3860 net-advantage formula via objdump | ⬜ Open | 🧠 Opus | S |

### J1 Faithful foul-bucket pair port
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` (ADR-0061's `offQualityConstant = 1.575` corpus stand-in). Plan: `$HOME/.claude/plans/jsb-faithful-foul-pair.md` (written 2026-07-08, `impl_model: sonnet`, `auto_merge: true`).
**Problem:** The stand-in is structurally unfaithful — it couples BOTH teams' foul weights to defense at ~0.38, where the statically-pinned 5.60 behavior is an asymmetric pair: HOME = deterministic defense-coupled weight `(defQ − (5/6)·teamDef)/5 + 0.2`; AWAY/NEUTRAL = a stochastic `U[0, 0.6)` redraw with zero coupling. This is also why ADR-0061's GATE-1 (±0.5 home margin) was proven unsatisfiable in the healthy foul range.
**Direction:** Execute the plan (Sonnet-executable delegation packets; golden regen + band re-derivation interleaved by design).
**Unblocks:** J2 (the count-axis Cov readout is the plan's acceptance signal), J12, J13; drops the synthetic GATE-1 degeneracy guards.
**Status (2026-07-08):** 📋 Planned — plan on disk, not yet run. ⚙️ Sonnet impl / 🧠 Opus final review.

### J2 Count-axis carrier adjudication (post-J1)
**Location:** The wrong-signed Cov(lnFGA,lnPPS) — the sole remaining cut-over blocker (PF dispersion ≈ ½ real while level and offense-coupling are fixed).
**Problem:** The mapped search space is exhausted by measured NULLs: foulCompress, Branch-B, make-value variance and form (ADR-0053/0055), offVolumeScale and base_time form (ADR-0054 + RE), putback resolution, ORB intensity/level/retry-count (ADR-0056–0060, RE-faithful), transition gate (RE-faithful), the POSS channel (closed 2026-06-13 as a projection of the PPS-realization inversion, not a separable carrier), and TOV (an independent bug that goes the *wrong* direction). J1 spends the last mapped carrier. If the sign survives J1, naming the residual carrier — or ruling the model terminal-vs-shippable — is hypothesis generation over a refuted-premise space, the class Opus has repeatedly bounced off and Fable has cracked.
**Direction:** Re-run the channel split after J1 merges (scratch `archive`-tagged test; recipes preserved in the re-artifacts and memory). If the sign flips or the standings residuals reach the ~3–5-win binomial floor → proceed straight to J13. If not → 🔮 Fable adjudication session (candidate forks: per-origin make-value armed with J3/J4 data; a novel carrier; accept-residual and cut over anyway). **New named candidate (J5, 2026-07-09):** Go's `defQuality` injects a live OD coupling into the foul term that 5.60 provably lacks (defQ ≡ 0 — J15); an unfaithful coupling of foul share to roster quality is exactly the class that could carry a wrong-signed Cov — measure J15's effect before opening a Fable adjudication.
**Risk if untouched:** The engine never cuts over; jumpshot.exe stays load-bearing.
**Status (2026-07-08):** ⬜ Open — blocked on J1. 🔮 Fable (the measurement re-run itself is ⚙️/🧠).

### J3 Per-origin efficiency identifiability (IBL5.log)
**Location:** IBL5.log — 1.1 GB, **22,798 games** ≈ 19–20 seasons of 5.60 play-by-play (machine-local; earlier "23,714" was a miscount).
**Result (Fable session, 2026-07-09):** **Identifiable.** The stamp grammar is a *closed* 509-template table compiled into jumpshot.exe `.data` (va 0x6ac184–0x6b0a3c); a template matcher achieved **100.00% sentence closure over the full corpus (22,797 parsed games, 15.6M sentences, 0 unmatched)**. Load-bearing pins, all static: (1) transition possessions are marked **deterministically** (FUN_004ee320 first-trip guard; RNG selects phrasing only) — P(miss)=P(false)=0, ~24.5 markers/game ⇒ ~1,000 transition possessions/team-season; (2) rebound side is text-partitioned between the ORB/DRB handlers except one shared string (`"Rebounded by %s."`, ~2.6% of rebounds, resolved exactly by roster join); (3) putback = sequence rule (ORB → same-team shot; putback text is flag-gated, sufficient-not-necessary), matching engine origin semantics (`possession.go`/`transition.go`: transition tags all trips, putback = trip>0 half-court); (4) running score after every stamp (5,320,495 == 5,320,495) gives per-event ground-truth points. Power: se(PPS_transition) ≈ 0.033/team-season vs total lnPPS sd ≈ 0.038 → split-half disattenuation over ~500 team-seasons, se(corr) ≈ 0.046. Full study + **J4 build spec**: `jsb-native/re-artifacts/jsb-pbp-identifiability-J3-20260709.md` (parser `j3_study.py` + full-corpus output preserved alongside).
**Status (2026-07-09):** ✅ Implemented — study complete; J4 unblocked with spec.

### J4 Play-by-play extraction parser
**Location:** New machine-local tooling consuming IBL5.log per the J3 spec (`jsb-native/re-artifacts/jsb-pbp-identifiability-J3-20260709.md` § "J4 build spec"; the study parser `j3_study.py` next to it is ~80% of the matcher).
**Problem:** None — mechanical half of J3, now fully specified: segmenter (header regex + digit-boundary team/score split + season-by-date-rollback), 509-template matcher (gate: 100% closure — any unmatched sentence is a parser bug), roster-join attribution, possession state machine with engine-aligned origin rules.
**Direction:** Build to spec. Machine-verifiable gates: per-game Σ score-deltas == header final; **per-player per-game reconciliation against `ibl_box_scores`** (1988–2008, ~606K rows — stronger than the `.sco` season-total recon originally envisioned); Σ origin FGA == total FGA. Output: team-game per-origin {FGA,FTA,PTS,PPS} CSV → team-season demeaned couplings → J2's per-origin decomposition.
**Status (2026-07-09):** ⬜ Open — **unblocked** (J3 ✅). ⚙️ Sonnet build; 🧠 Opus owns the J2 decomposition readout.

### J5 Unpinnable-claims sweep + static closures
**Location:** `jsb-native/jsb_560/` master reference + decompile + `jumpshot.exe` PE. Full record: `jsb-native/re-artifacts/jsb-J5-static-closures-20260709.md`.
**Result (Fable session, 2026-07-09):** **All four seed residuals closed statically — zero VM dependencies remain in them.** New load-bearing method: `llvm-objdump -d --start/--stop-address` on the PE recovers every operand Ghidra's `__ftol()`/`extraout` decompilation loses; `.rdata` doubles read via PE section parse. Closures: (1) HCA site-2 `s` = `2·(*(CEngine+0x33e4)) − 3` with +0x33e4 = offense team index, **home = teamIdx 2** (Go `hcaDelta` sign-match); `+0x4C18` is a ctor-set guard, ASG zeroes the magnitude not the guard. (2) `+0x68A8`/`+0x68D8` = league **STL/48** and **TOV/48** — the whole FUN_004385f0 league table is now identified (FGA/FTA/ORB/DRB/AST/STL/TOV/BLK/PF per 48). (3) `param_8` = shot-clock desperation flag (`+0x4c24 < 4`, restricts outcomes to {3pt, foul}); `param_6` = FUN_004e3860 return (internals → J16). (4) **Player `+0xDD0` has NO computed writer** (exhaustive `.text` store enumeration) ⇒ `FUN_004e3d90` (defQ) **≡ 0** — the :97163 foul coupling is roster-invariant in 5.60. Bonus: FUN_004cfa50 fully decoded (team rates = Σstat×`f`/D×48, D = ΣteamMIN/5, TOV/PF ×(2−f), STL ×44) — closes J6's `dVar60` fork and corrected ~15 master-ref rows (+0xDC8↔+0xDD0 swap, +0xDF0 = PF not PTS, +0xDA8 = 3PA not FTA).
**Spawned:** J15 (Go defQ port), J16 (FUN_004e3860 decode); remaining `.rdata` pins (`00669fc0/0066d3d0/0066d3c0/0066d3c8/00669ac8`) are now trivial via the PE-parse method — fold into whichever item touches them next.
**Status (2026-07-09):** ✅ Implemented.

### J6 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60)
**Location:** `engine/internal/sim/bucketweights.go` `twoPtBucketWeight`/`threePtBucketWeight` — admitted SHAPE stand-ins for 5.60's per-half composites (FUN_004cfa50, decompile ~91076–91110).
**Problem:** These weights are the foul-share DENOMINATOR and the largest unpinned formula surface left. Fork-A RE confirmed the *structure* faithful, but the composite SCALEs were never pinned — they gate any future share-level fidelity claim and feed J2's residual analysis.
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

### J12 HCA magnitude calibration vs archive
**Location:** The four RE-confirmed HCA sites; magnitude calibration against the 53 GB archive was deferred when the sign-only version landed (#955), and J1 restructures two of the sites (the home foul arm now carries HCA intrinsically; the divisor shrink is removed).
**Problem:** Home-margin magnitude has never been calibrated against the archive with the faithful mechanism in place.
**Direction:** After J1 merges, re-measure the home-margin gap on the full corpus and calibrate the remaining free magnitude(s); preserve the home-favorable sign as the hard constraint. Note: win-share is a √N runs artifact — tune margin_gap, compare win share only at `--runs 1`.
**Status (2026-07-08):** ⬜ Open — blocked on J1. 🧠 Opus.

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

### J15 Faithful defQ ≡ 0 port (drop the Go OD-coupled stand-in)
**Location:** `engine/internal/sim/teamquality.go` `defQuality` (`Σ floor1(OD)×0.25`, cap `teamDefBaseline×5.0×1.5`, `defQualityNeutral = 8.21`) + its consumer `foulBucketWeight` in `bucketweights.go`. Answers the FOLLOW-UP comment at `teamquality.go` ~:62.
**Problem:** J5 proved statically that 5.60's per-player defQ input (`+0xDD0`) is never computed — `FUN_004e3d90` returns **0.0 for every lineup, always**; the :97163 foul coupling reduces to a roster-invariant negative rescale (`e80 −= (e80/offQ)×5×leagueSTL48×5/6`). The Go model is an unfaithful substitution that injects OD-roster coupling into the foul numerator — the same class of invented carrier as the Fork-B OO stand-in that J1 removes.
**Direction:** Replace defQuality with the faithful constant 0 and simplify `foulBucketWeight` accordingly; corpus re-validation required (changes foul-share coupling; A/B the headline Cov and the home-margin gap). **Sequence after J1 merges** — J1 restructures the same foul pair; landing both restructures independently invites conflicting golden regens. Interaction: measure whether removing the coupling moves J2's Cov readout (see J2's candidate note).
**Risk if untouched:** The engine carries a phantom defensive-quality mechanism no 5.60 game ever expressed; every foul-share fidelity readout is confounded.
**Status (2026-07-09):** ⬜ Open — sequence after J1. 🧠 Opus (design + A/B verdict); port mechanics ⚙️ Sonnet.

### J16 FUN_004e3860 net-advantage formula via objdump
**Location:** `jumpshot.exe` va 0x4e3860 — the play-outcome selector's `param_6` (net advantage). Ghidra fails to decompile it (`failed_decompile_004e3860_RAW.c` in the machine-local decompile dir).
**Problem:** The one remaining unknown *input* to the play-outcome formula. Its identity is pinned (J5); its internal arithmetic is not.
**Direction:** `llvm-objdump -d --start-address=0x4e3860` on the PE (the J5 method — Ghidra's failure is irrelevant to a direct disassembly read) → derive the formula → master-ref update; escalate only if the asm shows the NaN/FPU-flag class. Fold in the five remaining `.rdata` weight pins if they appear as operands.
**Status (2026-07-09):** ⬜ Open. 🧠 Opus (🔮 only if the asm resists).
