<?php

declare(strict_types=1);

namespace Rivalries;

use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;
use Rivalries\Contracts\RivalryScorerInterface;
use Security\HtmlSanitizer;

/**
 * RivalriesView - HTML for the league, pair, and GM rivalry pages.
 *
 * Every dynamic string goes through HtmlSanitizer::e(); every href value is
 * urlencoded first. Uses only existing design-system classes.
 *
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 */
final class RivalriesView implements RivalriesViewInterface
{
    private const BASE = 'modules.php?name=Rivalries';

    /**
     * @see RivalriesViewInterface::renderEmpty()
     */
    public function renderEmpty(string $message): string
    {
        return '<div class="ibl-empty-state">' . HtmlSanitizer::e($message) . '</div>';
    }

    /**
     * @see RivalriesViewInterface::renderIndex()
     */
    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm): string
    {
        return '<h1 class="ibl-title">Rivalries</h1>'
            . '<div class="ibl-grid ibl-grid--2col">'
            . '<section class="ibl-card"><h2>Top franchise rivalries</h2>'
            . $this->renderRankTable($league['franchises'], 'teams', $userTeamKey, 'No pair has met 10 times yet.')
            . '</section>'
            . '<section class="ibl-card"><h2>Top GM rivalries</h2>'
            . $this->renderRankTable($league['gms'], 'gms', $userGm, 'No pair has met 10 times yet.')
            . '</section>'
            . '</div>';
    }

    /**
     * @see RivalriesViewInterface::renderGm()
     */
    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm): string
    {
        return '<h1 class="ibl-title">' . HtmlSanitizer::e($gmName) . "'s rivals</h1>"
            . $this->renderRankTable($rivals, 'gms', $userGm, 'This general manager has no rival with 10 or more games yet.');
    }

    /**
     * @see RivalriesViewInterface::renderPair()
     */
    public function renderPair(?array $page, string $kind): string
    {
        if ($page === null) {
            return $this->renderEmpty('These two have never played each other.');
        }

        $labels = [$page['a'] => $page['a_label'], $page['b'] => $page['b_label']];
        $sideLabel = static fn (string $side): string => $labels[$side] ?? $side;

        $html = '<h1 class="ibl-title">' . HtmlSanitizer::e($page['a_label']) . ' vs ' . HtmlSanitizer::e($page['b_label']) . '</h1>';

        $html .= '<section class="ibl-card">';
        if ($page['kind'] === 'gms') {
            $html .= '<p>' . $this->gmLink($page['a'], $page['a_label']) . ' vs ' . $this->gmLink($page['b'], $page['b_label']) . '</p>';
        }
        $html .= '<p>All-time: ' . $page['a_wins'] . '-' . $page['b_wins'] . ' (' . $page['games'] . ' games)</p>';
        $html .= '<p>Playoffs: ' . $page['playoff_a_wins'] . '-' . $page['playoff_b_wins'] . '</p>';
        if ($page['streak'] !== null) {
            $html .= '<p>' . HtmlSanitizer::e($sideLabel($page['streak']['side'])) . ' has won ' . $page['streak']['length'] . ' straight</p>';
        }
        $html .= '<p>Score: <span class="ibl-badge">' . $page['score'] . '</span></p>';
        if (!$page['qualifies']) {
            $html .= '<p>Fewer than 10 meetings, so this pair is not ranked as a rivalry.</p>';
        }
        $html .= '</section>';

        $html .= '<section class="ibl-card"><h2>Playoff series</h2>';
        if ($page['series'] === []) {
            $html .= $this->renderEmpty('No playoff series between them.');
        } else {
            $rows = '';
            foreach ($page['series'] as $s) {
                $rows .= '<tr><td>' . $s['year'] . '</td><td>Round ' . $s['round'] . '</td><td>'
                    . HtmlSanitizer::e($sideLabel($s['winner_side'])) . '</td><td>'
                    . $s['winner_games'] . '-' . $s['loser_games'] . '</td></tr>';
            }
            $html .= $this->table(['Year', 'Round', 'Winner', 'Result'], $rows);
        }
        $html .= '</section>';

        $html .= '<div class="ibl-grid ibl-grid--2col">'
            . '<section class="ibl-card"><h2>Closest games</h2>' . $this->gamesTable($page['closest']) . '</section>'
            . '<section class="ibl-card"><h2>Biggest blowouts</h2>' . $this->gamesTable($page['blowouts']) . '</section>'
            . '</div>';

        $totals = '';
        foreach ($page['totals'] as $t) {
            $totals .= '<tr><td class="ibl-player-cell">' . $this->playerLink($t['pid'], $t['name']) . '</td><td>'
                . HtmlSanitizer::e($sideLabel($t['side'])) . '</td><td>' . $t['games'] . '</td><td>' . $t['total_points'] . '</td></tr>';
        }
        $highs = '';
        foreach ($page['highs'] as $h) {
            $highs .= '<tr><td class="ibl-player-cell">' . $this->playerLink($h['pid'], $h['name']) . '</td><td>'
                . HtmlSanitizer::e($sideLabel($h['side'])) . '</td><td>' . $h['points'] . '</td><td>'
                . HtmlSanitizer::e($h['game_date']) . '</td></tr>';
        }
        $html .= '<div class="ibl-grid ibl-grid--2col">'
            . '<section class="ibl-card"><h2>Top scorers in the matchup</h2>' . $this->table(['Player', 'Side', 'Games', 'Points'], $totals) . '</section>'
            . '<section class="ibl-card"><h2>Best single games</h2>' . $this->table(['Player', 'Side', 'Points', 'Date'], $highs) . '</section>'
            . '</div>';

        return $html;
    }

    /**
     * @param list<ScoredRivalry> $rows
     * @param 'teams'|'gms' $kind
     */
    private function renderRankTable(array $rows, string $kind, ?string $highlightKey, string $emptyMessage): string
    {
        if ($rows === []) {
            return $this->renderEmpty($emptyMessage);
        }

        $body = '';
        foreach ($rows as $i => $r) {
            $highlight = $highlightKey !== null && ($r['a'] === $highlightKey || $r['b'] === $highlightKey);
            $body .= '<tr' . ($highlight ? ' class="user-team-row"' : '') . '>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td><a href="' . HtmlSanitizer::e($this->pairHref($kind, $r['a'], $r['b'])) . '">'
                . HtmlSanitizer::e($r['a_label']) . ' vs ' . HtmlSanitizer::e($r['b_label']) . '</a></td>'
                . '<td>' . $r['a_wins'] . '-' . $r['b_wins'] . '</td>'
                . '<td>' . $r['games'] . '</td>'
                . '<td><span class="ibl-badge">' . $r['score'] . '</span></td>'
                . '</tr>';
        }

        return $this->table(['Rank', 'Rivalry', 'Record', 'Games', 'Score'], $body);
    }

    /**
     * @param list<GameRow> $games
     */
    private function gamesTable(array $games): string
    {
        $rows = '';
        foreach ($games as $g) {
            $rows .= '<tr><td>' . HtmlSanitizer::e($g['game_date']) . '</td>'
                . '<td class="ibl-team-cell">' . HtmlSanitizer::e($g['visitor_label']) . '</td>'
                . '<td>' . $g['visitor_points'] . '-' . $g['home_points'] . '</td>'
                . '<td class="ibl-team-cell">' . HtmlSanitizer::e($g['home_label']) . '</td></tr>';
        }

        return $this->table(['Date', 'Visitor', 'Score', 'Home'], $rows);
    }

    /**
     * @param list<string> $headers
     */
    private function table(array $headers, string $rows): string
    {
        $head = '';
        foreach ($headers as $h) {
            $head .= '<th>' . HtmlSanitizer::e($h) . '</th>';
        }

        return '<div class="table-scroll-wrapper"><table class="ibl-data-table"><thead><tr>' . $head
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * @param 'teams'|'gms' $kind
     */
    private function pairHref(string $kind, string $a, string $b): string
    {
        if ($kind === 'teams') {
            return self::BASE . '&op=teams&a=' . (int) $a . '&b=' . (int) $b;
        }

        return self::BASE . '&op=gms&a=' . urlencode($a) . '&b=' . urlencode($b);
    }

    private function gmLink(string $gm, string $label): string
    {
        return '<a href="' . HtmlSanitizer::e(self::BASE . '&op=gm&gm=' . urlencode($gm)) . '">' . HtmlSanitizer::e($label) . '</a>';
    }

    private function playerLink(int $pid, string $name): string
    {
        return '<a href="' . HtmlSanitizer::e('modules.php?name=Player&pa=showpage&pid=' . $pid) . '">' . HtmlSanitizer::e($name) . '</a>';
    }
}
