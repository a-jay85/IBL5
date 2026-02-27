<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamTableServiceInterface - Contract for Team module table rendering
 *
 * Handles roster table rendering, dropdown group construction, and
 * starter extraction logic. Extracted from TeamServiceInterface to
 * separate table concerns from page orchestration.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type StartersData from TeamServiceInterface
 */
interface TeamTableServiceInterface
{
    /**
     * Render the table output (tabs/dropdown + table HTML) for a given display mode
     *
     * @param int $teamID Team ID (>0 = specific team, 0 = free agents, -1 = entire league)
     * @param ?string $yr Historical year parameter (null if current season)
     * @param string $display Active display tab (e.g., 'ratings', 'contracts', 'split')
     * @param ?string $split Split stats key when display is 'split' (e.g. 'home', 'road')
     * @return string Complete table HTML with tab/dropdown navigation
     */
    public function getTableOutput(int $teamID, ?string $yr, string $display, ?string $split = null): string;

    /**
     * Render the appropriate table HTML based on display type
     *
     * @param string $display Display mode (ratings, total_s, avg_s, per36mins, chunk, playoffs, contracts, split)
     * @param list<PlayerRow>|list<array<string, mixed>> $result Roster data
     * @param \Team $team Team object
     * @param ?string $yr Historical year (null for current)
     * @param \Season $season Season object
     * @param list<int> $starterPids Player IDs of starters for highlighting
     * @param ?string $split Split stats key when display is 'split'
     * @return string Table HTML
     */
    public function renderTableForDisplay(string $display, array $result, \Team $team, ?string $yr, \Season $season, array $starterPids = [], ?string $split = null): string;

    /**
     * Build the optgroup structure for the dropdown view selector
     *
     * @param \Season $season Season object (used to determine playoff availability)
     * @return array<string, array<string, string>> Groups keyed by label, each containing value => label pairs
     */
    public function buildDropdownGroups(\Season $season): array;

    /**
     * Extract starting lineup data from roster array
     *
     * @param list<PlayerRow> $roster Array of player rows with depth chart fields
     * @return StartersData Starters keyed by position
     */
    public function extractStartersData(array $roster): array;

    /**
     * Get roster data and starter PIDs for a team
     *
     * @param int $teamID Team ID (>0 = specific team)
     * @return array{roster: list<array<string, mixed>>, starterPids: list<int>}
     */
    public function getRosterAndStarters(int $teamID): array;
}
