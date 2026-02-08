<?php

declare(strict_types=1);

namespace AwardHistory\Contracts;

/**
 * AwardHistoryServiceInterface - Contract for the player awards business logic
 * 
 * Defines the public API for the player awards service. This is the main entry point
 * that orchestrates validation, database search, and data transformation.
 */
interface AwardHistoryServiceInterface
{
    /**
     * Execute an award search based on form parameters
     * 
     * Orchestrates the complete search workflow:
     * 1. Validates all parameters using AwardHistoryValidatorInterface
     * 2. Executes search using AwardHistoryRepositoryInterface
     * 3. Returns results with validated parameters for form repopulation
     * 
     * @param array<string, mixed> $rawParams Raw POST parameters from form
     * @return array{
     *     awards: array<int, array{year: int, Award: string, name: string}>,
     *     count: int,
     *     params: array{name: string|null, award: string|null, year: int|null, sortby: int}
     * } Search results:
     *     - awards: array of award records
     *     - count: total number of matching awards
     *     - params: validated parameters used for search (for form re-population)
     * 
     * IMPORTANT BEHAVIORS:
     *  - If $rawParams is empty (no form submission), returns empty results
     *  - All parameters are validated before database search
     *  - Returns params so form can be re-populated with user's search criteria
     *  - Never throws exceptions – returns empty results on error
     * 
     * Examples:
     *  $result = $service->search(['aw_name' => 'Johnson', 'aw_Award' => 'MVP']);
     *  $result['count'] = 3;
     *  $result['awards'][0] = ['year' => 2025, 'Award' => 'Regular Season MVP', 'name' => 'Magic Johnson'];
     */
    public function search(array $rawParams): array;

    /**
     * Get the sort column options for the form
     * 
     * @return array<int, string> Sort option ID => label pairs
     * 
     * Example:
     *  [1 => 'Name', 2 => 'Award Name', 3 => 'Year']
     */
    public function getSortOptions(): array;
}
