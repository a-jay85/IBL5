<?php

declare(strict_types=1);

namespace TeamSchedule\Contracts;

/**
 * TeamScheduleServiceInterface - Contract for team schedule business logic
 *
 * Defines methods for processing team schedule data.
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
     * @return array Processed game data with results, streaks, etc.
     */
    public function getProcessedSchedule(int $teamId, \Season $season): array;
}
