<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

use Player\Player;

/**
 * RookieOptionFormViewInterface - Contract for rookie option form rendering
 *
 * Defines the presentation layer for the rookie option eligibility form.
 * Renders the confirmation form with design system styling.
 *
 * @package RookieOption\Contracts
 */
interface RookieOptionFormViewInterface
{
    /**
     * Renders the rookie option form for a player
     *
     * Displays the confirmation form for exercising a rookie option,
     * including player image, option value, and warning text.
     * Uses design system classes (ibl-card, ibl-alert, ibl-btn).
     *
     * @param Player $player Player object
     * @param string $teamName User's team name for form submission
     * @param int $rookieOptionValue Calculated rookie option value in thousands
     * @param string|null $error Error message from PRG redirect (via ?error= param)
     * @param string|null $result Result type from PRG redirect (via ?result= param)
     * @param string|null $from Origin page for redirect
     * @return string Rendered HTML
     *
     * **HTML Structure:**
     * - Error banner (if $error set) with ibl-alert--error
     * - Result banner (if $result set) with ibl-alert--success/warning
     * - ibl-card with player image (flex layout) and option value
     * - Warning card with ibl-alert--warning for exercise consequences
     * - Form with hidden fields and ibl-btn--primary submit button
     *
     * **Form Fields:**
     * - teamname: Team name
     * - playerID: Player ID
     * - rookieOptionValue: Calculated option value
     * - from: Origin page for PRG redirect ('player' or 'fa')
     *
     * **Security:**
     * - All output HTML-escaped via safeHtmlOutput()
     * - Player ID cast to integer
     */
    public function renderForm(Player $player, string $teamName, int $rookieOptionValue, ?string $error = null, ?string $result = null, ?string $from = null): string;
}
