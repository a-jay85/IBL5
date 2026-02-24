<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntryControllerInterface - Contract for depth chart entry module orchestration
 * 
 * Manages the main entry point for the depth chart module,
 * handling form display and user interaction workflow.
 */
interface DepthChartEntryControllerInterface
{
    /**
     * Display the depth chart entry form for a user's team
     * 
     * Renders the complete depth chart entry interface including:
     * 1. Page header and navigation
     * 2. Team logo
     * 3. Team players with current ratings
     * 4. Depth chart form with all player rows
     * 5. Page footer
     * 
     * **Workflow:**
     * - Look up team for authenticated user
     * - Retrieve team roster from database
     * - Display UI header and team branding
     * - Show current player ratings for reference
     * - Render editable depth chart form
     * - Display page footer
     * 
     * @param string $username Authenticated username (used to look up user's team)
     * @return void Renders complete HTML page with form
     * 
     * **Important Behaviors:**
     * - Directly renders HTML to output (no return value)
     * - Uses UI utilities for headers, footers, menus
     * - Retrieves players from database for team roster
     * - Includes current depth chart settings for each player
     * - Form is ready for user to edit and submit
     * - Uses Season object to determine current phase
     */
    public function displayForm(string $username): void;

    /**
     * Get the stats table HTML for a given team and display mode
     *
     * Returns the TableViewDropdown-wrapped table HTML (dropdown + table) for use
     * by both the full page render and the AJAX tab-switching API.
     *
     * @param int $teamID Team ID
     * @param string $display Display mode (ratings, total_s, avg_s, per36mins, chunk, playoffs, contracts, split)
     * @param ?string $split Split stats key when display is 'split' (e.g. 'home', 'road')
     * @return string HTML output
     */
    public function getTableOutput(int $teamID, string $display, ?string $split = null): string;
}
