<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionProcessorInterface - Contract for extension processing workflow
 *
 * Defines the main entry point for processing contract extension offers.
 * Orchestrates validation, evaluation, database operations, and notifications.
 *
 * @phpstan-import-type ExtensionOffer from ExtensionDatabaseOperationsInterface
 *
 * @phpstan-type ExtensionData array{playerID?: int, player?: \Player\Player, teamName?: string, team?: \Team, offer: ExtensionOffer, demands?: array{total: int, years: int}|ExtensionOffer|null}
 * @phpstan-type ExtensionSuccessResult array{success: true, accepted: bool, message: string, offerValue: float, demandValue: float, modifier: float, extensionYears: int, offerInMillions: float, offerDetails: string, discordNotificationSent: bool, discordChannel: string, refusalMessage?: string}
 * @phpstan-type ExtensionErrorResult array{success: false, error: string}
 * @phpstan-type ExtensionResult ExtensionSuccessResult|ExtensionErrorResult
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
     * @param ExtensionData $extensionData Extension request data
     * @return ExtensionResult Result with success/error or full extension outcome
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
