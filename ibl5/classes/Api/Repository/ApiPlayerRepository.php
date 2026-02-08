<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

class ApiPlayerRepository extends \BaseMysqliRepository
{
    /**
     * Get paginated list of active players from the API view.
     *
     * @param array<string, string> $filters Optional filters (position, team UUID)
     * @return array<int, array<string, mixed>>
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

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderBy = $paginator->getOrderByClause();

        $query = "SELECT * FROM vw_player_current {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

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

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM vw_player_current {$whereClause}", $types, ...$params);

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get a single player by UUID from the API view.
     *
     * @return array<string, mixed>|null
     */
    public function getPlayerByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM vw_player_current WHERE player_uuid = ?',
            's',
            $uuid
        );
    }
}
