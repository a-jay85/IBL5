<?php

declare(strict_types=1);

namespace LeagueSchedule\Contracts;

/**
 * LeagueScheduleRepositoryInterface - Contract for league schedule database operations
 *
 * @phpstan-type ScheduleRow array{
 *     SchedID: int,
 *     Date: string,
 *     Visitor: int,
 *     VScore: int,
 *     Home: int,
 *     HScore: int,
 *     BoxID: int,
 *     gameOfThatDay: int
 * }
 *
 * @see \LeagueSchedule\LeagueScheduleRepository For the concrete implementation
 */
interface LeagueScheduleRepositoryInterface
{
    /**
     * Get all scheduled games with box score info
     *
     * @return list<ScheduleRow> Games ordered by date ascending, then SchedID ascending
     */
    public function getAllGamesWithBoxScoreInfo(): array;

    /**
     * Get team records indexed by team ID
     *
     * @return array<int, string> Map of team ID => league record string (e.g. "25-10")
     */
    public function getTeamRecords(): array;
}
