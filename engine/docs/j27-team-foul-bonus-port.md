---
description: J27 port of the JSB 5.60 team-foul bonus into the Go engine. Covers the shooting-foul draw, the per-period team-foul counter, the 4-foul FT guard, in-trip retention on a below-bonus foul, the residual unknowns, the golden diff, and the before/after archive walk. FTA per game fell from 14.252 to 8.587 against sco 18.587, so the close criterion is NOT MET and backlog#265 stays open.
last_verified: 2026-11-11
---

# J27: JSB 5.60 team-foul bonus port

This doc records the port of the JSB 5.60 team-foul bonus into `engine/internal/sim` (a-jay85/IBL5-backlog#265). ADR-0236 (faithful JSB port), ADR-0090 (faithful mechanisms ship whatever their FTA cost) and ADR-0158 (no retuning dials) cover the change, so it carries no ADR of its own.

## JSB rules ported

All line numbers refer to FUN_004d8570 in `jsb560_decompiled.c` (decompile directory, untracked).

- **Shooting draw, 93469-93480.** Every foul draws a uniform value in [0, 1). The foul is a shooting foul when the draw is strictly above `_DAT_00669ef0` (0.5), when the trip is an and-one, or when the attempt is a 3-pointer. The 5.60 forced flag (`local_15e`) then clears shooting. The draw runs before the forced test, so the RNG consumes it on every foul. Go: `isShootingFoul` and `shootingFoulDrawCut` in `engine/internal/sim/teamfoul.go`.
- **Team-foul increment, 93515.** The fouling team's period counter goes up by one before the guard reads it. JSB keeps the counter in a slot at offset 0x4c0c plus 4 times the possession-team index. Fouls against the possession team are fouls by the defense, so the Go port stores the count on the defending `teamState` as `teamFouls`.
- **FT guard, 93647.** Free throws are shot when the foul is shooting, when the team count is above 4, on an and-one, or on a 3-point attempt. The 5th team foul of a period is the first bonus foul. There is no one-and-one and no double bonus. Go: `foulAwardsFreeThrows` and `teamFoulBonusThreshold`.
- **Per-period reset, 94467-94468.** Both counters, at offsets 0x4c10 and 0x4c14, go back to 0 at each period advance. Go: `playPeriod` in `engine/internal/sim/gameloop.go` zeroes `visitor.teamFouls` and `home.teamFouls` for every quarter and every overtime.
- **Putback undo, 98430-98435.** This block decrements offset 0x4c20 when 0x4c24 equals 300. Both offsets sit outside the 0x4c10/0x4c14 counter slots, so the undo never touches the team-foul count and the port adds no decrement.

A below-bonus non-shooting foul calls `chargeFoul`. That charges the PF to the defender's live tally (foul-out and foul-trouble substitution read it), emits an `EventFoul` (box PF is built from it), shoots no free throws, and keeps the ball with the offense. In the half-court loop (`possession.go`) and the transition loop (`transition.go`) the foul-only arm does `trip--` and `continue`, so the offense rolls another outcome in the same trip. A retained foul does not spend the offensive-rebound budget. A retained half-court trip takes the trip-0 origin (`OriginInitial`), because the offense inbounds into a fresh set. Retention always ends: each retained foul raises the defense's count, which never falls inside a period, so a defending team retains at most 4 fouls per period.

The and-one arms call `resolveFoul` too. They always clear the guard, so they always shoot their one free throw. They still consume the draw and raise the counter.

The 3-point clause is ported in both helpers and passed `false` at every engine call site, because no engine outcome is a fouled 3-point attempt. The unit tests keep it live.

## Pinned and unpinned

Each residual unknown ships with the default named here.

- **R1.** Where control goes when the 93647 guard fails. Default: in-trip retention, using the offensive-rebound `continue` shape the engine already has. The alternative, giving the same team a new possession, would need `gameloop.go` to skip its offense/defense swap and would tick the clock.
- **R2.** Whether the foul arm's `local_15e` is the same variable `lateGameForcing` models. Default: the half-court arms pass `fm` from `lateGameForcing`; the transition arms pass `false`, matching their `selectOutcome` call. `allowedPaths` never offers foul-only on a forced trip, so the override only reaches and-ones, which clear the guard anyway. The output is the same either way.
- **R3.** The counter value at game start. Default: 0, the Go zero value. The decompile shows only the per-period reset.
- **R4.** The guard at 93647 reads slot `iVar12`, which held a player index at 93514. The findings file `/Users/ajaynicolas/claude-plans/_handoffs/2026-11-08-j27-nonshooting-fouls-re-findings.md` pins it as the fouling team's counter, and this port relies on that read.
- **R5.** `_DAT_00669ef0` = 0.5 comes from the same findings file. This port did not re-read the value.
- **Pinned.** The putback undo at 98430-98435 does not touch the counter (offsets 0x4c20/0x4c24 against 0x4c10/0x4c14).

## Predicted sign

The plan stated the FTA sign before measurement: DOWN. Before the port every foul-only outcome shot 2 FTs. After it, a foul-only outcome below the bonus with a draw at or under 0.5 shoots none. Retention offsets part of the drop, because the retained trip rolls another outcome, which can be another foul or an and-one. PF and foul-outs were predicted to rise.

## Golden diff by category

The extra draw on every foul shifts the whole RNG stream, so the regenerated `engine/internal/sim/testdata/golden.json` differs everywhere. Totals over the single golden game, both teams:

| Category | Before | After |
|---|---|---|
| FTA | 21 | 10 |
| FTM | 19 | 8 |
| PF | 12 | 12 |
| FGA | 204 | 191 |
| Points | 181 | 168 |
| Foul-outs (PF 6+) | 0 | 0 |
| Events | 875 | 831 |

FTA and points fell as predicted. PF stayed flat at 12, and FGA fell by 13 where the plan predicted a rise. One game is too small a sample to show the retention effect: the RNG shift reshuffles every outcome roll, and that noise is larger than the few extra rolls retention adds. The archive walk below shows PF up and pace up, which is the predicted direction.

## Measurement

Archive walk `TestRealArchive_FTADecomp` (`engine/internal/calibrate/fta_decomp_archive_test.go`, build tag `archive`), stride 1, games cap 60, seed 20240601, 113 recent-era snapshots. Master sha for both runs: `b69f41073519ed8c7a7b892719f9845a9f8e16ef`. The AFTER run adds only this port on top of that sha. Rates are per team-game.

| Row | Value |
|---|---|
| BEFORE engine FTA | 14.252 per team-game (189,317 total) |
| AFTER engine FTA | 8.587 per team-game (114,070 total) |
| sco FTA | 18.587 per team-game |
| BEFORE gap % | -23.32% |
| AFTER gap % | -53.80% |
| BEFORE engine PF | 8.112 per team-game (107,758 fouls) |
| AFTER engine PF | 8.317 per team-game (110,482 fouls) |
| sco PF | 17.116 per team-game |
| BEFORE engine FTA per PF | 1.7569 |
| AFTER engine FTA per PF | 1.0325 |
| sco FTA per PF | 1.0859 |
| BEFORE / AFTER engine pace | 102.420 / 103.100 per game |
| BEFORE / AFTER foul trips that ended in FTs | 107,758 / 70,549 |
| BEFORE / AFTER possessions | 1,358,801 / 1,362,917 |
| BEFORE / AFTER engine home/away FTA ratio | 1.040 / 1.267 |
| sco home/away FTA ratio | 1.101 |
| BEFORE / AFTER walk dYield | +0.6178 / -0.0492 (band 0.05) |

The gap widened, as predicted. The port moves FTA per foul from 1.7569 to 1.0325, which sits 4.9% under sco's 1.0859. Before the port every engine foul shot FTs, so each foul carried too many FTs and hid part of the foul-volume deficit. Now the FTA gap is almost all foul volume: the engine commits 8.317 fouls per team-game against sco's 17.116. PR #2650 already attributed the J27 FTA undershoot to that foul-volume deficit, and the walk's own branch verdict still reads VOLUME. The walk's FTA yield delta (dYield) moved from +0.6178 to -0.0492, inside its 0.05 band, so FTA per foul now matches sco and only the foul count is off.

The home/away FTA ratio rose from 1.040 to 1.267. The guard treats both teams the same. I ran the golden fixture over 2000 seeds on both trees: the guard kept 51.7% of the away team's 2-shot foul trips and 51.2% of the home team's. And-ones always shoot, and in the engine they go mostly to the home team (4,551 home against 137 away in that run). The port halves the 2-shot trips on both sides and leaves the and-ones alone, so the home team's share of FTA grows.

## Close criterion verdict

The criterion, set in the plan before measurement: J27 closes only when the AFTER walk shows |engine FTA - sco FTA| / sco FTA at or under 5%, with stride 1, games cap 60 and seed 20240601. `engine/internal/validate/bands.go` carries `fta` bands, but its header marks them as not a useful tolerance for this stat, so the 5% gap applies instead.

Close criterion verdict: NOT MET. The AFTER gap is -53.80%. The port ships anyway as a faithful mechanism (ADR-0090 precedent), and backlog#265 stays open with foul volume as its lever.

## Master-reference correction

`00_MASTER_REFERENCE.md` in the decompile directory (untracked), lines 1655-1661, says JSB 5.60 has no team-foul bonus. The increment at 93515 and the guard at 93647 show that it does. That file is untracked, so the owner fixes it outside this PR.
