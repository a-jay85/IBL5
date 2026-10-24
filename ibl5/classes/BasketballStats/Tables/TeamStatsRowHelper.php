<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\Stats\PlayerStats;
use Security\HtmlSanitizer;
use Team\Team;
use UI\TeamCellHelper;

/**
 * TeamStatsRowHelper - the HTML fragments shared byte-for-byte across the team stats
 * tables. SeasonAverages, SeasonTotals and Per36Minutes share the head prefix, the
 * player-row prefix and the footer label cells. SplitStats and PeriodAverages share
 * the whole per-game averages <thead> block.
 *
 * Holds no state and never receives a database handle or a logged-in identity;
 * TeamStatsRowHelperTest pins that structurally. Every HTML-escape call sits
 * on the exact value it guards, in the position the three templates used before
 * the split. The HtmlSanitizer::trusted(openTag) preamble stays in each table class.
 * Each builder returns the same bytes the template emitted, including
 * the leading indentation and the trailing newline that PHP's close-tag swallowing
 * produced.
 */
final class TeamStatsRowHelper
{
    /**
     * The `Team` (LeagueStarters only), `Pos`, `Player`, `g` and `gs` header cells.
     */
    public static function headPrefix(string $moduleName): string
    {
        $team = ($moduleName === "LeagueStarters") ? "            <th>Team</th>\n" : '';

        return $team
            . "            <th>Pos</th>\n"
            . "            <th class=\"sticky-col\">Player</th>\n"
            . "            <th>g</th>\n"
            . "            <th>gs</th>\n";
    }

    /**
     * `<tr>` plus the team cell (LeagueStarters only), position, player, games and games-started cells.
     *
     * @param list<int> $starterPids
     */
    public static function playerRowPrefix(Player $player, PlayerStats $playerStats, array $starterPids, string $moduleName): string
    {
        $teamCell = '';
        if ($moduleName === "LeagueStarters") {
            $teamCell = TeamCellHelper::renderTeamCellOrFreeAgent($player->getTeamid() ?? 0, $player->getTeamName() ?? '', $player->getTeamColor1() ?? 'FFFFFF', $player->getTeamColor2() ?? '000000');
        }

        return "        <tr>\n"
            . $teamCell
            . '            <td>' . HtmlSanitizer::e($player->getPosition() ?? '') . "</td>\n"
            . '            ' . PlayerImageHelper::renderPlayerCell($player->getPlayerID() ?? 0, $player->getDecoratedName() ?? '', $starterPids, $player->getNameStatusClass())
            . '            <td>' . $playerStats->seasonGamesPlayed . "</td>\n"
            . '            <td>' . $playerStats->seasonGamesStarted . "</td>\n";
    }

    /**
     * `<tr>` plus the label cell (`<team> Offense` / `<team> Defense`), the two games cells and the empty separator cell.
     *
     * @param 'Offense'|'Defense' $label
     */
    public static function footerLabelCells(Team $team, string $label, int $games, string $moduleName): string
    {
        $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;

        return "        <tr>\n"
            . '            <td colspan="' . $labelColspan . '">' . HtmlSanitizer::e($team->name) . ' ' . $label . "</td>\n"
            . '            <td>' . $games . "</td>\n"
            . '            <td>' . $games . "</td>\n"
            . "            <td class=\"sep-r-team\"></td>\n";
    }

    /**
     * The per-game averages `<thead>` block (21 header cells) shared by SplitStats and
     * PeriodAverages, byte-for-byte the former inline template.
     */
    public static function perGameAveragesHead(): string
    {
        return "    <thead>\n"
            . "        <tr>\n"
            . "            <th>Pos</th>\n"
            . '            <th class="sticky-col">Player</th>' . "\n"
            . "            <th>g</th>\n"
            . '            <th class="sep-r-team">min</th>' . "\n"
            . "            <th>fgm</th>\n"
            . "            <th>fga</th>\n"
            . "            <th>fgp</th>\n"
            . "            <th>ftm</th>\n"
            . "            <th>fta</th>\n"
            . "            <th>ftp</th>\n"
            . "            <th>3gm</th>\n"
            . "            <th>3ga</th>\n"
            . '            <th class="sep-r-team">3gp</th>' . "\n"
            . "            <th>orb</th>\n"
            . "            <th>reb</th>\n"
            . "            <th>ast</th>\n"
            . "            <th>stl</th>\n"
            . "            <th>to</th>\n"
            . "            <th>blk</th>\n"
            . "            <th>pf</th>\n"
            . "            <th>pts</th>\n"
            . "        </tr>\n"
            . "    </thead>\n";
    }
}
