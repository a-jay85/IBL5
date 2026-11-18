---
description: Trade suggestions module notes. Covers the v3 engine namespaces, the pipeline order, the laugh-test sweep, every veto id with its rule, the hide and refresh feedback flow, and the admin tuning page.
last_verified: 2026-11-18
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

## Feedback (hide and refresh)

Each card has a hide form and the panel has one refresh button. Both post to `modules.php?name=Trading&op=trade-suggestion-action`, which `Feedback\SuggestionFeedbackController` handles.

A hide writes one row to `ibl_trade_suggestion_feedback`. The row holds the GM team id, the deal key, the core key, the partner team id, a JSON list of the assets, the reason codes and an optional note of up to 500 characters. The table allows one row per (`tid`, `deal_key`).

`Feedback\DealKey` builds two keys for each deal. The deal key is a sha1 over both team ids and every asset on each side. The core key uses the same inputs with players only. `Feedback\HiddenDealFilter` drops a deal when either key matches a stored hide. So a variant that swaps the pick sweetener stays hidden.

The service saves every idea it can show into the PHP session, keyed by deal key, through `Feedback\PanelSessionState`. The hide form posts only `deal_key`. The controller looks the deal up in the session and stores what it finds there. It takes the team id from the logged-in GM. It ignores any team id, partner or asset in the form.

On failure the controller redirects to `/ibl5/index.php?trade_suggestion_error=<code>`. The codes are `method`, `csrf`, `auth`, `action`, `stale`, `reason` and `length`. A deal key the session does not hold is `stale`.

Refresh moves the session offset forward by 3 ideas. When the offset reaches the end of the list it goes back to 0. A new sim resets the offset and the saved ideas.

## Tuning page (admin)

The tuning page lives at `modules.php?name=TradeSuggestionsTuning`. Only admins can open it. It reads data and never writes. The menu has no link to it.

It takes two GET parameters:

- `team` is `all` or a franchise tid. It defaults to tid 1. `all` runs every team and may stop early at the time budget.
- `status` is `all`, `shown`, `vetoed` or `hidden`. It defaults to `all`.

An unknown value falls back to the default, and the page names the parameter it ignored.

### How to read a row

Each row is one scored deal the engine built for that team in the current sim. The columns are:

| Column | Meaning |
|--------|---------|
| Rank | The order the ranker gave the deal. |
| Outcome | Where the deal ended. The labels are listed below. |
| Partner | The other team. |
| Shape | The deal shape that built it. |
| Weaker side | The total gain of the side that gains less. |
| Your gain by season | The `SideGain` parts for this team, one cell per season. |
| Partner gain by season | The same parts for the partner. |
| Vetoes | The veto ids that fired. |
| Detail | The gate that dropped the deal, such as the validator fail code, the exposure cap or the lopsided ratio. |
| Exposure | Whether the partner may be shown to this team. |
| Feedback | The GM's hide reasons and note, as the GM typed them. |

The outcome labels come from `Engine\RankOutcome`:

| Label | Meaning |
|-------|---------|
| Shown (top 3) | The ranker picked it for the current panel. |
| Eligible (later refresh) | It passed every gate and would show after a refresh. |
| One side does not gain | One team's total gain is zero or less. |
| Lopsided | One side gains far more than the other. |
| Partner not open to this team | The exposure rule keeps this partner off this team's panel. |
| Vetoed | At least one veto fired. |
| Refused by trade rules | The trade validator rejected the deal. |
| No reason for one side | One team has no motive for the deal. |
| Not reached (cap hit) | The ranker filled its picks before it reached this deal. |
| Hidden by the GM | The GM hid this deal from the panel. |

Each team section opens with counts: the mode, the eligible partners, the raw candidates, the prefilter rejections, whether generation hit its cap, and the ranker diagnostics.

For what each veto id means, see [Vetoes and reasons](#vetoes-and-reasons). This page uses the same ids.

### Hidden ideas not in this run

Below the rows, each team has a table of stored hides whose deal the current run did not build again. It lists the partner, the reasons, the note and the time the GM hid it.

### Cost

The page reads one cached snapshot. Then for each team it generates deals under the `CandidateBudget` caps, scores them and ranks them. It shows 60 rows per team. It checks a 20 second budget between teams. When the budget runs out it stops, and a note names the teams it skipped. The config block on the page prints the live values of these limits.
