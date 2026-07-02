<?php

declare(strict_types=1);

namespace Schedule\Contracts;

/**
 * ScheduleControllerInterface - Contract for the Schedule module's page controller
 *
 * @see \Schedule\ScheduleController For the concrete implementation
 */
interface ScheduleControllerInterface
{
    /**
     * Render the schedule page body for a team (if valid) or the league
     *
     * @param int $teamid Team ID to render a team-specific schedule for, or 0/invalid for the league schedule
     * @return string HTML output
     */
    public function render(int $teamid): string;
}
