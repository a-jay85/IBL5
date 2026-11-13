---
description: J27 second foul-volume pass. Covers the decompile sweep for unlisted foul sites, the 98430-98435 write and the team-foul counter at game start, the made-2pt and-one roll, the two 3pt shooting-foul rolls of backlog#1838, the golden result, and the before/after archive walk. PF per team-game moved from 8.034 to 10.473 against sco 17.116, and the FTA gap moved from -55.04% to -39.82%, so backlog#265 stays open.
last_verified: 2026-11-12
---

# J27: second foul-volume pass and ports

This doc records the second search for the foul volume the engine lacks, and the three rolls it ports, in `engine/internal/sim` (a-jay85/IBL5-backlog#265, a-jay85/IBL5-backlog#1838). ADR-0236 (faithful JSB port), ADR-0090 (faithful mechanisms ship whatever their FTA cost) and ADR-0158 (no retuning dials) cover the change. The first pass is `engine/docs/j27-foul-volume-port.md`. Its audited rows (93458-93523 outcome 3, the forced foul, 94289-94307 turnover foul) are not re-audited here.

## RE sweep

The sweep ran over `jsb560_decompiled.c` (JSB 5.60). Each command wrote its hits to a file, and the count is the line count of that file.

- `grep -n -F 'LAB_004d9d4d'` (jumps into the foul handler): 2 hits. Line 93459 is the label and 94153 is the only jump, taken when `local_130 == 3`.
- `grep -n -E '0x4c0c|0x4c10|0x4c14'` (team-foul slots): 5 hits. 93515 is the increment (audited), 93568 and 93647 are reads, and 94467-94468 is the per-period reset (faithful).
- `grep -n -E '\+ 0xc0\)'` (player PF slot): 81 hits, 11 of them assignments. All 11 sit outside FUN_004d8570 (copy constructors at 3564 and 3792, a setter at 22991, zero-inits at 22779, 79815 and 169481, struct copies at 107382, 108598, 182220, 213704 and 238861). None increments a foul count.
- `grep -n -E 'local_130 = 3|local_134 = 1|local_150 = 1|local_110 = 1'` (foul outcome code and handler flags): 5 hits. 93925 (made 2pt), 94059 (made 3), and 94147-94148 (missed 3) sit in FUN_004d8570. 183297 is a local of FUN_00597c00, a different function, so it is not a foul site.
- `bin/jsb-ref 'foul'` (master-reference prose): 1120 lines. They name the same sites as the decompile greps and add no new trigger.

| Decompile lines | FUN | Trigger | Effect | Class | Host/team-game | Rate | PF/team-game | Verdict |
|-----------------|-----|---------|--------|-------|----------------|------|--------------|---------|
| 93919-93926 | FUN_004d8570 | made 2pt on the `local_130 == 1` shot path | `local_130 = 3`, `local_150 = 1`: and-one foul | new foul site | made 2pt (the walk prints no made-2 count) | 0.05 (`_DAT_0066c7d8`) | about 1.2 at plan time | PORT |
| 94054-94060 | FUN_004d8570 | made 3pt | `local_130 = 3`, `local_150 = 1`: four-point play | new foul site | made 3pt, about 5 | 0.01 (`_DAT_00669e80`) | about 0.05 | PORT |
| 94102-94150 | FUN_004d8570 | missed 3pt, after the block check | `local_130 = 3`, `local_110 = 1`: 3-FT trip, no attempt stat | new foul site | missed 3pt, about 9 | 0.01 (`_DAT_00669e80`) | about 0.09 | PORT |
| 93459-93523 | FUN_004d8570 | `local_130 == 3` | foul handler, team-foul increment at 93515 | audited | n/a | n/a | 0 | audited in the first pass |
| 93568-93647 | FUN_004d8570 | FT trip guard | reads the team-foul slot | read-only | n/a | n/a | 0 | no port |
| 94467-94468 | FUN_004d8570 | period end | zeroes the team-foul slots | reset/init | n/a | n/a | 0 | faithful (`gameloop.go`) |

Sum of new-site estimates: 1.34 PF per team-game against an unlocated 8.77 (17.116 - 8.349). Unlocated after this sweep: 7.43.

## Putback undo and initial counter

- Lines 98415-98450 (FUN_004e42e0, the game-clock tick). Lines 98430-98435 hold `*(int *)(param_1 + 0x4c20) = *(int *)(param_1 + 0x4c20) + -1;` and `*(undefined4 *)(param_1 + 0x4c24) = 1;`. They roll the period counter back by one and set the clock to 1 when the clock reads 300 past period 4. No PF slot or team-foul slot is written. Verdict: `RESIDUAL (no foul effect)`. PF per team-game: 0.
- Team-foul slots at game start. Outside the 93515 increment and the 94467-94468 reset, the only write is a zero loop in FUN_004cee00 (the engine-object constructor) at 90257-90264: `puVar1 = param_1 + 0x1303;` over 3 slots with `*puVar1 = 0;`. With `param_1` an `undefined4 *`, index 0x1303 is byte offset 0x4c0c, so the loop zeroes 0x4c0c, 0x4c10 and 0x4c14. The engine starts each game's `teamFouls` at the Go zero value. Verdict: `FAITHFUL`. PF per team-game: 0.

## And-one frequency

The `_DAT_0066c7d8` roll sits at FUN_004d8570 93919-93926. It runs inside `if (local_130 == 1)` at 93872, the 2pt shot outcome, after the make test `cVar6 == '\x01'`. Every made 2pt reaches it. The pre-shot and-one weight is a separate draw in FUN_004e1ba0 (`local_e90`, 97165), so JSB has both the and-one bucket and the made-2pt roll. The engine had only the bucket.

A scratch test over the 500 fixture seeds of `TestAndOneHomeAwaySplit` on the master base counted 1289 one-FT trips right after a made 2pt, out of 39015 made 2pt. The ratio is 0.0330. The JSB rate from the made-2pt roll alone is 0.05 × 1.0, because every made 2pt reaches the roll. The scratch test was deleted after the run.

The roll is a PORT and became the first ported site (see Ported sites).

## Ported sites

- **Made-2pt and-one roll** (FUN_004d8570 93919-93926). After a made 2pt, a uniform draw strictly below 0.05 (`_DAT_0066c7d8`) charges a defensive and-one foul and shoots 1 FT. Go: `andOneMadeTwoFires` and `andOneMadeTwoCut` in `foulvolume.go`, called from the `outcome2pt` arms of `possession.go` and `transition.go` through `resolveFoul(..., 1, true, false, ...)`. Tests: `TestFoulVolumeSiteTriggers` (0.0499 fires, 0.05 does not) and `TestAndOneAfterMadeTwo` (3246 one-FT trips after a made 2pt over the fixture seeds against a floor of 2000; 1255 with the roll disabled).
- **Made-3 four-point play** (backlog#1838, FUN_004d8570 94054-94060). After a made 3pt, a draw strictly below 0.01 (`_DAT_00669e80`) charges a defensive and-one foul and shoots 1 FT. Go: `threePtShot` and `threePtFoulFires` in `threeptfoul.go`. Tests: `TestThreePtFoulFires` and `TestThreePtFourPointPlay` (44 four-point plays in 5000 forced makes). The fixture makes about 5 threes over 500 seeds, so this test calls `threePtShot` directly with a shot value of 2000 instead of looping the fixture.
- **Missed unblocked 3 shooting foul** (backlog#1838, FUN_004d8570 94102-94150). On a missed 3pt that the engine's block draw did not block, a draw strictly below 0.01 sets JSB's `local_110` and shoots 3 FTs. JSB writes no attempt stat on this branch, so `threePtShot` emits no attempt or miss event for the fouled shot. `local_110` is a stack local of FUN_004d8570, cleared at function entry (93309, `local_110 = 0;`), so it needs no engine state. `resolveFoulShot` in `teamfoul.go` carries it as the `threePt` argument for one call. Tests: `TestThreePtMissFoulShootsThree` (195 three-FT trips over the fixture seeds, none after an own 3pt attempt or a block, and a seed-scanned blocked miss that never fouls) and `TestResolveFoulShotThreePtShootsBelowBonus`.

## Blocking questions

- Q1, the source of the 8.8 missing PF per team-game. This sweep found three rolls with an estimated 1.34 PF per team-game. The AFTER walk measured 2.44 more PF per team-game than BEFORE. That is more than the estimate, because the made-2pt count per team-game was not in the walk log. The remaining 6.64 PF per team-game has no decompile site in FUN_004d8570 or in any PF-slot write. It stays a residual.
- Q2, the engine and-one bucket against JSB's made-2pt 5% roll. They are two separate JSB draws, and the engine lacked the roll. The roll is ported (see And-one frequency).
- Q3, engine state for the 3-FT trip. None is needed. `local_110` lives for one FUN_004d8570 call. `isShootingFoul` and `foulAwardsFreeThrows` already took a `threePt` argument, and `resolveFoul` had passed `false`. The new `resolveFoulShot` passes it through, and `resolveFoul` keeps its signature as a wrapper.
- Q4 (open), a blocked miss and the 3pt foul roll in JSB. At 94102-94150 the 1% roll runs after the block check FUN_004d7e10 returns, at the same nesting level as the `if (cVar6 == '\0')` block. The roll does not read `cVar6`. The port follows the plan and rolls only on an unblocked miss (`!blocked &&` in `threePtShot`). If JSB also rolls on a blocked miss, about 5% of fouled-miss rolls are missing. That is under 0.005 PF per team-game. A human reviewer should confirm which branch `cVar6 == '\0'` is.

## Residual unknowns

- 98430-98435 period and clock rewind in FUN_004e42e0: `RESIDUAL (no foul effect)`.
- 183297 `local_134 = 1` in FUN_00597c00: a local of another function, no foul effect.
- Unlocated foul volume: about 6.64 PF per team-game.

## Golden snapshot

`git diff --stat origin/master -- engine/internal/sim/testdata` printed `engine/internal/sim/testdata/golden.json | 6210 +++++++++++++++++-------------` (3556 insertions, 2654 deletions). The cause is the new draws: one on every made 3pt, one on every unblocked missed 3pt, and one on every made 2pt. Each shifts every later draw in the fixture game, so box fields from the first made shot on change. No other test literal moved. `TestAndOneHomeAwaySplit` passed with its away floor of 34 unchanged (away 1046).

## Measurement

Master base 1cadb0416. FTA walk at stride 1, games cap 60.

| Run | Engine PF/g | Sco PF/g | Engine FTA/g | Sco FTA/g | FTA per PF (engine / sco) | FTA gap % | Home/away FTA |
|-----|-------------|----------|--------------|-----------|---------------------------|-----------|---------------|
| BEFORE | 8.034 | 17.116 | 8.356 | 18.587 | 1.0400 / 1.0859 | -55.04% | 1.144 (sco 1.101) |
| AFTER | 10.473 | 17.116 | 11.186 | 18.587 | 1.0681 / 1.0859 | -39.82% | 1.082 (sco 1.101) |

Engine-only counters, BEFORE: possessions 1363145, and-one sequences 22471, fouls 106727, FTA 110997. AFTER: possessions 1363876, and-one sequences 52264, fouls 139122, FTA 148601. The AFTER gap is outside the 5% close bar, so backlog#265 stays open.
