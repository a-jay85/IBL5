---
description: J27 third foul-volume pass. Covers the decompile sweep for PF and team-foul writes outside FUN_004d8570 (zero PORT sites), the per-site foul tags and walk tallies, and the backlog#1841 blocked-miss verdict (undetermined statically, guard kept). Engine PF per team-game stays 10.473 against sco 17.116, a measured residual of 6.643, so backlog#265 stays open.
last_verified: 2026-11-13
---

# J27: third foul-volume pass

This doc records the third search for the foul volume the engine lacks (a-jay85/IBL5-backlog#265) and the static reading of the blocked-miss 3pt foul roll (a-jay85/IBL5-backlog#1841). ADR-0236 (faithful JSB port) and ADR-0158 (no retuning dials) cover the change. The earlier passes are `engine/docs/j27-foul-volume-port.md` and `engine/docs/j27-foul-volume-2-port.md`. This pass excludes their audited rows, all of FUN_004d8570 (92789-94674), the 98430-98435 period rewind, and the FUN_004cee00 counter init at 90257.

The engine now tags every foul with the engine site that charged it. `chargeFoul` in `engine/internal/sim/possession.go` takes a `result.FoulSite` and writes it to the event. The field carries `json:"-"`, so CLI output and golden.json keep their bytes. `TestEveryFoulCarriesSite` in `engine/internal/sim/foulsite_test.go` fails when any foul carries `FoulSiteUnknown`. The FTA archive walk (`engine/internal/calibrate/fta_decomp_archive_test.go`) prints PF per team-game for each site and fails when the site sum misses engine PF/g by more than 0.001.

## RE sweep

The sweep ran over `jsb560_decompiled.c` (JSB 5.60). The player record is 0xe28 bytes at `param_1 + 0x63cc`, and its PF slot is `+ 0xc0`. The team-foul slots are 0x4c0c, 0x4c10 and 0x4c14 (index 0x1303 as `undefined4 *`). The foul handler is the label LAB_004d9d4d inside FUN_004d8570, so it has no external call sites.

| Decompile lines | FUN | Trigger | Effect | Class | Host/team-game | Rate | PF/team-game | Verdict |
|-----------------|-----|---------|--------|-------|----------------|------|--------------|---------|
| 95607, 95674, 95710 | FUN_004db520 | substitution pass | reads the PF slot for foul-out and foul trouble | read-only | n/a | n/a | 0 | RULED OUT |
| 92127-92324 | FUN_004d6f00 | rebound | writes 0x4c1c only, no PF or team-foul slot | no foul effect | n/a | n/a | 0 | RULED OUT |
| 92627-92757 | FUN_004d82a0 | energy update | writes the energy slot `+ 0x24` only | no foul effect | n/a | n/a | 0 | RULED OUT |
| 94696-95738 | FUN_004db520 | substitution dispatcher | no PF or team-foul write | no foul effect | n/a | n/a | 0 | RULED OUT |
| whole body | FUN_004e2610, FUN_004e22a0, FUN_004e4450 | functions the master reference ties to substitution, timeout and clock stops | no PF or team-foul write | no foul effect | n/a | n/a | 0 | RULED OUT |
| 23512, 197872, 198861 | FUN_00431e20, FUN_005b4e00, FUN_005b72c0 | game loops that call FUN_004d8570 until the game ends | no PF or team-foul write | caller only | n/a | n/a | 0 | RULED OUT |

No row is PORT. No row is RESIDUAL. Phase 5 of the plan made no engine change.

Searches and match counts:

- `command grep -n -E '0x63cc\) \+ 0xc0'` (PF slot on the player record): 8 hits. 93517, 93523, 94299, 94307 and 94360 sit in FUN_004d8570 (94360 is a foul-out read). 95607, 95674 and 95710 are reads in FUN_004db520.
- `command grep -n -E '= \(int \*\)\(.*\+ 0xc0\);'` (pointer alias to the PF slot): 2 hits, both in FUN_004d8570.
- Increments of a `+ 0xc0` target in any form (`+ 1`, `++`, `+= 1`): 2 hits, 93522-93523 and 94298-94299, both in FUN_004d8570.
- `command grep -n -E '0x4c0c|0x4c10|0x4c14|0x1303'` (team-foul slots): 6 hits. 90257 is the FUN_004cee00 init (excluded). 93515 is the increment, 93568 and 93647 are reads, and 94467-94468 is the period reset. All but the init sit in FUN_004d8570.
- `command grep -n 'LAB_004d9d4d'` (foul handler): 2 hits, both in FUN_004d8570.
- Callers of FUN_004d8570: 3 hits, the three game loops in the table.
- Writes through `param_1 + 0x63cc` outside FUN_004d8570: 212 lines across about 37 functions. The written offsets are 0x24, 0x80, 0x8c, 300, 0x351738 and 0x4fa18c. None is the PF slot.
- Stat-slot increments at 0xa8, 0xac, 0xb0, 0xb4 and 0xbc: 0 hits in every form.
- `bin/jsb-ref` topic counts: rebound 104 lines, substitution 43, timeout 9, fouled out 7. Loose ball, inbound, end of period, end of quarter and foul out each print 5 lines of headers with no matches. The functions those labels name are the table rows.

## Blocked-miss verdict (backlog#1841)

Step 4a answers, from `jsb560_decompiled.c`:

1. Line 94106 holds `cVar6 = FUN_004d7e10();`. The `if (cVar6 == '\0') {` block runs from 94108 to its closing brace at 94127 and holds the miss commentary. The 1% roll runs from 94131 to 94149.
2. FUN_004d7e10 (92550-92623) returns 1 when FUN_004e3400 (97934-98072) returns nonzero, and 0 otherwise. FUN_004e3400 is a weighted rng roll that picks a blocker slot 1 to 5, or 0 for no block. So FUN_004d7e10 is a block roll. On a block it calls the shot-block commentary FUN_004ef630 (104608, writes scratch randoms 0x4bf0, 0x4bf8 and 0x4c00 only) and FUN_004efcd0 (104817, no writes), then FUN_004f0830.
3. The decompile shows no block-stat increment. The 0xa8 to 0xbc slot increments return 0 hits, and FUN_004d7e10 and FUN_004e3400 write no stat slot. The value of `cVar6` that reaches the block-stat write cannot be read statically.
4. The 1% roll at 94131-94149 sits after the `if (cVar6 == '\0')` block closes, so it runs on both values of `cVar6`. At 94133-94144 a draw at or above 0.01 (`_DAT_00669e80`) books the 3pt attempt at slot 0xa4 and calls the rebound handler FUN_004d6f00, passing `cVar6` through `uStack_64` at 94138. A draw below 0.01 sets `local_130 = 3` and `local_110 = 1` at 94147-94148.

The Step 4b row that applies is "FUN_004d7e10 or the block write cannot be resolved statically". The verdict is undetermined statically. Point 4 suggests JSB rolls the foul after a blocked miss too. Without the block-stat write, the decompile cannot show whether JSB still credits the block on the fouled path, so the engine keeps the status quo.

The engine keeps `!blocked &&` in `threePtShot` (`engine/internal/sim/threeptfoul.go`). A comment above the guard cites the lines above and the runbook. `TestThreePtBlockedMissFoulVerdict` in `engine/internal/sim/threeptfoul_test.go` scans for a seed where the block draw and the foul draw both fire. It asserts that the shot stays a miss, one `EventBlock` is emitted, and no shooting-three foul or free throw appears. Deleting `!blocked && ` turns it red.

A debugger read settles the block credit. Follow `engine/docs/x32dbg-foul-divisor-pin-runbook.md` and break at 94131 after a blocked 3pt miss. If JSB rolls there and credits the block, the engine change is to drop the guard and emit `EventBlock` before `return threePtFouled`. Pass 2 estimated the missing volume at under 0.005 PF per team-game.

## Ported sites

No site ported.

## Per-site foul tally

PF per team-game from the `pf-by-site` lines of the BEFORE and AFTER walks. No site was ported, so no row has a Phase 3 estimate.

| Site | BEFORE PF/team-game | AFTER PF/team-game | Estimate (Phase 3) | Delta |
|------|---------------------|--------------------|--------------------|-------|
| shooting-two | 3.220 | 3.220 | n/a | 0.000 |
| shooting-three | 0.107 | 0.107 | n/a | 0.000 |
| and-one | 3.934 | 3.934 | n/a | 0.000 |
| non-shooting | 3.188 | 3.188 | n/a | 0.000 |
| late-game | 0.024 | 0.024 | n/a | 0.000 |
| unknown | 0.000 | 0.000 | n/a | 0.000 |
| sum | 10.473 | 10.473 | n/a | 0.000 |

The site sum equals engine PF/g (10.473) in both walks, and the unknown tally is 0.

## Blocking questions

- Q1, block credit on a fouled blocked 3pt miss (backlog#1841). The decompile shows no block-stat write, so the verdict is undetermined statically. Human follow-up: `engine/docs/x32dbg-foul-divisor-pin-runbook.md`, with a breakpoint at 94131 after a blocked miss.
- Q2, the source of the missing 6.643 PF per team-game. Every PF-slot and team-foul write in the decompile sits in FUN_004d8570, and the first two passes audited them all. No RESIDUAL (constant unread) site came out of this sweep. The next lever is a debugger trace of PF-slot writes during one game, also through `engine/docs/x32dbg-foul-divisor-pin-runbook.md`.

## Residual unknowns

Residual = sco PF/team-game minus engine AFTER PF/team-game = 17.116 - 10.473 = 6.643, both read from the AFTER walk.

No site in this sweep has a RESIDUAL verdict. The sites that could account for the 6.643 are the ones the sweep cannot see:

- The blocked-miss 3pt roll (undetermined statically). Pass 2 put it under 0.005 PF per team-game, so it covers little of the residual.
- Rates inside FUN_004d8570 that the earlier passes ported, if a runtime global differs from its static value. Only a debugger read can test this.
- A foul path whose PF write the decompile hides, such as an indirect store through a register the decompiler did not resolve. The increment counts above found no candidate.

## Golden snapshot

golden.json unchanged. `go test ./internal/sim -run Golden -count=1` passed with no regeneration, because the guard stayed and no site was ported.

## Measurement

Master base f1737fae7549a6615f096d4be488774b09dee4db. The walk command, run from `engine/`, was `JSB_ARCHIVE_DIR=<archive dir> go test -tags archive -run TestRealArchive_FTADecomp -count=1 -v ./internal/calibrate/`. It used the built-in settings: seed 20240601, stride 1, games cap 60, over 113 snapshots. Engine PF/g BEFORE is 10.473, the same figure as the latest backlog#265 comment, so master did not move the walk since pass 2.

| Row | BEFORE | AFTER | sco |
|-----|--------|-------|-----|
| engine PF/g | 10.473 | 10.473 | 17.116 |
| FTA/g | 11.186 | 11.186 | 18.587 |
| FTA per PF | 1.0681 | 1.0681 | 1.0859 |
| FTA gap % | -39.82% | -39.82% | 0% |
| home/away FTA ratio | 1.082 | 1.082 | 1.101 |

Engine-only counters, the same in both walks: possessions 1363876, and-one sequences 52264, fouls 139122, FTA 148601. The AFTER gap is outside the 5% close bar, so backlog#265 stays open.
