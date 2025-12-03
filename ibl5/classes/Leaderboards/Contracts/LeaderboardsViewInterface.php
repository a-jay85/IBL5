<?php

declare(strict_types=1);

namespace Leaderboards\Contracts;

/**
 * LeaderboardsViewInterface - Leaderboards HTML rendering
 *
 * Handles HTML rendering for the leaderboards page using
 * output buffering pattern for clean, maintainable HTML.
 */
interface LeaderboardsViewInterface
{
    /**
     * Render the filter form
     *
     * Generates HTML form with dropdowns for board type, sort category,
     * active/retired filter, and record limit.
     *
     * @param array $currentFilters Current filter values with keys:
     *                              - 'boards_type' (string): Selected board display name
     *                              - 'sort_cat' (string): Selected sort category display name
     *                              - 'active' (string): '0' include retirees, '1' exclude
     *                              - 'display' (int|string): Record limit
     * @return string HTML form output
     *
     * **Form Fields:**
     * - boards_type: Dropdown with 8 board type options
     * - sort_cat: Dropdown with 19 sort category options
     * - active: Yes/No dropdown for including retirees
     * - display: Number input for record limit
     * - submitted: Hidden field set to "1"
     *
     * **Behaviors:**
     * - Form submits to modules.php?name=Leaderboards
     * - Pre-selects current filter values
     * - HTML-escapes all values for XSS protection
     * - Board types and sort categories from LeaderboardsService
     */
    public function renderFilterForm(array $currentFilters): string;

    /**
     * Render the statistics table header
     *
     * Generates table opening tag, title, and header row with column labels.
     *
     * @return string HTML table header
     *
     * **Columns:**
     * Rank, Name, Games, Minutes, FGM, FGA, FG%, FTM, FTA, FT%,
     * 3GM, 3GA, 3P%, ORB, REB, AST, STL, TVR, BLK, FOULS, PTS
     *
     * **Styling:**
     * - Class: sortable (for JavaScript table sorting)
     * - Center-aligned columns
     * - Title: "Leaderboards Display" in <h2>
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
     * - Rank number
     * - Player name linked to player page (with * if retired)
     * - All statistical columns
     *
     * **Behaviors:**
     * - All columns center-aligned
     * - All values HTML-escaped
     * - Player link: modules.php?name=Player&pa=showpage&pid=X
     */
    public function renderPlayerRow(array $stats, int $rank): string;

    /**
     * Render the table footer
     *
     * Generates table closing tags.
     *
     * @return string HTML closing tags: </table></center></td></tr>
     */
    public function renderTableFooter(): string;
}
