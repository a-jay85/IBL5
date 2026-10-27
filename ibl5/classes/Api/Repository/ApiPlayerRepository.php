<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

/**
 * @phpstan-type PlayerCurrentRow array{player_uuid: string, pid: int, name: string, nickname: string|null, position: string, age: int, htft: int, htin: int, dc_can_play_in_game: int|null, retired: int, experience: int, bird_rights: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null, owner_name: string|null, full_team_name: string|null, contract_year: int, current_salary: int, year1_salary: int, year2_salary: int, year3_salary: int, year4_salary: int, year5_salary: int, year6_salary: int, games_played: int, minutes_played: int, field_goals_made: int, field_goals_attempted: int, free_throws_made: int, free_throws_attempted: int, three_pointers_made: int, three_pointers_attempted: int, offensive_rebounds: int, defensive_rebounds: int, assists: int, steals: int, turnovers: int, blocks: int, personal_fouls: int, points_per_game: float|null, fg_percentage: float|null, ft_percentage: float|null, three_pt_percentage: float|null, ...<string, mixed>}
 * @phpstan-type PlayerExportRow array{player_uuid: string, pid: int, name: string, nickname: string|null, position: string, age: int, htft: int, htin: int, dc_can_play_in_game: int|null, retired: int, experience: int, bird_rights: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null, owner_name: string|null, full_team_name: string|null, contract_year: int, current_salary: int, year1_salary: int, year2_salary: int, year3_salary: int, year4_salary: int, year5_salary: int, year6_salary: int, games_played: int, minutes_played: int, field_goals_made: int, field_goals_attempted: int, free_throws_made: int, free_throws_attempted: int, three_pointers_made: int, three_pointers_attempted: int, offensive_rebounds: int, defensive_rebounds: int, assists: int, steals: int, turnovers: int, blocks: int, personal_fouls: int, points_per_game: float|null, fg_percentage: float|null, ft_percentage: float|null, three_pt_percentage: float|null, injured: int|null, r_fga: int|null, r_fgp: int|null, r_fta: int|null, r_ftp: int|null, r_3ga: int|null, r_3gp: int|null, r_orb: int|null, r_drb: int|null, r_ast: int|null, r_stl: int|null, r_tvr: int|null, r_blk: int|null, r_foul: int|null, oo: int|null, r_drive_off: int|null, po: int|null, r_trans_off: int|null, od: int|null, dd: int|null, pd: int|null, td: int|null, stamina: int|null, clutch: int|null, consistency: int|null, talent: int|null, skill: int|null, intangibles: int|null, loyalty: int|null, playing_time: int|null, winner: int|null, tradition: int|null, security: int|null, ...<string, mixed>}
 */
class ApiPlayerRepository extends \Database\BaseMysqliRepository
{
    /** Public API sort key => SQL column. The controller's allowlist is array_keys() of this map. */
    public const SORT_COLUMNS = [
        'name' => 'name',
        'age' => 'age',
        'position' => 'position',
        'points_per_game' => 'points_per_game',
        'experience' => 'experience',
    ];

    /**
     * Get paginated list of players from the API view.
     *
     * @param array<string, string> $filters Optional filters (position, team UUID, search)
     * @return list<PlayerCurrentRow>
     */
    public function getPlayers(Paginator $paginator, array $filters = []): array
    {
        $where = [];
        $types = '';
        $params = [];

        if (isset($filters['position']) && $filters['position'] !== '') {
            $where[] = 'position = ?';
            $types .= 's';
            $params[] = $filters['position'];
        }

        if (isset($filters['team']) && $filters['team'] !== '') {
            $where[] = 'team_uuid = ?';
            $types .= 's';
            $params[] = $filters['team'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[] = 'name LIKE ?';
            $types .= 's';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $sortColumn = self::SORT_COLUMNS[$paginator->getSort()]
            ?? throw new \InvalidArgumentException('Invalid sort column: ' . $paginator->getSort());
        $direction = $paginator->getOrder() === 'desc' ? 'DESC' : 'ASC';

        // IDENTIFIER: $whereClause = hardcoded fragments; $sortColumn = SORT_COLUMNS map value; $direction = literal ternary
        $query = 'SELECT * FROM vw_player_current ' . $whereClause . ' ORDER BY ' . $sortColumn . ' ' . $direction . ', pid ASC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

        /** @var list<PlayerCurrentRow> */
        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * Count total players matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countPlayers(array $filters = []): int
    {
        $where = [];
        $types = '';
        $params = [];

        if (isset($filters['position']) && $filters['position'] !== '') {
            $where[] = 'position = ?';
            $types .= 's';
            $params[] = $filters['position'];
        }

        if (isset($filters['team']) && $filters['team'] !== '') {
            $where[] = 'team_uuid = ?';
            $types .= 's';
            $params[] = $filters['team'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[] = 'name LIKE ?';
            $types .= 's';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        /** @var array{total: int}|null $row */
        // IDENTIFIER (already-validated): $whereClause built from hardcoded fragments only
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM vw_player_current ' . $whereClause, $types, ...$params);

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get all players for CSV export. No pagination.
     *
     * Adds injury days, ratings, TSI, and free agency factors from ibl_plr
     * on top of the vw_player_current columns.
     *
     * @return list<PlayerExportRow>
     */
    public function getAllPlayersForExport(): array
    {
        /** @var list<PlayerExportRow> */
        return $this->fetchAll(
            'SELECT v.*, p.injured,
                    p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_3ga, p.r_3gp,
                    p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_tvr, p.r_blk, p.r_foul,
                    p.oo, p.r_drive_off, p.po, p.r_trans_off, p.od, p.dd, p.pd, p.td,
                    p.stamina, p.clutch, p.consistency,
                    p.talent, p.skill, p.intangibles,
                    p.loyalty, p.playing_time, p.winner, p.tradition, p.security
             FROM vw_player_current v
             LEFT JOIN `ibl_plr` p ON p.pid = v.pid
             ORDER BY v.name ASC, v.pid ASC',
            ''
        );
    }

    /**
     * Get a single player by UUID from the API view.
     *
     * @return PlayerCurrentRow|null
     */
    public function getPlayerByUuid(string $uuid): ?array
    {
        /** @var PlayerCurrentRow|null */
        return $this->fetchOne(
            'SELECT * FROM vw_player_current WHERE player_uuid = ?',
            's',
            $uuid
        );
    }
}
