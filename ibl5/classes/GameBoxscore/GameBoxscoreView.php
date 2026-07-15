<?php

declare(strict_types=1);

namespace GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreServiceInterface;
use GameBoxscore\Contracts\GameBoxscoreViewInterface;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use UI\TableStyles;

/**
 * View class for rendering a single game's boxscore.
 *
 * Renders the score header, the CSS-only away/home selector (default home),
 * and one sortable stats table per team with a pinned totals row.
 *
 * @phpstan-import-type GameBoxscoreViewModel from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscorePlayerRow from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTeamHeader from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTotals from GameBoxscoreServiceInterface
 *
 * @see GameBoxscoreViewInterface
 */
class GameBoxscoreView implements GameBoxscoreViewInterface
{
    /**
     * Stat column headers, in render order, keyed by view-model key.
     * Pos and Name are rendered separately (they are not numeric).
     *
     * @var array<string, string>
     */
    private const STAT_COLUMNS = [
        'min' => 'Min',
        'fgm' => 'FGM',
        'fga' => 'FGA',
        'ftm' => 'FTM',
        'fta' => 'FTA',
        'tpm' => '3PM',
        'tpa' => '3PA',
        'pts' => 'PTS',
        'orb' => 'ORB',
        'reb' => 'REB',
        'ast' => 'AST',
        'stl' => 'STL',
        'blk' => 'BLK',
        'tov' => 'TOV',
        'pf' => 'PF',
    ];

    /** Pos + Name + the 15 stat columns. */
    private const COLUMN_COUNT = 17;

    /**
     * @see GameBoxscoreViewInterface::render()
     *
     * @param GameBoxscoreViewModel $viewModel
     */
    public function render(array $viewModel): string
    {
        if ($viewModel['found'] !== true) {
            return $this->renderNotFound();
        }

        $output = '<section class="game-boxscore">';
        $output .= $this->renderScoreHeader($viewModel);
        $output .= $this->renderTeamSelector();
        $output .= $this->renderTeamPanel(
            $viewModel['awayTeam'],
            $viewModel['awayPlayers'],
            $viewModel['awayTotals'],
            'away',
        );
        $output .= $this->renderTeamPanel(
            $viewModel['homeTeam'],
            $viewModel['homePlayers'],
            $viewModel['homeTotals'],
            'home',
        );
        $output .= '</section>';

        return $output;
    }

    /**
     * Render the scoreboard: date, game number, both teams and their scores.
     *
     * The team colors are interpolated only through TableStyles::inlineTeamVars,
     * which sanitizes each value down to a hex literal.
     *
     * @param GameBoxscoreViewModel $viewModel
     */
    private function renderScoreHeader(array $viewModel): string
    {
        $away = $viewModel['awayTeam'];
        $home = $viewModel['homeTeam'];

        /** @var string $safeDate */
        $safeDate = HtmlSanitizer::safeHtmlOutput($viewModel['date']);
        $gameNumber = (int) $viewModel['gameOfThatDay'];

        $vars = TableStyles::inlineTeamVars($away['color1'], $home['color1']);

        $output = '<div class="game-boxscore__scoreboard" style="' . $vars . '">';
        $output .= '<div class="game-boxscore__meta">'
            . '<span class="game-boxscore__date">' . $safeDate . '</span>'
            . '<span class="game-boxscore__game-number">Game ' . $gameNumber . '</span>'
            . '</div>';
        $output .= '<div class="game-boxscore__matchup">';
        $output .= $this->renderScoreSide($away, 'away');
        $output .= '<span class="game-boxscore__at">@</span>';
        $output .= $this->renderScoreSide($home, 'home');
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render one side of the scoreboard: logo, city/name and score.
     *
     * @param GameBoxscoreTeamHeader $team
     * @param string $side "away" or "home"
     */
    private function renderScoreSide(array $team, string $side): string
    {
        $teamId = (int) $team['teamId'];
        /** @var string $safeCity */
        $safeCity = HtmlSanitizer::safeHtmlOutput($team['city']);
        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($team['name']);
        $score = (int) $team['score'];

        $output = '<div class="game-boxscore__team game-boxscore__team--' . $side . '">';
        $output .= '<img class="game-boxscore__logo" src="images/logo/new' . $teamId . '.png"'
            . ' alt="' . $safeCity . ' ' . $safeName . ' logo">';
        $output .= '<span class="game-boxscore__team-name">'
            . $safeCity . ' ' . $safeName
            . '</span>';
        $output .= '<span class="game-boxscore__score">' . $score . '</span>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render the CSS-only team selector. Home is checked by default.
     *
     * The radios are siblings of the panels so the :checked ~ panel rules in
     * game-boxscore.css can reveal the matching side without JavaScript.
     */
    private function renderTeamSelector(): string
    {
        $output = '<input class="game-boxscore__radio" type="radio" name="boxscore-team"'
            . ' id="boxscore-team-away">';
        $output .= '<input class="game-boxscore__radio" type="radio" name="boxscore-team"'
            . ' id="boxscore-team-home" checked>';
        $output .= '<div class="game-boxscore__tabs">';
        $output .= '<label class="game-boxscore__tab" for="boxscore-team-away">Away</label>';
        $output .= '<label class="game-boxscore__tab" for="boxscore-team-home">Home</label>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render one team's stats panel.
     *
     * @param GameBoxscoreTeamHeader $team
     * @param list<GameBoxscorePlayerRow> $players
     * @param GameBoxscoreTotals $totals
     * @param string $side "away" or "home"
     */
    private function renderTeamPanel(array $team, array $players, array $totals, string $side): string
    {
        /** @var string $safeCity */
        $safeCity = HtmlSanitizer::safeHtmlOutput($team['city']);
        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($team['name']);

        $output = '<div class="game-boxscore__panel game-boxscore__panel--' . $side . '"'
            . ' data-team-panel="' . $side . '">';
        $output .= '<h2 class="ibl-table-title">' . $safeCity . ' ' . $safeName . '</h2>';
        $output .= '<div class="table-scroll-wrapper"><div class="table-scroll-container">';
        $output .= '<table class="ibl-data-table sortable game-boxscore__table">';
        $output .= $this->renderTableHead();
        $output .= '<tbody>';

        if ($players === []) {
            $output .= '<tr><td colspan="' . self::COLUMN_COUNT . '" class="game-boxscore__empty">'
                . 'No player stats recorded for this game.'
                . '</td></tr>';
        } else {
            foreach ($players as $player) {
                $output .= $this->renderPlayerRow($player);
            }
        }

        $output .= '</tbody>';
        $output .= '<tfoot>' . $this->renderTotalsRow($totals) . '</tfoot>';
        $output .= '</table>';
        $output .= '</div></div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render the fixed 17-column header row. All values are static literals.
     */
    private function renderTableHead(): string
    {
        $output = '<thead><tr><th>Pos</th><th>Name</th>';

        foreach (self::STAT_COLUMNS as $key => $label) {
            $attr = $key === 'pts' ? ' data-col="pts"' : '';
            $output .= '<th' . $attr . '>' . $label . '</th>';
        }

        $output .= '</tr></thead>';

        return $output;
    }

    /**
     * Render one player's stat row.
     *
     * Every stat arrives int-cast from the Service, so it interpolates directly.
     * The name cell comes from PlayerImageHelper, which escapes internally.
     *
     * @param GameBoxscorePlayerRow $player
     */
    private function renderPlayerRow(array $player): string
    {
        /** @var string $safePos */
        $safePos = HtmlSanitizer::safeHtmlOutput($player['pos']);
        $nameCell = PlayerImageHelper::renderPlayerLink((int) $player['pid'], $player['name']);

        $output = '<tr>';
        $output .= '<td>' . $safePos . '</td>';
        $output .= '<td class="player-cell">' . $nameCell . '</td>';

        foreach (array_keys(self::STAT_COLUMNS) as $key) {
            $class = $key === 'pts' ? ' class="game-boxscore__cell--pts"' : '';
            $output .= '<td' . $class . '>' . (int) $player[$key] . '</td>';
        }

        $output .= '</tr>';

        return $output;
    }

    /**
     * Render the totals row. Lives in <tfoot>, which sorttable.js ignores,
     * so it stays pinned when a column is sorted.
     *
     * @param GameBoxscoreTotals $totals
     */
    private function renderTotalsRow(array $totals): string
    {
        $output = '<tr class="game-boxscore__totals"><td>Totals</td><td></td>';

        foreach (array_keys(self::STAT_COLUMNS) as $key) {
            $class = $key === 'pts' ? ' class="game-boxscore__cell--pts"' : '';
            $output .= '<td' . $class . '>' . (int) $totals[$key] . '</td>';
        }

        $output .= '</tr>';

        return $output;
    }

    /**
     * Render the not-found panel.
     *
     * Deliberately echoes back none of the request input — the requested date
     * and game number are never reflected here.
     */
    private function renderNotFound(): string
    {
        return '<div class="ibl-card game-boxscore-not-found">'
            . '<h1 class="ibl-title">Game Not Found</h1>'
            . '<p>No boxscore exists for the requested date and game.</p>'
            . '</div>';
    }
}
