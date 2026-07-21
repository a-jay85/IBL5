---
description: J13-3 go/no-go for JSB native-engine cut-over. HOLD: master DRBPushSharePct 12.37% is inside drift band [11.97, 12.54] (ADR-0088) but −0.05pp under tightest 2-season CI floor ~12.42. Hold until gate-1 reaches ≥12.42% or the 2-season CI floor recalibrates to cover 12.37%. Records open non-blocking residuals, conceptual cut-over mechanism, and re-open criteria.
last_verified: 2026-07-21
---

# ADR-0090: JSB native-engine cut-over — go/no-go

**Status:** Proposed  
**Date:** 2026-07-21  
**Supersedes / references:** ADR-0088 (gate-1 re-denomination), ADR-0049 (band construction)

## Context

J13 (JSB cut-over package) has three sub-items:
- **Bands gate** ✅ — `jsbcalibrate --mode gate` PASS post-J18; band encoding in `bands.go` J18 block.
- **Leaders instrument** ✅ — shipped PR #1463 (J13-2).
- **Cut-over ADR** ← this (J13-3).

**Gate-1 status (from ADR-0088, 2026-07-21):**
- Master's `DRBPushSharePct` = **12.37%** (10,608,830 code-7 / 75,158,396 half-court possessions; 97 snapshots, 85.77M possessions on commit `84ff51085`).
- Measured on recent 05-08 rosters at 216.58 poss/g (box-score, Dean-Oliver).
- Inside between-season drift band **[11.97, 12.54]** — WITHIN-NOISE.
- **SEM caveat:** −0.05pp under the tightest era-matched 2-season CI floor **~12.42** (1-season point 12.53%, bootstrap CI [12.374, 12.698]). ADR-0088 verdict: "NOT a clean GO."

**All other gates PASS** under the recent-era re-spec (Var/Cov gates, bands gate). Gate-1 was the sole blocker; ADR-0088 overturns the prior "NO-GO vs 12.94" as a denomination artifact and unblocks this ADR to make the final call.

**Open residuals (explicitly NOT cut-over blockers):**
- (7) 3P undershoot ~2.8pp
- (6) .plb `dc_minutes` wiring
- (3) CEngine+0x30 reader
- (5) `baseTimeMid` walkback
- J7 TOV port (sequenced after J13)

## Decision

**HOLD.**

12.37% is inside the drift band and the gap is within measurement noise. However, the 2-season consensus floor (~12.42%) sets a tighter bar that has been chosen as the cut-over threshold. Master falls −0.05pp below it. Cut-over authorization waits for gate-1 to reach ≥12.42%.

Rationale for the 2-season floor as the bar (over the 1-season drift band): the between-season band [11.97, 12.54] absorbs multi-year variance and dissolves window-dependence (per ADR-0049); the 2-season CI floor ~12.42 is a tighter, bootstrapped consensus that represents a cleaner authorization gate when a measurement sits near the lower tail. A GO at 12.37% — inside the band but below the 2-season CI floor — would be defensible on the band alone; this ADR elects the tighter threshold.

**Do NOT tune any constant toward the band.** The gap is documented and left for natural measurement convergence.

## Re-open criteria

Cut-over is authorized (amend this ADR or create successor ADR) when **either**:
1. `TestFastClassArmingShareBaseline -tags archive` (recent-era 05-08, `JSB_ARCHIVE_DIR` set) returns `DRBPushSharePct ≥ 12.42%`, OR
2. The 2-season bootstrap CI is recalibrated such that its lower bound covers 12.37%.

Re-open process: re-run the gate, confirm the threshold is met, then proceed to the cut-over mechanism phase (§ Conceptual cut-over mechanism).

## Conceptual cut-over mechanism (reference — activates on GO)

When gate-1 reaches the threshold, the cut-over follows this pattern:

1. **Env-flag promotion** — add `JSB_USE_GO_ENGINE` env var following the `ENGINE_SHADOW_ENABLED` pattern (`updateAllTheThings.php:282`). When set, a new Go→canonical-tables step (sequenced follow-on; not yet built) runs `jsbsim` as the primary boxscore source.
2. **Prerequisite: Go→canonical importer** — `EngineShadowLoader` writes to shadow tables only; `ProcessBoxscoresStep` reads `.sco` only. A new service mirroring `EngineShadowRunService` but targeting `ibl_box_scores` / `ibl_box_scores_teams` must be built before the flag can be flipped.
3. **Rollback** — the `.sco` import path is preserved end-to-end. Set `JSB_USE_GO_ENGINE` off and process the admin's backup zip as before.
4. **Live distributional check** — `EngineShadow` continues running in parallel post-swap; shadow tables provide the before/after comparison.

## Consequences

- Cut-over is **not** authorized at this time.
- Gate-1 is the sole remaining blocker; open residuals (7)(6)(3)(5)(J7) are non-blocking.
- J13-3 closes with a HOLD decision; the cut-over mechanism ships as a sequenced follow-on once gate-1 clears.
- No engine constant, parameter, or code is changed by this ADR.
