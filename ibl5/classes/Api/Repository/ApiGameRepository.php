<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

class ApiGameRepository extends \BaseMysqliRepository
{
    /**
     * Get paginated list of games from the schedule view.
     *
     * @param array<string, string> $filters Optional filters (season, status, team, date, date_start, date_end)
     * @return array<int, array<string, mixed>>
     */
    public function getGames(Paginator $paginator, array $filters = []): array
    {
        $where = [];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderBy = $paginator->getOrderByClause();

        $query = "SELECT * FROM vw_schedule_upcoming {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * Count total games matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countGames(array $filters = []): int
    {
        $where = [];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM vw_schedule_upcoming {$whereClause}", $types, ...$params);

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get a single game by UUID.
     *
     * @return array<string, mixed>|null
     */
    public function getGameByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM vw_schedule_upcoming WHERE game_uuid = ?',
            's',
            $uuid
        );
    }

    /**
     * Get team box score stats for a game identified by date and team IDs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBoxscoreTeams(int $visitorTeamId, int $homeTeamId, string $date): array
    {
        return $this->fetchAll(
            'SELECT * FROM ibl_box_scores_teams WHERE visitorTeamID = ? AND homeTeamID = ? AND Date = ? ORDER BY id ASC',
            'iis',
            $visitorTeamId,
            $homeTeamId,
            $date
        );
    }

    /**
     * Get player box score lines for a game identified by date and team IDs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBoxscorePlayers(int $visitorTid, int $homeTid, string $date): array
    {
        return $this->fetchAll(
            'SELECT b.*, COALESCE(p.name, b.name) AS name, p.uuid AS player_uuid, p.tid AS player_tid
             FROM ibl_box_scores b
             LEFT JOIN ibl_plr p ON b.pid = p.pid
             WHERE b.Date = ? AND b.visitorTID = ? AND b.homeTID = ?
             ORDER BY b.id ASC',
            'sii',
            $date,
            $visitorTid,
            $homeTid
        );
    }

    /**
     * Apply common filters to query building arrays.
     *
     * @param array<string, string> $filters
     * @param list<string> $where
     * @param list<mixed> $params
     */
    private function applyFilters(array $filters, array &$where, string &$types, array &$params): void
    {
        if (isset($filters['season']) && $filters['season'] !== '') {
            $where[] = 'season_year = ?';
            $types .= 'i';
            $params[] = (int) $filters['season'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'game_status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }

        if (isset($filters['team']) && $filters['team'] !== '') {
            $where[] = '(visitor_uuid = ? OR home_uuid = ?)';
            $types .= 'ss';
            $params[] = $filters['team'];
            $params[] = $filters['team'];
        }

        if (isset($filters['date']) && $filters['date'] !== '') {
            $where[] = 'game_date = ?';
            $types .= 's';
            $params[] = $filters['date'];
        }

        if (isset($filters['date_start']) && $filters['date_start'] !== '') {
            $where[] = 'game_date >= ?';
            $types .= 's';
            $params[] = $filters['date_start'];
        }

        if (isset($filters['date_end']) && $filters['date_end'] !== '') {
            $where[] = 'game_date <= ?';
            $types .= 's';
            $params[] = $filters['date_end'];
        }
    }
}
