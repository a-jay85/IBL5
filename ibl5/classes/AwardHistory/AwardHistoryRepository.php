<?php

declare(strict_types=1);

namespace AwardHistory;

use Database\BaseMysqliRepository;
use AwardHistory\Contracts\AwardHistoryRepositoryInterface;

/**
 * AwardHistoryRepository - Database operations for player awards search
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Implements the repository contract defined in AwardHistoryRepositoryInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 * @see AwardHistoryRepositoryInterface For method contracts
 */
class AwardHistoryRepository extends BaseMysqliRepository implements AwardHistoryRepositoryInterface
{
    /**
     * Whitelist of valid sort columns to prevent SQL injection
     * Maps sortby option to column name
     */
    private const SORT_COLUMN_MAP = [
        1 => 'a.name',
        2 => 'a.award',
        3 => 'a.year',
    ];

    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see AwardHistoryRepositoryInterface::searchAwards()
     */
    public function searchAwards(array $params): array
    {
        $types = '';
        $bind = [];

        $yearClause = '';
        if ($params['year'] !== null) {
            $yearClause = ' AND a.year = ?';
            $types .= 'i';
            $bind[] = $params['year'];
        }

        $awardClause = '';
        if ($params['award'] !== null) {
            $awardClause = ' AND a.award LIKE ?';
            $types .= 's';
            $bind[] = '%' . $params['award'] . '%';
        }

        $nameClause = '';
        if ($params['name'] !== null) {
            $nameClause = ' AND a.name LIKE ?';
            $types .= 's';
            $bind[] = '%' . $params['name'] . '%';
        }

        $sortColumn = self::SORT_COLUMN_MAP[$params['sortby']] ?? 'year';
        // Clauses are constant-union literals with values bound; $sortColumn = SORT_COLUMN_MAP
        // allowlist value (?? 'year').
        $query = "SELECT a.year, a.award, a.name, a.table_id, p.pid FROM `ibl_awards` a LEFT JOIN `ibl_plr` p ON a.name = p.name WHERE 1=1" . $yearClause . $awardClause . $nameClause . " ORDER BY " . $sortColumn . " ASC";

        /** @var array<int, array{year: int, award: string, name: string, table_id: int, pid: int|null}> $results */
        $results = $this->fetchAll($query, $types, ...$bind);

        return [
            'results' => $results,
            'count' => count($results),
        ];
    }
}
