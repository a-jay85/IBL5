<?php

declare(strict_types=1);

namespace TeamSchedule\Contracts;

/**
 * TeamScheduleViewInterface - Contract for team schedule view rendering
 *
 * Defines methods for generating HTML output for a team's schedule.
 *
 * @see \TeamSchedule\TeamScheduleView For the concrete implementation
 */
interface TeamScheduleViewInterface
{
    /**
     * Render the complete team schedule
     *
     * @param \Team $team Team object
     * @param array $games Processed game data
     * @param int $simLengthInDays Simulation length in days
     * @return string HTML output
     */
    public function render(\Team $team, array $games, int $simLengthInDays): string;
}
