<?php

declare(strict_types=1);

namespace Api\Repository;

use Repositories\PlayerTeamJoinQuery;

/**
 * @phpstan-type InjuredPlayerRow array{player_uuid: string, pid: int, name: string, pos: string, injured: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null}
 */
class ApiInjuriesRepository extends \Database\BaseMysqliRepository
{
    use PlayerTeamJoinQuery;

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
             FROM `ibl_plr` p
             ' . $this->playerTeamLeftJoin() . '
             WHERE p.injured > 0 AND p.dc_can_play_in_game = 1
             ORDER BY p.injured DESC'
        );
    }
}
