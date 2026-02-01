<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamServiceInterface - Contract for Team module data orchestration
 *
 * Assembles all data needed by TeamView from repositories, domain objects,
 * and sub-components. The view receives a pre-computed data array and never
 * touches the database.
 */
interface TeamServiceInterface
{
    /**
     * Assemble all data needed by the team page view
     *
     * Initialises Team, Season, and Shared objects, loads the appropriate roster
     * via the repository, and calls private rendering helpers for sub-components
     * (tabs, table, starters, sidebar).
     *
     * @param int $teamID Team ID (>0 = specific team, 0 = free agents, -1 = entire league)
     * @param ?string $yr Historical year parameter (null if current season)
     * @param string $display Active display tab (e.g., 'ratings', 'contracts')
     * @return array{
     *     teamID: int,
     *     team: object,
     *     imagesPath: string,
     *     yr: ?string,
     *     display: string,
     *     insertyear: string,
     *     isActualTeam: bool,
     *     tableOutput: string,
     *     startersTable: string,
     *     draftPicksTable: string,
     *     teamInfoRight: string,
     *     rafters: string
     * }
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display): array;

    /**
     * Extract starting lineup data from roster array
     *
     * Parses a roster array with depth chart information to identify the
     * starting player (depth = 1) for each position.
     *
     * @param array $roster Array of player rows with depth chart fields
     * @return array<string, array{name: string|null, pid: int|null}> Starters keyed by position
     */
    public function extractStartersData(array $roster): array;
}
