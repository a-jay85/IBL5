<?php

declare(strict_types=1);

namespace LeagueSchedule\Contracts;

/**
 * LeagueScheduleRepositoryInterface - Contract for league schedule database operations
 *
 * @phpstan-type ScheduleRow array{
 *     id: int,
 *     game_date: string,
 *     visitor_teamid: int,
 *     visitor_score: int,
 *     home_teamid: int,
 *     home_score: int,
 *     box_id: int,
 *     game_of_that_day: int
 * }
 *
 * @see \LeagueSchedule\LeagueScheduleRepository For the concrete implementation
 */
interface LeagueScheduleRepositoryInterface
{
    /**
     * Get all scheduled games with box score info
     *
     * @return list<ScheduleRow> Games ordered by date ascending, then id ascending
     */
    public function getAllGamesWithBoxScoreInfo(): array;

    /**
     * Get team records indexed by team ID
     *
     * @return array<int, string> Map of team ID => league record string (e.g. "25-10")
     */
    public function getTeamRecords(): array;
}
