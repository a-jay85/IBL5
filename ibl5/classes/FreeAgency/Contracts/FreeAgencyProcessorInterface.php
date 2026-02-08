<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for orchestrating Free Agency operations
 *
 * Handles the complete workflow for contract offers including validation,
 * calculation, database persistence, and result reporting.
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
     * 7. Return result array for PRG redirect
     *
     * @param array<string, mixed> $postData POST data from negotiation form including:
     *                                        - teamname: Offering team
     *                                        - playerID: Player ID being offered to
     *                                        - offerType: Exception type (0=custom, 1+=exception)
     *                                        - offeryear1-6: Custom offer amounts (if offerType=0)
     *
     * @return array{success: bool, type: string, message: string, playerID: int}
     *         Result array for PRG redirect handling
     */
    public function processOfferSubmission(array $postData): array;

    /**
     * Delete contract offer(s) from this team to a player
     *
     * Removes any pending contract offer(s) from the specified team to the specified player.
     * Used when team wants to withdraw an offer.
     *
     * @param string $teamName Team withdrawing the offer
     * @param int $playerID Player ID having offer withdrawn
     *
     * @return array{success: bool} Result array for PRG redirect handling
     */
    public function deleteOffers(string $teamName, int $playerID): array;
}
