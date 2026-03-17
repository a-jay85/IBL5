<?php

declare(strict_types=1);

namespace LeagueStarters\Contracts;

/**
 * LeagueStartersRepositoryInterface - Contract for batch-loading league starters
 *
 * @see \LeagueStarters\LeagueStartersRepository For the concrete implementation
 */
interface LeagueStartersRepositoryInterface
{
    /**
     * Fetch all starters (Depth=1 at any position) with full player and team data
     *
     * Returns rows from ibl_plr joined with ibl_team_info, including a
     * `starter_position` column derived from which position depth equals 1.
     *
     * @return array<int, array<string, mixed>> Player rows with team data and starter_position
     */
    public function getAllStartersWithTeamData(): array;
}
