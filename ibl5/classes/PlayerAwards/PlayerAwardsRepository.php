<?php

declare(strict_types=1);

namespace PlayerAwards;

use BaseMysqliRepository;
use PlayerAwards\Contracts\PlayerAwardsRepositoryInterface;

/**
 * PlayerAwardsRepository - Database operations for player awards search
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Implements the repository contract defined in PlayerAwardsRepositoryInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 * @see PlayerAwardsRepositoryInterface For method contracts
 */
class PlayerAwardsRepository extends BaseMysqliRepository implements PlayerAwardsRepositoryInterface
{
    /**
     * Whitelist of valid sort columns to prevent SQL injection
     * Maps sortby option to column name
     */
    private const SORT_COLUMN_MAP = [
        1 => 'name',
        2 => 'Award',
        3 => 'year',
    ];

    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during testing)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     * 
     * TEMPORARY: Accepts duck-typed objects during migration for testing compatibility.
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see PlayerAwardsRepositoryInterface::searchAwards()
     */
    public function searchAwards(array $params): array
    {
        $conditions = [];
        $bindParams = [];
        $bindTypes = '';

        // Build WHERE conditions based on provided params
        if ($params['year'] !== null) {
            $conditions[] = 'year = ?';
            $bindParams[] = $params['year'];
            $bindTypes .= 'i';
        }

        if ($params['award'] !== null) {
            $conditions[] = 'Award LIKE ?';
            $bindParams[] = '%' . $params['award'] . '%';
            $bindTypes .= 's';
        }

        if ($params['name'] !== null) {
            $conditions[] = 'name LIKE ?';
            $bindParams[] = '%' . $params['name'] . '%';
            $bindTypes .= 's';
        }

        // Build the query
        $query = 'SELECT year, Award, name, table_ID FROM ibl_awards';
        
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // Add ORDER BY clause using whitelisted column
        $sortColumn = self::SORT_COLUMN_MAP[$params['sortby']] ?? 'year';
        $query .= ' ORDER BY ' . $sortColumn . ' ASC';

        // Execute query
        if (empty($bindParams)) {
            $results = $this->fetchAll($query);
        } else {
            $results = $this->fetchAll($query, $bindTypes, ...$bindParams);
        }

        return [
            'results' => $results,
            'count' => count($results),
        ];
    }
}
