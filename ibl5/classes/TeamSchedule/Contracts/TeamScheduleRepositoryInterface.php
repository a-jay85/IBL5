<?php

declare(strict_types=1);

namespace TeamSchedule\Contracts;

/**
 * TeamScheduleRepositoryInterface - Contract for team schedule data retrieval
 *
 * Provides methods for retrieving team schedule information from the database.
 *
 * @phpstan-type ScheduleRow array{Year: int, BoxID: int, Date: string, Visitor: int, VScore: int, Home: int, HScore: int, SchedID: int, created_at: string, updated_at: string, uuid: string, gameOfThatDay: ?int}
 * @phpstan-type ProjectedGameRow array{Year: int, BoxID: int, Date: string, Visitor: int, VScore: int, Home: int, HScore: int, SchedID: int, created_at: string, updated_at: string, uuid: string}
 *
 * @see \TeamSchedule\TeamScheduleRepository For the concrete implementation
 */
interface TeamScheduleRepositoryInterface
{
    /**
     * Get full season schedule for a team
     *
     * Retrieves all games (home and away) for a team ordered by date,
     * joined with box score data to include gameOfThatDay.
     *
     * @param int $teamID Team ID to get schedule for
     * @return list<ScheduleRow> Schedule rows ordered by date ascending
     */
    public function getSchedule(int $teamID): array;

    /**
     * Get projected games for next simulation result
     *
     * Retrieves games scheduled within the next sim period
     * starting from the day after the last sim end date.
     *
     * @param int $teamID Team ID to get schedule for
     * @param string $lastSimEndDate Date string (YYYY-MM-DD) of last sim end
     * @param string $projectedNextSimEndDate Date string (YYYY-MM-DD) of projected next sim end (break-aware)
     * @return list<ProjectedGameRow> Upcoming game rows ordered by date ascending
     */
    public function getProjectedGamesNextSimResult(int $teamID, string $lastSimEndDate, string $projectedNextSimEndDate): array;
}
