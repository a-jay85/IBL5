---
description: ibl_team_win_loss keys on the (date, visitor, home) matchup triple with min(game_of_that_day) as the canonical row, so a duplicate boxscore is invisible in the view by design; the duplicate invariant therefore lives on the raw ibl_box_scores_teams table, runs unconditionally, and is scoped to game_type = 1.
last_verified: 2026-08-29
---

# ADR-0109: Matchup-Triple Dedup Key for `ibl_team_win_loss`, and a Raw-Table Duplicate Invariant

**Status:** Accepted
**Date:** 2026-08-29

## Context

`ibl_team_win_loss` was defined in migration 121 with a four-column dedup key in its `unique_games` CTE: `(game_date, visitor_teamid, home_teamid, game_of_that_day)`. That key was chosen because it looks like the game's full natural identity.

It is not. `game_of_that_day` is a **league-wide ordinal within a date**, not a per-matchup counter — the same reasoning ADR-0108 already used to reject it as a join key. A boxscore duplicated for one matchup lands at a *different* ordinal, so the four-column key sees two distinct games where there is one. The season-2004 corruption is exactly this shape: a second Aces @ Jazz row at a different ordinal made both teams read **83** games in an 82-game season, while the other 26 teams read 82. The wrong number was not in the data being aggregated; it was in the key doing the aggregating.

Meanwhile the duplicate check that should have caught it — `KIND_DUPLICATE_TRIPLE` in `ScheduleReconciliationAudit` — sat inside the audit's fail-open guard. ADR-0108 established that guard so a season with **no** `ibl_schedule` rows would not report every boxscore as an orphan. That reasoning is sound for the orphan direction, which needs the schedule to decide anything. It does not transfer to the duplicate direction, which compares `ibl_box_scores_teams` against itself. Season 2004 has no schedule rows, so the one check that would have named the duplicate was silently skipped on the one season that had it.

## Decision

**1. The view's dedup key is the matchup triple.** Migration 172 redefines `ibl_team_win_loss` to canonicalize on `(game_date, visitor_teamid, home_teamid)`, selecting `min(game_of_that_day)` as the canonical row — "first recorded wins". This is the in-repo house pattern; `vw_schedule_upcoming` in migration 121 canonicalizes identically. Choosing a row *deterministically* is load-bearing rather than cosmetic: duplicate rows can disagree on the score, and an arbitrary-row `GROUP BY` selection would make **which team is credited with the win** nondeterministic across query plans.

**2. A duplicate is therefore invisible in the view, by design.** Collapsing the pair is the correct reporting behaviour — a league page should show 82 games — but it also means the view can never be the place a duplicate is *detected*. The two properties are in tension only if one surface is asked to do both jobs.

**3. The duplicate invariant lives on the raw table and runs unconditionally.** `ScheduleReconciliationAudit` moves the `findDuplicateTripleGames()` loop out of the fail-open guard. **ADR-0108's fail-open guard is hereby narrowed to the orphan direction only.** The duplicate direction never reads `ibl_schedule`, so an empty schedule index is not a reason to skip it.

**4. The invariant is scoped to `game_type = 1`.** Playoff series and HEAT games legitimately repeat a matchup on one date; the regular season never does. `findDuplicateTripleGames()` gains two optional parameters, `?int $seasonYear = null, ?int $gameType = null`, that compose independently — a null drops that predicate, so an unscoped call is an all-seasons, all-type scan. Existing single-argument callers are byte-identical.

## Alternatives Considered

- **Repair the 2004 rows and leave the view's key alone.** Rejected because: it fixes one instance of a defect that will recur on the next duplicated import. The key would still count any future duplicate as a distinct game.
- **Delete the duplicate row as part of this change.** Rejected because: this ADR's change is *reporting* correctness, and deleting production rows is a separate decision with a separate blast radius. ADR-0108's "the audit reports; a human repairs" split is retained.
- **`max(game_of_that_day)` as the canonical row.** Equally deterministic. Rejected because `min()` matches `vw_schedule_upcoming`, and "first recorded wins" is the weaker claim — it prefers the row that existed before whatever wrote the second one.
- **Detect duplicates from `ibl_team_win_loss`.** Rejected because decision 1 makes it structurally impossible: the view collapses the pair before anything downstream can see it. Detection must read the raw table.
- **A new `bin/check-*` script for the invariant.** Rejected under `.claude/rules/meta-tooling-bar.md` (extend before add): `bin/check-boxscore-schedule` already exposes the audit with `--season`, `--json`, and exit codes. It gains a `--duplicates-only` mode instead.

## Consequences

- Positive: a duplicated boxscore no longer inflates a season's game count. The season-2004 Aces and Jazz read 82.
- Positive: the duplicate invariant now fires on seasons with no schedule data — the exact class of season the fail-open guard was hiding it on.
- Positive: `game_type = 1` scoping removes the false positive on legitimate playoff repeats (for example the 1993-06-04 pair at gotd 1 and 5), so the check can run unattended without a known-noise allowlist.
- Positive: two CI hosts carry the invariant with honest, different claims — `deploy-rehearsal.yml` proves it at merge time on a two-season sample, and `db-backup.yml` proves it daily across every season of a complete restored dump.
- **Negative:** the view now hides duplicates from anyone reading it, including a human eyeballing standings for anomalies. The detection responsibility moves entirely to the raw-table invariant and its CI hosts. Removing those hosts would restore silent corruption with no visible symptom — which is why the negative is recorded here rather than left implicit.
- Negative: `min(game_of_that_day)` picks a row, and if the duplicate rows disagree on the score the view reports the first-recorded score. That is deterministic but not necessarily *correct*; correctness still requires a human repairing the underlying rows.

## References

- `ibl5/migrations/172_dedupe_team_win_loss_by_matchup.sql` — the view redefinition.
- `ibl5/migrations/121_snake_case_boxscore_and_schedule_columns.sql` — the original four-column key, and `vw_schedule_upcoming`'s `min(game_of_that_day)` prior art.
- `ibl5/classes/Boxscore/ScheduleReconciliationAudit.php` — the unconditional, `game_type = 1`-scoped duplicate loop.
- `ibl5/classes/Boxscore/BoxscoreRepository.php` — `findDuplicateTripleGames(?int $seasonYear, ?int $gameType)`.
- `ibl5/bin/check-boxscore-schedule-run` — the `--duplicates-only` mode.
- `ibl5/docs/decisions/0108-boxscore-schedule-reconciliation-severity.md` — the severity policy this ADR narrows.
