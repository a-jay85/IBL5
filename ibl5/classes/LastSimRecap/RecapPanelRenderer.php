<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapSlate;
use Security\HtmlSanitizer;
use Utilities\BoxScoreUrlBuilder;

/**
 * Renders one `last-sim-recap__panel`: verdict strip, main row (final score /
 * quarter-margin chart / injury cell), and the starter battles row.
 */
final class RecapPanelRenderer
{
    private readonly RecapInjuryCellRenderer $injuryCellRenderer;

    private readonly RecapBattlesRenderer $battlesRenderer;

    public function __construct(
        ?RecapInjuryCellRenderer $injuryCellRenderer = null,
        ?RecapBattlesRenderer $battlesRenderer = null,
    ) {
        $this->injuryCellRenderer = $injuryCellRenderer ?? new RecapInjuryCellRenderer();
        $this->battlesRenderer = $battlesRenderer ?? new RecapBattlesRenderer();
    }

    public function render(RecapSlate $slate, RecapGame $g, int $idx): string
    {
        $isActive = $idx === 0;
        $hiddenAttr = $isActive ? '' : ' hidden';

        $h  = '<div class="last-sim-recap__panel"';
        $h .= ' role="tabpanel"';
        $h .= ' id="last-sim-recap-panel-' . $idx . '"';
        $h .= ' aria-labelledby="last-sim-recap-tab-' . $idx . '"';
        $h .= ' data-panel-index="' . $idx . '"';
        $h .= ' tabindex="0"' . $hiddenAttr . '>';
        $h .= $this->renderVerdictStrip($g);
        $h .= $this->renderMainRow($slate, $g);
        $h .= $this->battlesRenderer->render($g);
        $h .= '</div>';

        return $h;
    }

    private function renderVerdictStrip(RecapGame $g): string
    {
        $resultMod = $g->won ? 'win' : 'loss';
        $sign = $g->margin >= 0 ? '+' : '−';
        $abs = abs($g->margin);
        $venueWord = $g->home ? 'vs' : '@';
        $marginLabel = $sign . $abs . ($g->ot ? ' OT' : '');
        $dateText = $this->formatLongDate($g->date);

        $h  = '<div class="last-sim-recap__strip last-sim-recap__strip--' . $resultMod . '">';
        $h .= '  <span class="last-sim-recap__verdict">';
        $h .= '    ' . ($g->won ? 'W' : 'L') . ' <span class="last-sim-recap__verdict-margin">' . HtmlSanitizer::e($marginLabel) . '</span>';
        $h .= '  </span>';
        $h .= '  <span class="last-sim-recap__vs">' . HtmlSanitizer::e($venueWord . ' ') . RecapTeamNameHelper::responsive($g->oppName) . '</span>';
        $h .= '  <div class="last-sim-recap__strip-right">';
        $h .= '    <span>' . HtmlSanitizer::e($dateText) . '</span>';
        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    private function renderMainRow(RecapSlate $slate, RecapGame $g): string
    {
        $h  = '<div class="last-sim-recap__main">';
        $h .= $this->renderFinalCell($slate, $g);
        $h .= $this->renderQuarterChart($g);
        $h .= $this->injuryCellRenderer->render($slate, $g);
        $h .= '</div>';

        return $h;
    }

    private function renderFinalCell(RecapSlate $slate, RecapGame $g): string
    {
        $yourRec = $slate->teamWins . '–' . $slate->teamLosses;
        $oppRec = $g->oppPreWins . '–' . $g->oppPreLosses;
        $yourLogo = 'images/logo/new' . $slate->teamTid . '.png';
        $oppLogo = 'images/logo/new' . $g->oppTid . '.png';
        $yourTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $slate->teamTid;
        $oppTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $g->oppTid;
        $boxUrl = BoxScoreUrlBuilder::buildUrl($g->date, $g->gameOfThatDay, $g->boxId);

        $h  = '<div class="last-sim-recap__cell">';
        $h .= '  <div class="last-sim-recap__cell-head-row">';
        $h .= '    <h4 class="last-sim-recap__cell-head">Final</h4>';
        if ($boxUrl !== '') {
            $h .= '    <a href="' . HtmlSanitizer::e($boxUrl) . '" class="last-sim-recap__box-link">Box score</a>';
        }
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__final">';

        $awayLogo = $g->home ? $oppLogo : $yourLogo;
        $homeLogo = $g->home ? $yourLogo : $oppLogo;
        $awayUrl = $g->home ? $oppTeamUrl : $yourTeamUrl;
        $homeUrl = $g->home ? $yourTeamUrl : $oppTeamUrl;
        $awayName = $g->home ? $g->oppName : $slate->teamName;
        $homeName = $g->home ? $slate->teamName : $g->oppName;
        $awayRec = $g->home ? $oppRec : $yourRec;
        $homeRec = $g->home ? $yourRec : $oppRec;
        $awayScore = $g->home ? $g->oppScore : $g->yourScore;
        $homeScore = $g->home ? $g->yourScore : $g->oppScore;
        $awayRowMod = ($awayScore > $homeScore) ? ' last-sim-recap__final-row--win' : '';
        $homeRowMod = ($homeScore > $awayScore) ? ' last-sim-recap__final-row--win' : '';

        $boxLink = $boxUrl !== '' ? $boxUrl : '';

        $h .= '    <div class="last-sim-recap__final-row' . $awayRowMod . '">';
        $h .= '      <a href="' . $awayUrl . '" class="last-sim-recap__team-link"><img src="' . HtmlSanitizer::e($awayLogo) . '" alt="" class="last-sim-recap__team-mark" width="50" height="50" loading="lazy"></a>';
        $h .= '      <a href="' . $awayUrl . '" class="last-sim-recap__final-name">' . RecapTeamNameHelper::responsive($awayName);
        $h .= '        <span class="last-sim-recap__final-rec">' . HtmlSanitizer::e($awayRec) . '</span>';
        $h .= '      </a>';
        if ($boxLink !== '') {
            $h .= '      <a href="' . HtmlSanitizer::e($boxLink) . '" class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $awayScore) . '</a>';
        } else {
            $h .= '      <span class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $awayScore) . '</span>';
        }
        $h .= '    </div>';

        $h .= '    <div class="last-sim-recap__final-row' . $homeRowMod . '">';
        $h .= '      <a href="' . $homeUrl . '" class="last-sim-recap__team-link"><img src="' . HtmlSanitizer::e($homeLogo) . '" alt="" class="last-sim-recap__team-mark" width="50" height="50" loading="lazy"></a>';
        $h .= '      <a href="' . $homeUrl . '" class="last-sim-recap__final-name">' . RecapTeamNameHelper::responsive($homeName);
        $h .= '        <span class="last-sim-recap__final-rec">' . HtmlSanitizer::e($homeRec) . '</span>';
        $h .= '      </a>';
        if ($boxLink !== '') {
            $h .= '      <a href="' . HtmlSanitizer::e($boxLink) . '" class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $homeScore) . '</a>';
        } else {
            $h .= '      <span class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $homeScore) . '</span>';
        }
        $h .= '    </div>';

        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    private function renderQuarterChart(RecapGame $g): string
    {
        $n = count($g->margins);
        if ($n === 0) {
            return '<div class="last-sim-recap__cell"><h4 class="last-sim-recap__cell-head">Quarter margin</h4></div>';
        }

        $maxAbs = 6;
        foreach ($g->margins as $m) {
            $maxAbs = max($maxAbs, abs($m));
        }
        $maxH = 22;

        $h  = '<div class="last-sim-recap__cell">';
        $h .= '  <h4 class="last-sim-recap__cell-head">Quarter margin</h4>';
        $h .= '  <div class="last-sim-recap__mom">';
        $h .= '    <div class="last-sim-recap__mom-chart" style="--last-sim-recap-quarters: ' . $n . ';">';
        $h .= '      <div class="last-sim-recap__mom-bars">';

        foreach ($g->margins as $m) {
            $height = (int) round((abs($m) / $maxAbs) * $maxH);
            $sign = $m >= 0 ? 'pos' : 'neg';
            $signGlyph = $m >= 0 ? '+' : '−';
            $valStyle = 'style="--last-sim-recap-bar-h: ' . $height . 'px;"';
            $h .= '        <div class="last-sim-recap__mom-bar">';
            $h .= '          <span class="last-sim-recap__mom-bar-shape last-sim-recap__mom-bar-shape--' . $sign . '" style="--last-sim-recap-bar-h: ' . $height . 'px;"></span>';
            $h .= '          <span class="last-sim-recap__mom-bar-val last-sim-recap__mom-bar-val--' . $sign . '" ' . $valStyle . '>'
                . HtmlSanitizer::e($signGlyph . abs($m)) . '</span>';
            $h .= '        </div>';
        }

        $h .= '      </div>';
        $h .= '      <div class="last-sim-recap__mom-labels">';
        foreach ($g->qLabels as $label) {
            $h .= '<span>' . HtmlSanitizer::e($label) . '</span>';
        }
        $h .= '      </div>';
        $h .= '    </div>';
        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    /**
     * Format an ISO date `Y-m-d` as e.g. `May 13, 2026`.
     */
    private function formatLongDate(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('M j, Y', $ts);
    }
}
