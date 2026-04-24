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
     * Returns rows from ibl_plr joined with ibl_team_info. Position assignment
     * is determined by the caller from the depth columns (pg_depth, sg_depth, etc.).
     *
     * @return array<int, array<string, mixed>> Player rows with team data
     */
    public function getAllStartersWithTeamData(): array;
}
