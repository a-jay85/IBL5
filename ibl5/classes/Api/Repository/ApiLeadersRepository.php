<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

class ApiLeadersRepository extends \BaseMysqliRepository
{
    /**
     * Map category names to SQL sort expressions.
     *
     * SECURITY NOTE: All values are pre-defined SQL expressions from a strict whitelist.
     * String concatenation in ORDER BY is safe because values never come from user input.
     */
    private const CATEGORY_SORT_MAP = [
        'ppg' => '((2*`fgm`+`ftm`+`tgm`)/`games`)',
        'rpg' => '(`reb`/`games`)',
        'apg' => '(`ast`/`games`)',
        'spg' => '(`stl`/`games`)',
        'bpg' => '(`blk`/`games`)',
        'fgp' => '(`fgm`/`fga`)',
        'ftp' => '(`ftm`/`fta`)',
        'tgp' => '(`tgm`/`tga`)',
        'qa' => '(((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk)-((fga-fgm)+(fta-ftm)+tvr+pf))/games)',
    ];

    private const VALID_CATEGORIES = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fgp', 'ftp', 'tgp', 'qa'];

    /**
     * Get paginated list of statistical leaders.
     *
     * @param array<string, string> $filters Optional filters (season, category, min_games)
     * @return array<int, array<string, mixed>>
     */
    public function getLeaders(Paginator $paginator, array $filters = []): array
    {
        $where = ['h.games > 0'];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = implode(' AND ', $where);
        $category = $this->resolveCategory($filters);
        $sortExpr = self::CATEGORY_SORT_MAP[$category];

        $query = "SELECT h.*, p.uuid AS player_uuid, t.uuid AS team_uuid, t.team_city, t.team_name
                  FROM ibl_hist h
                  JOIN ibl_plr p ON h.pid = p.pid
                  LEFT JOIN ibl_team_info t ON h.teamid = t.teamid
                  WHERE {$whereClause}
                  ORDER BY {$sortExpr} DESC
                  LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * Count total leaders matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countLeaders(array $filters = []): int
    {
        $where = ['h.games > 0'];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = implode(' AND ', $where);

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total FROM ibl_hist h WHERE {$whereClause}",
            $types,
            ...$params
        );

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get available seasons for the leaders endpoint.
     *
     * @return list<int>
     */
    public function getAvailableSeasons(): array
    {
        /** @var list<array{year: int}> $rows */
        $rows = $this->fetchAll('SELECT DISTINCT year FROM ibl_hist ORDER BY year DESC');

        $years = [];
        foreach ($rows as $row) {
            $years[] = $row['year'];
        }

        return $years;
    }

    /**
     * Resolve the category from filters, defaulting to 'ppg'.
     *
     * @param array<string, string> $filters
     */
    private function resolveCategory(array $filters): string
    {
        $category = $filters['category'] ?? 'ppg';
        if (in_array($category, self::VALID_CATEGORIES, true)) {
            return $category;
        }

        return 'ppg';
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
            $where[] = 'h.year = ?';
            $types .= 'i';
            $params[] = (int) $filters['season'];
        }

        if (isset($filters['min_games']) && $filters['min_games'] !== '') {
            $minGames = (int) $filters['min_games'];
            if ($minGames > 0) {
                $where[] = 'h.games >= ?';
                $types .= 'i';
                $params[] = $minGames;
            }
        }
    }
}
