---
description: J27 audit of every JSB 5.60 foul site against the Go engine. Covers the RE site tables, the three blocking questions, the forced-foul port, the and-one floor order, the golden result, and the before/after archive walk. PF per team-game moved from 8.317 to 8.034 against sco 17.116 and the FTA gap moved from -53.80% to -55.04%, so the close criterion is NOT MET and backlog#265 stays open.
last_verified: 2026-11-12
---

# J27: JSB 5.60 foul-volume audit and ports

This doc records the audit of every place JSB 5.60 charges a personal foul, and the two ports it produced, in `engine/internal/sim` (a-jay85/IBL5-backlog#265). ADR-0236 (faithful JSB port), ADR-0090 (faithful mechanisms ship whatever their FTA cost) and ADR-0158 (no retuning dials) cover the change.

## Summary

The engine now ports the late-game forced foul. When the offense leads by 1 to 3 in Q4 or overtime with under 25 seconds left and the ball handler fails the DriveOff roll, the trailing defense commits a non-shooting foul (FUN_004d8570 93458). Before this change the engine turned that flag into a restricted shot and never charged a foul. The and-one verdict is UNFAITHFUL-ORDER: JSB adds the home-court term to the and-one weight and only then applies the floor (FUN_004e1ba0 97158-97168), and the engine now does the same. The offensive foul on turnovers (94289-94307) stays residual, because the engine draws its turnovers through a different structure (ADR-0045). The FTA gap did not close: AFTER is -55.04% against a 5% close bar.

## RE foul-site tables

Line numbers refer to `jsb560_decompiled.c` (decompile directory, untracked). The player PF slot is offset 0xC0 in the 0xe28-byte player record at `+0x63cc`. A sweep of every `+ 0xc0` increment finds exactly two in the possession handler FUN_004d8570.

| Decompile line | Function | Condition | Counter effect | In engine | Est. fouls per team-game |
|---|---|---|---|---|---|
| 93458-93523 | FUN_004d8570 | outcome 3 (`local_130 == 3`, 94153) | team counter `+0x4c0c` +1, defender PF +1, FTs per the 93647 guard | yes (`resolveFoul`) | 8.317 (BEFORE engine PF) |
| 92991-93009, 93458 | FUN_004d8570 | `local_15e == 1`: period >= 4, clock < 25, offense leads 1 to 3, DriveOff < rand_int(10) | same handler, shooting forced off (93478) | no before this PR, ported | 0.032 (measured A/B) |
| 94289-94307 | FUN_004d8570 | outcome 4 turnover, `local_15c == 0`, rand < `_DAT_00669aa0` (0.1) | ball-handler PF +1 and TO +1, no team counter, no FTs, steal check skipped | no | 0.1 per turnover-outcome trip (unpinned host) |

| Engine site | Arm | FTs (n) | forced arg | Decompile match |
|---|---|---|---|---|
| `possession.go` forced branch | half-court forced foul | 2 | `true` | 93458 |
| `possession.go` and-one arm | half-court and-one | 1 | `fm` (always false once the forced branch runs first) | 93458-93523 via outcome 3 |
| `possession.go` foul-only arm | half-court foul-only | 2 | `fm` (always false once the forced branch runs first) | 93458-93523 via outcome 3 |
| `transition.go` and-one arm | transition and-one | 1 | `false` | 93458-93523 via outcome 3 |
| `transition.go` foul-only arm | transition foul-only | 2 | `false` | 93458-93523 via outcome 3 |

Every engine foul runs through `resolveFoul` and then `freeThrows` or `chargeFoul`. No other engine path shoots a 1-FT trip.

Sums. In-engine estimates: 8.349 PF per team-game (the AFTER forced-only walk). All estimates: 8.349 plus 0.1 per turnover-outcome trip. The sco gap is 17.116 - 8.349 = 8.77 PF per team-game. The offensive-foul site would need more than 67 turnovers per team-game to bring that gap under 2 PF, so:

unlocated foul volume: about 8.8 PF per team-game, less the unpinned offensive-foul share.

## Blocking questions

**Q1. Missing foul sites.** Two rows had `In engine` = no.

- Forced foul (92991-93009, 93458): PORT. The lines were read in windows. Each operand maps to an engine field (`gs.period`, `gs.clock`, the offense lead, `bh.DriveOff`). Each constant is a decompile literal (4, 0x19, 1 and 3, `push 0xa`). The effect is pinned: the 93515 counter, the defender's PF, shooting cleared at 93478. The flag adds a foul on a trip that would otherwise draw a shot.
- Offensive foul on turnovers (94289-94307): RESIDUAL, criterion 2 failed. JSB rolls the 0.1 charge on its outcome-4 turnover and skips the steal check when the charge fires. The engine decides steals at the top of the trip (ADR-0045), and its outcome-4 arm (`outcomeTurnover`) fires on about 0.1% of trips. No engine branch matches the JSB turnover set without a mapping choice the decompile does not pin.

**Q2. Forced-foul gate.** PORT. `uVar20` is team1 score minus team2 score and `+0x33e4` is the team with the ball (J17 objdump, 0x4d87c6-0x4d87f1). The gate opens when the offense leads by 1 to 3, so the trailing defense fouls. The period read is FUN_004e4440 (`+0x4c20`, Q4 = 4), and the clock test is `+0x4c24 < 0x19`. The flag has three reads: 93029 and 93034 (the `+0x30` shot-clock byte), 93458 (jump to the foul handler whatever outcome FUN_004e1ba0 drew), and 93478 (clear shooting). FUN_004e1ba0 at 93293 does not take the flag, so the outcome draw still runs.

**Q3. And-one home/away skew.** UNFAITHFUL-ORDER. At 97158-97161 JSB adds `(side*2 - 3) * hca` to `local_e88`, signed per team. At 97165 `local_e90 = param_6 * _DAT_00669f58 + local_e88`, so the and-one weight inherits the term. At 97166-97168 the floor applies to the sum. The engine floored first and added `hcaScaled` after. CREDIT-BUG: no (`misCredited` = 0).

## Ported sites

- **Forced foul.** Decompile 92991-93009 (gate), 93458 (dispatch), 93478 (non-shooting). Condition: period >= 4, clock < 25, offense lead in 1 to 3, then the existing DriveOff roll. Effect: `resolveFoul(offense, defense, bh, def, 2, false, true, periodIdx)`, which raises the team counter and the defender's PF and shoots 2 FTs only in the bonus. A below-bonus foul keeps the ball in the trip, the same as the foul-only arm. Go: `forcedFoulFires` in `engine/internal/sim/foulvolume.go`, wired in `possession.go` after `selectOutcome`. Tests: `TestFoulVolumeSiteTriggers`, `TestForcedFoulTrigger`.
- **And-one floor order.** Decompile 97158-97168. The half-court field is `max(andOneBucketRaw(mq, bh)+hcaScaled, andOneBucketFloor)`. `andOneBucketWeight` keeps its floor for the transition arm. No constant changed. Test: `TestAndOneWeightOrder`.

The forced flag is wired for the half-court arm only, the same scope `lateGameForcing` already had.

## Residual unknowns

- The offensive foul on turnovers (Q1 above).
- JSB's and-one floor test is `local_e90 <= _DAT_00669f00` (0.0), and its replacement is `FUN_005c6100(0.03)`, a uniform draw in [0, 0.03). The engine keeps the constant 0.03 floor at the 0.03 threshold. Porting the draw adds an RNG call on every floored trip.
- The and-one basis. JSB's `local_e90` carries the whole `local_e88` made-shot weight, while the engine's and-one stand-in is about 0.04 against an HCA term of 0.57 (`hcaSite2BasisScale` 2.85 times 0.2). Under the faithful order the home/away and-one ratio is still about 11.5 to 1 (1186 home, 103 away over 500 fixture seeds). The plan expected a ratio between 0.5 and 2.0.
- Unlocated foul volume: about 8.8 PF per team-game.
- Still open from the 2026-11-08 handoff: the putback undo at 98430-98435 and the first-game initial counter. The late-game margin sign is now pinned by the J17 objdump.

## Golden snapshot

golden unchanged. `TestGolden` simulates one fixture game, and neither port changes an outcome in it. The 500-seed and-one count did move: home 1184 to 1186, away 34 to 103.

## Measurement

Master base b382459d216102deaa1305bdbf6ef915c458208e. FTA walk at stride 1, games cap 60. Baseline walk at runs 20, stride 50.

| Row | BEFORE engine | AFTER engine | sco |
|---|---|---|---|
| PF per team-game | 8.317 | 8.034 | 17.116 |
| FTA per team-game | 8.587 | 8.356 | 18.587 |
| FTA per PF | 1.0325 | 1.0400 | 1.0859 |
| FTA gap % | -53.80% | -55.04% | n/a |
| walk dYield | -0.0492 | not printed by the AFTER log | n/a |
| home/away FTA ratio (FTA walk) | 1.267 | 1.144 | 1.101 |
| home/away FTA ratio (baseline walk) | 1.259 | 1.193 | 1.140 |
| home margin gt=2 gap | +2.141 | +1.389 | n/a |
| and-ones home/away (500 seeds) | 1184 / 34 | 1186 / 103 | n/a |

An A/B walk with only the forced foul gives PF 8.349 and FTA 8.604. The and-one order alone cuts and-one trips from 27026 to 22471 over the walk, which accounts for the PF drop.

## Close criterion verdict

The AFTER FTA gap is -55.04%. Its absolute value is above 5%, so the close criterion is NOT MET. No weight or scalar was tuned (ADR-0158 line 36).
