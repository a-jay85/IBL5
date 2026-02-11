<?php

declare(strict_types=1);

namespace ActivityTracker\Contracts;

/**
 * ActivityTrackerRepositoryInterface - Contract for activity tracker database operations
 *
 * @phpstan-type ActivityRow array{
 *     teamid: int,
 *     team_name: string,
 *     team_city: string,
 *     color1: string,
 *     color2: string,
 *     depth: string,
 *     sim_depth: string,
 *     asg_vote: string,
 *     eoy_vote: string
 * }
 *
 * @see \ActivityTracker\ActivityTrackerRepository For the concrete implementation
 */
interface ActivityTrackerRepositoryInterface
{
    /**
     * Get team activity data (excluding Free Agents)
     *
     * @return list<ActivityRow> Teams ordered by team ID ascending
     */
    public function getTeamActivity(): array;
}
