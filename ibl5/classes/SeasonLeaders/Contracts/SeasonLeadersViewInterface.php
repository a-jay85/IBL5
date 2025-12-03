<?php

namespace SeasonLeaders\Contracts;

/**
 * SeasonLeadersViewInterface - Season leaders HTML rendering
 *
 * Handles HTML rendering for the season leaders page using
 * output buffering pattern for clean, maintainable HTML.
 */
interface SeasonLeadersViewInterface
{
    /**
     * Render the filter form
     *
     * Generates HTML form with dropdowns for team, year, and sort options.
     *
     * @param mixed $teams Teams query result resource
     * @param array $years Array of available years
     * @param array $currentFilters Current filter values with keys:
     *                              - 'team' (int): Selected team ID (0 for all)
     *                              - 'year' (string): Selected year (empty for all)
     *                              - 'sortby' (string): Selected sort option (1-20)
     * @return string HTML form output
     *
     * **Form Fields:**
     * - team: Dropdown with "All" + team list
     * - year: Dropdown with "All" + year list
     * - sortby: Dropdown with sort options (PPG, REB, etc.)
     *
     * **Behaviors:**
     * - Form submits to modules.php?name=Season_Leaders
     * - Pre-selects current filter values
     * - HTML-escapes all values for XSS protection
     */
    public function renderFilterForm($teams, array $years, array $currentFilters): string;

    /**
     * Render the statistics table header
     *
     * Generates table opening tag and header row with column labels.
     *
     * @return string HTML table header
     *
     * **Columns:**
     * Rank, Year, Name, Team, G, Min, fgm, fga, fg%, ftm, fta, ft%,
     * tgm, tga, tg%, orb, reb, ast, stl, to, blk, pf, ppg, qa
     *
     * **Styling:**
     * - Background color: #C2D69A
     * - Right-aligned numeric columns
     */
    public function renderTableHeader(): string;

    /**
     * Render a single player statistics row
     *
     * Generates table row with all player statistics.
     *
     * @param array $stats Formatted player statistics from processPlayerRow()
     * @param int $rank Player's rank in the leaderboard (1-based)
     * @return string HTML table row
     *
     * **Row Contents:**
     * - Rank number with period
     * - Year
     * - Player name linked to player page
     * - Team name linked to team page
     * - All statistical columns
     *
     * **Behaviors:**
     * - Alternating row colors (odd: #DDDDDD, even: #FFFFFF)
     * - All values HTML-escaped
     * - Player link: modules.php?name=Player&pa=showpage&pid=X
     * - Team link: modules.php?name=Team&op=team&teamID=X
     */
    public function renderPlayerRow(array $stats, int $rank): string;

    /**
     * Render the table footer
     *
     * Generates table closing tag.
     *
     * @return string HTML closing </table> tag
     */
    public function renderTableFooter(): string;
}
