<?php

namespace Extension\Contracts;

/**
 * ExtensionDatabaseOperationsInterface - Contract for extension database operations
 * 
 * Defines the data access layer for contract extension transactions. Handles all
 * database operations related to updating player contracts, managing extension
 * usage flags, and creating news stories for extension results.
 * 
 * @package Extension\Contracts
 */
interface ExtensionDatabaseOperationsInterface
{
    /**
     * Updates a player's contract with the new extension terms
     * 
     * Modifies the player's contract record to reflect the newly signed extension.
     * The current salary becomes year 1, and offer years become years 2-6.
     * 
     * @param string $playerName Player name for lookup
     * @param array $offer Offer array with keys:
     *   - 'year1': int - First extension year salary
     *   - 'year2': int - Second extension year salary
     *   - 'year3': int - Third extension year salary
     *   - 'year4': int - Fourth extension year salary (0 if 3-year deal)
     *   - 'year5': int - Fifth extension year salary (0 if 4-year deal or less)
     * @param int $currentSalary Player's current year salary (becomes cy1)
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Sets cy = 1 (current year of contract)
     * - Sets cyt = total years (current + extension years)
     * - Sets cy1 = currentSalary
     * - Sets cy2-cy6 = offer year1-year5
     * 
     * **Behaviors:**
     * - Escapes player name for SQL safety
     * - Treats empty/null year4, year5 as 0
     */
    public function updatePlayerContract($playerName, $offer, $currentSalary);

    /**
     * Marks that a team has used their extension attempt for this sim
     * 
     * Teams can only make one extension offer per sim (chunk). This method
     * sets the flag that prevents additional offers until the next sim.
     * 
     * @param string $teamName Team name for lookup
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Sets Used_Extension_This_Chunk = 1 in ibl_team_info
     */
    public function markExtensionUsedThisSim($teamName);

    /**
     * Marks that a team has used their extension for this season
     * 
     * Teams can only successfully complete one extension per season. This
     * method sets the flag when an extension is accepted.
     * 
     * @param string $teamName Team name for lookup
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Sets Used_Extension_This_Season = 1 in ibl_team_info
     */
    public function markExtensionUsedThisSeason($teamName);

    /**
     * Creates a news story for an accepted extension
     * 
     * Generates a news article announcing the player has signed an extension
     * with the team, including contract details.
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions (e.g., 50.5)
     * @param int $offerYears Number of years (3, 4, or 5)
     * @param string $offerDetails Year-by-year breakdown (e.g., "500 480 460 440 420")
     * @return bool True if story created, false on error
     * 
     * **Behaviors:**
     * - Gets team's topic ID for categorization
     * - Gets "Contract Extensions" category ID
     * - Increments category counter
     * - Creates news story with title and details
     */
    public function createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);

    /**
     * Creates a news story for a rejected extension
     * 
     * Generates a news article announcing the player has rejected an extension
     * offer from the team.
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years (3, 4, or 5)
     * @return bool True if story created, false on error
     */
    public function createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);

    /**
     * Retrieves player preferences and info
     * 
     * Gets the full player record including free agency preferences used
     * in extension evaluation calculations.
     * 
     * @param string $playerName Player name for lookup
     * @return array|null Player info array or null if not found
     * 
     * **Return Fields (relevant for extensions):**
     * - Free agency preference fields (winner, tradition, loyalty, playing_time)
     * - Contract fields (cy, cy1-cy6, cyt)
     * - Experience and bird years
     */
    public function getPlayerPreferences($playerName);

    /**
     * Retrieves player's current contract information
     * 
     * Gets contract details needed for extension processing, including
     * the current year salary calculation.
     * 
     * @param string $playerName Player name for lookup
     * @return array|null Contract info with keys:
     *   - 'cy': int - Current contract year
     *   - 'cy1'-'cy6': int - Salary for each year
     *   - 'currentSalary': int - Calculated current year salary
     * 
     * **Behaviors:**
     * - Calculates currentSalary based on cy field
     * - Returns null if player not found
     */
    public function getPlayerCurrentContract($playerName);

    /**
     * Process a complete accepted extension workflow
     * 
     * Convenience method that executes all steps for an accepted extension:
     * updates contract, marks extension used, and creates news story.
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param array $offer Offer array with year1-year5
     * @param int $currentSalary Current salary
     * @return array ['success' => bool]
     */
    public function processAcceptedExtension($playerName, $teamName, $offer, $currentSalary);

    /**
     * Process a complete rejected extension workflow
     * 
     * Convenience method that creates the rejection news story.
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param array $offer Offer array with year1-year5
     * @return array ['success' => bool]
     */
    public function processRejectedExtension($playerName, $teamName, $offer);
}
