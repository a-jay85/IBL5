<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamUIServiceInterface - Contract for Team module UI rendering
 * 
 * Handles all presentation logic for team pages including navigation tabs,
 * sidebar content, and table displays based on selected view type.
 * 
 * All methods return complete HTML strings or arrays of HTML, never throw exceptions.
 */
interface TeamUIServiceInterface
{
    /**
     * Render team information right sidebar
     * 
     * Generates the right column of the team page containing current season info,
     * GM history, championship banners, accomplishments, and historical results.
     * 
     * @param object $team Team object with properties needed for rendering
     *                      (name, teamID, color1, color2, etc.)
     * 
     * @return array{0: string, 1: string} [mainContent, rafters]
     *         - mainContent: HTML sidebar with team info tables
     *         - rafters: HTML with championship banners/rafters (rendered separately)
     * 
     * **Return Structure:**
     * - [0]: Complete HTML for main sidebar content (GM history, accomplishments, results)
     * - [1]: Complete HTML for championship banners/rafters (hanging from top)
     * 
     * **Behaviors:**
     * - All output is HTML-safe
     * - Returns ready-to-echo HTML strings
     * - Never throws exceptions
     */
    public function renderTeamInfoRight(object $team): array;

    /**
     * Render tab navigation for team display selection
     * 
     * Generates the tab bar for switching between different roster views
     * (ratings, season totals, averages, per-36 minutes, sim averages, playoffs, contracts).
     * Automatically includes playoff tab if season is in playoffs/draft/free-agency phase.
     * 
     * @param int $teamID Team ID for navigation links
     * @param string $display Currently active display tab (e.g., "ratings", "contracts")
     * @param string $insertyear Query parameter for historical year (e.g., "&yr=2023"), empty string if current season
     * @param object $season Season object with phase property ("Playoffs", "Draft", "Free Agency", etc.)
     * 
     * @return string Complete HTML table row with tab cells
     * 
     * **Return Structure:**
     * - HTML table row (<tr>) with multiple <td> cells
     * - Each cell contains a link to a different display tab
     * - Active tab (matching $display parameter) uses team colors and bold text
     * - Inactive tabs use neutral styling
     * - Tabs included: ratings, total_s, avg_s, per36mins, chunk, [playoffs if applicable], contracts
     * 
     * **Behaviors:**
     * - Active tab identified by matching display parameter
     * - Active tab background uses team->color2, text is bold black
     * - Playoff tab only included if season->phase matches playoff phases
     * - All links include teamID and display parameter
     * - All links include $insertyear parameter
     * - Never throws exceptions
     * 
     * **Example:**
     * $tabs = $uiService->renderTabs(5, 'ratings', '&yr=2023', $season);
     * // Returns: <tr><td bgcolor="..."><a href="...">Ratings</a></td>...</tr>
     */
    public function renderTabs(int $teamID, string $display, string $insertyear, object $season): string;

    /**
     * Get the appropriate table output based on display type
     * 
     * Routes to different UI rendering functions based on the selected display type.
     * Each display type generates a different statistics or information table.
     * 
     * @param string $display Display type selector (ratings, total_s, avg_s, per36mins, chunk, playoffs, contracts)
     * @param object $db Database connection object
     * @param mixed $result Database result object with roster/stats data
     * @param object $team Team object with properties for rendering and color scheme
     * @param ?string $yr Historical year parameter (null if current season, e.g., "2023" if historical)
     * @param object $season Season object with phase and date range properties
     * @param object $sharedFunctions Shared utility functions object for common operations
     * 
     * @return string Complete HTML table for the selected display type
     * 
     * **Display Types:**
     * - 'ratings': Player ratings and skill levels for current season
     * - 'total_s': Total statistics accumulated in current season
     * - 'avg_s': Per-game averages for current season
     * - 'per36mins': Per-36-minute statistics (pace-adjusted averages)
     * - 'chunk': Sim averages (averages across simulation chunks/periods)
     * - 'playoffs': Per-game averages during playoff period (if playoffs phase)
     * - 'contracts': Contract details and salary information for roster
     * - Default: 'ratings' (if display type not recognized)
     * 
     * **Return Structure:**
     * Each display type returns a styled HTML table:
     * - Table headers with column labels
     * - One row per player with relevant statistics
     * - Colored by team colors from $team object
     * - Sortable if applicable (depends on UI implementation)
     * 
     * **Behaviors:**
     * - Calls appropriate UI::* static method based on display type
     * - All output is HTML-safe
     * - Returns ready-to-echo HTML string
     * - Never throws exceptions
     * 
     * **Example:**
     * $table = $uiService->getTableOutput('ratings', $db, $result, $team, null, $season, $shared);
     * // Returns complete HTML ratings table
     * 
     * $table = $uiService->getTableOutput('contracts', $db, $result, $team, '2023', $season, $shared);
     * // Returns contracts table for 2023 historical season
     */
    public function getTableOutput(
        string $display,
        object $db,
        mixed $result,
        object $team,
        ?string $yr,
        object $season,
        object $sharedFunctions
    ): string;
}
