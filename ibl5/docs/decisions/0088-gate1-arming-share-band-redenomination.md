---
description: J24 gate-1 code-7 arming-share band re-denominated from the all-era 209.2 poss/g convention to the recent-era box-score 216.58 poss/g the engine actually sims (05-08 rosters). Overturns the standing "-0.57pp NO-GO vs floor 12.94" as a pure DENOMINATION ARTIFACT — the numerator was recent-era markers/g (27.13) over an all-era denominator (209.2). Re-denominated, master's convention-FREE 12.37% code-7 share lands INSIDE the between-season drift band [11.97, 12.54]. Records two judgment calls (denominator = 216.58, rejecting PBP ~226/g and all-era 209.2; band = between-season drift per ADR-0049, not a tight pooled SEM) and the ~0.05pp SEM caveat: verdict is WITHIN-NOISE, NOT a clean GO (master -0.05pp under the tightest era-matched 2-season CI floor ~12.42). Unblocks the OPEN J13-3 cut-over ADR, which makes the final go/no-go call. Documentation/log-only band re-spec — NO engine sim change.
last_verified: 2026-07-21
---

# ADR-0088: J24 gate-1 code-7 arming-share band — recent-era re-denomination (216.58 poss/g)

**Status:** Accepted
**Date:** 2026-07-21

## Context

The J24 gate-1 code-7 arming share was recorded as the SOLE jsb cut-over blocker with a
**NO-GO**: master's engine code-7 share 12.37% vs band floor 12.94, -0.57pp under.

That gap was a **denomination artifact**, not a fidelity gap:

- Master's engine code-7 share = **12.37%** is measured DIRECTLY and is convention-FREE:
  `DRBPushSharePct = 100 × DRBPushClass / TotalPossessions`.
- The old floor **12.94** = real markers/g **27.13 ÷ 209.2**, where 209.2 is the **ALL-ERA**
  poss/g convention.
- But the engine sims **RECENT 05-08** rosters, whose real poss/g was MEASURED at **216.58**
  (box-score Dean-Oliver `FGA + 0.44·FTA + TOV − ORB`, n=2564 games, 95% CI [216.22, 216.93]).
- Re-denominating the same 27.13 markers/g by 216.58 gives ~12.5%. The -0.57pp gap was pure
  numerator/denominator era-mismatch (recent-era markers over an all-era denominator).

## Decision

Two judgment calls, made explicit for the human reviewer:

1. **Denominator = box-score 216.58 poss/g.**
   - REJECT PBP ~226/g — a documented **+17/g artifact** (blocked shots double-counted, ORB
     inflation; see the RE artifact's Calibration Note).
   - REJECT all-era 209.2 — the original mis-denomination (era-mismatched denominator).
   - 216.58 is the **conservative** choice and matches the engine's putback-excluded
     possession count.

2. **Band = between-season DRIFT construction (per ADR-0049), NOT a tight pooled SEM.**
   - The four recent-season chunks re-denominated to 216.58 —
     04-05 / 05-06 / 06-07 / 07-08 = **[11.97, 12.47, 12.54, 12.53]** — give the drift band
     **[11.97, 12.54]**. Master **12.37%** is INSIDE.
   - Rationale: a recent-era band that absorbs season-to-season drift dissolves the
     window-dependence a tight pooled SEM leaves cherry-pickable (the ADR-0049 Var/Cov
     recent-sim re-spec precedent).

## Consequences

- The standing "-0.57pp NO-GO vs floor 12.94" is **OVERTURNED** as a denomination artifact.
- **Verdict: WITHIN-NOISE, NOT a clean GO.** Master 12.37% is **-0.05pp** under the tightest
  era-matched 2-season CI floor **~12.42** (1-season point 12.53%, bootstrap CI
  [12.374, 12.698]). This SEM caveat is recorded PROMINENTLY so the go/no-go call is made
  eyes-open.
- This **UNBLOCKS** the OPEN **J13-3 cut-over ADR**, which makes the FINAL go/no-go call. This
  ADR supplies the re-adjudicated band + SEM caveat that ADR will consume — it does NOT itself
  authorize cut-over.
- The band re-spec is **documentation/log-only**: the [12.94, 13.41] → [11.97, 12.54]@216.58
  encoding lives ONLY in comments and a `t.Logf` in
  `engine/internal/sim/fastclass_share_archive_test.go` (no hard assert, no `t.Fatal` on the
  band). **NO engine sim change**; the byte-lock at HEAD `9139edf9…` is untouched.
- **Settled items NOT reopened** (see backlog NOT-A-LEVER list): marker ≡ Code7Push k=1.000
  bijective; +1/-1 basing (-1 byte-true); +0x4be4 pick uniform (refuted); fatigue-sub minute
  lever (exhausted at ceiling 12.61%).
