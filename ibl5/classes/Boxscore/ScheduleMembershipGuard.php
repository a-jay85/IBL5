<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;

/**
 * Validates incoming decoded boxscore games against the season schedule.
 *
 * All decision logic lives here so it is unit-testable with zero database
 * infrastructure. The array-taking constructor accepts pre-loaded indexes;
 * fromRepository() is the only entry point that touches the database.
 *
 * evaluate() implements six ordered rules and MUST NOT throw for any input.
 * Returning null means accepted; returning a RejectedGame means rejected.
 */
final class ScheduleMembershipGuard
{
    /** Months with no ibl_schedule rows by design: 8 Olympics, 9 Preseason, 10 HEAT. */
    public const OFF_SCHEDULE_MONTHS = [8, 9, 10];

    /** Rising Stars (40/41) and All-Star (50/51) pseudo-teams are never scheduled. */
    public const EXEMPT_TEAMIDS = [40, 41, 50, 51];

    /**
     * @param int                                               $seasonYear         The season_year value (e.g. 2008 for the 2007-08 season)
     * @param array<string, array<int, array<int, true>>>      $scheduledGameIndex  [date][visitor_teamid][home_teamid] => true
     * @param array<string, array<int, array<int, list<int>>>> $gameOfThatDayIndex  [date][visitor_teamid][home_teamid] => list<int> of stored gotd values
     */
    public function __construct(
        private int $seasonYear,
        private array $scheduledGameIndex,
        private array $gameOfThatDayIndex,
    ) {}

    /**
     * Build a guard from the repository, loading indexes with a single query each.
     *
     * This is the only method that touches the database. All subsequent evaluate()
     * calls run purely in-memory against the pre-loaded indexes.
     */
    public static function fromRepository(BoxscoreRepositoryInterface $repository, int $seasonYear): self
    {
        return new self(
            $seasonYear,
            $repository->fetchScheduledGameIndex($seasonYear),
            $repository->fetchBoxscoreGameOfThatDayIndex($seasonYear),
        );
    }

    /**
     * The season_year this guard's indexes were loaded for.
     *
     * Reject reporting and the reconciliation audit need to state which season a
     * rejection belongs to, and callers use it to assert the guard was built for
     * the season currently being imported.
     */
    public function seasonYear(): int
    {
        return $this->seasonYear;
    }

    /**
     * Returns true when the season has schedule rows and the guard is active.
     *
     * When false, evaluate() fails open (returns null for every game) to preserve
     * the pre-guard behavior for seasons whose schedule was never imported.
     */
    public function isEnabled(): bool
    {
        return $this->scheduledGameIndex !== [];
    }

    /**
     * The [minDate, maxDate] ISO date range spanned by the loaded schedule index.
     *
     * Derived by folding the already-preloaded schedule keys — no new query.
     * Returns null when the schedule index is empty (same fail-open condition
     * as isEnabled()), so callers can skip the window check entirely on seasons
     * whose schedule was never imported.
     *
     * @return array{0: string, 1: string}|null [minDate, maxDate], null when the index is empty
     */
    public function scheduleDateWindow(): ?array
    {
        if ($this->scheduledGameIndex === []) {
            return null;
        }

        $dates = array_keys($this->scheduledGameIndex);

        return [min($dates), max($dates)];
    }

    /**
     * Evaluate a decoded boxscore game against the schedule and duplicate-triple rules.
     *
     * Returns null (accepted) or a RejectedGame (rejected). Never throws.
     *
     * Rules, in this exact order:
     *  1. Fail open when guard is disabled (empty schedule index).
     *  2. Exempt All-Star and Rising Stars pseudo-teams (teamids 40/41/50/51).
     *  3. Exempt off-schedule months (8 Olympics, 9 Preseason, 10 HEAT).
     *  4. Reject if the (date, visitor, home) triple is absent from the schedule.
     *  5. Reject if the triple already exists at a different game_of_that_day.
     *  6. Register and accept.
     */
    public function evaluate(Boxscore $game): ?RejectedGame
    {
        // Rule 1: fail open when no schedule is loaded for this season.
        if (!$this->isEnabled()) {
            return null;
        }

        // Rule 2: All-Star Weekend and Rising Stars pseudo-teams are never in ibl_schedule.
        if (
            in_array($game->visitor_teamid, self::EXEMPT_TEAMIDS, true) ||
            in_array($game->home_teamid, self::EXEMPT_TEAMIDS, true)
        ) {
            return null;
        }

        // Rule 3: Olympics, Preseason, and HEAT months have no ibl_schedule rows by design.
        // Read from $game->gameMonth (zero-padded string); never from game_type.
        $month = (int) $game->gameMonth;
        if (in_array($month, self::OFF_SCHEDULE_MONTHS, true)) {
            return null;
        }

        // Rule 4: schedule membership — check the triple (date, visitor, home) only.
        // game_of_that_day is a league-wide ordinal within a date and MUST NOT appear
        // in this predicate; doing so would falsely reject real playoff games whose
        // game_of_that_day differs from their position in ibl_schedule.
        $d = $game->gameDate;
        $v = $game->visitor_teamid;
        $h = $game->home_teamid;
        if (!isset($this->scheduledGameIndex[$d][$v][$h])) {
            return new RejectedGame(
                gameDate: $d,
                visitorTeamid: $v,
                homeTeamid: $h,
                gameOfThatDay: $game->game_of_that_day,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            );
        }

        // Rule 5: duplicate-triple check.
        // If the triple already exists in the DB at a DIFFERENT game_of_that_day, reject.
        // If the incoming gotd matches one already stored, accept — that is the normal
        // re-import/update path and processGameUpsert() must still be reached.
        $stored = $this->gameOfThatDayIndex[$d][$v][$h] ?? [];
        if ($stored !== [] && !in_array($game->game_of_that_day, $stored, true)) {
            return new RejectedGame(
                gameDate: $d,
                visitorTeamid: $v,
                homeTeamid: $h,
                gameOfThatDay: $game->game_of_that_day,
                reason: RejectedGame::REASON_DUPLICATE_TRIPLE,
                storedGameOfThatDay: $stored,
            );
        }

        // Rule 6: register the accepted game's gotd so that a second copy of the same
        // triple inside a single .sco file (at a different gotd) is caught by rule 5.
        if (!in_array($game->game_of_that_day, $stored, true)) {
            $this->gameOfThatDayIndex[$d][$v][$h][] = $game->game_of_that_day;
        }

        return null;
    }
}
