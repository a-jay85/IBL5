<?php

declare(strict_types=1);

namespace TeamSchedule\Contracts;

/**
 * TeamScheduleServiceInterface - Contract for team schedule business logic
 *
 * Defines methods for processing team schedule data.
 *
 * @phpstan-type ScheduleGameRow array{
 *     game: \Game,
 *     currentMonth: string,
 *     opposingTeam: \Team,
 *     opponentText: string,
 *     highlight: string,
 *     gameResult: string,
 *     wins: int,
 *     losses: int,
 *     streak: string,
 *     winLossColor: string,
 *     isUnplayed: bool
 * }
 *
 * @see \TeamSchedule\TeamScheduleService For the concrete implementation
 */
interface TeamScheduleServiceInterface
{
    /**
     * Get processed schedule data for a team
     *
     * @param int $teamId Team ID
     * @param \Season $season Current season
     * @return list<ScheduleGameRow> Processed game data with results, streaks, etc.
     */
    public function getProcessedSchedule(int $teamId, \Season $season): array;
}
