<?php

declare(strict_types=1);

namespace AwardHistory\Contracts;

/**
 * AwardHistoryViewInterface - Contract for player awards view rendering
 * 
 * Defines the HTML rendering methods for the player awards search interface.
 * All methods return HTML strings that can be echoed directly to the page.
 * All output is properly escaped to prevent XSS attacks.
 */
interface AwardHistoryViewInterface
{
    /**
     * Render the search form with current parameter values
     * 
     * Generates the HTML form for searching player awards. The form is
     * pre-populated with the provided parameters to maintain user's selections.
     * 
     * @param array{
     *     name: string|null,
     *     award: string|null,
     *     year: int|null,
     *     sortby: int
     * } $params Current search parameters for form pre-population
     * @return string Complete HTML form including form tags, inputs, and submit button
     * 
     * FORM FIELDS:
     *  - aw_name: Text input for player name (LIKE search)
     *  - aw_Award: Text input for award name (LIKE search)
     *  - aw_year: Text input for year (exact match)
     *  - aw_sortby: Radio buttons for sort option (1=name, 2=award, 3=year)
     * 
     * IMPORTANT BEHAVIORS:
     *  - All output values are HTML escaped
     *  - Form submits to modules.php?name=AwardHistory via POST
     *  - Sort option defaults to 3 (year) if not set
     */
    public function renderSearchForm(array $params): string;

    /**
     * Render the results table header
     * 
     * @return string HTML for the table opening tags and header row
     * 
     * COLUMNS:
     *  - Year
     *  - Player
     *  - Award
     */
    public function renderTableHeader(): string;

    /**
     * Render a single award row in the results table
     * 
     * @param array{year: int, Award: string, name: string} $award Award data
     * @param int $rowIndex Row index for alternating row colors (0-based)
     * @return string HTML table row
     * 
     * IMPORTANT BEHAVIORS:
     *  - All output is HTML escaped
     *  - Row background alternates based on rowIndex (even/odd)
     *  - Year, player name, and award are displayed
     */
    public function renderAwardRow(array $award, int $rowIndex): string;

    /**
     * Render the results table footer
     * 
     * @return string HTML for closing the table
     */
    public function renderTableFooter(): string;
}
