<?php

declare(strict_types=1);

namespace CareerLeaderboards\Contracts;

/**
 * CareerLeaderboardsViewInterface - Career Leaderboards HTML rendering
 *
 * Handles HTML rendering for the leaderboards page using
 * output buffering pattern for clean, maintainable HTML.
 *
 * @phpstan-import-type FormattedPlayerStats from CareerLeaderboardsServiceInterface
 * @phpstan-type FilterParams array{phase?: string, mode?: string, sortby?: string, retirees?: bool, display?: int|string}
 */
interface CareerLeaderboardsViewInterface
{
    /**
     * Render the filter form
     *
     * Generates HTML form with a phase select, totals/averages segmented
     * control, sort select, retirees switch, and results limit.
     *
     * @param FilterParams $currentFilters Current filter values
     * @return string HTML form output
     *
     * **Form Fields:**
     * - phase: Dropdown of 7 phases
     * - mode: Totals/Averages radios (averages disabled for phases without an averages table)
     * - sortby: Dropdown of sort options (percentage options disabled on totals)
     * - retirees: Switch, checked = include retirees
     * - display: Number input for results limit
     * - submitted: Hidden field set to "1"
     *
     * **Behaviors:**
     * - Form submits to modules.php?name=Leaderboards&tab=career
     * - Pre-selects current filter values
     * - HTML-escapes all values for XSS protection
     * - Phases and sort options from CareerLeaderboardsService
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
     * 3GM, 3GA, 3P%, ORB, DRB, REB, AST, STL, TVR, BLK, FOULS, PTS
     *
     * **Styling:**
     * - Class: sortable (for JavaScript table sorting)
     * - Center-aligned columns
     * - Title: "Career Leaderboards Display" in <h2>
     */
    public function renderTableHeader(): string;

    /**
     * Render a single player statistics row
     *
     * Generates table row with all player statistics.
     *
     * @param FormattedPlayerStats $stats Formatted player statistics from processPlayerRow()
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
