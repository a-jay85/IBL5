---
description: JSB native-engine backlog — the count-axis cut-over blocker chain, static RE pins, faithful ports, and validation gates, each tagged with the model tier that owns its load-bearing reasoning (Fable-gated items marked).
last_verified: 2026-07-08
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
  └─→ J2 count-axis adjudication (🔮)  ←─ J4 pbp extraction (⚙️) ←─ J3 identifiability design (🔮)
        └─→ J12 HCA magnitude · J13 cut-over package (🧠)
J5 unpinnable-claims sweep (📇→🧠→🔮) ─→ converts blocked runbooks into ⚙️ ports (J6, J9 pattern)
```

The single cut-over blocker is the wrong-signed count-axis covariance Cov(lnFGA,lnPPS) (engine ≈ −0.0008 vs real +0.0003); every fidelity axis besides dispersion is resolved (level −2.5 ppg, offense-coupling corr 0.673). J1 is the last *mapped* carrier; J2 decides what happens if the sign survives it.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 13 |
| 📋 Planned | 1 |
| ◑ Partial | 0 |
| ✅ Implemented | 0 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Tier | Effort |
|---|-------|--------|------|-------:|
| J1 | Faithful foul-bucket pair port | 📋 Planned | ⚙️ Sonnet | M |
| J2 | Count-axis carrier adjudication (post-J1) | ⬜ Open | 🔮 Fable | L |
| J3 | Per-origin efficiency identifiability (IBL5.log) | ⬜ Open | 🔮 Fable | M |
| J4 | Play-by-play extraction parser | ⬜ Open | ⚙️ Sonnet | M |
| J5 | Unpinnable-claims sweep + static closures | ⬜ Open | 📇→🧠→🔮 ladder | M |
| J6 | 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60) | ⬜ Open | 🧠 Opus | M |
| J7 | Turnover volume-coupling fidelity RE | ⬜ Open | 🧠 Opus | M |
| J8 | Transition trigger denominator 18 | ⬜ Open | ⚙️ Sonnet | S |
| J9 | League-baseline faithful port (FUN_004385f0) | ⬜ Open | ⚙️ Sonnet | S |
| J10 | `.plb` minutes reader + stamina=100 bundle fix | ⬜ Open | ⚙️ Sonnet | S |
| J11 | Season-selection min-GP guard | ⬜ Open | ⚙️ Sonnet | S |
| J12 | HCA magnitude calibration vs archive | ⬜ Open | 🧠 Opus | M |
| J13 | Cut-over package: bands, leaders, decision | ⬜ Open | 🧠 Opus | L |
| J14 | AutoResearch eval-harness ADR (loop L9 companion) | ⬜ Open | 🧠 Opus | L |

### J1 Faithful foul-bucket pair port
**Location:** `engine/internal/sim/bucketweights.go` `foulBucketWeight` + `teamquality.go` (ADR-0061's `offQualityConstant = 1.575` corpus stand-in). Plan: `$HOME/.claude/plans/jsb-faithful-foul-pair.md` (written 2026-07-08, `impl_model: sonnet`, `auto_merge: true`).
**Problem:** The stand-in is structurally unfaithful — it couples BOTH teams' foul weights to defense at ~0.38, where the statically-pinned 5.60 behavior is an asymmetric pair: HOME = deterministic defense-coupled weight `(defQ − (5/6)·teamDef)/5 + 0.2`; AWAY/NEUTRAL = a stochastic `U[0, 0.6)` redraw with zero coupling. This is also why ADR-0061's GATE-1 (±0.5 home margin) was proven unsatisfiable in the healthy foul range.
**Direction:** Execute the plan (Sonnet-executable delegation packets; golden regen + band re-derivation interleaved by design).
**Unblocks:** J2 (the count-axis Cov readout is the plan's acceptance signal), J12, J13; drops the synthetic GATE-1 degeneracy guards.
**Status (2026-07-08):** 📋 Planned — plan on disk, not yet run. ⚙️ Sonnet impl / 🧠 Opus final review.

### J2 Count-axis carrier adjudication (post-J1)
**Location:** The wrong-signed Cov(lnFGA,lnPPS) — the sole remaining cut-over blocker (PF dispersion ≈ ½ real while level and offense-coupling are fixed).
**Problem:** The mapped search space is exhausted by measured NULLs: foulCompress, Branch-B, make-value variance and form (ADR-0053/0055), offVolumeScale and base_time form (ADR-0054 + RE), putback resolution, ORB intensity/level/retry-count (ADR-0056–0060, RE-faithful), transition gate (RE-faithful), the POSS channel (closed 2026-06-13 as a projection of the PPS-realization inversion, not a separable carrier), and TOV (an independent bug that goes the *wrong* direction). J1 spends the last mapped carrier. If the sign survives J1, naming the residual carrier — or ruling the model terminal-vs-shippable — is hypothesis generation over a refuted-premise space, the class Opus has repeatedly bounced off and Fable has cracked.
**Direction:** Re-run the channel split after J1 merges (scratch `archive`-tagged test; recipes preserved in the re-artifacts and memory). If the sign flips or the standings residuals reach the ~3–5-win binomial floor → proceed straight to J13. If not → 🔮 Fable adjudication session (candidate forks: per-origin make-value armed with J3/J4 data; a novel carrier; accept-residual and cut over anyway).
**Risk if untouched:** The engine never cuts over; jumpshot.exe stays load-bearing.
**Status (2026-07-08):** ⬜ Open — blocked on J1. 🔮 Fable (the measurement re-run itself is ⚙️/🧠).

### J3 Per-origin efficiency identifiability (IBL5.log)
**Location:** `jsb-native/` IBL5.log — 1.18 GB, 23,714 games of 5.60 play-by-play (machine-local).
**Problem:** The shots-per-possession make-value residual (initial/transition shot efficiency vs volume) is **data-limited**: `.sco` carries only season totals with no per-origin tag, so engine-only by-origin tests localize the dilution but cannot adjudicate faithfulness. The pbp log is the only untapped *real* 5.60 signal. Known limits: zero substitution markers and RNG-gated rotations (exact +/- impossible) — but shot events, running score, and possible fast-break/putback context may identify per-origin efficiency, which is all J2 needs.
**Direction:** Identifiability study *before* any parser is built: what per-origin signal is actually recoverable from the stamp grammar, and what statistical design ties it to the within-season demeaned couplings. A wrong design here burns a multi-day extraction — this is the instrument-design class where prior Opus attempts needed advisor correction mid-flight.
**Risk if untouched:** J2's "per-origin make-value" fork stays unadjudicable; a genuine carrier could be declared terminal for want of data.
**Status (2026-07-08):** ⬜ Open. 🔮 Fable (design gate); output = a build spec for J4.

### J4 Play-by-play extraction parser
**Location:** New tooling (likely `engine/cmd/` or a build-tagged archive test) consuming IBL5.log per the J3 spec.
**Problem:** None yet — this is the mechanical half of J3.
**Direction:** Build to the J3 spec. Machine-verifiable: extracted per-game/season totals must reconcile exactly against the corresponding `.sco` aggregates (the same reconciliation trick that validated the FTA-by-outcome instrument).
**Status (2026-07-08):** ⬜ Open — blocked on J3. ⚙️ Sonnet.

### J5 Unpinnable-claims sweep + static closures
**Location:** `jsb-native/jsb_560/` master reference (287K; access via `bin/jsb-ref`, never full-read) + the 10.4M decompile + `jumpshot.exe` PE (`.rdata` constants are static-readable).
**Problem:** Two "requires a live run / cannot be pinned statically" claims have now been refuted by pure static derivation (foul divisor 2026-07-07; CEngine runtime doubles 2026-07-08, closed by exact recomputation from `.plr`). Every remaining "unpinned / validation-phase / needs live debugging" marker in the master reference is therefore suspect. Each closed pin deletes an x32dbg/VM dependency (the Win-on-ARM VM is breakpoint-hostile: data BPs fault, stepping freezes) and converts a blocked runbook into a Sonnet-executable port.
**Direction:** 📇 Haiku enumerates every unpinned/live-run marker with line refs → 🧠 Opus triages by leverage and attempts the precedented decompile closures → 🔮 Fable takes the residue that resists (the NaN-semantics / FPU-flag / encoded-operand class). Known named residuals to seed the list: the foul-pair's s-sign/home-slot, `+0x68a8`/`+0x68d8` write-sites, `param_6`/`param_8`, the `+0xDD0` per-player formula (all assessed static-traceable, none blocking J1).
**Risk if untouched:** Future fidelity work keeps inheriting "needs VM" blockers that may be false.
**Status (2026-07-08):** ⬜ Open. 📇→🧠→🔮 ladder.

### J6 2pt/3pt bucket-weight SCALE pins (+0xD90/+0xDB0, dVar60)
**Location:** `engine/internal/sim/bucketweights.go` `twoPtBucketWeight`/`threePtBucketWeight` — admitted SHAPE stand-ins for 5.60's per-half composites (FUN_004cfa50, decompile ~91076–91110); includes the un-pinned Fork-A `dVar60` GP-vs-MIN divisor choice (Go chose MIN; scales magnitude, not sign).
**Problem:** These weights are the foul-share DENOMINATOR and the largest unpinned formula surface left. Fork-A RE confirmed the *structure* faithful and the measured 2GA over-coupling downstream-of-Fork-B, but the SCALEs were never pinned — they gate any future share-level fidelity claim and feed J2's residual analysis.
**Direction:** Decompile RE of the composite arithmetic (precedented Opus work); pin scale + divisor, then a ⚙️ Sonnet port PR if the Go values diverge. Escalate operands that resist static decode to J5's 🔮 lane.
**Status (2026-07-08):** ⬜ Open. 🧠 Opus (🔮 fallback via J5).

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
