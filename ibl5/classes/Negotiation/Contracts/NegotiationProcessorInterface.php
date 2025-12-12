<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

/**
 * NegotiationProcessorInterface - Negotiation workflow orchestration
 *
 * Orchestrates the complete contract negotiation workflow,
 * coordinating validation, demand calculation, and view rendering.
 */
interface NegotiationProcessorInterface
{
    /**
     * Process a contract negotiation request
     *
     * Loads player, validates eligibility, calculates demands,
     * and returns rendered HTML for the negotiation page.
     *
     * @param int $playerID Player ID to negotiate with
     * @param string $userTeamName User's team name for ownership validation
     * @param string $prefix Database table prefix (e.g., 'nuke')
     * @return string HTML output for the negotiation page
     *
     * **Workflow:**
     * 1. Load player using Player::withPlayerID()
     * 2. Render page header with player name/position
     * 3. Validate free agency module is not active
     * 4. Validate negotiation eligibility (ownership, contract status)
     * 5. Get team factors (wins, losses, tradition, money at position)
     * 6. Calculate contract demands using NegotiationDemandCalculator
     * 7. Calculate available cap space for next season
     * 8. Determine max first year salary based on experience
     * 9. Render negotiation form with all data
     *
     * **Error Handling:**
     * - Returns header + error message if player not found
     * - Returns header + error message if free agency is active
     * - Returns header + error message if player not eligible
     *
     * **Team Factors Retrieved:**
     * - Current season wins/losses from ibl_team_info
     * - Tradition wins/losses (Contract_AvgW/Contract_AvgL)
     * - Money committed at player's position (excluding this player)
     *
     * **Cap Space Calculation:**
     * - Starts from HARD_CAP_MAX
     * - Subtracts next year's salary for all non-retired players
     */
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix): string;
}
