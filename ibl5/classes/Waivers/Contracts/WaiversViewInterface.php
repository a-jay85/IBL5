<?php

namespace Waivers\Contracts;

/**
 * WaiversViewInterface - Contract for waiver wire UI rendering
 * 
 * Defines the presentation layer for waiver wire operations. Handles all
 * HTML rendering for the waiver wire forms, player selection dropdowns,
 * and status messages.
 * 
 * @package Waivers\Contracts
 */
interface WaiversViewInterface
{
    /**
     * Renders the waiver wire form
     * 
     * Generates the complete waiver wire HTML form including team logo,
     * player selection dropdown, roster status, and submit button.
     * 
     * @param string $teamName Team name for display and form submission
     * @param int $teamID Team ID for logo display
     * @param string $action Action type ('add' or 'drop')
     * @param array $players Array of pre-built HTML option strings for dropdown
     *   Each element should be output from buildPlayerOption()
     * @param int $openRosterSpots Number of open roster spots (0-15)
     * @param int $healthyOpenRosterSpots Number of healthy open roster spots (0-15)
     * @param string $errorMessage Optional error/success message to display (default: '')
     * @return void Outputs HTML directly
     * 
     * **HTML Structure:**
     * - Error message (if provided) in red
     * - Team logo image
     * - Roster status header
     * - Player selection dropdown
     * - Hidden form fields (team name, action, roster counts)
     * - Submit button with double-click prevention
     * 
     * **Security:**
     * - All output is HTML-escaped using htmlspecialchars()
     * - Uses output buffering pattern for clean HTML
     */
    public function renderWaiverForm(
        string $teamName,
        int $teamID,
        string $action,
        array $players,
        int $openRosterSpots,
        int $healthyOpenRosterSpots,
        string $errorMessage = ''
    ): void;

    /**
     * Builds player option HTML for dropdown selection
     * 
     * Creates a single <option> element for the player selection dropdown.
     * Used for both roster players (drop action) and waiver pool players (add action).
     * 
     * @param int $playerID Player ID for form value
     * @param string $playerName Player display name
     * @param string $contract Contract display string (from WaiversProcessor)
     * @param string $waitTime Optional waiver wait time display (default: '')
     * @return string HTML <option> element string
     * 
     * **Output Format:**
     * - Without wait time: `<option value="123">John Smith 500 450 400</option>`
     * - With wait time: `<option value="123">John Smith 103 (Clears in 5 h, 30 m, 15 s)</option>`
     * 
     * **Security:**
     * - All text content is HTML-escaped
     * - Player ID is used as-is (integer)
     */
    public function buildPlayerOption(
        int $playerID,
        string $playerName,
        string $contract,
        string $waitTime = ''
    ): string;

    /**
     * Renders the not logged in message
     * 
     * Displays an error message and login form for unauthenticated users
     * attempting to access the waiver wire.
     * 
     * @param string $message Message to display (typically _LOGININCOR or _USERREGLOGIN)
     * @return void Outputs HTML directly with Nuke header/footer
     * 
     * **HTML Structure:**
     * - Nuke header
     * - Top menu
     * - Error message
     * - Login box
     * - Nuke footer
     * 
     * **Security:**
     * - Message is HTML-escaped
     */
    public function renderNotLoggedIn(string $message): void;

    /**
     * Renders the waivers closed message
     * 
     * Displays a message indicating that waiver wire transactions are not
     * currently allowed based on the season phase.
     * 
     * @return void Outputs HTML directly with Nuke header/footer
     * 
     * **HTML Structure:**
     * - Nuke header
     * - Top menu
     * - "Waivers closed" message
     * - Nuke footer
     */
    public function renderWaiversClosed(): void;
}
