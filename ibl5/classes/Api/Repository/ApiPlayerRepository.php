<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

/**
 * @phpstan-type PlayerCurrentRow array{player_uuid: string, pid: int, name: string, nickname: string|null, position: string, age: int, htft: int, htin: int, dc_canPlayInGame: int|null, retired: int, experience: int, bird_rights: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null, owner_name: string|null, full_team_name: string|null, contract_year: int, current_salary: int, year1_salary: int, year2_salary: int, year3_salary: int, year4_salary: int, year5_salary: int, year6_salary: int, games_played: int, minutes_played: int, field_goals_made: int, field_goals_attempted: int, free_throws_made: int, free_throws_attempted: int, three_pointers_made: int, three_pointers_attempted: int, offensive_rebounds: int, defensive_rebounds: int, assists: int, steals: int, turnovers: int, blocks: int, personal_fouls: int, points_per_game: float|null, fg_percentage: float|null, ft_percentage: float|null, three_pt_percentage: float|null, ...}
 */
class ApiPlayerRepository extends \BaseMysqliRepository
{
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
        $orderBy = $paginator->getOrderByClause();

        $query = "SELECT * FROM vw_player_current {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
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
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM vw_player_current {$whereClause}", $types, ...$params);

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get all players for CSV export. No pagination.
     *
     * @return list<PlayerCurrentRow>
     */
    public function getAllPlayersForExport(): array
    {
        /** @var list<PlayerCurrentRow> */
        return $this->fetchAll('SELECT * FROM vw_player_current ORDER BY name ASC', '');
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
