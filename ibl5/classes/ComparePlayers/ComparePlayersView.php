<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersViewInterface;
use Utilities\HtmlSanitizer;

/**
 * ComparePlayersView - HTML rendering for player comparison
 *
 * Uses the IBL5 design system with ibl-filter-form for search
 * and ibl-data-table for comparison tables.
 *
 * @see ComparePlayersViewInterface
 */
class ComparePlayersView implements ComparePlayersViewInterface
{
    /**
     * @see ComparePlayersViewInterface::renderSearchForm()
     */
    public function renderSearchForm(array $playerNames): string
    {
        $output = '<h2 class="ibl-title">Compare Players</h2>';

        // Build datalist options for autocomplete
        $datalistHtml = '<datalist id="player-names">';
        foreach ($playerNames as $name) {
            $datalistHtml .= '<option value="' . HtmlSanitizer::safeHtmlOutput(stripslashes($name)) . '">';
        }
        $datalistHtml .= '</datalist>';

        $output .= '<form action="modules.php?name=Compare_Players" method="POST" class="ibl-filter-form">';
        $output .= '<div class="ibl-filter-form__row">';

        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<label class="ibl-filter-form__label" for="Player1">Player 1</label>';
        $output .= '<input type="text" name="Player1" id="Player1" list="player-names" placeholder="Search player..." autocomplete="off">';
        $output .= '</div>';

        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<label class="ibl-filter-form__label" for="Player2">Player 2</label>';
        $output .= '<input type="text" name="Player2" id="Player2" list="player-names" placeholder="Search player..." autocomplete="off">';
        $output .= '</div>';

        $output .= '<button type="submit" class="ibl-filter-form__submit">Compare</button>';

        $output .= '</div>';
        $output .= '</form>';
        $output .= $datalistHtml;

        return $output;
    }

    /**
     * @see ComparePlayersViewInterface::renderComparisonResults()
     */
    public function renderComparisonResults(array $comparisonData): string
    {
        $player1 = $comparisonData['player1'];
        $player2 = $comparisonData['player2'];

        $output = '';
        $output .= $this->renderRatingsTable($player1, $player2);
        $output .= $this->renderSeasonStatsTable($player1, $player2);
        $output .= $this->renderCareerStatsTable($player1, $player2);

        return $output;
    }

    /**
     * Render the Current Ratings comparison table.
     */
    private function renderRatingsTable(array $player1, array $player2): string
    {
        $headers = [
            'Pos', 'Player', 'Team', 'Age',
            '2ga', '2g%', 'fta', 'ft%', '3ga', '3g%',
            'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'foul',
            'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
        ];

        $output = '<h2 class="ibl-title">Current Ratings</h2>';
        $output .= '<div class="table-scroll-wrapper"><div class="table-scroll-container">';
        $output .= '<table class="sortable ibl-data-table responsive-table">';
        $output .= '<thead><tr>';
        foreach ($headers as $h) {
            $output .= '<th>' . HtmlSanitizer::safeHtmlOutput($h) . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        foreach ([$player1, $player2] as $player) {
            $output .= '<tr>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['pos']) . '</td>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['name']) . '</td>';
            $output .= $this->renderTeamCell($player);
            $output .= '<td>' . (int)$player['age'] . '</td>';
            $output .= '<td>' . (int)$player['r_fga'] . '</td>';
            $output .= '<td>' . (int)$player['r_fgp'] . '</td>';
            $output .= '<td>' . (int)$player['r_fta'] . '</td>';
            $output .= '<td>' . (int)$player['r_ftp'] . '</td>';
            $output .= '<td>' . (int)$player['r_tga'] . '</td>';
            $output .= '<td>' . (int)$player['r_tgp'] . '</td>';
            $output .= '<td>' . (int)$player['r_orb'] . '</td>';
            $output .= '<td>' . (int)$player['r_drb'] . '</td>';
            $output .= '<td>' . (int)$player['r_ast'] . '</td>';
            $output .= '<td>' . (int)$player['r_stl'] . '</td>';
            $output .= '<td>' . (int)$player['r_to'] . '</td>';
            $output .= '<td>' . (int)$player['r_blk'] . '</td>';
            $output .= '<td>' . (int)$player['r_foul'] . '</td>';
            $output .= '<td>' . (int)$player['oo'] . '</td>';
            $output .= '<td>' . (int)$player['do'] . '</td>';
            $output .= '<td>' . (int)$player['po'] . '</td>';
            $output .= '<td>' . (int)$player['to'] . '</td>';
            $output .= '<td>' . (int)$player['od'] . '</td>';
            $output .= '<td>' . (int)$player['dd'] . '</td>';
            $output .= '<td>' . (int)$player['pd'] . '</td>';
            $output .= '<td>' . (int)$player['td'] . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div></div>';

        return $output;
    }

    /**
     * Render the Current Season Stats comparison table.
     */
    private function renderSeasonStatsTable(array $player1, array $player2): string
    {
        $headers = [
            'Pos', 'Player', 'Team',
            'g', 'gs', 'min', 'fgm', 'fga', 'ftm', 'fta',
            '3gm', '3ga', 'orb', 'reb', 'ast', 'stl', 'to', 'blk', 'pf', 'pts',
        ];

        $output = '<h2 class="ibl-title">Current Season Stats</h2>';
        $output .= '<div class="table-scroll-wrapper"><div class="table-scroll-container">';
        $output .= '<table class="sortable ibl-data-table responsive-table">';
        $output .= '<thead><tr>';
        foreach ($headers as $h) {
            $output .= '<th>' . HtmlSanitizer::safeHtmlOutput($h) . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        foreach ([$player1, $player2] as $player) {
            $fgm = (int)$player['stats_fgm'];
            $ftm = (int)$player['stats_ftm'];
            $tgm = (int)$player['stats_3gm'];
            $pts = 2 * $fgm + $ftm + $tgm;

            $output .= '<tr>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['pos']) . '</td>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['name']) . '</td>';
            $output .= $this->renderTeamCell($player);
            $output .= '<td>' . (int)$player['stats_gm'] . '</td>';
            $output .= '<td>' . (int)$player['stats_gs'] . '</td>';
            $output .= '<td>' . (int)$player['stats_min'] . '</td>';
            $output .= '<td>' . $fgm . '</td>';
            $output .= '<td>' . (int)$player['stats_fga'] . '</td>';
            $output .= '<td>' . $ftm . '</td>';
            $output .= '<td>' . (int)$player['stats_fta'] . '</td>';
            $output .= '<td>' . $tgm . '</td>';
            $output .= '<td>' . (int)$player['stats_3ga'] . '</td>';
            $output .= '<td>' . (int)$player['stats_orb'] . '</td>';
            $output .= '<td>' . (int)$player['stats_drb'] . '</td>';
            $output .= '<td>' . (int)$player['stats_ast'] . '</td>';
            $output .= '<td>' . (int)$player['stats_stl'] . '</td>';
            $output .= '<td>' . (int)$player['stats_to'] . '</td>';
            $output .= '<td>' . (int)$player['stats_blk'] . '</td>';
            $output .= '<td>' . (int)$player['stats_pf'] . '</td>';
            $output .= '<td>' . $pts . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div></div>';

        return $output;
    }

    /**
     * Render the Career Stats comparison table.
     */
    private function renderCareerStatsTable(array $player1, array $player2): string
    {
        $headers = [
            'Pos', 'Player', 'Team',
            'g', 'min', 'fgm', 'fga', 'ftm', 'fta',
            '3gm', '3ga', 'orb', 'drb', 'reb', 'ast', 'stl', 'to', 'blk', 'pf', 'pts',
        ];

        $output = '<h2 class="ibl-title">Career Stats</h2>';
        $output .= '<div class="table-scroll-wrapper"><div class="table-scroll-container">';
        $output .= '<table class="sortable ibl-data-table responsive-table">';
        $output .= '<thead><tr>';
        foreach ($headers as $h) {
            $output .= '<th>' . HtmlSanitizer::safeHtmlOutput($h) . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        foreach ([$player1, $player2] as $player) {
            $output .= '<tr>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['pos']) . '</td>';
            $output .= '<td>' . HtmlSanitizer::safeHtmlOutput($player['name']) . '</td>';
            $output .= $this->renderTeamCell($player);
            $output .= '<td>' . (int)$player['car_gm'] . '</td>';
            $output .= '<td>' . (int)$player['car_min'] . '</td>';
            $output .= '<td>' . (int)$player['car_fgm'] . '</td>';
            $output .= '<td>' . (int)$player['car_fga'] . '</td>';
            $output .= '<td>' . (int)$player['car_ftm'] . '</td>';
            $output .= '<td>' . (int)$player['car_fta'] . '</td>';
            $output .= '<td>' . (int)$player['car_tgm'] . '</td>';
            $output .= '<td>' . (int)$player['car_tga'] . '</td>';
            $output .= '<td>' . (int)$player['car_orb'] . '</td>';
            $output .= '<td>' . (int)$player['car_drb'] . '</td>';
            $output .= '<td>' . (int)$player['car_reb'] . '</td>';
            $output .= '<td>' . (int)$player['car_ast'] . '</td>';
            $output .= '<td>' . (int)$player['car_stl'] . '</td>';
            $output .= '<td>' . (int)$player['car_to'] . '</td>';
            $output .= '<td>' . (int)$player['car_blk'] . '</td>';
            $output .= '<td>' . (int)$player['car_pf'] . '</td>';
            $output .= '<td>' . (int)$player['car_pts'] . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div></div>';

        return $output;
    }

    /**
     * Render a team cell with team colors and logo.
     */
    private function renderTeamCell(array $player): string
    {
        $tid = (int)($player['tid'] ?? 0);
        $teamName = HtmlSanitizer::safeHtmlOutput($player['teamname'] ?? '');
        $color1 = HtmlSanitizer::safeHtmlOutput($player['color1'] ?? 'FFFFFF');
        $color2 = HtmlSanitizer::safeHtmlOutput($player['color2'] ?? '000000');

        if ($tid === 0) {
            return '<td>Free Agent</td>';
        }

        return '<td class="ibl-team-cell--colored" style="background-color: #' . $color1 . ';">'
            . '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $tid . '" class="ibl-team-cell__name" style="color: #' . $color2 . ';">'
            . '<img src="images/logo/new' . $tid . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
            . '<span class="ibl-team-cell__text">' . $teamName . '</span>'
            . '</a></td>';
    }
}
