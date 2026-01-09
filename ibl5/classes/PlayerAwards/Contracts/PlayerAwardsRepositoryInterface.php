<?php

declare(strict_types=1);

namespace PlayerAwards\Contracts;

/**
 * PlayerAwardsRepositoryInterface - Contract for player awards database operations
 * 
 * Defines the database operations for searching and retrieving player awards.
 * All methods use prepared statements to prevent SQL injection.
 */
interface PlayerAwardsRepositoryInterface
{
    /**
     * Search for awards based on validated criteria
     * 
     * Executes a database query to find awards matching provided criteria.
     * Uses dynamic WHERE clause building with prepared statements.
     * 
     * @param array{
     *     name: string|null,
     *     award: string|null,
     *     year: int|null,
     *     sortby: int
     * } $params Validated parameters from PlayerAwardsValidatorInterface
     * @return array{
     *     results: array<int, array{year: int, Award: string, name: string, table_ID: int}>,
     *     count: int
     * } Search results:
     *     - results: array of award records from ibl_awards table
     *     - count: total number of matching records
     * 
     * IMPORTANT BEHAVIORS:
     *  - Filters with null values are SKIPPED (not applied to WHERE clause)
     *  - If no filters produce conditions, ALL awards are returned
     *  - Name and Award searches use LIKE with % wildcards (case-insensitive)
     *  - Year uses exact match (=)
     *  - Results are ordered by sortby parameter (ASC):
     *      - 1: name
     *      - 2: Award
     *      - 3: year
     *  - Returns array{results: [], count: 0} if no matches
     *  - Never throws exceptions – returns empty results on error
     * 
     * SCHEMA REFERENCE (ibl_awards):
     *  - year: int(11) - Year of the award
     *  - Award: varchar(128) - Award name/type
     *  - name: varchar(32) - Player name
     *  - table_ID: int(11) AUTO_INCREMENT PRIMARY KEY
     * 
     * Examples:
     *  $result = $repo->searchAwards(['name' => 'Smith', 'award' => null, 'year' => null, 'sortby' => 1]);
     *  // Returns all awards for players with 'Smith' in name, sorted by name
     *  
     *  $result = $repo->searchAwards(['name' => null, 'award' => 'MVP', 'year' => 2025, 'sortby' => 3]);
     *  // Returns all MVP awards from 2025, sorted by year
     */
    public function searchAwards(array $params): array;
}
