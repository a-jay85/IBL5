<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartControllerInterface - Contract for depth chart entry module orchestration
 * 
 * Manages the main entry point for the depth chart module,
 * handling form display and user interaction workflow.
 */
interface DepthChartControllerInterface
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
}
