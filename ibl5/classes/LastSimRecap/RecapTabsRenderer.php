<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use Security\HtmlSanitizer;

/**
 * Renders the `last-sim-recap__tabs` tablist and its per-game tab buttons.
 */
final class RecapTabsRenderer
{
    /**
     * @param list<RecapGame> $games
     */
    public function render(array $games, int $tabCount): string
    {
        $style = 'style="--last-sim-recap-tab-count: ' . $tabCount . ';"';
        $h = '<div class="last-sim-recap__tabs" role="tablist" aria-label="Games in last sim" ' . $style . '>';
        foreach ($games as $idx => $g) {
            $h .= $this->renderTab($g, $idx);
        }
        $h .= '</div>';

        return $h;
    }

    private function renderTab(RecapGame $g, int $idx): string
    {
        $isActive = $idx === 0;
        $wlMod = $g->won ? 'win' : 'loss';
        $activeMod = $isActive ? ' last-sim-recap__tab--active' : '';
        $cls = 'last-sim-recap__tab last-sim-recap__tab--' . $wlMod . $activeMod;
        $ariaSelected = $isActive ? 'true' : 'false';
        $tabIndex = $isActive ? '0' : '-1';
        $where = $g->home ? 'vs' : '@';
        $dateLabel = $this->formatMonthDay($g->date);
        $tabFlagVisible = $g->hasNewYourInjury() || $g->hasNewOppInjury();

        $h  = '<button type="button" class="' . $cls . '"';
        $h .= ' role="tab"';
        $h .= ' id="last-sim-recap-tab-' . $idx . '"';
        $h .= ' aria-controls="last-sim-recap-panel-' . $idx . '"';
        $h .= ' aria-selected="' . $ariaSelected . '"';
        $h .= ' tabindex="' . $tabIndex . '"';
        $h .= ' data-tab-index="' . $idx . '">';
        $oppLogo = 'images/logo/new' . $g->oppTid . '.png';
        $h .= '  <span class="last-sim-recap__tab-top">';
        $h .= '    <span class="last-sim-recap__tab-where">' . HtmlSanitizer::e($where) . '</span>';
        $h .= '    <img src="' . HtmlSanitizer::e($oppLogo) . '" alt="" class="last-sim-recap__tab-logo" width="24" height="24" loading="lazy">';
        $h .= '    <span class="last-sim-recap__tab-opp">' . RecapTeamNameHelper::responsive($g->oppName) . '</span>';
        $h .= '    <span class="last-sim-recap__tab-date">' . HtmlSanitizer::e($dateLabel) . '</span>';
        $h .= '  </span>';
        $h .= '  <span class="last-sim-recap__tab-score">';
        $h .= '    <span class="last-sim-recap__tab-wl">' . ($g->won ? 'W' : 'L') . '</span>';
        $awayScore = $g->home ? $g->oppScore : $g->yourScore;
        $homeScore = $g->home ? $g->yourScore : $g->oppScore;
        $topYou = $g->home ? '' : ' last-sim-recap__tab-num-you';
        $botYou = $g->home ? ' last-sim-recap__tab-num-you' : '';
        $h .= '    <span class="last-sim-recap__tab-num">'
            . '<span class="last-sim-recap__tab-num-top' . $topYou . '">' . HtmlSanitizer::e((string) $awayScore) . '</span>'
            . '<span class="last-sim-recap__tab-num-bot' . $botYou . '">' . HtmlSanitizer::e((string) $homeScore) . '</span>'
            . '</span>';
        if ($g->ot) {
            $h .= '    <span class="last-sim-recap__tab-ot">OT</span>';
        }
        if ($tabFlagVisible) {
            $h .= '    <span class="last-sim-recap__tab-flag" aria-label="New injury this game">!</span>';
        }
        $h .= '  </span>';
        $h .= '</button>';

        return $h;
    }

    /**
     * Format an ISO date `Y-m-d` as e.g. `May 13`.
     */
    private function formatMonthDay(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('M j', $ts);
    }
}
