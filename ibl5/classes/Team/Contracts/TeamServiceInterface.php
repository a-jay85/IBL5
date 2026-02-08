<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamServiceInterface - Contract for Team module data orchestration
 *
 * Assembles all data needed by TeamView from repositories, domain objects,
 * and sub-components. The view receives a pre-computed data array and never
 * touches the database.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TeamPageData array{
 *     teamID: int,
 *     team: \Team,
 *     imagesPath: string,
 *     yr: ?string,
 *     display: string,
 *     insertyear: string,
 *     isActualTeam: bool,
 *     tableOutput: string,
 *     draftPicksTable: string,
 *     currentSeasonCard: string,
 *     awardsCard: string,
 *     franchiseHistoryCard: string,
 *     rafters: string
 * }
 * @phpstan-type StartersData array<string, array{name: string|null, pid: int|null}>
 * @phpstan-type SidebarData array{currentSeasonCard: string, awardsCard: string, franchiseHistoryCard: string, rafters: string}
 */
interface TeamServiceInterface
{
    /**
     * Assemble all data needed by the team page view
     *
     * Initialises Team, Season, and Shared objects, loads the appropriate roster
     * via the repository, and calls private rendering helpers for sub-components
     * (tabs, table, sidebar).
     *
     * @param int $teamID Team ID (>0 = specific team, 0 = free agents, -1 = entire league)
     * @param ?string $yr Historical year parameter (null if current season)
     * @param string $display Active display tab (e.g., 'ratings', 'contracts')
     * @return TeamPageData
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display): array;

    /**
     * Extract starting lineup data from roster array
     *
     * Parses a roster array with depth chart information to identify the
     * starting player (depth = 1) for each position.
     *
     * @param list<PlayerRow> $roster Array of player rows with depth chart fields
     * @return StartersData Starters keyed by position
     */
    public function extractStartersData(array $roster): array;

    /**
     * Render the table output (tabs + table HTML) for a given display mode
     *
     * Used by the API handler to return just the table portion of the team page
     * without the full page layout (banner, sidebar, etc.).
     *
     * @param int $teamID Team ID (>0 = specific team, 0 = free agents, -1 = entire league)
     * @param ?string $yr Historical year parameter (null if current season)
     * @param string $display Active display tab (e.g., 'ratings', 'contracts')
     * @return string Complete table HTML with tab navigation
     */
    public function getTableOutput(int $teamID, ?string $yr, string $display): string;
}
