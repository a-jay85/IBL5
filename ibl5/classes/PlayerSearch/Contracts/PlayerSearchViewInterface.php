<?php

declare(strict_types=1);

namespace PlayerSearch\Contracts;

use Player\PlayerData;

/**
 * Interface for player search view rendering
 * 
 * Defines the contract for rendering player search form and results.
 * All output is properly escaped to prevent XSS attacks using htmlspecialchars().
 * Uses output buffering pattern for clean HTML generation.
 */
interface PlayerSearchViewInterface
{
    /**
     * Render the search form with all filter fields
     * 
     * The form submits to modules.php?name=Player_Search via POST.
     * All input values are repopulated with htmlspecialchars() escaping.
     * 
     * Form includes:
     * - Basics: name (text), position (dropdown), include retirees (dropdown)
     * - Years: age (text), experience range, bird years range
     * - Statistical Ratings: 2ga/2gp/fta/ftp/3ga/3gp/orb/drb/ast/stl/blk/tvr/foul (text fields)
     * - Offensive/Defensive Ratings: oo/do/po/to/od/dd/pd/td (text fields)
     * - Misc Attributes: talent/skill/intangibles/clutch/consistency/college (text fields)
     * 
     * The form includes JavaScript to reset all fields via resetPlayerSearch() function.
     * All numeric input fields accept >= comparison (except age which uses <=).
     * Name and college fields support case-insensitive partial matching with LIKE.
     * Position field uses dropdown with whitelist from JSB::PLAYER_POSITIONS.
     * 
     * @param array<string, mixed> $params Current filter values for repopulating form fields
     *                                      Keys: all 35 search parameters (pos, age, talent, skill, etc.)
     *                                      Values: user-provided filter values (strings/integers)
     *                                      Null values default to empty string '' in form
     * 
     * @return string Complete HTML form as string (not printed)
     *                 Ready to be embedded in page output
     *                 Uses output buffering to capture HTML
     */
    public function renderSearchForm(array $params): string;

    /**
     * Render the results table header row
     * 
     * Outputs the <table> tag and column headers for the sortable results table.
     * The table class="sortable" enables JavaScript sorting via sorttable.js plugin.
     * 
     * Columns (in order):
     * - Pos: Player position
     * - Player: Clickable link to player page
     * - Age: Years old
     * - Team: Clickable link to team page
     * - Exp: Years of experience
     * - Bird: Bird years remaining
     * - Statistical ratings: 2ga/2gp/fta/ftp/3ga/3gp/orb/drb/ast/stl/tvr/blk/foul
     * - Offensive/Defensive ratings: oo/do/po/to/od/dd/pd/td
     * - Misc ratings: Talent/Skill/Intangibles/Clutch/Consistency
     * - College: College name
     * 
     * @return string HTML table header (opening <table> tag through </tr>)
     *                Ready for player rows to be appended
     *                Uses output buffering to capture HTML
     */
    public function renderTableHeader(): string;

    /**
     * Render a single player row in the results table
     * 
     * Outputs a <tr> row with the player's all statistics in corresponding columns.
     * Alternating row colors: white (#ffffff) for odd rows, light gray (#e6e7e2) for even rows.
     * 
     * Retired players display differently:
     * - Show position and name (linked to player page)
     * - Show all middle columns as "--- Retired ---"
     * - Show college name
     * - No statistics displayed for retired players
     * 
     * Active players show:
     * - Position and name (linked to player page)
     * - Age, team (linked), experience, bird years
     * - All statistical and rating values right-aligned
     * - College name at end
     * 
     * All string output (name, team, college) is escaped with htmlspecialchars().
     * All numeric values output as-is (no escaping needed).
     * Links use href="modules.php?name=Player&amp;pa=showpage&amp;pid=PID" (note &amp;)
     * Team links use href="team.php?tid=TID"
     * 
     * @param PlayerData $player Player object with all statistics and attributes
     * @param int $rowIndex Zero-based row index (used for alternating background colors)
     * 
     * @return string HTML table row (<tr>...</tr>) as string
     *                Ready to be appended after table header
     *                Uses output buffering to capture HTML
     */
    public function renderPlayerRow(PlayerData $player, int $rowIndex): string;

    /**
     * Render the table closing tag
     * 
     * Simply closes the <table> tag opened by renderTableHeader().
     * Should be called after all player rows have been rendered.
     * 
     * @return string Closing </table> tag
     */
    public function renderTableFooter(): string;
}
