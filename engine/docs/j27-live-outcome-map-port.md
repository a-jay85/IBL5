---
description: J27 live outcome-map port. The Go engine now reads play-outcome codes 1-4 the way live JSB 5.60 does. Code 3 is the foul handler, and code 4 is the turnover path with a 10% offensive foul and the FUN_004e2fc0 steal split. Lists the removed scales, before/after aggregates, re-pinned test literals, and the open residuals.
last_verified: 2026-11-17
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

On the steal split, the roll is `Float64() × turnoverPool(offense)`. The turnover pool is Σ (2.0 − fatigue) × tovRate over the five offensive players. The play is a steal when the roll lands below `stealPool(defense)`. That pool is Σ stlRate × fatigue over the five defenders, capped at 1.5 × 5 × leagueSTL48. An offensive foul charges the ball handler a turnover and a personal foul tagged `FoulSiteOffensiveTurnover`. It adds one team foul and shoots no free throws.

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
| steal share of non-offensive-foul code 4 | 56.61% | 84.2% observed, 74.5% predicted |
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
- The engine picks the stealer with `selectStealer`, which draws its own roll. JSB reuses the steal-split roll to pick the stealer.
- `leagueSTL48` is 1.834 in the engine. The live value is 2.043, so the steal-pool cap sits lower than live.
- The offensive foul counts as a team foul, which can push the offense toward the bonus. The trace does not show whether JSB counts it.
- The steal split is 56.6% in the engine against 84.2% observed and 74.5% predicted by the trace.
- Code 2 made 29 four-point plays over 200 real-life games. They carry `FoulSiteAndOne`, the tag the old and-one bucket used.
- On bundle.json the home/away FTA ratio is 2.03. That fixture runs the rating fallback path. On bundle_reallife.json the ratio is 1.105.
- The calibrate stand-ins `steal_turnover_scale` and `non_steal_turnover_scale` no longer drive anything. The archive-tagged research test that varies them would see no change.
- On bundle.json, FTA fell from 12.828 to 4.577 and TOV from 29.930 to 13.568 per team-game. No archive walk has compared the new aggregates to `.sco`.
