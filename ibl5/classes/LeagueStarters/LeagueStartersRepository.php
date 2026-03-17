<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersRepositoryInterface;

/**
 * LeagueStartersRepository - Batch query for all league starters
 *
 * @see LeagueStartersRepositoryInterface For the interface contract
 */
class LeagueStartersRepository extends \BaseMysqliRepository implements LeagueStartersRepositoryInterface
{
    /**
     * @see LeagueStartersRepositoryInterface::getAllStartersWithTeamData()
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllStartersWithTeamData(): array
    {
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2,
                CASE
                    WHEN p.PGDepth = 1 THEN 'PG'
                    WHEN p.SGDepth = 1 THEN 'SG'
                    WHEN p.SFDepth = 1 THEN 'SF'
                    WHEN p.PFDepth = 1 THEN 'PF'
                    WHEN p.CDepth  = 1 THEN 'C'
                END AS starter_position
            FROM ibl_plr p
            JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.retired = 0
              AND p.tid BETWEEN 1 AND ?
              AND (p.PGDepth = 1 OR p.SGDepth = 1 OR p.SFDepth = 1 OR p.PFDepth = 1 OR p.CDepth = 1)",
            'i',
            \League::MAX_REAL_TEAMID
        );
    }
}
