---
description: J27 live outcome-map port. The Go engine now reads play-outcome codes 1-4 the way live JSB 5.60 does. Code 3 is the foul handler, and code 4 is the turnover path with a 10% offensive foul and the FUN_004e2fc0 steal split. Lists the removed scales, before/after aggregates, re-pinned test literals, the open residuals, an archive walk against .sco and a per-type foul table against the live trace.
last_verified: 2026-11-27
---

# J27: live outcome-map port

This doc records the move of the Go engine to the live JSB 5.60 play-outcome map. The live trace (`~/claude-plans/_reports/2026-11-16-jsb-tov-trace.md`) showed that the engine had codes 3 and 4 swapped. The engine sent code 3 to an and-one path and code 4 to a foul-only path, and it rolled steals before the picker ran. This port takes the map from the bucket loop at `jsb560_decompiled.c:97179-97196` and the trace.

## The live map

| Code | Live JSB handler | Engine handler after this port |
|------|------------------|--------------------------------|
| 1 | 2pt attempt | `outcome2pt`. The made-2 roll can add an and-one (`FoulSiteAndOneMadeTwo`). |
| 2 | 3pt attempt | `outcome3pt`. The made-3 1% roll can add a four-point play (`FoulSiteAndOne`). |
| 3 | foul handler at 0x4d9ea2 | `outcomeFoul`. Every pick goes to `resolveFoul`, shooting or non-shooting. |
| 4 | turnover path | `outcomeTurnover`, `resolveTurnoverPath` in `engine/internal/sim/steal.go`. 10% are offensive fouls (const 0.1 at 0x4da7a0). The rest split into steal or plain turnover by the FUN_004e2fc0 roll. |
| 5 | 0.08% override that makes no turnover | Not ported. The picker returns only codes 1-4. |

On the steal split, the roll is `Float64() × turnoverPool(offense)`. The turnover pool is Σ (2.0 − fatigue) × tovRate over the five offensive players. The play is a steal when the roll lands below `stealPool(defense)`. That pool is Σ stlRate × fatigue over the five defenders, capped at 1.5 × 5 × `stealCapLeagueSTL48` (2.043, the league STL/48 that the live trace read at `+0x68A8`). On a steal, `pickStealerFromRoll` picks the stealer from the same roll. Each defender owns a bin of width stlRate × fatigue in lineup order, and the bin that holds the roll wins. When the uncapped total passes the cap, every bin edge is scaled by pool/total, the way the decompile does it at `jsb560_decompiled.c:97880-97889`. The pick draws no RNG. An offensive foul charges the ball handler a turnover and a personal foul tagged `FoulSiteOffensiveTurnover`. It adds one team foul and shoots no free throws.

## Removed scales and old values

| Name | Old value | Where it lived |
|------|-----------|----------------|
| `stealTurnoverScale` | 1.69e-5 | pre-picker steal roll, `steal.go` |
| `nonStealTurnoverScale` | 0.00175 | pre-picker non-steal turnover roll, `steal.go` |
| `turnoverDenom` / `energyCeiling` | 1793 | code-5 turnover override, `outcome.go` and `possession.go` |
| `foulBucketVolumeScale` | 2.10 | foul-only bucket, removed by the second commit on this branch |
| `andOneHalfCourtScale` | 0.053 | half-court and-one basis, removed by the second commit on this branch |

The freeze TVR arm (`FreezeConfig.TVR`, `FreezeMeans.TurnProb`) froze the old steal-driven turnover probability. It is inert now, and `Options.validate` no longer rejects a zero `TurnProb`.

## Before and after

Fixture run: `engine/internal/sim/testdata/bundle.json`, 200 seeds from 20240601 (`foulRateSeed`), one game per seed. The command, run from `engine/`, copies a scratch test into `internal/sim`, runs `go test ./internal/sim -run "^(TestElompScratchAggregates|TestFoulRatesByTypeFixture)$" -count=1 -v`, then removes it. The real-life row is one run of the same scratch test on `bundle_reallife.json` after the port. No master run exists on that fixture. These fixture rows carry no `.sco` column. The archive walk in the next section supplies the `.sco` comparison.

Per team-game:

| Run | PF | FTA | TOV | STL | home/away FTA |
|-----|----|-----|-----|-----|---------------|
| bundle.json, master BEFORE | 11.363 | 12.828 | 29.930 | 19.773 | 1.068 |
| bundle.json, AFTER | 5.515 | 4.577 | 13.568 | 5.000 | 2.031 |
| bundle_reallife.json, AFTER | 16.830 | 18.119 | 16.592 | 8.409 | 1.105 |

PF per team-game by foul site on bundle.json:

| Site | BEFORE | AFTER |
|------|--------|-------|
| shooting-two | 4.232 | 0.755 |
| shooting-three | 0.220 | 0.233 |
| and-one | 1.147 | 0.000 |
| non-shooting | 3.562 | 0.797 |
| late-game | 0.025 | 0.025 |
| and-one-made-two | 1.810 | 2.330 |
| non-shooting-bonus | 0.365 | 0.018 |
| offensive-turnover | (no site) | 1.357 |

## Archive walk against .sco

`TestRealArchive_FTADecomp` (build tag `archive`) produced these rows on 2026-11-17. The command, run from `engine/` with `JSB_ARCHIVE_DIR` at the backup archive, was `JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 JSB_FTA_PHASE=before go test -tags archive -run '^TestRealArchive_FTADecomp$' -count=1 -v ./internal/calibrate`, and the AFTER row used `JSB_FTA_PHASE=after`. The test reads neither `JSB_ARCHIVE_RUNS` nor `JSB_ARCHIVE_STRIDE`. Its own settings stayed at their defaults: 60 games per snapshot (`JSB_FTA_GAMES`), every snapshot (`JSB_FTA_SNAP_STRIDE=1`), and seed 20240601 (`JSB_FTA_SEED`). Each walk read 112 recent-era (05-08) snapshots, which gave 13,284 engine team-games and 12,056 `.sco` team-games after the test dropped 78 all-zero engine games. Values come from the `WALK side=` lines, per team-game.

| Run | PF | FTA | TOV | STL | home/away FTA |
|-----|----|-----|-----|-----|---------------|
| .sco | 17.116 | 18.587 | 13.762 | 9.131 | 1.101 |
| archive BEFORE | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |
| archive AFTER | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |

## Foul rates by type against the live trace

Each share is that type's PF divided by total PF. The trace shares come from the 1524 fouls of the 47-game main run in `~/claude-plans/_reports/2026-11-16-jsb-foul-trace-q2.md`. The BEFORE and AFTER shares come from the `WALK site_pf` lines of the archive walk above. The trace and the walk cover different seasons, so this table compares the mix of foul types and leaves raw rates out.

Engine sites map to trace types this way. Non-shooting is `non-shooting` + `non-shooting-bonus` + `late-game`. And-one is `and-one` + `and-one-made-two`. Shooting-two, shooting-three, and offensive-turnover map to the site of the same name. The engine has no site for the one-FT trip at `0x4da427`, so its engine share is 0.

A type is `ported` when its share differs from the trace by 0.03 or more and its cause sits at a cited decompile line that the engine ports differently. It is `residual` when the gap is there and the cause is not located. It is `matched` when the gap is under 0.03.

| Type | Trace share | BEFORE share | AFTER share | Decompile line | Verdict |
|------|-------------|--------------|-------------|----------------|---------|
| non-shooting | 0.386 | 0.396 | 0.396 | jsb560_decompiled.c:93469-93480 | matched |
| shooting-two | 0.388 | 0.387 | 0.387 | jsb560_decompiled.c:93469-93480 | matched |
| shooting-three | 0.006 | 0.005 | 0.005 | jsb560_decompiled.c:93469-93480 | matched |
| and-one | 0.115 | 0.124 | 0.124 | jsb560_decompiled.c:93919-93926 | matched |
| offensive-turnover | 0.081 | 0.088 | 0.088 | jsb560_decompiled.c:94290-94293 | matched |
| one-FT 0x4da427 | 0.024 | 0.000 | 0.000 | jsb560_decompiled.c:93790-93828 | matched |

Every gap is under 0.03, so this PR ports no foul rate. The largest gap is the one-FT trip at 0.024. JSB reaches it in the `else` branch of the made-shot test `FUN_004e2220()` at 93790-93828: the shot missed, `local_150 == 1` marks an and-one foul, and the FT call `FUN_004e9fc0` returns to `0x4da42c` with `piStack_114` (the made-shot result, 0 here) as its argument. The trace saw one FT on each of these trips. The engine has no path that charges this foul, so a port needs a site for it.

## Path shares against the trace

`livemap_test.go` logs these from 200 games on `bundle_reallife.json`:

| Measure | Engine | Live trace |
|---------|--------|------------|
| code-3 share of picks | 10.53% | 9.37% (610/6507) |
| code-4 share of picks | 13.32% | 11.02% (717/6507) |
| offensive-foul share of code 4 | 10.26% | const 0.1 (82/926 observed) |
| steal share of non-offensive-foul code 4 | 56.79% | 84.2% observed, 74.5% predicted |

The steal row comes from the backlog#1923 run. The other rows come from the port run. The backlog#1923 run removed one RNG draw per steal, so the stream shifted. In that run the code-4 share printed 13.2680% and the offensive-foul share printed 9.4397%.

The backlog#1919 run changed no engine code. It re-measured the steal share at 56.7879% on the same 200 games, then tested one candidate cause offline against `ev2.jsonl` (the 2026-11-16 live trace, 650 steal rolls, 17 player dumps). The candidate: JSB caps fatigue at 1.0 on the defender side of the steal roll only (decompile 97858-97867), and leaves it uncapped on the offense side (97878-97890). Live `+0x24` ran 51 to 2,285, so live offense fatigue ran 1.017 to 1.762 and shrinks the turnover pool, which raises the steal share.

Each player dump pairs with the steal roll logged just before it, because the `tvhook.js` callback at `0x4e3388` pushes the `S` row and then calls `dump()`. With that pairing the check reproduces the trace's inputs:

| Check | Result |
|-------|--------|
| all-event share `mean(min(1, pool/range))` over 650 rolls | 74.492% (trace report 74.5%) |
| dumps paired with a roll | 17 of 17 |
| defense pool, capped fatigue, median error | 0.000% |
| offense pool at `+0xDE0`, uncapped fatigue, median error | 6.961% |
| offense pool at `+0xDE0`, capped fatigue, median error | 29.038% |
| offense pool at `+0xDD8`, uncapped fatigue, median error | 0.000% |
| offense pool at `+0xDD8`, capped fatigue, median error | 37.700% |

The plan fixed the offense slot at `+0xDE0` before the run, because `tovRate` cites that offset. At `+0xDE0` the uncapped reading misses the 1% bar (6.961%), so the port rule failed and this PR ports nothing. The pool-check output is `~/claude-plans/_reports/2026-11-21-steal-share-1919-pool-check.txt`.

### Archive walk before/after (backlog#1923)

`TestRealArchive_FTADecomp` (build tag `archive`) produced these rows on 2026-11-20 at its defaults: 60 games per snapshot, every snapshot, seed 20240601. Both walk binaries were built from master with the walk patch from PR #4089 applied, because master's walk does not print STL. Each walk read 113 matched recent-era snapshots and skipped one, giving 13,284 engine team-games and 12,056 `.sco` team-games. Values come from the `WALK side=` lines, per team-game. The before row matches the PR #4089 BEFORE row.

| Side | STL | TOV | FTA | PF |
|------|-----|-----|-----|----|
| `.sco` | 9.131 | 13.762 | 18.587 | 17.116 |
| engine before (master) | 7.203 | 14.662 | 19.983 | 18.089 |
| engine after (this port) | 7.346 | 14.914 | 19.851 | 17.886 |

STL rose by 2.0%, which is below the 11.4% bound the cap raise allows. TOV rose by 1.7% and FTA fell by 0.7%.

### Archive walk before/after (backlog#1919)

The engine code did not change, so there is one engine row. `TestRealArchive_FTADecomp` ran on 2026-11-21 at its defaults and printed these `WALK side=` lines per team-game. The engine row matches the "after this port" row of the backlog#1923 table.

| Side | STL | TOV | FTA | PF |
|------|-----|-----|-----|----|
| `.sco` | 9.131 | 13.762 | 18.587 | 17.116 |
| engine (code unchanged) | 7.346 | 14.914 | 19.851 | 17.886 |

| code-2 four-point plays | 29 | not traced |

## Re-pinned literals

Each was re-pinned from one run of this tree.

| Literal | Old | New |
|---------|-----|-----|
| `foulRateBands` shooting-two (`foulrate_test.go`) | 4.232 | 0.755 |
| `foulRateBands` shooting-three | 0.220 | 0.233 |
| `foulRateBands` and-one | 1.147 | 0 |
| `foulRateBands` non-shooting | 3.562 | 0.797 |
| `foulRateBands` late-game | 0.025 | 0.025 |
| `foulRateBands` and-one-made-two | 1.810 | 2.330 |
| `foulRateBands` non-shooting-bonus | 0.365 | 0.018 |
| `foulRateBands` offensive-turnover | (none) | 1.357 |
| `TestBucketWeights_FoulPathMix` home share band | [0.03, 0.15] | [0.1056, 0.2256] (0.1656 ±0.06) |
| `engine/internal/sim/testdata/golden.json` | master snapshot | regenerated with `-update` |
| `engine/internal/sim/testdata/golden_seeds.sha256` | (none) | new. `TestGolden` hashes 30 more seeds, because one golden game missed a 0.1 → 0.11 change to `offensiveFoulOnTurnover`. |
| `engine/internal/validate/bands.go` literals | unchanged | unchanged (the validate tests pass as they stand) |
| `TestSimulate_InjuryRateBand` upper bound (`sim_test.go`) | 0.30 | 0.38 |

The injury upper bound came with backlog#1923, and it is derived rather than read off one run. The synthetic `richBundle` fixture runs about 44.7 turnovers per game. `injuryProbability` is 0.007 per turnover, so the expected rate is 0.007 × 44.72 = 0.313 injuries per game. Over 1200 games that is about 375.6 injuries. The binomial sigma is about 19.3 injuries, or 0.016 per game. The expected rate plus 4 sigma is about 0.377, and 0.38 rounds that up. On 2026-11-27 the test measured 0.3417 injuries per game (410 over 1200 games). That is about 1.8 sigma above the expected rate, and it fails the old 0.30 bound. 0.38 is a bound for this synthetic fixture only. It makes no claim that the real corpus injury rate rose. `engine/internal/sim/injury.go` cites a historical corpus rate of about 0.2 injuries per game.

The `foulRateBands` width rule stays at ±10% with a floor of ±0.05. The path-mix band keeps the ±0.06 half-width the old band held around its 0.090 capture.

## Residuals

These stay open. This doc sets no target and proposes no scale.

- Code 5 is not ported. Live JSB fires it on 0.08% of picks and makes no turnover there.
- Closed by backlog#1923: the stealer pick now reuses the steal roll through `pickStealerFromRoll`, and the second draw is gone. The decompile scales the bins to the capped pool, so every steal credits a defender.
- Closed for the steal cap only by backlog#1923: the cap reads `stealCapLeagueSTL48 = 2.043`. The foul-bucket baseline in `bucketweights.go` still reads `leagueSTL48 = 1.834`. JSB keeps one value, so the two constants should merge once that file's owner moves it. The foul baseline probably reads the same `+0x68A8` global, which nobody has confirmed.
- The offensive foul counts as a team foul, which can push the offense toward the bonus. The trace does not show whether JSB counts it.
- The steal split is 56.79% in the engine after backlog#1923, against 74.5% predicted and 84.2% observed by the trace. That port moved the share through cited values only and did not close the gap. On `bundle_reallife.json` the cap rarely binds, so the cap raise moved the share by 0.18 points. On the archive walk the cap raise moved STL from 7.203 to 7.346 per team-game, against 9.131 in `.sco`.
- Open after backlog#1919: the steal share is still 56.7879% (the engine's `turnoverPool` in `steal.go` still uses the capped fatigue curve). The offline check on `ev2.jsonl` found the live turnover pool reproduces to a 0.000% median error over 17 pairs when each offense player's fatigue is uncapped and the rate is `+0xDD8`. The capped reading misses by 37.700% at `+0xDD8`. At `+0xDE0` the uncapped reading misses by 6.961% and the capped one by 29.038%. `00_MASTER_REFERENCE.md:529` says `+0xDD8` and `+0xDE0` start as one TOV/MIN×48 value, and `+0xDE0` is then usage-shrunk in the second pass while `+0xDD8` stays raw (store at `4d4509-4d451e`, line 1526). The engine's `tovRate` implements the unshrunk form. The next attempt can fix the slot at `+0xDD8` before it runs the check, port an uncapped fatigue helper into `turnoverPool` only (decompile 97878-97890), and then test whether the engine's on-court energy range covers live `+0x24` of 51 to 2,285. The engine's energy scale may differ from `+0x24`, so the share could move only part of the way. The defender side stays capped at 1.0 (97858-97867).
- After backlog#1923 the archive TOV rose from 14.662 to 14.914 per team-game, more than the 1% the plan expected. A steal is a subset of code-4 turnovers, so the rise likely comes from more possessions after steals through the fast break. Nobody has measured that.
- Code 2 made 29 four-point plays over 200 real-life games. They carry `FoulSiteAndOne`, the tag the old and-one bucket used.
- On bundle.json the home/away FTA ratio is 2.03. That fixture runs the rating fallback path. On bundle_reallife.json the ratio is 1.105.
- The calibrate stand-ins `steal_turnover_scale` and `non_steal_turnover_scale` no longer drive anything. The archive-tagged research test that varies them would see no change.
- The archive walk puts the engine above `.sco` on PF (18.089 against 17.116 per team-game, BEFORE and AFTER alike) and on FTA (19.983 against 18.587). STL runs low (7.203 against 9.131) and TOV high (14.662 against 13.762). The walk table under `## Archive walk against .sco` holds the rows. The foul mix matches the trace, so the PF excess points at the overall foul volume, which no single foul type explains.
