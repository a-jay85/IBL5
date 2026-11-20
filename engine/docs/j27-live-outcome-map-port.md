---
description: J27 live outcome-map port. The Go engine now reads play-outcome codes 1-4 the way live JSB 5.60 does. Code 3 is the foul handler, and code 4 is the turnover path with a 10% offensive foul and the FUN_004e2fc0 steal split. Lists the removed scales, before/after aggregates, re-pinned test literals, and the open residuals.
last_verified: 2026-11-20
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

Fixture run: `engine/internal/sim/testdata/bundle.json`, 200 seeds from 20240601 (`foulRateSeed`), one game per seed. The command, run from `engine/`, copies a scratch test into `internal/sim`, runs `go test ./internal/sim -run "^(TestElompScratchAggregates|TestFoulRatesByTypeFixture)$" -count=1 -v`, then removes it. The real-life row is one run of the same scratch test on `bundle_reallife.json` after the port. No master run exists on that fixture. No `.sco` column appears, because `jsbcalibrate` needs the archive and no archive walk ran for this port. The plan asked for a `.sco` column. It stays open as a follow-up walk, and the owner's baseline decision rests on the sim BEFORE/AFTER rows only.

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

## Path shares against the trace

`livemap_test.go` logs these from 200 games on `bundle_reallife.json`:

| Measure | Engine | Live trace |
|---------|--------|------------|
| code-3 share of picks | 10.53% | 9.37% (610/6507) |
| code-4 share of picks | 13.32% | 11.02% (717/6507) |
| offensive-foul share of code 4 | 10.26% | const 0.1 (82/926 observed) |
| steal share of non-offensive-foul code 4 | 56.79% | 84.2% observed, 74.5% predicted |

The steal row comes from the backlog#1923 run. The other rows come from the port run. The backlog#1923 run removed one RNG draw per steal, so the stream shifted. In that run the code-4 share printed 13.2680% and the offensive-foul share printed 9.4397%.

### Archive walk before/after (backlog#1923)

`TestRealArchive_FTADecomp` (build tag `archive`) produced these rows on 2026-11-20 at its defaults: 60 games per snapshot, every snapshot, seed 20240601. Both walk binaries were built from master with the walk patch from PR #4089 applied, because master's walk does not print STL. Each walk read 113 matched recent-era snapshots and skipped one, giving 13,284 engine team-games and 12,056 `.sco` team-games. Values come from the `WALK side=` lines, per team-game. The before row matches the PR #4089 BEFORE row.

| Side | STL | TOV | FTA | PF |
|------|-----|-----|-----|----|
| `.sco` | 9.131 | 13.762 | 18.587 | 17.116 |
| engine before (master) | 7.203 | 14.662 | 19.983 | 18.089 |
| engine after (this port) | 7.346 | 14.914 | 19.851 | 17.886 |

STL rose by 2.0%, which is below the 11.4% bound the cap raise allows. TOV rose by 1.7% and FTA fell by 0.7%.
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

The `foulRateBands` width rule stays at ±10% with a floor of ±0.05. The path-mix band keeps the ±0.06 half-width the old band held around its 0.090 capture.

## Residuals

These stay open. This doc sets no target and proposes no scale.

- Code 5 is not ported. Live JSB fires it on 0.08% of picks and makes no turnover there.
- Closed by backlog#1923: the stealer pick now reuses the steal roll through `pickStealerFromRoll`, and the second draw is gone. The decompile scales the bins to the capped pool, so every steal credits a defender.
- Closed for the steal cap only by backlog#1923: the cap reads `stealCapLeagueSTL48 = 2.043`. The foul-bucket baseline in `bucketweights.go` still reads `leagueSTL48 = 1.834`. JSB keeps one value, so the two constants should merge once that file's owner moves it. The foul baseline probably reads the same `+0x68A8` global, which nobody has confirmed.
- The offensive foul counts as a team foul, which can push the offense toward the bonus. The trace does not show whether JSB counts it.
- The steal split is 56.79% in the engine after backlog#1923, against 74.5% predicted and 84.2% observed by the trace. That port moved the share through cited values only and did not close the gap. On `bundle_reallife.json` the cap rarely binds, so the cap raise moved the share by 0.18 points. On the archive walk the cap raise moved STL from 7.203 to 7.346 per team-game, against 9.131 in `.sco`.
- After backlog#1923 the archive TOV rose from 14.662 to 14.914 per team-game, more than the 1% the plan expected. A steal is a subset of code-4 turnovers, so the rise likely comes from more possessions after steals through the fast break. Nobody has measured that.
- Code 2 made 29 four-point plays over 200 real-life games. They carry `FoulSiteAndOne`, the tag the old and-one bucket used.
- On bundle.json the home/away FTA ratio is 2.03. That fixture runs the rating fallback path. On bundle_reallife.json the ratio is 1.105.
- The calibrate stand-ins `steal_turnover_scale` and `non_steal_turnover_scale` no longer drive anything. The archive-tagged research test that varies them would see no change.
- On bundle.json, FTA fell from 12.828 to 4.577 and TOV from 29.930 to 13.568 per team-game. No archive walk has compared the new aggregates to `.sco`.
