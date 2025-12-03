<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering the Free Agency negotiation page
 * 
 * Renders the contract offer form where teams make or amend offers to free agents.
 * Displays player ratings, demands, cap constraints, and offer input fields.
 */
interface FreeAgencyNegotiationHelperInterface
{
    /**
     * Render the complete negotiation page for a specific player
     * 
     * Outputs a comprehensive negotiation interface showing:
     * - Player position and name
     * - Player ratings (statistical abilities: 2ga, 3gp, orb, drb, ast, etc.)
     * - Player image
     * - Player demands for all 6 contract years
     * - Cap space information (soft cap, hard cap, roster spots)
     * - Contract offer input fields (6 years)
     * - Max contract offer buttons (pre-calculated max salaries by year)
     * - Exception offer buttons (MLE, LLE, Veteran Minimum)
     * - Existing offer display (if already offered to this player)
     * - Delete offer button (if offer exists)
     * 
     * **No Roster Spots**: If team has no roster spots available, displays error message
     * instead of negotiation form (unless amending existing offer).
     * 
     * **Existing Offers**: If team has already made an offer to this player,
     * displays that offer in the input fields and shows amended cap space
     * (original cap space + existing offer amount).
     * 
     * Uses output buffering (ob_start/ob_get_clean) for HTML generation.
     * All output is properly escaped with htmlspecialchars().
     * 
     * @param int $playerID Player ID to display negotiation for
     * @param \Team $team Team object (represents user's team, provides name and cap data)
     * 
     * @return string Complete HTML negotiation form (not including HTML/body tags)
     *                Ready to be embedded in module page
     */
    public function renderNegotiationPage(int $playerID, \Team $team): string;

    /**
     * Get existing offer from this team to a specific player
     * 
     * Queries ibl_fa_offers table for any pending offer from the team to the player.
     * Returns all contract years (offer1-6) or all zeros if no offer exists.
     * 
     * Used when amending offers (to show current offer in form fields)
     * and for calculating amended cap space (original cap + existing offer).
     * 
     * @param string $teamName Offering team name
     * @param string $playerName Player name
     * 
     * @return array<string, int> Existing offer with keys offer1-6, all integers
     *                            Returns array with all offer1-6 set to 0 if no offer exists
     */
    public function getExistingOffer(string $teamName, string $playerName): array;
}
