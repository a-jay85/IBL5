<?php

declare(strict_types=1);

namespace FranchiseHistory\Contracts;

/**
 * FranchiseHistoryRepositoryInterface - Contract for franchise history data access
 *
 * Defines methods for retrieving franchise history data from the database.
 *
 * @see \FranchiseHistory\FranchiseHistoryRepository For the concrete implementation
 */
interface FranchiseHistoryRepositoryInterface
{
    /**
     * Get all franchise history data with win/loss records
     *
     * @param int $currentEndingYear Current season ending year
     * @return array Array of franchise history data
     */
    public function getAllFranchiseHistory(int $currentEndingYear): array;

    /**
     * Get number of titles for a team
     *
     * @param string $teamName Team name
     * @param string $titleType Type of title (HEAT, Division, Conference, IBL Champions)
     * @return int Number of titles
     */
    public function getNumberOfTitles(string $teamName, string $titleType): int;
}
