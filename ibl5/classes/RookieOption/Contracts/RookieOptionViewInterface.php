<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

/**
 * RookieOptionViewInterface - Contract for rookie option result rendering
 * 
 * Defines the presentation layer for displaying rookie option processing results.
 * Shows success messages and navigation links after option exercise.
 * 
 * @package RookieOption\Contracts
 */
interface RookieOptionViewInterface
{
    /**
     * Renders the success page after processing rookie option
     * 
     * Displays confirmation that the rookie option was exercised,
     * with appropriate navigation links based on season phase.
     * 
     * @param string $teamName Team name (for display purposes)
     * @param int $teamID Team ID for navigation link
     * @param string $phase Current season phase ("Free Agency" or other)
     * @param bool $emailSuccess Whether commissioner email was sent successfully
     * @return void Outputs HTML directly
     * 
     * **HTML Structure:**
     * - Basic HTML page with title
     * - Success confirmation message
     * - Navigation link (to Free Agency or Team page based on phase)
     * - Email status message (success or failure notice)
     * 
     * **Phase-Based Navigation:**
     * - "Free Agency" phase: Link to Free Agency screen
     * - Other phases: Link to team page with teamID
     * 
     * **Email Status Display:**
     * - Success: Shows confirmation of email sent
     * - Failure: Shows error message asking to notify commissioner
     */
    public function renderSuccessPage(string $teamName, int $teamID, string $phase, bool $emailSuccess): void;
}
