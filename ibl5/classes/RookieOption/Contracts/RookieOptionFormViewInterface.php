<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

/**
 * RookieOptionFormViewInterface - Contract for rookie option form rendering
 * 
 * Defines the presentation layer for the rookie option eligibility form.
 * Handles error display and the confirmation form.
 * 
 * @package RookieOption\Contracts
 */
interface RookieOptionFormViewInterface
{
    /**
     * Renders a generic error message with proper HTML escaping
     * 
     * Displays an error message when rookie option validation fails,
     * with a link to go back to the previous page.
     * 
     * @param string $errorMessage Error message to display
     * @return void Outputs HTML directly
     * 
     * **HTML Structure:**
     * - HTML-escaped error message with preserved newlines
     * - "Go Back" link using JavaScript history
     * 
     * **Security:**
     * - Error message is HTML-escaped using safeHtmlOutput()
     * - Newlines converted to <br> tags
     */
    public function renderError(string $errorMessage): void;

    /**
     * Renders the rookie option form for a player
     * 
     * Displays the confirmation form for exercising a rookie option,
     * including player image, option value, and warning text.
     * 
     * @param object $player Player object with properties:
     *   - 'playerID': int - Player ID for form submission
     *   - 'position': string - Player position (PG, SG, etc.)
     *   - 'name': string - Player's full name
     * @param string $teamName User's team name for form submission
     * @param int $rookieOptionValue Calculated rookie option value in thousands
     * @return void Outputs HTML directly
     * 
     * **HTML Structure:**
     * - Player image (left-aligned)
     * - Option value display
     * - Warning about extension ineligibility
     * - Free agency notice
     * - Form with hidden fields and submit button
     * 
     * **Form Fields:**
     * - teamname: Team name
     * - playerID: Player ID
     * - rookieOptionValue: Calculated option value
     * 
     * **Security:**
     * - All output HTML-escaped
     * - Player ID cast to integer
     */
    public function renderForm($player, string $teamName, int $rookieOptionValue): void;
}
