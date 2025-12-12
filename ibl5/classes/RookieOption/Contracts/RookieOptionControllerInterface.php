<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

/**
 * RookieOptionControllerInterface - Contract for rookie option controller operations
 * 
 * Defines the main entry point for processing rookie option exercises.
 * Handles the complete workflow including validation, database updates,
 * notifications, and news story creation.
 * 
 * @package RookieOption\Contracts
 */
interface RookieOptionControllerInterface
{
    /**
     * Main entry point for processing rookie option exercise
     * 
     * Processes a team's request to exercise a rookie option on one of their players.
     * Validates eligibility, updates contract, sends notifications, and creates news.
     * 
     * @param string $teamName Team name exercising the option
     * @param int $playerID Player ID of the rookie
     * @param int $extensionAmount Rookie option salary amount in thousands
     * @return void Renders success page or terminates with error
     * 
     * **Validation:**
     * - Player must pass canRookieOption() check
     * - Player must be a first or second round draft pick
     * 
     * **Processing Steps:**
     * 1. Load player from database
     * 2. Validate rookie option eligibility
     * 3. Update player's contract (cy3 or cy4 depending on draft round)
     * 4. Send Discord notification to #rookie-options
     * 5. Send email notification to commissioner
     * 6. Create news story if email succeeded
     * 7. Render success page with link back to team page
     * 
     * **Error Handling:**
     * - Terminates with die() if validation fails
     * - Terminates with die() if database update fails
     */
    public function processRookieOption(string $teamName, int $playerID, int $extensionAmount): void;
}
