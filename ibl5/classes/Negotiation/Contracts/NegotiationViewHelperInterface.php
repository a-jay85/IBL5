<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

use Player\Player;

/**
 * NegotiationViewHelperInterface - Negotiation page HTML rendering
 *
 * Handles HTML rendering for the negotiation page, separating
 * presentation logic from business logic. All methods are static.
 */
interface NegotiationViewHelperInterface
{
    /**
     * Render the negotiation form
     *
     * Generates the complete contract offer form with demands display,
     * input fields, and informational notes about contract rules.
     *
     * @param Player $player The player object
     * @param array $demands Calculated demands with keys year1-year6, years, total
     * @param int $capSpace Available cap space for year 1
     * @param int $maxYearOneSalary Maximum first year salary based on experience
     * @return string HTML form output
     *
     * **Form Fields:**
     * - offerYear1 through offerYear5: Contract offer amounts
     * - maxyr1: Hidden field with max year 1 salary
     * - demandsTotal: Hidden field with total demands
     * - demandsYears: Hidden field with years demanded
     * - teamName: Hidden field with team name
     * - playerName: Hidden field with player name
     * - playerID: Hidden field with player ID
     *
     * **Display Logic:**
     * - If demands['year1'] < maxYearOneSalary: Show editable fields with demands as defaults
     * - If demands['year1'] >= maxYearOneSalary: Show max salary fields pre-filled
     *
     * **Notes Displayed:**
     * - Cap space available
     * - Maximum year 1 salary
     * - Instructions for 0 entries
     * - Minimum contract length (3 years)
     * - Raise requirements
     * - Bird rights raise percentage (12.5% vs 10%)
     * - Hard cap information
     *
     * **Behaviors:**
     * - Escapes player name and team name for HTML safety
     * - Calculates max raise based on bird years (>= 3 = 12.5%, else 10%)
     * - Form submits to modules/Player/extension.php
     */
    public static function renderNegotiationForm(
        Player $player,
        array $demands,
        int $capSpace,
        int $maxYearOneSalary
    ): string;

    /**
     * Render error message
     *
     * Displays an error message with HTML escaping for safety.
     *
     * @param string $error Error message to display
     * @return string HTML paragraph with escaped error message
     *
     * **Behaviors:**
     * - Wraps message in <p> tags
     * - Escapes message using DatabaseService::safeHtmlOutput()
     */
    public static function renderError(string $error): string;

    /**
     * Render page header
     *
     * Displays the player's position and name as a header.
     *
     * @param Player $player The player object
     * @return string HTML header with format: "<b>POS Name</b> - Contract Demands:<br>"
     *
     * **Behaviors:**
     * - Escapes player name and position for HTML safety
     * - Uses bold formatting
     */
    public static function renderHeader(Player $player): string;
}
