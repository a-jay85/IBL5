<?php

declare(strict_types=1);

namespace Team\Views;

use BasketballStats\StatsFormatter;

/**
 * Pure renderer for franchise history: regular season, HEAT, and playoffs.
 *
 * @phpstan-import-type WinLossHistoryData from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossRecord from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffData from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffRoundData from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffResultItem from \Team\Contracts\TeamServiceInterface
 */
class FranchiseHistoryView
{
    /**
     * Render regular season win/loss history.
     *
     * @param WinLossHistoryData $data
     */
    public function renderRegularSeason(array $data): string
    {
        return $this->renderWinLossHistory($data);
    }

    /**
     * Render HEAT tournament history.
     *
     * @param WinLossHistoryData $data
     */
    public function renderHeat(array $data): string
    {
        return $this->renderWinLossHistory($data);
    }

    /**
     * Render playoff results grouped by round.
     *
     * @param PlayoffData $data
     */
    public function renderPlayoffs(array $data): string
    {
        $output = '';

        foreach ($data['rounds'] as $round) {
            if ($round['results'] === []) {
                continue;
            }

            $roundName = $round['name'];
            $gamePct = StatsFormatter::formatPercentage($round['gameWins'], $round['gameWins'] + $round['gameLosses']);
            $seriesPct = StatsFormatter::formatPercentage($round['seriesWins'], $round['seriesWins'] + $round['seriesLosses']);

            $output .= "<div class=\"team-card__body team-card__body--tight\">"
                . "<strong class=\"team-card__section-label\">$roundName</strong>"
                . '</div>'
                . '<ul class="team-history-list team-history-list--padded">';

            foreach ($round['results'] as $result) {
                $year = $result['year'];
                $winner = $result['winner'];
                $loser = $result['loser'];
                $winnerGames = $result['winnerGames'];
                $loserGames = $result['loserGames'];
                $cssClass = $result['isWin'] ? ' playoff-result--win' : '';

                $output .= "<li class=\"playoff-result$cssClass\">$year &mdash; $winner $winnerGames, $loser $loserGames</li>";
            }

            $output .= '</ul>'
                . "<div class=\"team-card__footer\">Games: {$round['gameWins']}-{$round['gameLosses']} ($gamePct) &middot; Series: {$round['seriesWins']}-{$round['seriesLosses']} ($seriesPct)</div>";
        }

        $pwlpct = StatsFormatter::formatPercentage($data['totalGameWins'], $data['totalGameWins'] + $data['totalGameLosses']);
        $swlpct = StatsFormatter::formatPercentage($data['totalSeriesWins'], $data['totalSeriesWins'] + $data['totalSeriesLosses']);

        $output .= "<div class=\"team-card__footer team-card__footer--bold\">Post-Season: {$data['totalGameWins']}-{$data['totalGameLosses']} ($pwlpct) &middot; Series: {$data['totalSeriesWins']}-{$data['totalSeriesLosses']} ($swlpct)</div>";

        return $output;
    }

    /**
     * Render a win/loss history list with best-record bolding and totals footer.
     *
     * @param WinLossHistoryData $data
     */
    private function renderWinLossHistory(array $data): string
    {
        $teamid = $data['teamid'];
        $output = '<ul class="team-history-list">';

        foreach ($data['records'] as $record) {
            $label = $record['label'];
            $wins = $record['wins'];
            $losses = $record['losses'];
            $urlYear = $record['urlYear'];
            $winpct = StatsFormatter::formatPercentage($wins, $wins + $losses);
            $boldOpen = $record['isBest'] ? '<strong>' : '';
            $boldClose = $record['isBest'] ? '</strong>' : '';
            $output .= "<li>{$boldOpen}<a href=\"./modules.php?name=Team&amp;op=team&amp;teamid=$teamid&amp;yr=$urlYear\">$label</a> <span class=\"record\">$wins-$losses ($winpct)</span>{$boldClose}</li>";
        }

        $output .= '</ul>';

        $totalWins = $data['totalWins'];
        $totalLosses = $data['totalLosses'];
        $wlpct = StatsFormatter::formatPercentage($totalWins, $totalWins + $totalLosses);
        $output .= "<div class=\"team-card__footer\">Totals: $totalWins-$totalLosses ($wlpct)</div>";

        return $output;
    }
}
