<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

class ApiTeamRepository extends \BaseMysqliRepository
{
    /**
     * Get paginated list of teams.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTeams(Paginator $paginator): array
    {
        $orderBy = $paginator->getOrderByClause();

        return $this->fetchAll(
            "SELECT t.uuid, t.team_city, t.team_name, t.owner_name, t.arena,
                    s.conference, s.division
             FROM ibl_team_info t
             LEFT JOIN ibl_standings s ON t.teamid = s.tid
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            'ii',
            $paginator->getLimit(),
            $paginator->getOffset()
        );
    }

    /**
     * Count total teams.
     */
    public function countTeams(): int
    {
        /** @var array{total: int}|null $row */
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM ibl_team_info');

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get a single team by UUID with standings data.
     *
     * @return array<string, mixed>|null
     */
    public function getTeamByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            "SELECT t.uuid, t.team_city, t.team_name, t.owner_name, t.arena,
                    s.conference, s.division,
                    s.leagueRecord AS league_record,
                    s.pct AS win_percentage,
                    s.confRecord AS conference_record,
                    s.confGB AS conference_games_back,
                    s.divRecord AS division_record,
                    s.divGB AS division_games_back,
                    s.homeWins AS home_wins,
                    s.homeLosses AS home_losses,
                    s.awayWins AS away_wins,
                    s.awayLosses AS away_losses,
                    s.gamesUnplayed AS games_remaining
             FROM ibl_team_info t
             LEFT JOIN ibl_standings s ON t.teamid = s.tid
             WHERE t.uuid = ?",
            's',
            $uuid
        );
    }
}
