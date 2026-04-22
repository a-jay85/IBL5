<?php

declare(strict_types=1);

namespace Api\Repository;

/**
 * @phpstan-type InjuredPlayerRow array{player_uuid: string, pid: int, name: string, pos: string, injured: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null}
 */
class ApiInjuriesRepository extends \BaseMysqliRepository
{
    /**
     * Get all currently injured active players with team information.
     *
     * @return list<InjuredPlayerRow>
     */
    public function getInjuredPlayers(): array
    {
        /** @var list<InjuredPlayerRow> */
        return $this->fetchAll(
            'SELECT p.uuid AS player_uuid, p.pid, p.name, p.pos, p.injured,
                    t.teamid, t.uuid AS team_uuid, t.team_city, t.team_name
             FROM ibl_plr p
             LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
             WHERE p.injured > 0 AND p.dc_canPlayInGame = 1
             ORDER BY p.injured DESC'
        );
    }
}
