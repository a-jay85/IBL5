<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;

/**
 * @phpstan-type LeaderRow array{player_uuid: string, pid: int, name: string, teamid: int, team_uuid: string|null, team_city: string|null, team_name: string|null, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, ...<string, mixed>}
 */
class ApiLeadersRepository extends \Database\BaseMysqliRepository
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
        'qa' => '((CAST(2*fgm+ftm+tgm+reb+2*ast+2*stl+2*blk AS SIGNED) - CAST((fga-fgm)+(fta-ftm)+tvr+pf AS SIGNED))/games)',
    ];

    private const VALID_CATEGORIES = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fgp', 'ftp', 'tgp', 'qa'];

    /**
     * Get paginated list of statistical leaders.
     *
     * @param array<string, string> $filters Optional filters (season, category, min_games)
     * @return list<LeaderRow>
     */
    public function getLeaders(Paginator $paginator, array $filters = []): array
    {
        $applied = $this->applyFilters($filters);
        $types = $applied['types'];
        $params = $applied['params'];

        $category = $this->resolveCategory($filters);
        $sortExpr = self::CATEGORY_SORT_MAP[$category];

        // IDENTIFIER (already-validated): clauses are constant-union literals; $sortExpr = CATEGORY_SORT_MAP value, category validated against VALID_CATEGORIES
        $query = 'SELECT h.*, p.uuid AS player_uuid, t.uuid AS team_uuid, t.team_city, t.team_name'
               . ' FROM `ibl_hist` h'
               . ' JOIN `ibl_plr` p ON h.pid = p.pid'
               . ' LEFT JOIN `ibl_team_info` t ON h.teamid = t.teamid'
               . ' WHERE h.games > 0' . $applied['seasonClause'] . $applied['minGamesClause']
               . ' ORDER BY ' . $sortExpr . ' DESC'
               . ' LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

        /** @var list<LeaderRow> */
        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * Count total leaders matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countLeaders(array $filters = []): int
    {
        $applied = $this->applyFilters($filters);

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM `ibl_hist` h WHERE h.games > 0' . $applied['seasonClause'] . $applied['minGamesClause'],
            $applied['types'],
            ...$applied['params']
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
        return (new \Season\SeasonQueryRepository($this->db))->getAvailableSeasonYears();
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
     * Build the optional filter clauses and their bind values.
     *
     * Each clause is a two-member constant union ('' or one literal predicate); the
     * matching type letter and value are pushed in the same branch, so bind order
     * equals placeholder order (season, then min_games).
     *
     * @param array<string, string> $filters
     * @return array{seasonClause: ''|' AND h.year = ?', minGamesClause: ''|' AND h.games >= ?', types: string, params: list<int>}
     */
    private function applyFilters(array $filters): array
    {
        $types = '';
        $params = [];

        $seasonClause = '';
        if (isset($filters['season']) && $filters['season'] !== '') {
            $seasonClause = ' AND h.year = ?';
            $types .= 'i';
            $params[] = (int) $filters['season'];
        }

        $minGamesClause = '';
        if (isset($filters['min_games']) && $filters['min_games'] !== '') {
            $minGames = (int) $filters['min_games'];
            if ($minGames > 0) {
                $minGamesClause = ' AND h.games >= ?';
                $types .= 'i';
                $params[] = $minGames;
            }
        }

        return [
            'seasonClause' => $seasonClause,
            'minGamesClause' => $minGamesClause,
            'types' => $types,
            'params' => $params,
        ];
    }
}
