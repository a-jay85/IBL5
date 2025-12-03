<?php

namespace Extension\Contracts;

/**
 * ExtensionProcessorInterface - Contract for extension processing workflow
 * 
 * Defines the main entry point for processing contract extension offers.
 * Orchestrates validation, evaluation, database operations, and notifications.
 * 
 * @package Extension\Contracts
 */
interface ExtensionProcessorInterface
{
    /**
     * Processes a contract extension offer through the complete workflow
     * 
     * Main method that handles the entire extension offer lifecycle:
     * validation, evaluation, database updates, and notifications.
     * 
     * @param array $extensionData Array containing:
     *   - 'playerID': int - Player ID (or 'player': Player object)
     *   - 'teamName': string - Team name (or 'team': Team object)
     *   - 'offer': array - Offer details [year1, year2, year3, year4, year5]
     *   - 'demands': array|null - Optional player demands [total, years] or [year1..year5]
     * @return array Result array with:
     *   - 'success': bool - Whether processing completed without errors
     *   - 'accepted': bool - Whether player accepted (only if success=true)
     *   - 'error': string - Error message (only if success=false)
     *   - 'message': string - Player's response message
     *   - 'offerValue': float - Calculated offer value
     *   - 'demandValue': float - Player's demand value
     *   - 'modifier': float - Combined modifier applied
     *   - 'extensionYears': int - Number of years in extension
     *   - 'offerInMillions': float - Offer total in millions
     *   - 'offerDetails': string - Year-by-year breakdown
     *   - 'discordNotificationSent': bool - Whether Discord was notified
     *   - 'discordChannel': string - Channel used for notification
     * 
     * **Validation Steps:**
     * 1. Validate offer amounts (years 1-3 must be non-zero)
     * 2. Validate extension eligibility (not used this season/sim)
     * 3. Validate maximum year-one offer based on experience
     * 4. Validate raises don't exceed allowed percentages
     * 5. Validate no salary decreases (except to zero)
     * 
     * **Processing Steps (after validation):**
     * 1. Mark extension used this sim (even if rejected)
     * 2. Calculate team factors and player preferences
     * 3. Evaluate offer against demands with modifiers
     * 4. If accepted: update contract, mark used this season, create news
     * 5. If rejected: create rejection news
     * 6. Send Discord and email notifications
     * 
     * **Error Handling:**
     * - Returns success=false with error message for validation failures
     * - Returns success=false if player or team not found
     */
    public function processExtension($extensionData);
}
