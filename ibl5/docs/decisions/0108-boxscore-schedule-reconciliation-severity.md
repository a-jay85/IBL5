---
description: Boxscore/schedule reconciliation treats orphan boxscores and duplicate triples as errors but missing boxscores as warnings, because a missing boxscore is the normal mid-sim state and a strict check would be muted within a week.
last_verified: 2026-08-29
---

# ADR-0108: Asymmetric Severity for Boxscore/Schedule Reconciliation

**Status:** Accepted — superseded in part by [ADR-0109](0109-team-win-loss-dedup-key-and-raw-table-duplicate-invariant.md)
**Date:** 2026-08-23

## Context

An unguarded `.sco` import wrote 621 phantom 2008 games into `ibl_box_scores_teams` — 618 boxscores for games that appear nowhere in `ibl_schedule`, plus 3 duplicate `(date, visitor, home)` triples landing at a second `game_of_that_day`. The archive selected for that import, `07-08_36_playoffs.zip`, was both newest-by-mtime and highest-by-sequence, so nothing about the *selection* looked wrong; only the *contents* were out of season. Nobody noticed until the phantom rows distorted season totals.

`ScheduleMembershipGuard` (Phase 3) now blocks the write path. But a guard only protects imports that happen after it ships, and the existing corruption still has to be found. So reconciliation between `ibl_box_scores_teams` and `ibl_schedule` becomes a standing check, runnable on demand and in CI.

The two directions of that reconciliation are not equally trustworthy:

- A boxscore with **no matching schedule row** is unambiguous. The game was never scheduled; the row is fabricated. There is no legitimate state that produces it.
- A **played schedule row with no boxscore** is ambiguous. During a sim, every game that has not yet been processed looks exactly like this. It is the normal state for most of the season, most of the time.

An audit that fails on both directions would exit non-zero on nearly every mid-sim invocation. A check that is red by default is a check that gets ignored, and then muted, and then deleted.

## Decision

Reconciliation severity is **asymmetric**:

- **Orphan boxscores** (a row in `ibl_box_scores_teams` with no `ibl_schedule` match) are **errors** — `exitCode()` returns 1.
- **Duplicate triples** (the same `(date, visitor_teamid, home_teamid)` appearing at more than one `game_of_that_day`) are **errors** — `exitCode()` returns 1.
- **Missing boxscores** (a played `ibl_schedule` row with no boxscore) are **warnings** — they never move `exitCode()` off 0.

Every check is **scoped to a single season**. All-Star team ids and `OFF_SCHEDULE_MONTHS` are exempt, and both exemption lists are read from `ScheduleMembershipGuard` constants rather than re-literalled, so the guard and the audit can never disagree about what counts as off-schedule.

The audit has **no write path**. It reports; a human repairs. `ScheduleReconciliationAudit` exposes no delete or repair method, and adding one is out of scope for this class.

## Alternatives Considered

- **Both directions strict.** Symmetric, simpler to explain, and it would catch a genuinely lost boxscore by exit code. Rejected because: it exits 1 on every run made while a sim is in progress, which is most runs. A signal that fires constantly during normal operation carries no information, and the predictable end state is a muted or deleted check — strictly worse than a check that is quiet but trusted.
- **`box_id` as the join key.** The natural-looking foreign key between the two tables. Rejected because: all 1250 rows carry `box_id = 0`. The column was never populated; it is dead, and joining on it would match everything to everything.
- **Including `game_of_that_day` in the join.** Intuitively the fourth component of the game's identity. Rejected because: `game_of_that_day` is a **league-wide ordinal within a date**, not a per-matchup counter. `2008-06-14 19@21` sits at gotd 4 because it is the 4th game listed that day, not because it is a repeat of an earlier 19@21. Joining on it would fabricate orphans for every game that is not first on its date.

## Consequences

- Positive: the check is safe to run mid-sim, so it can live in CI and in a routine admin workflow without a "only run this between sims" caveat that nobody would honor.
- Positive: the two error classes it *does* enforce are exactly the two the 2007-08 incident produced, so a recurrence trips the exit code immediately.
- Positive: sourcing exemptions from `ScheduleMembershipGuard` means the guard's accept rule and the audit's orphan rule cannot drift apart.
- **Negative — this is the known hole:** a genuinely lost boxscore, one that should exist and does not, is reported only as a warning. No exit code catches it. Finding it requires a human reading the warning list and knowing that the sim for that date has already completed. This trade-off is deliberate, and it is the price of a check that stays trusted; it is recorded here so a future reader does not "fix" the asymmetry without understanding what it buys.
- Negative: season scoping means a cross-season corruption pattern needs one invocation per season; there is no all-seasons sweep mode.

## References

- `ibl5/classes/Boxscore/ScheduleReconciliationAudit.php` — the two-sided audit implementing this severity policy.
- `ibl5/classes/Boxscore/ScheduleMembershipGuard.php` — the import-time guard; source of the shared exemption constants.
- `ibl5/bin/check-boxscore-schedule-run` — the CLI entry point that surfaces `exitCode()`.
- `ibl5/tests/DatabaseIntegration/ScheduleReconciliationAuditTest.php` — pins the 618 / 3 counts and the warning-only missing direction against real data shape.
