---
description: The J27 FTA undershoot decomposes to foul volume; JSB 5.60's non-shooting fouls, team-foul bonus, and 3-shot trips need new engine state, so no lever ships and the levers are recorded with their measured FTA effects.
last_verified: 2026-10-01
---

# ADR-0158: Record the J27 FTA undershoot decomposition and its ruled-out levers

**Status:** Accepted
**Date:** 2026-10-01

## Context

The Go engine produced 15.81 FTA per team-game against 21.32 in the `.sco` box scores (minus 25.8 percent) on master 8d435122b under `TestMeasureBaseline_Archive`. The home/away FTA ratio was 1.044 in the engine and 1.140 in `.sco`. ADR-0084 re-anchored the foul bucket to the then-current target and attributed the home split to late-game fouling the per-bucket model does not capture. Two later faithful ports from ADR-0090 (the OReb putback 3pt restore and the transition 3pt port) widened the gap. Backlog item J27 ([backlog#265](https://github.com/a-jay85/IBL5-backlog/issues/265)) records the gap as unattributed. The binary's free-throw engine `FUN_004e9fc0`, its foul-type routine `FUN_004ec630`, and a second foul decision near decompile line 93471 had never been reverse-engineered in this repo. The engine has two free-throw paths only (and-one with one shot, foul-only with two). It has no bonus, non-shooting, offensive, or intentional foul path. The `.sco` files carry team-game totals only, without a foul type, an and-one flag, or a trip split. Shooting-foul share, and-one share, and bonus FTs are therefore engine-only diagnostics.

## Decision

A new archive instrument (`TestRealArchive_FTADecomp`) decomposes the gap into foul volume (PF per game), yield per foul (FTA per PF), the late-game axis (FTA by final-margin bucket, winner versus loser), and the home/away split, on sco-comparable totals only. A pure selector maps the volume and yield deltas to a branch and a reverse-engineering target. On the before run the branch was `VOLUME` (PF per game engine 8.02 versus sco 17.12; FTA per PF engine 1.757 versus sco 1.086). The engine fouls about half as often as JSB and gets more free throws per foul. The reverse-engineering step read every `FUN_004e9fc0` call site and the second foul decision; the private note's `PORT_DECISION` was `PORT_DECISION: none`. A port ships only when a pure evaluator (`EvaluateFTAShip`) passes: the measured gap shrinks by at least 5.0 percentage points, the engine does not exceed `.sco` by more than 5 percent, both home-margin gaps worsen by no more than 0.25, and pace and points per shot move by no more than 2 percent. The verdict was `FTA_SHIP_VERDICT ship=false failed=[b1-gap-shrink] gap_before=-25.84 gap_after=-25.84`.

No engine change ships. The levers below are ruled out or deferred, each with its measured FTA effect. Phase 3 of the plan was skipped because no candidate was portable.

The reverse-engineering note is `jsb-native/re-artifacts/jsb-J27-fta-RE-20261001.md` (private repo). The JSB free-throw trip is awarded at decompile line 93647 of `FUN_004d8570` when any of four flags holds: a shooting foul, more than four team fouls, an and-one, or a fouled 3pt attempt. The shooting-foul flag comes from the second foul decision at lines 93471-93480. A plain foul is a shooting foul with probability 0.5 (`_DAT_00669ef0`). And-one and 3pt fouls are always shooting fouls. Every foul bumps a per-team counter (`CEngine+0x4c0c`, line 93515) and the fouler's `+0xC0` count. So about half of JSB's fouls award free throws only once the team is in the bonus. That shape matches the measured `.sco` yield of 1.09 FTA per PF. Each engine foul is a free-throw trip, so the engine yield is near 2 by construction.

Measured and ruled-out levers. Each FTA per game figure is the instrument's engine value for that A/B arm versus 14.09 on the default arm (60 games per snapshot, seed 20240601):

- **`unfaithful3pt`** (ADR-0090 putback 3pt restore, reverted in the arm): 13.62, a change of minus 0.46. Faithful and not reverted.
- **`putback`** (ADR-0055 putback arm): 14.16, plus 0.07. Faithful and not reverted.
- **`suppress_transition`** (ADR-0090 transition 3pt port, removed in the arm): 14.28, plus 0.20. Faithful and not reverted.
- **`suppress_w4_rescale`** (the shot-clock foul-bucket rescale from [#1675](https://github.com/a-jay85/IBL5/pull/1675), removed in the arm): 14.19, plus 0.11. This is the first recorded FTA effect for that port.
- **Shooting versus non-shooting foul draw** (`FUN_004d8570` decompile lines 93471-93480). NEEDS-STATE: the engine has no non-shooting foul kind. Deferred to the follow-up backlog item.
- **Team-foul bonus** (line 93647, counter at line 93515). NEEDS-STATE: the bonus only matters for a non-shooting foul, which the engine never draws. The counter's reset period is also unpinned. Deferred.
- **Three-shot trip on a fouled 3pt attempt** (`FUN_004e9fc0` call sites at lines 93605, 93714, and 93759; the flag is set at line 94148). NEEDS-STATE: the engine draws foul-only as its own bucket with no shot type. Deferred.
- **Forced non-shooting foul** (`local_15e`, set at line 93009 under a guard that may read the period and a 1-to-3-point margin). NEEDS-STATE, and the late-game reading is inferred. It is the lead for ADR-0084's late-game claim. Deferred.
- **`FUN_004ec630` foul types** (line 93571) and the foul-out check (lines 93553-93555). These feed play-by-play text and the fouled-out slot. Neither gates a free-throw count.
- **Putback-foul label.** JSB numbers outcomes 1 made, 2 missed, 3 and-one, 4 foul-only, which differs from the engine's numbering in `engine/internal/sim/outcome.go`. Under the putback flag JSB rejects outcome 4 (foul-only), and the engine does not. A faithful fix would lower FTA. Record-only.
- **Previously ruled out:** `param_6` as a static home-FTA source (J16), the 3pt denominator and numerator (J24 residual 7), `foulCompress` (ADR-0044), and the asymmetric pair (ADR-0082, superseded by ADR-0084).
- **`foulBucketScale` re-tuning:** rejected by policy (ADR-0090, toggle a mechanism and leave the constants alone). Not measured.

## Alternatives Considered

- **Re-tune `foulBucketScale` upward.** This would close the aggregate gap. Rejected because it has no binary site and would mask the missing mechanism.
- **Compare shooting-foul share against `.sco`.** Dropped because `.sco` carries no foul type.
- **Port the putback-foul rejection.** Deferred because it moves FTA in the wrong direction for this item and deserves its own measurement.

## Consequences

- Positive: the decomposition instrument and its dated artifacts are the before/after harness for any later FTA work, together with `TestMeasureBaseline_Archive`.
- Positive: `TestFreezeConfig_SuppressArmsWiredInAbFreeze` makes every future `Suppress*` arm reachable from the A/B harness by construction.
- Positive: `TestFTAShipVerdict_Committed` re-derives the ship verdict from the committed artifacts on every CI run.
- Negative: J27 stays open. Closing it needs a non-shooting foul kind and a team-foul counter in the engine, which is a larger change with its own plan.

## References

- `engine/internal/calibrate/ftadecomp.go`
- `engine/internal/calibrate/ftadecomp_test.go`
- `engine/internal/calibrate/fta_decomp_archive_test.go`
- `engine/internal/calibrate/measure_baseline_archive_test.go`
- `engine/internal/sim/freeze_polarity_test.go`
- `engine/internal/validate/testdata/calibration-5.60-20261001-fta-decomp-before.json`
- `engine/internal/validate/testdata/calibration-5.60-20261001-fta-measure-before.txt`
- `jsb-native/re-artifacts/jsb-J27-fta-RE-20261001.md` (private repo)
- ADR-0084, ADR-0090
