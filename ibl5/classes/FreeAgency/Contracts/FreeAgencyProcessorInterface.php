<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for orchestrating Free Agency operations
 * 
 * Handles the complete workflow for contract offers including validation,
 * calculation, database persistence, and response rendering.
 */
interface FreeAgencyProcessorInterface
{
    /**
     * Process a contract offer submission from the negotiation form
     * 
     * Executes complete offer workflow:
     * 1. Extract and validate input (team name, player ID)
     * 2. Load player object
     * 3. Check if player already signed (prevent duplicate signings)
     * 4. Parse offer data from form (reconstruct cap space, max contract, vet min)
     * 5. Validate offer with FreeAgencyOfferValidator
     * 6. Save valid offer to database
     * 7. Return success/error response HTML
     * 
     * **Error responses** include:
     * - Player already signed this free agency period
     * - Offer fails validation (with detailed error message)
     * - Database save failure
     * 
     * **Offer parsing logic**:
     * - If using Veteran Minimum exception: offer1-6 set to vet min, rest = 0
     * - If using LLE exception: offer1 = LLE amount, rest = 0
     * - If using MLE exception: offer1-6 based on years selected (1-6 years)
     * - If custom offer: use offer amounts from form fields (offeryear1-6)
     * 
     * **Cap space recalculation**:
     * - If amending existing offer, cap space is calculated excluding that player's current offer
     * - Amended cap space = original soft cap space + existing offer amount
     * - Bird rights preserved from player's current contract (if staying on same team)
     * 
     * @param array<string, mixed> $postData POST data from negotiation form including:
     *                                        - teamname: Offering team
     *                                        - playerID: Player ID being offered to
     *                                        - offerType: Exception type (0=custom, 1+=exception)
     *                                        - offeryear1-6: Custom offer amounts (if offerType=0)
     * 
     * @return string HTML response (success or error message with navigation links)
     *                Ready to display to user
     */
    public function processOfferSubmission(array $postData): string;

    /**
     * Delete contract offer(s) from this team to a player
     * 
     * Removes any pending contract offer(s) from the specified team to the specified player.
     * Used when team wants to withdraw an offer.
     * 
     * This method should handle cleanup:
     * - Delete from ibl_fa_offers table
     * - Recalculate cap space without the deleted offer
     * - Return success response
     * 
     * @param string $teamName Team withdrawing the offer
     * @param int $playerID Player ID having offer withdrawn
     * 
     * @return string HTML response (success message with navigation links)
     *                Ready to display to user
     */
    public function deleteOffers(string $teamName, int $playerID): string;
}
