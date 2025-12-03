<?php

namespace Voting\Contracts;

/**
 * VotingResultsControllerInterface - Voting results page coordination
 *
 * Coordinates fetching and rendering of voting results based on the
 * season phase (All-Star vs End-of-Year awards).
 */
interface VotingResultsControllerInterface
{
    /**
     * Renders the appropriate voting results for the active season phase
     *
     * Automatically selects between All-Star and End-of-Year views
     * based on current season phase.
     *
     * @return string HTML output for the voting results page
     *
     * **Behaviors:**
     * - During Regular Season: Renders All-Star voting results
     * - During other phases: Renders End-of-Year awards results
     */
    public function render(): string;

    /**
     * Renders All-Star voting results regardless of season phase
     *
     * Force renders All-Star results even if not in regular season.
     *
     * @return string HTML output for All-Star voting tables
     *
     * **Categories Displayed:**
     * - Eastern Conference Frontcourt
     * - Eastern Conference Backcourt
     * - Western Conference Frontcourt
     * - Western Conference Backcourt
     */
    public function renderAllStarView(): string;

    /**
     * Renders end-of-year awards voting results regardless of season phase
     *
     * Force renders EOY results even if in regular season.
     *
     * @return string HTML output for End-of-Year awards tables
     *
     * **Categories Displayed:**
     * - Most Valuable Player
     * - Sixth Man of the Year
     * - Rookie of the Year
     * - GM of the Year
     */
    public function renderEndOfYearView(): string;
}
