<?php

declare(strict_types=1);

namespace TeamSchedule\Contracts;

/**
 * TeamScheduleViewInterface - Contract for team schedule view rendering
 *
 * Defines methods for generating HTML output for a team's schedule.
 *
 * @phpstan-import-type ScheduleGameRow from \TeamSchedule\Contracts\TeamScheduleServiceInterface
 *
 * @see \TeamSchedule\TeamScheduleView For the concrete implementation
 */
interface TeamScheduleViewInterface
{
    /**
     * Render the complete team schedule
     *
     * @param \Team $team Team object
     * @param list<ScheduleGameRow> $games Processed game data
     * @param int $simLengthInDays Simulation length in days
     * @param string $seasonPhase Current season phase (e.g., 'Regular Season', 'Playoffs')
     * @return string HTML output
     */
    public function render(\Team $team, array $games, int $simLengthInDays, string $seasonPhase): string;
}
