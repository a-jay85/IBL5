<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

use Boxscore\RejectedGame;

/**
 * BoxscoreAuditRepositoryInterface - Contract for box score data-integrity checks
 *
 * Finds box scores with no schedule row, played schedule rows with no box score,
 * and duplicate (date, visitor, home) triples, and records schedule-guard rejects.
 *
 * @see \Boxscore\BoxscoreAuditRepository For the concrete implementation
 */
interface BoxscoreAuditRepositoryInterface
{
    /**
     * Boxscore games with no matching ibl_schedule row for the season.
     *
     * Applies ScheduleMembershipGuard exemptions: off-schedule months and
     * All-Star/Rising-Stars pseudo-team IDs are excluded so they are never
     * reported as orphans.
     *
     * @param int $seasonYear The season_year generated-column value (e.g. 2008)
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, name: string}>
     */
    public function findOrphanBoxscoreGames(int $seasonYear): array;

    /**
     * Played schedule rows with no boxscore rows at all.
     *
     * Rows where visitor_score = 0 AND home_score = 0 are excluded — those are
     * scheduled-but-unplayed (series ended early) and are benign by design.
     *
     * @param int $seasonYear The season_year stored column value (e.g. 2008)
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int}>
     */
    public function findScheduledGamesWithoutBoxscores(int $seasonYear): array;

    /**
     * Triples recorded at more than one game_of_that_day within the season.
     *
     * These are invisible to the orphan query because the legitimate half of the
     * pair matches the schedule. The detail names both gotd values without asserting
     * which is the phantom.
     *
     * Both filters are optional. Passing null for either axis drops that
     * predicate, so `findDuplicateTripleGames()` scans every season and every
     * game type.
     *
     * @param int|null $seasonYear The season_year generated-column value (e.g. 2008), or null for all seasons
     * @param int|null $gameType The game_type generated-column value (1 = regular season), or null for all game types
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, occurrences: int, gotds: string}>
     */
    public function findDuplicateTripleGames(?int $seasonYear = null, ?int $gameType = null): array;

    /**
     * Best-effort audit write. Never throws; returns the number of rows recorded.
     *
     * Records up to MAX_RECORDED_REJECTS rows in a single transaction. On any DB
     * failure the exception is caught, a warning is logged to the 'audit' channel,
     * and 0 is returned — the import pipeline must never be aborted by an audit write.
     *
     * @param list<RejectedGame> $rejects
     */
    public function recordRejectedGames(int $seasonYear, array $rejects, ?string $sourceArchive): int;
}
