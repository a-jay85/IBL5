<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamControllerInterface - Main controller contract for Team module
 * 
 * Coordinates between Repository, Services, and UI components following
 * the MVC pattern used in other refactored modules.
 * 
 * The controller handles user input, orchestrates business logic via services,
 * and composes the final page output.
 */
interface TeamControllerInterface
{
    /**
     * Display team page with roster and statistics
     * 
     * Orchestrates the complete team page rendering including:
     * 1. Load team data and initialize roster based on context (team/free agents/league)
     * 2. Determine which roster to display (current, free agency, historical, or entire league)
     * 3. Render page header with team logo and tabs
     * 4. Render selected statistics table (ratings, totals, averages, etc.)
     * 5. Render starting lineup if applicable
     * 6. Render sidebar with team history and accomplishments
     * 
     * **Context Modes:**
     * - teamID > 0: Specific team roster (current, free agency, or historical year)
     * - teamID = 0: Free agents available for signing
     * - teamID = -1: Entire league roster
     * 
     * **Display Modes (from $_REQUEST['display']):**
     * - 'ratings': Player ratings and skill levels
     * - 'total_s': Season total statistics
     * - 'avg_s': Season per-game averages
     * - 'per36mins': Per-36-minute statistics
     * - 'chunk': Sim period averages
     * - 'playoffs': Playoff period statistics
     * - 'contracts': Player contract details and salary
     * - Default: 'ratings' if not specified
     * 
     * **Historical Year Mode (from $_REQUEST['yr']):**
     * - If 'yr' parameter provided: load historical roster from ibl_hist for that year
     * - If 'yr' not provided: load current season roster
     * - Displays year in page heading when in historical mode
     * 
     * **Starting Lineup Display:**
     * - Only shown for specific teams (teamID > 0)
     * - Not shown for free agents (teamID = 0) or entire league (teamID = -1)
     * - Not shown for historical years (when 'yr' parameter provided)
     * - Shows depth chart starters from most recent simulation
     * 
     * **Free Agency Filtering:**
     * - If free agency module is active: show only expiring contracts (cyt != cy)
     * - If free agency module is inactive: show all rosters (all contract years)
     * 
     * @return void Outputs complete HTML page directly (via echo)
     * 
     * **Side Effects:**
     * - Calls OpenTable() and Nuke\Header::header() for page framing
     * - Echoes complete HTML to output buffer
     * - Expects these global functions to be available:
     *   - OpenTable(), CloseTable() - page framing
     *   - Nuke\Header::header() - page header
     *   - \UI\Modules\Team - static UI helper methods
     *   - \UI:: - static UI helper methods
     *   - \Team::initialize() - team factory
     * 
     * **Behaviors:**
     * - Never returns data; always outputs HTML directly
     * - Never throws exceptions (catches all errors internally)
     * - Renders complete page in one call
     */
    public function displayTeamPage(int $teamID): void;
}
