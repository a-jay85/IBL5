<?php

declare(strict_types=1);

namespace Rivalries;

use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;
use Rivalries\Contracts\RivalryScorerInterface;
use Player\PlayerImageHelper;
use Player\Views\TeamColorHelper;
use Security\HtmlSanitizer;
use UI\TableStyles;
use UI\TeamCellHelper;
use Utilities\BoxScoreUrlBuilder;

/**
 * RivalriesView - HTML for the league, pair, and GM rivalry pages.
 *
 * Every dynamic string goes through HtmlSanitizer::e(); every href value is
 * urlencoded first. Uses only existing design-system classes.
 *
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type SeriesRow from RivalriesRepositoryInterface
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
    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm, array $teamSideColors = [], array $gmSideColors = []): string
    {
        // These tables span the whole league, so their strips take the site's
        // card header in place of a team color.
        return '<h1 class="ibl-title">Rivalries</h1>'
            . $this->renderRankTable($league['franchises'], 'teams', $userTeamKey, 'No pair has met 10 times yet.', 'Top franchise rivalries', $teamSideColors, 'ibl-card__header')
            . $this->renderRankTable($league['gms'], 'gms', $userGm, 'No pair has met 10 times yet.', 'Top GM rivalries', $gmSideColors, 'ibl-card__header');
    }

    /**
     * @see RivalriesViewInterface::renderGm()
     */
    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm, array $sideColors = []): string
    {
        return '<h1 class="ibl-title">' . HtmlSanitizer::e($gmName) . "'s rivals</h1>"
            . $this->renderRankTable($rivals, 'gms', $userGm, 'This general manager has no rival with 10 or more games yet.', null, $sideColors);
    }

    /**
     * @see RivalriesViewInterface::renderTeam()
     */
    public function renderTeam(
        string $teamLabel,
        array $teamRivals,
        array $teamSideColors,
        ?string $gmName,
        array $gmRivals,
        array $gmSideColors,
        ?string $userTeamKey,
        ?string $userGm,
        ?string $captionColor = null,
    ): string {
        $gmTable = $gmName === null
            ? $this->renderRankTable([], 'gms', null, 'This franchise has no general manager right now.', 'GM Rivals')
            : $this->renderRankTable($gmRivals, 'gms', $userGm, 'This general manager has no rival with 10 or more games yet.', $gmName . "'s Rivals", $gmSideColors);

        $tables = $this->renderRankTable($teamRivals, 'teams', $userTeamKey, 'This franchise has no rival with 10 or more games yet.', $teamLabel . "'s Rivals", $teamSideColors)
            . $gmTable;

        // The caption strips take the page franchise's color, like the Team page's view dropdown.
        if ($captionColor !== null) {
            $tables = '<div class="rivalries-team-colored" style="--team-tab-bg-color: #' . TableStyles::sanitizeColor($captionColor) . ';">'
                . $tables . '</div>';
        }

        return '<h1 class="ibl-title">Rivalries</h1>' . $tables;
    }

    /**
     * @see RivalriesViewInterface::renderPair()
     */
    public function renderPair(?array $page, string $kind, ?array $colors = null, array $teamColors = [], ?string $viewerKey = null, array $shortLabels = []): string
    {
        if ($page === null) {
            return $this->renderEmpty('These two have never played each other.');
        }

        $labels = [$page['a'] => $page['a_label'], $page['b'] => $page['b_label']];
        $sideLabel = static fn (string $side): string => $labels[$side] ?? $side;
        $viewer = $viewerKey !== null && isset($labels[$viewerKey]) ? $viewerKey : null;
        $isTeams = $page['kind'] === 'teams';
        // Table team cells carry nicknames only, like the game tables. GM names
        // never wrap: a wrapping name lets responsive-tables.js settle the table
        // as either a scroll box or a wrapped fit, depending on resize timing.
        $sideCell = function (string $side) use ($isTeams, $sideLabel, $shortLabels, $teamColors): string {
            return $isTeams
                ? $this->teamCell((int) $side, $shortLabels[$side] ?? $sideLabel($side), $teamColors)
                : '<td class="rivalries-nowrap">' . HtmlSanitizer::e($sideLabel($side)) . '</td>';
        };
        $sideHeader = $isTeams ? 'Team' : 'GM';

        // Franchise pairs show the Team page banners; the alt text keeps the heading readable.
        $html = $isTeams
            ? '<h1 class="rivalries-banner-title">' . $this->teamBanner((int) $page['a'], $page['a_label'], $teamColors)
                . '<span class="rivalries-banner-vs">vs</span>' . $this->teamBanner((int) $page['b'], $page['b_label'], $teamColors) . '</h1>'
            : '<h1 class="ibl-title">' . HtmlSanitizer::e($page['a_label']) . ' vs ' . HtmlSanitizer::e($page['b_label']) . '</h1>';
        if ($colors !== null) {
            $html .= $this->openTeamColors($colors['a'], $colors['b']);
        }

        $html .= '<section class="rivalries-section"><h2 class="rivalries-caption">Head to head</h2><div class="ibl-card rivalries-summary">';
        if ($page['kind'] === 'gms') {
            $html .= '<p>' . $this->gmLink($page['a'], $page['a_label']) . ' vs ' . $this->gmLink($page['b'], $page['b_label']) . '</p>';
        }
        // Laid out like the Team page Awards card: a bold label leads each line.
        $html .= '<ul class="team-awards-list">'
            . '<li><span class="award-year">All-time</span> ' . $this->record($page['a'], $page['a_wins'], $page['b_wins'], $viewer) . ' (' . $page['games'] . ' games)</li>'
            . '<li><span class="award-year">Playoffs</span> ' . $this->record($page['a'], $page['playoff_a_wins'], $page['playoff_b_wins'], $viewer) . '</li>';
        if ($page['streak'] !== null) {
            $streak = 'won ' . $page['streak']['length'] . ' straight';
            if ($viewer !== null) {
                $streak = $this->outcome($streak, $page['streak']['side'] === $viewer ? 1 : -1);
            }
            $html .= '<li><span class="award-year">Streak</span> ' . HtmlSanitizer::e($sideLabel($page['streak']['side'])) . ' has ' . $streak . '</li>';
        }
        $html .= '</ul>';
        if (!$page['qualifies']) {
            $html .= '<p>Fewer than 10 meetings, so this pair is not ranked as a rivalry.</p>';
        }
        $html .= '</div></section>';

        $rows = '';
        foreach ($page['series'] as $s) {
            $rows .= '<tr><td>' . $s['year'] . '</td><td class="rivalries-nowrap">Round ' . $s['round'] . '</td>'
                . $sideCell($s['winner_side']) . $this->seriesResultCell($s, $viewer) . '</tr>';
        }
        $html .= $this->table(['Year', 'Round', 'Winner', 'Result'], $rows, 'Playoff series', 'No playoff series between them.');

        // Four cards: one row on wide screens, two by two below that.
        $html .= '<div class="ibl-grid rivalries-cards">'
            . $this->gamesTable($page['closest'], 'Closest games', $teamColors, $viewer)
            . $this->gamesTable($page['blowouts'], 'Biggest blowouts', $teamColors, $viewer);

        $totals = '';
        foreach ($page['totals'] as $i => $t) {
            $totals .= '<tr>' . PlayerImageHelper::renderPlayerCell($t['pid'], $t['name'])
                . $sideCell($t['side']) . '<td>' . $t['games'] . '</td><td>' . $this->pointsValue($t['total_points'], $i === 0) . '</td></tr>';
        }
        $highs = '';
        foreach ($page['highs'] as $i => $h) {
            $highs .= '<tr>' . PlayerImageHelper::renderPlayerCell($h['pid'], $h['name'])
                . $sideCell($h['side']) . '<td>' . $this->pointsValue($h['points'], $i === 0) . '</td><td class="rivalries-nowrap">'
                . $this->boxScoreLink($h['game_date'], $h['game_of_that_day'], $h['game_date']) . '</td></tr>';
        }
        $html .= $this->table(['Player', $sideHeader, 'Games', 'Points'], $totals, 'Top scorers in the matchup')
            . $this->table(['Player', $sideHeader, 'Points', 'Date'], $highs, 'Best single games')
            . '</div>';

        return $colors !== null ? $html . '</div>' : $html;
    }

    /**
     * Opens the wrapper that paints every caption strip inside it with a gradient
     * from side a's color to side b's. The text is white or black, whichever stays
     * more readable at the worst point of the gradient: either end or the blended
     * middle. A soft shadow in the other color keeps it legible where contrast dips.
     */
    private function openTeamColors(string $a, string $b): string
    {
        return '<div class="rivalries-pair" style="' . $this->gradientVars($a, $b) . '">';
    }

    /**
     * The custom properties for a gradient from $a to $b: both end colors, the
     * text color, and its shadow color.
     */
    private function gradientVars(string $a, string $b): string
    {
        $a = TableStyles::sanitizeColor($a);
        $b = TableStyles::sanitizeColor($b);
        $ra = TeamColorHelper::hexToRgb($a);
        $rb = TeamColorHelper::hexToRgb($b);
        $mid = sprintf('%02x%02x%02x', intdiv($ra['r'] + $rb['r'], 2), intdiv($ra['g'] + $rb['g'], 2), intdiv($ra['b'] + $rb['b'], 2));

        $worst = static fn (string $text): float => min(
            TeamColorHelper::getContrastRatio($a, $text),
            TeamColorHelper::getContrastRatio($mid, $text),
            TeamColorHelper::getContrastRatio($b, $text),
        );
        $text = $worst('ffffff') >= $worst('000000') ? 'ffffff' : '000000';
        $shadow = $text === 'ffffff' ? '000000' : 'ffffff';

        return '--rivalry-color-a: #' . $a . '; --rivalry-color-b: #' . $b
            . '; --rivalry-text: #' . $text . '; --rivalry-text-shadow: #' . $shadow . ';';
    }

    /**
     * When $sideColors has both sides of a row, its Rivalry cell gets the pair
     * page's caption gradient.
     *
     * @param list<ScoredRivalry> $rows
     * @param 'teams'|'gms' $kind
     * @param array<array-key, string> $sideColors Primary team color hex keyed by side key
     */
    private function renderRankTable(array $rows, string $kind, ?string $highlightKey, string $emptyMessage, ?string $title = null, array $sideColors = [], string $captionClass = 'rivalries-caption'): string
    {
        if ($rows === [] && $title === null) {
            return $this->renderEmpty($emptyMessage);
        }

        $body = '';
        foreach ($rows as $i => $r) {
            $highlight = $highlightKey !== null && ($r['a'] === $highlightKey || $r['b'] === $highlightKey);
            $rivalryCell = isset($sideColors[$r['a']], $sideColors[$r['b']])
                ? '<td class="rivalries-gradient" style="' . $this->gradientVars($sideColors[$r['a']], $sideColors[$r['b']]) . '">'
                : '<td>';
            $body .= '<tr' . ($highlight ? ' class="user-team-row"' : '') . '>'
                . '<td>' . ($i + 1) . '</td>'
                . $rivalryCell . '<a href="' . HtmlSanitizer::e($this->pairHref($kind, $r['a'], $r['b'])) . '">'
                . HtmlSanitizer::e($r['a_label']) . ' vs ' . HtmlSanitizer::e($r['b_label']) . '</a></td>'
                . '<td>' . $r['a_wins'] . '-' . $r['b_wins'] . '</td>'
                . '<td>' . $r['games'] . '</td>'
                . '</tr>';
        }

        return $this->table(['Rank', 'Rivalry', 'Record', 'Games'], $body, $title, $emptyMessage, $captionClass);
    }

    /**
     * The +/- column is signed from the viewer's side when the viewer is one of
     * the pair: green when their side won, red when it lost. Anyone else sees
     * the bare margin.
     *
     * @param list<GameRow> $games
     * @param array<int, array{color1: string, color2: string}> $teamColors
     */
    private function gamesTable(array $games, string $title, array $teamColors, ?string $viewer): string
    {
        $rows = '';
        foreach ($games as $g) {
            $rows .= '<tr><td class="rivalries-nowrap">' . $this->boxScoreLink($g['game_date'], $g['game_of_that_day'], $g['game_date']) . '</td>'
                . $this->teamCell($g['visitor_teamid'], $g['visitor_label'], $teamColors, $g['visitor_era_colors'])
                . '<td class="rivalries-nowrap">' . $this->boxScoreLink($g['game_date'], $g['game_of_that_day'], $g['visitor_points'] . '-' . $g['home_points']) . '</td>'
                . $this->teamCell($g['home_teamid'], $g['home_label'], $teamColors, $g['home_era_colors'])
                . $this->marginCell($g, $viewer) . '</tr>';
        }

        return $this->table(['Date', 'Away', 'Score', 'Home', '+/-'], $rows, $title);
    }

    /**
     * The link is a .team-banner-box in the team's color, the same box the Team
     * page uses, so a team whose image is a small square logo still fills a
     * banner's space.
     *
     * @param array<int, array{color1: string, color2: string}> $teamColors
     */
    private function teamBanner(int $teamId, string $label, array $teamColors): string
    {
        $color = ($teamColors[$teamId] ?? TeamColorHelper::resolveTeamColors(null))['color1'];

        return '<a class="team-banner-box" style="--team-color-primary: #' . TableStyles::sanitizeColor($color) . ';" href="modules.php?name=Team&amp;op=team&amp;teamid=' . $teamId . '">'
            . '<img src="images/logo/' . $teamId . '.jpg" alt="' . HtmlSanitizer::e($label) . '" width="415" height="50"></a>';
    }

    /**
     * The top row's points get the Season Leaders headline style, so the
     * leader stands out from the rest of the column.
     */
    private function pointsValue(int $points, bool $isTop): string
    {
        return $isTop
            ? '<span class="leaders-tabbed__leader-value rivalries-top-value">' . $points . '</span>'
            : (string) $points;
    }

    /**
     * A head-to-head record. A viewer in the pair sees their own wins first, in
     * the margin column's win or loss color; anyone else sees side a first.
     */
    private function record(string $a, int $aWins, int $bWins, ?string $viewer): string
    {
        if ($viewer === null) {
            return $aWins . '-' . $bWins;
        }

        [$mine, $theirs] = $viewer === $a ? [$aWins, $bWins] : [$bWins, $aWins];

        return $this->outcome($mine . '-' . $theirs, $mine <=> $theirs);
    }

    /**
     * @param SeriesRow $s
     */
    private function seriesResultCell(array $s, ?string $viewer): string
    {
        if ($viewer === null) {
            return '<td>' . $s['winner_games'] . '-' . $s['loser_games'] . '</td>';
        }

        return $s['winner_side'] === $viewer
            ? '<td class="rivalries-margin rivalries-margin--win">' . $s['winner_games'] . '-' . $s['loser_games'] . '</td>'
            : '<td class="rivalries-margin rivalries-margin--loss">' . $s['loser_games'] . '-' . $s['winner_games'] . '</td>';
    }

    /**
     * Wraps already-safe text in the win color (positive), the loss color
     * (negative), or nothing (even).
     */
    private function outcome(string $text, int $sign): string
    {
        return match (true) {
            $sign > 0 => '<span class="rivalries-margin rivalries-margin--win">' . $text . '</span>',
            $sign < 0 => '<span class="rivalries-margin rivalries-margin--loss">' . $text . '</span>',
            default => $text,
        };
    }

    /**
     * @param GameRow $g
     */
    private function marginCell(array $g, ?string $viewer): string
    {
        $margin = $g['visitor_points'] - $g['home_points'];
        if ($viewer === null) {
            return '<td>' . abs($margin) . '</td>';
        }

        $signed = $viewer === $g['visitor_side'] ? $margin : -$margin;
        if ($signed === 0) {
            return '<td>0</td>';
        }

        return $signed > 0
            ? '<td class="rivalries-margin rivalries-margin--win">+' . $signed . '</td>'
            : '<td class="rivalries-margin rivalries-margin--loss">' . $signed . '</td>';
    }

    /**
     * A game from a retired identity (say, the Seattle Supersonics) takes that
     * era's colors when it has them, else the franchise's colors today.
     *
     * @param array<int, array{color1: string, color2: string}> $teamColors
     * @param array{color1: string, color2: string}|null $eraColors
     */
    private function teamCell(int $teamId, string $label, array $teamColors, ?array $eraColors = null): string
    {
        $colors = $eraColors !== null
            ? TeamColorHelper::resolveTeamColors($eraColors)
            : $teamColors[$teamId] ?? TeamColorHelper::resolveTeamColors(null);

        return TeamCellHelper::renderTeamCell($teamId, $label, $colors['color1'], $colors['color2']);
    }

    private function boxScoreLink(string $date, int $gameOfThatDay, string $text): string
    {
        $url = BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay);
        if ($url === '') {
            return HtmlSanitizer::e($text);
        }

        return '<a href="' . HtmlSanitizer::e($url) . '">' . HtmlSanitizer::e($text) . '</a>';
    }

    /**
     * A titled table sits in a <section> under a title strip joined to the table top.
     * The strip stays outside the scroll box, as on Season Highs, so a table that
     * scrolls sideways on a phone never drags its title off screen.
     * An empty titled table keeps its title and shows $emptyMessage in one row.
     *
     * @param list<string> $headers
     */
    private function table(array $headers, string $rows, ?string $title = null, string $emptyMessage = '', string $captionClass = 'rivalries-caption'): string
    {
        $head = '';
        foreach ($headers as $h) {
            $head .= '<th>' . HtmlSanitizer::e($h) . '</th>';
        }

        if ($rows === '' && $emptyMessage !== '') {
            $rows = '<tr><td colspan="' . count($headers) . '">' . $this->renderEmpty($emptyMessage) . '</td></tr>';
        }

        $table = '<div class="table-scroll-wrapper"><table class="ibl-data-table rivalries-table"><thead><tr>' . $head
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';

        if ($title === null) {
            return $table;
        }

        return '<section class="rivalries-section"><h2 class="' . $captionClass . '">' . HtmlSanitizer::e($title) . '</h2>'
            . $table . '</section>';
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
}
