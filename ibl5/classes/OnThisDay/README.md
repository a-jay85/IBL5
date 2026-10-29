---
description: On this day module. Ranks past league-calendar moments from the latest sim's dates for the homepage card and a post-sim Discord line, and never repeats a moment shown in an earlier sim.
last_verified: 2026-10-28
---

# OnThisDay

## What it shows

The module picks a few past moments that happened on the same league-calendar day as the latest sim. The dates come from the latest sim in `ibl_sim_dates`, from its `start_date` through its `end_date`. The wall clock plays no part. Moments from the current season are excluded. The `N seasons ago` badge is the current season year minus the moment's season year, and a season rolls over at month 9 or later.

## Where moments come from

The nightly `RebuildOnThisDayMomentsStep` fills `ibl_on_this_day_moments` through `OnThisDayMomentBuilder`. The builder is the only class here that reads `ibl_box_scores`. Each run deletes every row and inserts the full set again inside one transaction, so a failed rebuild keeps last night's rows. The step skips when the updater has used more than 120 s of its 300 s.

The homepage and `PostOnThisDayStep` read the table through `OnThisDayRepository::findMoments()`. That is one indexed query per calendar radius. An empty or missing table shows no card and posts nothing.

| Kind | Built from | Why |
|------|-------|-----|
| `fifty_point` | `ibl_box_scores` | Box scores are the primary record of every game. |
| `triple_double` | `ibl_box_scores` | Ten or more in three of points, rebounds, assists, steals, blocks. |
| `record_game` | `ibl_box_scores` | A regular-season game that strictly beats the league's prior high for one stat. One ordered pass feeds `RecordGameTracker`, and the first season only sets the bar. |
| `series_clincher` | `ibl_box_scores` plus `ibl_playoff_series_results` | The last playoff game between the pair dates the clinch. The series table supplies only the round label and the win count. |
| `trade` | `ibl_box_scores` | A scorer's team changes mid-season. `ibl_trade_info` and `nuke_stories` carry real-world timestamps only, so they cannot place a trade on the league calendar. |

Player names come from `ibl_plr` joined on `pid`. The box-score `name` column is never used.

## Phantom guard

Every player-game query groups on `(game_date, visitor_teamid, home_teamid, pid)`. A real game is unique on date, visitor and home, so a repeated row set for the same player is a phantom. Grouping collapses it to one line.

## Wow score

`WowScorer` gives each moment a score. A score of 0 drops it.

| Kind | Score |
|------|-------|
| 50-point game | 100, plus 2 per point above 50 |
| Triple-double | 60, plus points + rebounds + assists above 30; a quadruple-double adds 40 |
| Record game | 150 |
| Series clincher | 70; the Finals add 30 and a game 7 adds 15 |
| Trade | 40, plus 5 per player moved, counting at most 6 |

Ranking is a total order: score descending, then season year descending, then kind order, then key. At most 2 moments of one kind make the cut.

## Window and repetition

The base window is every month-day of the latest sim, from its start date through its end date. The service widens that span by 0, 1, 3 and 7 days on each side and stops at the first radius with a moment. A span longer than 31 days keeps its last 31 days. A sim with no start date searches its end date alone. The card's date label is still the sim's end date.

The `ibl_on_this_day_shown` table records each moment key a sim showed. The service leaves out every key shown in an earlier sim, in any season, so the same calendar week shows different moments each year. A sim's own rows never exclude themselves, so the card and the Discord post agree for the whole sim. When every candidate at radius 7 was shown before, the service picks the ones shown longest ago. A missing or empty history table excludes nothing.

## Surfaces

The homepage card renders in the News module on the landing page only, for anonymous and logged-in visitors. Topic-filtered pages omit it.

`PostOnThisDayStep` runs at the end of the updater. It posts the top moment to Discord during HEAT, Regular Season and Playoffs. The `on_this_day_last_posted_sim` key in `ibl_settings` makes it post once per sim. The step never returns a failure, and Discord errors are swallowed.

`RecordOnThisDayShownStep` runs on every updater run in every season phase, after the moments rebuild and before the Discord post. It writes the latest sim's selection to `ibl_on_this_day_shown`, one row per moment and sim, so a re-run adds nothing. It never returns a failure. The homepage card only reads the table.

## Composition

`OnThisDayFactory` is the only place the object graph is built. The homepage, the updater and the pipeline test all call it.
