<?php

declare(strict_types=1);

namespace Api\Repository;

class ApiInjuriesRepository extends \BaseMysqliRepository
{
    /**
     * Get all currently injured active players with team information.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInjuredPlayers(): array
    {
        return $this->fetchAll(
            'SELECT p.uuid AS player_uuid, p.pid, p.name, p.pos, p.injured,
                    t.teamid, t.uuid AS team_uuid, t.team_city, t.team_name
             FROM ibl_plr p
             LEFT JOIN ibl_team_info t ON p.tid = t.teamid
             WHERE p.injured > 0 AND p.active = 1
             ORDER BY p.injured DESC'
        );
    }
}
