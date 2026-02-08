<?php

declare(strict_types=1);

namespace Api\Repository;

class ApiPlayerStatsRepository extends \BaseMysqliRepository
{
    /**
     * Get career stats for a player by UUID from the career stats view.
     *
     * @return array<string, mixed>|null
     */
    public function getCareerStats(string $playerUuid): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM vw_player_career_stats WHERE player_uuid = ?',
            's',
            $playerUuid
        );
    }

    /**
     * Get season-by-season history for a player by UUID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSeasonHistory(string $playerUuid): array
    {
        return $this->fetchAll(
            'SELECT h.*, p.uuid AS player_uuid, t.uuid AS team_uuid, t.team_city, t.team_name
             FROM ibl_hist h
             JOIN ibl_plr p ON h.pid = p.pid
             LEFT JOIN ibl_team_info t ON h.teamid = t.teamid
             WHERE p.uuid = ?
             ORDER BY h.year DESC',
            's',
            $playerUuid
        );
    }
}
