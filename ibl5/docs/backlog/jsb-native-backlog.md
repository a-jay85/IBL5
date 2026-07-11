---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-11
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
  └─→ J6 composite-scale pins (✅ 2026-07-10, Fable): OVERTURNS the defQ≡0 / +0xDE0 / +0xDC8 dead-zero pins
        └─→ J15 faithful foul-bucket program (⬜ — RE-SCOPED: live defQ = Σ STL/MIN×44, offQ = Σ TOV/48)
              ├─ absorbs J12 (HCA re-homing — corpus margin ground truth 4.12 unchanged)
              ├─ prerequisite: J16 escape bound re-derived with LIVE AST/48 (J19)
              └─→ J2 session 2 (re-adjudicate, then re-measure) → residual hunt: J18 ports → J4 → J13
J17 game-state foul coupling (⬜, new 2026-07-10) — real 5.60 mechanism the engine lacks entirely
J18 composite fidelity ports (⬜) · J19 J6-residue RE (⬜) — both spawned by J6
```

The cut-over blocker — the wrong-signed Cov(lnFGA,lnPPS) — has a **named dominant carrier** (J2 session 1, 2026-07-10): a mechanical Cov injection from unfaithful foul share. PPS = PF/FGA counts FT points in the numerator while foul plays displace FGA from the denominator, so excess foul-share level/dispersion injects negative Cov directly; the engine ran foul share at **1.8× real** (37.8 vs 20.65 FTA/g, a pre-ADR-0082 legacy). Zeroing defQ moved gt2 Cov **−0.000774 → −0.000340** (real +0.000269) — 56% of the residual, ~15× any prior single lever; that A/B stands as measurement. **But J6 (same day) overturned the static premise underneath it:** J5's "defQ ≡ 0" was a store-enumeration blindspot — 5.60 builds the player record on the STACK (FUN_004cfa50 → FUN_00405970 write-back), so +0xDD0 (STL/MIN×44), +0xDE0 (usage-shrunk TOV/48), and +0xDC8 (AST/48) are all **live**. The faithful foul coupling is therefore roster-VARYING (defQ = Σ defenders' STL/MIN×44; offQ = Σ offense TOV/48 − HCA, TOV-coupled not volume-neutral), and J2's "symmetric U[0,0.6) both sides" verdict plus the J15 program must be re-adjudicated against the live-composite semantics before any port ships. "Mapped carriers exhausted" stays refuted; the map had a foul-path hole — and a method hole (see J6's caveat).

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 13 |
| 📋 Planned | 0 |
| ◑ Partial | 1 |
| ✅ Implemented | 5 |
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
| J6 | Composite-scale pins (+0xD90/+0xDB0, `f`, full player formula map) | ✅ Implemented | 🔮 Fable | M |
| J7 | Turnover volume-coupling fidelity RE | ⬜ Open | 🧠 Opus | M |
| J8 | Transition trigger denominator 18 | ⬜ Open | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ⬜ Open | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ⬜ Open | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ⬜ Open | ⚙️ Sonnet | S |
| J12 | HCA re-homing to basis-scaled site-2 (absorbed into J15) | ⬜ Open | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |
| J15 | Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor) | ⬜ Open | 🧠 Opus | L |
| J16 | FUN_004e3860 net-advantage formula via objdump | ✅ Implemented | 🔮 Fable | S |
| J17 | Game-state foul coupling port (param_8 desperation + late-game fouling) | ⬜ Open | 🧠 Opus | M |
| J18 | Composite fidelity ports (bucketweights/teamquality vs the J6 formula map) | ⬜ Open | 🧠 Opus | M |
| J19 | J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58) | ⬜ Open | 🧠 Opus | M |

### J1 Faithful foul-bucket pair port
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` (ADR-0061's `offQualityConstant = 1.575` corpus stand-in). Plan: `$HOME/.claude/plans/jsb-faithful-foul-pair.md` (written 2026-07-08, `impl_model: sonnet`, `auto_merge: true`).
**Problem:** The stand-in is structurally unfaithful — it couples BOTH teams' foul weights to defense at ~0.38, where the statically-pinned 5.60 behavior is an asymmetric pair: HOME = deterministic defense-coupled weight `(defQ − (5/6)·teamDef)/5 + 0.2`; AWAY/NEUTRAL = a stochastic `U[0, 0.6)` redraw with zero coupling. This is also why ADR-0061's GATE-1 (±0.5 home margin) was proven unsatisfiable in the healthy foul range.
**Result:** Merged 2026-07-10 (PR #1395, ADR-0082, k = 8.6 pair). Count-axis effect ~3% of the Cov gap (gt2 −0.000807 → −0.000774); sign survived, arming J2.
**Caveat (J2 session 1, then re-corrected by J6 — all same day):** the k-sweep's A-relative gates calibrated the pair to reproduce the pre-existing 1.8× FTA-level inflation (37.8 vs real 20.65/g) — still true, corrected by J15's level re-anchor. J2 additionally called the home arm "dynamically dead" via J5's defQ ≡ 0 pin, but J6 overturned that pin (+0xDD0 is live STL/MIN×44), so the shipped home arm's coupling STRUCTURE is closer to faithful than J2 concluded — its inputs (Go's defQuality formula) and scale are what remain unfaithful. Resolution owner: J15.
**Status (2026-07-10):** ✅ Implemented — see J15 for the faithful successor.

### J2 Count-axis carrier adjudication (post-J1)
**Location:** The wrong-signed Cov(lnFGA,lnPPS) — the sole remaining cut-over blocker (PF dispersion ≈ ½ real while level and offense-coupling are fixed).
**Problem:** The mapped search space is exhausted by measured NULLs: foulCompress, Branch-B, make-value variance and form (ADR-0053/0055), offVolumeScale and base_time form (ADR-0054 + RE), putback resolution, ORB intensity/level/retry-count (ADR-0056–0060, RE-faithful), transition gate (RE-faithful), the POSS channel (closed 2026-06-13 as a projection of the PPS-realization inversion, not a separable carrier), and TOV (an independent bug that goes the *wrong* direction). J1 spends the last mapped carrier. If the sign survives J1, naming the residual carrier — or ruling the model terminal-vs-shippable — is hypothesis generation over a refuted-premise space, the class Opus has repeatedly bounced off and Fable has cracked.
**Adjudication session 1 (🔮 Fable, 2026-07-10 — full record `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md`):** Both escape hatches failed post-J1 (sign survives at −0.000774; wins_resid_p50 7.75). Verdict: **not terminal — the exhausted-map premise is refuted.** The dominant carrier is a *mechanical* Cov injection from unfaithful foul share (PPS counts FT points while foul plays displace FGA), led by the 1.8× FTA level (legacy). Measured: defQ ≡ 0 full-corpus A/B moved gt2 Cov −0.000774 → **−0.000340** (real +0.000269; gt4 −0.001027 → −0.000365), FTA/g 37.8 → 23.3 (real 20.65), FTADisp 2.02 → 1.51 — while unmasking deficits the phantom FTA was paying for: margin 3.44 → −0.06 (real 4.12), level_gap_pf −2.27 → −4.05, Var(lnPPS)/Var(lnFGA) now UNDER real. New corpus instrument: real home/away FTA split (25,892 games) = 22.04/19.30 (1.142×), edge follows the *winner* (game-state fouling — J17); engine at master = 48.6/22.1 (2.20×). Explains ADR-0043's foul-only freeze arm carrying 47.6% of |Cov| and the inverted corr(realized PPS, roster FGP) = −0.14.
**Premise overturned same day (J6):** session 1's static substitution rested on J5's "defQ ≡ 0" pin, which J6 overturned (+0xDD0 = live STL/MIN×44; +0xDE0 = live TOV/48). The A/B *numbers* stand as measurements of the defQ0 configuration, but the "faithful" label on defQ0 — and the "symmetric U[0,0.6) both sides / home arm dead" verdict — are void. The corpus evidence (winner-following FTA edge, no deterministic-arm bimodality) still constrains how large a live deterministic arm can be, but that is now an empirical question, not a static proof.
**Direction:** **Session 2 first re-adjudicates against live composites** (what does the faithful pair look like with defQ = Σ STL/MIN×44 and offQ = Σ TOV/48 − HCA? does the redraw still dominate?), then re-runs the channel split on the re-scoped J15 engine. Residual (−0.00034) hunt order: J18 composite ports (foul-share *denominator* dispersion — FTADisp still 1.51) → J4 per-origin decomposition → then terminal-vs-shippable if the sign still holds.
**Status (2026-07-10):** ◑ Partial — carrier named and measured; static premise overturned by J6; re-adjudication + final verdict pend the re-scoped J15. 🔮 Fable.

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
**Result (Fable session, 2026-07-09):** New load-bearing method: `llvm-objdump -d --start/--stop-address` on the PE recovers every operand Ghidra's `__ftol()`/`extraout` decompilation loses; `.rdata` doubles read via PE section parse. Closures that STAND: (1) HCA site-2 `s` = `2·(*(CEngine+0x33e4)) − 3` with +0x33e4 = offense team index, **home = teamIdx 2** (Go `hcaDelta` sign-match); `+0x4C18` is a ctor-set guard, ASG zeroes the magnitude not the guard. (2) `+0x68A8`/`+0x68D8` = league **STL/48** and **TOV/48** — the whole FUN_004385f0 league table is now identified (FGA/FTA/ORB/DRB/AST/STL/TOV/BLK/PF per 48). (3) `param_8` = shot-clock desperation flag (`+0x4c24 < 4`, restricts outcomes to {3pt, foul}); `param_6` = FUN_004e3860 return (internals → J16). Team-side FUN_004cfa50 decode (team rates = Σstat×`f`/D×48, D = ΣteamMIN/5, TOV/PF ×(2−f), STL ×44) stands and closed J6's `dVar60` fork.
**Closure (4) OVERTURNED by J6 (2026-07-10):** the "+0xDD0 has NO computed writer ⇒ defQ ≡ 0" claim was a **store-enumeration blindspot** — the sweep matched register-base stores (`fstpl 0xdNN(reg)`) but the per-player pass builds the record on the STACK (esp-relative stores into FUN_004cfa50's `esp+0x1f8` clone) and FUN_00405970 block-copies +0xd3c–0xe24 back. +0xDD0 = live STL/MIN×44. The same blindspot also produced the +0xDE0 (2026-06-12), +0xDC8 (J16), and Confidence-never-read pins — all four overturned. **Method rule going forward: no dead-composite claim without sweeping esp-relative stores + block-copy write-backs.**
**Spawned:** J15 (now re-scoped by J6), J16 (✅ closed 2026-07-10, which also folded in the five remaining `.rdata` pins: 0.625/1.4/1.25/2880.0/4.0).
**Status (2026-07-09):** ✅ Implemented — closures 1–3 + team decode stand; closure 4 overturned (see J6).

### J6 Composite-scale pins (+0xD90/+0xDB0, `f`, full player formula map)
**Location:** `engine/internal/sim/bucketweights.go` `twoPtBucketWeight`/`threePtBucketWeight` — admitted SHAPE stand-ins for 5.60's per-half composites (FUN_004cfa50). Full record: `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md`; master ref carries the pinned formula map.
**Result (🔮 Fable session, 2026-07-10):** **Closed statically — every target pinned, plus the mechanism that four prior "dead" pins missed.** (1) **Mechanism:** the per-player pass clones the record to the STACK (`esp+0x1f8`), computes ALL composites there, snapshots five pre-shrink fields (FUN_00578b30 → +0xE00–0xE20), then FUN_00405970 block-copies +0xd3c–0xe24 back to the league record — invisible to register-base store greps AND to decompile greps (Ghidra renders the slots as anonymous locals). (2) **Full per-player formula map** pinned with PE-verified constants (asm 4d432a–4d47c4): 2P‰/3P‰/FT‰ ints, +0xD70 = FTA×S/MIN×48 with **S = (leaguePF48×5 − leagueTOV48×0.5)/(leagueFTA48×5)** exact, +0xD78/+0xD90 interaction-shrunk shot rates, +0xD88 = **2PA**/MIN×48, **+0xDA8 = +0xDB0 = 3GA/MIN×48** (one value, both slots; DB0 = the usage-shrunk pass-2 twin), +0xDB8/+0xDC0 ORB/DRB per 48, **+0xDC8 = AST/48 · +0xDD0 = STL/MIN×44 · +0xDD8/+0xDE0 = TOV/48 — ALL LIVE**, +0xDE8 = BLK/48, +0xDF0 = (PF − TOV/10)/MIN×48, +0xDF8 = clamped energy. Branch-B pass-2 usage shrink rescales D90/D78/DE0/DB0 (raw twins DA8/DD8 untouched). (3) **`f` pinned:** `f = (Confidence + rec[+0x18] + 95)/200` — default 1.00, spread ≈ ±2%; good stats × f, TOV/PF × (2−f). Confidence IS read during gameplay — the master ref's "never read" verdict is deleted. (4) **Four dead-zero pins overturned** (+0xDD0, +0xDE0, +0xDC8, Confidence-never-read) — cascade recorded in J2/J5/J15/J16 entries; downstream, 5.60's defQ = Σ STL/MIN×44 and offQ = Σ TOV/48 − HCA are roster-varying, and transition retention (+0xDA0/+0xDA8) is NOT vestigial for 3pt shooters.
**Spawned:** J18 (engine ports of the divergences found in `bucketweights.go`/`teamquality.go`), J19 (residue RE), J15 re-scope, J2 re-adjudication.
**Status (2026-07-10):** ✅ Implemented. 🔮 Fable.

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
**Location:** `engine/internal/sim/possession.go` `s2 + hca` (the site-2 2pt-composite nudge).
**Problem (re-scoped by J2 session 1):** the foul path was carrying ~ALL engine home margin (3.44 → −0.06 under defQ ≡ 0) — unfaithfully. 5.60's static HCA site-2 is `e88 += s·0.2`; Go applies raw ±0.2 on a ~16.5 basis (~1.2%). If site-2 is in raw bucket units like the foul pair was, the faithful proportional effect is ~8.6× larger (~10%) — ADR-0082's flagged under-scaling caveat, now load-bearing. Whether the foul-side `−s·0.2` also expresses depends on the live-composite home-arm question J6 reopened (J15 prerequisite) — the made-bucket nudge is live either way. Real corpus home margin: 4.12.
**Direction:** Executes inside J15 (the margin gate can't pass without it). The J6 composite-basis pins are now the faithful ground for the scale; corpus home-margin re-measure is the acceptance check. Win-share caveat stands: tune margin_gap, compare win share only at `--runs 1`.
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

### J15 Faithful foul-bucket program (live composites + HCA re-homing + level re-anchor)
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` `defQuality`/`offQuality` + `possession.go` site-2 HCA + `engine/internal/validate` bands/goldens. Measured ground: `jsb-native/re-artifacts/jsb-J2-adjudication-20260710.md` §4/§6; faithful formulas: `jsb-J6-composite-scales-20260710.md`.
**Problem (re-scoped TWICE on 2026-07-10 — J2 then J6):** J2 proposed a *symmetric* program on J5's defQ ≡ 0 pin; **J6 overturned that pin**, so the faithful 5.60 pair is now statically known and roster-VARYING: **defQ = Σ five defenders' STL/MIN×44** (live +0xDD0), **offQ = Σ offense TOV/48 − HCA** (live +0xDE0 — TOV-coupled, so ADR-0061's `offQualityConstant = 1.575` and its volume-neutrality claim are unfaithful, and ADR-0082's shipped `defQuality` formula — floor1(OD)×0.25, neutral 8.21 — is the wrong composite entirely). Whether the away-side `≤ 0 → redraw` still dominates with live values is an EMPIRICAL question (the J2 corpus evidence — smooth winner-following FTA edge, no bimodality — bounds a deterministic arm but no longer proves it dead). What survives J2's A/B unconditionally: the FTA LEVEL must re-anchor to real 20.65 (37.8 shipped via A-relative gates), and HCA must re-home off the foul path (margin 3.44 → −0.06 under defQ0 while real = 4.12).
**Direction:** One program PR, now design-first: (1) port the faithful pair — defQ = Σ STL/MIN×44, offQ = Σ TOV/48 − HCA, in 5.60's units with the k-scale derived, not swept; (2) HCA re-homed to basis-scaled site-2 (J12 — target real margin 4.12; account for the `e88 → e90` and-one arm J16 identified); (3) FTA-level re-anchor against **real 20.65**, never the A-baseline; (4) band/golden re-derivation. **Prerequisite:** J19's escape-bound re-derivation with live AST/48 (J16's "unreachable" verdict is void until redone) and a static check of whether live defQ keeps the home arm non-positive. `/plan` with `auto_merge: false` (gate re-grounding is judgment).
**Risk if untouched:** every foul-share fidelity readout stays confounded, J2's final adjudication cannot run, and two shipped stand-ins (ADR-0061 offQ constant, ADR-0082 defQuality composite) keep wearing a faithfulness label J6 disproved.
**Status (2026-07-10):** ⬜ Open — re-scoped by J6; design blocked only on J19's escape re-derivation. 🧠 Opus (design + gate re-grounding); port mechanics ⚙️ Sonnet.

### J16 FUN_004e3860 net-advantage formula via objdump
**Location:** `jumpshot.exe` va 0x4e3860 — the play-outcome selector's `param_6` (net advantage). Ghidra fails to decompile it (`failed_decompile_004e3860_RAW.c` in the machine-local decompile dir).
**Problem:** The one remaining unknown *input* to the play-outcome formula. Its identity is pinned (J5); its internal arithmetic is not. **Elevated (J2 session 1, 2026-07-10) — now gates two live conclusions:** (a) the :97164 shrink factor `1 − param_6·0.25/(5·[+0x68d8]·0.2)` goes negative iff param_6 exceeds a large threshold — the ONLY static escape from J15's dead-arm/symmetric-bucket conclusion; (b) param_6 is the candidate mechanism for the residual static home-FTA component (~+1–2 FTA) the corpus split shows after game-state effects (J4's per-quarter decomposition is the acceptance instrument).
**Direction:** `llvm-objdump -d --start-address=0x4e3860` on the PE (the J5 method — Ghidra's failure is irrelevant to a direct disassembly read) → derive the formula → master-ref update; escalate only if the asm shows the NaN/FPU-flag class. Fold in the five remaining `.rdata` weight pins if they appear as operands. **Sequence: before or alongside J15.**
**Result (🔮 Fable session, 2026-07-10 — full record `jsb-native/re-artifacts/jsb-J16-fun004e3860-20260710.md`):** Formula fully traced (this part STANDS); the prior master-ref reconstruction was structurally right but wrong on three load-bearing points (weight selector = *ball-handler-in-PG-slot* check, not "team==1"; an undocumented skip-self on the ball handler; matched-slot selector = the *pass-target* slot). The shrink identity is exact: `1 − param_6/(4·leagueTOV48)`, negative iff param_6 > **13.41** (IBL5.plr: leagueTOV48 = 3.3531). param_6 as the static home-FTA mechanism is **REFUTED** — every input is side-symmetric; the surviving static channel is the site-2 HCA `e88 += s·0.2` feeding the and-one bucket (`e90 = param_6·0.25 + e88`). Bonus closures: the five leftover J5 `.rdata` pins; `+0x350` = production-differential composite written once per depth-chart load (`FUN_00561c00` ← `FUN_0055f2a0`, the PLB handler — static per lineup, not per-game); `+0x6880` = league AST/48 position buckets (PG 13.30 … C 2.83); FUN_004e45a0's `+0x33F0` sum is positive-only with strategy ==4/==3/==2 paths. Master-ref corrected in place.
**Escape verdict OVERTURNED same day (J6):** the "unreachable" argument leaned on a same-method `+0xDC8` dead-zero pin, which J6 overturned — **+0xDC8 = live AST/48**, so the matched term is `(defAST48 − leagueAST48[pos])·0.8·fatigue` and can be POSITIVE (PG-league AST48 = 13.30; an elite-passer defense plausibly adds several units). The 13.41 threshold and the side-symmetry refutation are unchanged; the >57-from-remaining-terms gap analysis must be redone with live values (→ J19) before "effectively unreachable" can be cited. The distributional corroboration (param_6 ≈ 13 would force ~15% and-one share vs the corpus's few %) survives as evidence, not proof.
**Status (2026-07-10):** ✅ Implemented — formula + symmetry closures stand; reachability verdict reopened under J19. 🔮 Fable.

### J17 Game-state foul coupling port (param_8 desperation + late-game fouling)
**Location:** `engine/internal/sim/possession.go:175` + `transition.go:137` — both `selectOutcome` call sites hardcode `shotClock=false`; 5.60's param_8 path (`+0x4c24 < 4` ⇒ outcomes restricted to {3pt, foul}, pinned in J5) is entirely unported, as is any late-game intentional-foul logic.
**Problem (found by J2 session 1's corpus instrument):** real 5.60's home/away FTA split (22.04/19.30) **follows the winner, not the side** — home-won games 23.25/18.03, visitor-won 20.28/21.15; margin-banded edge monotone −3.2 → +7.3. The bulk of the real FTA asymmetry is game-state-coupled (trailing teams foul late), a mechanism the Go engine lacks entirely. Also a candidate contributor to the residual FTADisp gap (1.51 vs ~1.0) and thus J2's residual Cov.
**Direction:** RE the game-state fouling surface (param_8 desperation is pinned; the late-game intentional-foul trigger is not), then port + wire real shot-clock/margin state into the two call sites. Acceptance: reproduce the corpus margin-banded FTA-edge curve; J4's per-quarter split is the sharper instrument once built. Sequence after J15 (foul bucket must be faithful first).
**Status (2026-07-10):** ⬜ Open — new (spawned by J2 session 1). 🧠 Opus (RE + verdict); port ⚙️ Sonnet.

### J18 Composite fidelity ports (bucketweights/teamquality vs the J6 formula map)
**Location:** `engine/internal/sim/bucketweights.go` + `teamquality.go` — every divergence from the J6-pinned formulas, now enumerable against `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md` §2/§6.
**Problem:** J6 turned "unpinned stand-in" into "known-divergent." Confirmed divergences: (1) `bucketweights.go` comments (~:19, ~:180) assert "+0xDB0 is DEAD (always 0)" — false, it's usage-shrunk 3GA/MIN×48, so `threePtBucketWeight` needs re-derivation from the live composite (also a candidate for J2's residual: FTADisp 1.51, and the 3pt weight sits in the foul-share denominator); (2) `d70LeagueScalar = 1.0` (~:77) where 5.60 uses **S = (leaguePF48×5 − leagueTOV48×0.5)/(leagueFTA48×5)**, exactly computable from the FUN_004385f0 table the engine already models; (3) `d88` (~:159) uses `RealLifeFGA` where 5.60 uses **2PA** (and f-projected inputs); (4) the "+0xDE0 dead ⇒ foul floors" comment (~:85) premise is wrong; (5) `teamquality.go` faithful formulas per J15. Open modeling question: whether to port the `f = (Confidence + rec[+0x18] + 95)/200` ±2% projection modulation and the pass-2 usage shrink, or accept them as documented divergences (they need Confidence + the +0x18 marker in the bundle).
**Direction:** Foul-adjacent pieces (2)(4)(5) execute inside J15. The shot-mix pieces (1)(3) and the f/shrink question are a follow-on A/B'd PR — each changes bucket weights globally, so headline Cov + Var gates must be re-measured per change, not batched blind. Comment corrections ride the first PR that touches each file.
**Status (2026-07-10):** ⬜ Open — spawned by J6. 🧠 Opus (A/B verdicts + f-port decision); edits ⚙️ Sonnet.

### J19 J6-residue RE (energy operands, rec+0x18 semantics, escape re-derivation, +0xD58)
**Location:** `jsb-native/` decompile + PE; open items recorded in `jsb-native/re-artifacts/jsb-J6-composite-scales-20260710.md` § still-open.
**Problem:** J6 left four bounded unknowns, two of them load-bearing: (1) **J16 escape-bound re-derivation with live AST/48** — blocks J15's design (is the home arm's `≤ 0 → redraw` still the dominant path with defQ = Σ STL/MIN×44 and a sign-varying matched term?); (2) **rec[+0x18] in-season semantics** — pinned = 100 at reset, but constant-vs-decay determines `f`'s real spread (±2% vs wider) and whether the f-port matters (J18); (3) energy-formula operand identities (slots 0x1c/0x64/ebx in asm 4d4711–4d4774); (4) +0xD58 — computed and stored (4d42df) but no reader found; confirm dead or find the reader. Also parked here: the transition-retention re-trace (+0xDA0/+0xDA8 live for 3pt shooters — the master ref's vestigial claim was premise-corrected but the downstream retention path was never re-walked).
**Direction:** Item (1) is arithmetic over already-pinned formulas + IBL5.plr distributions — do it first, it unblocks J15. Items (2)–(4) + the retention re-trace are one objdump session, precedented method. Escalate to 🔮 only if the asm hits the NaN/FPU-flag class.
**Status (2026-07-10):** ⬜ Open — spawned by J6. 🧠 Opus.
