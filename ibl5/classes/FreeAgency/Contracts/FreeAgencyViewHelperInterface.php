<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering Free Agency offer form components
 * 
 * Renders HTML for contract offer forms including ratings, demands,
 * input fields, and quick-offer buttons (max contract, MLE, LLE, vet min).
 */
interface FreeAgencyViewHelperInterface
{
    /**
     * Render player ratings table
     * 
     * Outputs a horizontal table (center-aligned) showing player's statistical ratings:
     * - Shooting ratings: 2ga, 2gp, fta, ftp, 3ga, 3gp
     * - Rebounding ratings: orb (offensive), drb (defensive)
     * - Ball handling: ast (assists), stl (steals), tvr (turnovers)
     * - Defensive: blk (blocks), foul (fouls)
     * - Advanced: oo, do, po, to (outside/drive/post/transition offense)
     *             od, dd, pd, td (outside/drive/post/transition defense)
     * 
     * All values are pulled from player object and escaped with htmlspecialchars().
     * 
     * @return string HTML table (center-aligned, no borders)
     */
    public function renderPlayerRatings(): string;

    /**
     * Render player demand display
     * 
     * Outputs table cells showing player's salary demands for all 6 contract years.
     * Demands are displayed as a horizontal row of 6 values (dem1-dem6).
     * 
     * These are the base demands stated by the player, before team modifiers
     * are applied (see FreeAgencyDemandCalculator::calculatePerceivedValue).
     * 
     * @param array<string, int> $demands Player demands array with keys dem1-dem6
     * 
     * @return string HTML table cells (6 cells, one per year)
     */
    public function renderDemandDisplay(array $demands): string;

    /**
     * Render contract offer input fields
     * 
     * Outputs 6 text input fields for entering contract offer amounts for years 1-6.
     * Field names: offeryear1, offeryear2, offeryear3, offeryear4, offeryear5, offeryear6
     * 
     * **Prefilling existing offers**:
     * - If amending an existing offer, pre-populate fields with existing offer amounts
     * - Passed via $prefills array with keys offer1-6
     * - If no prefills, all fields default to empty (0)
     * 
     * Input validation (all performed server-side):
     * - Min value: 0
     * - Max value: varies (checked against hard cap in validator)
     * - Type: numeric only
     * 
     * @param array<string, int> $prefills Existing offer amounts to prepopulate (keys: offer1-6)
     *                                      Pass empty array if no existing offer
     * 
     * @return string HTML table cells (6 cells with input fields, one per year)
     */
    public function renderOfferInputs(array $prefills): string;

    /**
     * Render max contract offer buttons (one button per year)
     * 
     * Outputs a table row with 6 buttons, one for each contract year (1-6).
     * Each button pre-fills the offer form with the maximum allowed contract
     * values, using raise percentages based on bird rights.
     * 
     * **Button behavior**:
     * - Click button to populate offer fields with max salaries
     * - Year 1 = max contract value (based on years of service)
     * - Year 2 = Year 1 + max raise amount
     * - Year 3 = Year 2 + max raise amount (etc.)
     * - Max raise percentage: 10% standard, 12.5% with Bird Rights
     * 
     * **Button text**: "Max Yr 1", "Max Yr 2", etc.
     * 
     * @param array<int, int> $maxSalaries Max salaries for years 1-6, 0-indexed array
     *                                      Index 0 = year 1, etc.
     * @param int $birdYears Years of Bird Rights (0=no rights, 1-6=years with team)
     * 
     * @return string HTML table row with 6 buttons
     */
    public function renderMaxContractButtons(array $maxSalaries, int $birdYears = 0): string;

    /**
     * Render exception offer buttons
     * 
     * Outputs table cells with button(s) for the specified exception type.
     * Each exception can have 1-6 year options (where applicable).
     * 
     * **Exception types**:
     * - 'MLE': Mid-Level Exception (1-6 years available)
     *   Button per year, each offering standard MLE salary for that year count
     * - 'LLE': Lower-Level Exception (1-year only)
     *   Single button offering LLE salary
     * - 'VET': Veteran Minimum (1-year only)
     *   Single button offering veteran minimum salary
     * 
     * **Button behavior**:
     * - Click to populate offer form with exception salary values
     * - Sets hidden offerType field to corresponding value (handled by JavaScript)
     * - For multi-year exceptions (MLE), each button is for different year count
     * 
     * @param string $exceptionType Exception type: 'MLE', 'LLE', or 'VET'
     * 
     * @return string HTML table cells with button(s) for the exception type
     */
    public function renderExceptionButtons(string $exceptionType): string;
}
