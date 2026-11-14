---
description: Trade suggestions module notes. Covers the v3 engine namespaces, the pipeline order, the laugh-test sweep, and every veto id with its rule.
last_verified: 2026-11-14
---

# Trade Suggestions

The panel on a GM's homepage lists trade ideas for that GM's team. `TradeSuggestionsService` builds it with the v3 engine. Each step below reads the output of the step before it.

`snapshot ⇒ generate ⇒ score ⇒ rank (gates: both-positive, 3x lopsided, exposure, vetoes) ⇒ validate ⇒ reason`

## Namespaces

`Projection` turns each player's ratings into a projected line for this season and the next few, with an age curve and injury status. `PlayerProjection` and `ProjectionPlayer` are the main types.

`Profile` ranks every team across the league. `TeamProfile` holds the starter and best-bench rank at each slot (PG SG SF PF C) and the rank on each stat dimension. `RankUtility::gain` turns a rank move into a score, where a lower rank is better.

`Valuation` puts a worth on contracts, picks and cap room. `ContractValue`, `PickWorth` and `GainWeights` are the entry points. `GainWeights` also holds the weight a team mode gives to each season.

`Engine` runs the pipeline. `CachedLeagueSnapshotProvider`, behind `LeagueSnapshotProviderInterface`, builds and caches one `LeagueSnapshot` per league. `CandidateGenerator::generate` lists the deal ideas for one GM. `DealScorer::scoreAll` scores both sides of each idea. `DealRanker::select` drops ideas that fail a gate and keeps the best ones. `ReasonWriter::forSide` writes one line per side that says why the side would say yes.

`Veto` holds the laugh-test rules. `VetoSet::standard` lists them in the order the ranker checks them.

Some plumbing from v2 stays: `TeamModeService` and `TeamModeGuesser` pick each team's mode, `SuggestionLink` builds the link that opens the trade form, `SuggestionValidationFilter` runs the real trade validator, and `TradeSuggestionsView` draws the panel.

## Engine snapshot and deal shapes

`Engine\CachedLeagueSnapshotProvider` caches one `LeagueSnapshot` per league in the `cache` table, keyed by sim, phase, roster digest, trade marker and resolved modes. Per-GM work (`CandidateGenerator::generate`, `DealScorer::scoreAll`) runs on every request and is never cached.

| Shape | Fires when |
|-------|------------|
| `OneForOne` | both sides hold a surplus that matches the other side's deficit |
| `TwoForOne` / `OneForTwo` | the receiving side has a weak slot and the giver can send two players |
| `StarForYoungAndPicks` | a Rebuild giver with a star meets a WinNow partner |
| `DumpPaidWithPick` | the giver's offset-1 payroll rank is in the bottom tier |
| `ExpiringForLong` | one side has an expiring player and the other a long contract |
| `PickSwap` | both sides own a round-1 pick and their modes differ |
| `ChangeOfScenery` | a buried player on one side meets a weak slot on the other |

`CandidateBudget` caps the search at 6 players per partner, 2 assets per side, 2 pick rounds and 4000 raw candidates. `SideGain::KEYS` lists the nine gain components: starter slots, bench slots, profile dimensions, balance, payroll, cap room, picks, roster spots and contracts.

## Laugh-test sweep

`php scripts/trade-suggestions-laugh-test.php` runs the engine for every team and checks each shown trade against every veto. It prints one line per team and a total line `TEAMS= IDEAS= VETOED= SHOWN= VIOLATIONS=`. It exits 0 only when `VIOLATIONS=0`. Flags: `--max-teams=N`, `--host`, `--db`, `--user` and `--pass`. An unknown flag prints the usage line and exits 2. On a full local league, run it inside the worktree's php container with `php -d memory_limit=2G`.

## Vetoes and reasons

- `YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE`: a side gives a younger player who beats the one it gets at his own slot and costs no more per remaining season.
- `WIN_NOW_WORSE_THIS_SEASON`: a win-now side gets worse this season at any starter slot, any best-bench slot, or in injury-adjusted rotation depth.
- `REBUILDER_OLD_LONG_MONEY_UNPAID`: a rebuilding side takes a player aged 29 or more with two or more future paid seasons and is not paid in picks worth his negative surplus.
- `PICK_TO_NON_BURDEN_TAKER`: a pick goes to the side that takes on no more salary burden than the other.
- `ROSTER_OVER_MAX_AFTER`: a side ends with more than 15 players. Any player can play any position, so the GM decides who plays where. There is no per-position check and no minimum roster.
- `STRONG_FOR_WEAK_WITHOUT_NET_GAIN`: a side gives up a strong starter spot to patch a weak one without a clear net gain.

`ReasonWriter::forSide` quotes the side's top two gain components with their before and after numbers. It reads the season its mode weighs most and this season first. When neither season has a gain, it reads the other seasons by weight and adds the season to the clause, such as "next season". A rank clause quotes only a rank that got better. A side with no positive component gets no reason, and the ranker drops the deal.
