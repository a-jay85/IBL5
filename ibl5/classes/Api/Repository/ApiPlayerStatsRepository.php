<?php

declare(strict_types=1);

namespace Api\Repository;

/**
 * @phpstan-type CareerStatsRow array{player_uuid: string, pid: int, name: string, career_games: int, career_minutes: int, career_points: int|float, career_rebounds: int, career_assists: int, career_steals: int, career_blocks: int, ppg_career: float|null, rpg_career: float|null, apg_career: float|null, fg_pct_career: float|null, ft_pct_career: float|null, three_pt_pct_career: float|null, playoff_minutes: int, draft_year: int|null, draft_round: int|null, draft_pick: int|null, drafted_by_team: string|null, draft_team_id: int|null, ...}
 * @phpstan-type SeasonHistoryRow array{player_uuid: string, pid: int, name: string, year: int, teamid: int, team: string, team_uuid: string|null, team_city: string|null, team_name: string|null, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, salary: int, ...}
 */
class ApiPlayerStatsRepository extends \BaseMysqliRepository
{
    /**
     * Get career stats for a player by UUID from the career stats view.
     *
     * @return CareerStatsRow|null
     */
    public function getCareerStats(string $playerUuid): ?array
    {
        /** @var CareerStatsRow|null */
        return $this->fetchOne(
            'SELECT v.*, dt.teamid AS draft_team_id
             FROM vw_player_career_stats v
             LEFT JOIN ibl_team_info dt ON v.drafted_by_team = dt.team_name
             WHERE v.player_uuid = ?',
            's',
            $playerUuid
        );
    }

    /**
     * Get season-by-season history for a player by UUID.
     *
     * @return list<SeasonHistoryRow>
     */
    public function getSeasonHistory(string $playerUuid): array
    {
        /** @var list<SeasonHistoryRow> */
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
