<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering Free Agency offer form components
 *
 * Renders self-contained HTML elements for contract offer forms including
 * ratings, demands, input fields, and quick-offer buttons (max contract, MLE, LLE, vet min).
 * All methods return flex-based layouts rather than table cells.
 */
interface FreeAgencyFormComponentsInterface
{
    /**
     * Render player ratings as an .ibl-data-table
     *
     * Outputs a compact data table showing player's statistical ratings:
     * - Shooting ratings: 2ga, 2gp, fta, ftp, 3ga, 3gp
     * - Rebounding ratings: orb (offensive), drb (defensive)
     * - Ball handling: ast (assists), stl (steals), tvr (turnovers)
     * - Defensive: blk (blocks), foul (fouls)
     * - Advanced: oo, do, po, to (outside/drive/post/transition offense)
     *             od, dd, pd, td (outside/drive/post/transition defense)
     *
     * @return string Self-contained HTML table with .ibl-data-table class
     */
    public function renderPlayerRatings(): string;

    /**
     * Render player demand display as labeled values
     *
     * Outputs a flex container with labeled year/value pairs for
     * the player's salary demands (dem1-dem6). Years with zero demand are hidden.
     *
     * @param array<string, int> $demands Player demands array with keys dem1-dem6
     *
     * @return string Self-contained HTML flex container with labeled demand values
     */
    public function renderDemandDisplay(array $demands): string;

    /**
     * Render contract offer input fields as a flex row
     *
     * Outputs a flex container with 6 labeled number inputs for entering
     * contract offer amounts for years 1-6.
     * Field names: offeryear1, offeryear2, offeryear3, offeryear4, offeryear5, offeryear6
     *
     * @param array<string, int> $prefills Existing offer amounts to prepopulate (keys: offer1-6)
     *                                      Pass empty array if no existing offer
     *
     * @return string Self-contained HTML flex container with labeled inputs
     */
    public function renderOfferInputs(array $prefills): string;

    /**
     * Render max contract offer buttons
     *
     * Outputs a labeled row of 6 buttons, one for each contract year (1-6).
     * Each button submits a form with the maximum allowed contract values.
     *
     * @param array<int, int> $maxSalaries Max salaries for years 1-6, 0-indexed array
     * @param int $birdYears Years of Bird Rights (0=no rights, 1-6=years with team)
     *
     * @return string Self-contained HTML with label and flex row of button forms
     */
    public function renderMaxContractButtons(array $maxSalaries, int $birdYears = 0): string;

    /**
     * Render exception offer buttons
     *
     * Outputs a labeled row with button(s) for the specified exception type.
     *
     * Exception types:
     * - 'MLE': Mid-Level Exception (1-6 years available)
     * - 'LLE': Lower-Level Exception (1-year only)
     * - 'VET': Veteran Minimum (1-year only)
     *
     * @param string $exceptionType Exception type: 'MLE', 'LLE', or 'VET'
     *
     * @return string Self-contained HTML with label and flex row of button forms
     */
    public function renderExceptionButtons(string $exceptionType): string;
}
