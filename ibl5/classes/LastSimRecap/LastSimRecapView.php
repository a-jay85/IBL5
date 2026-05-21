<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapViewInterface;
use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\Dto\RecapStarter;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;

class LastSimRecapView implements LastSimRecapViewInterface
{
    public function render(RecapSlate $slate): string
    {
        $games = $slate->games;
        $tabCount = count($games);

        $html = '<section class="last-sim-recap" data-component="last-sim-recap">';
        $html .= $this->renderHeader($slate);
        $html .= $this->renderTabs($games, $tabCount);
        foreach ($games as $idx => $game) {
            $html .= $this->renderPanel($slate, $game, $idx);
        }
        $html .= '</section>';
        $html .= '<script src="jslib/last-sim-recap-tabs.js" defer></script>';

        return $html;
    }

    private function renderHeader(RecapSlate $slate): string
    {
        $windowLabel = $this->formatDateRange($slate->startDate, $slate->endDate);
        $gameCount = count($slate->games);
        $gameWord = $gameCount === 1 ? 'game' : 'games';
        $subtitle = $windowLabel . ' (' . $gameCount . ' ' . $gameWord . ')';

        $netSign = $slate->netMargin >= 0 ? '+' : '−';
        $netAbs = abs($slate->netMargin);
        $netValue = $netSign . $netAbs;

        $h  = '<header class="last-sim-recap__head">';
        $h .= '  <div class="last-sim-recap__lbl">';
        $h .= '    Last sim';
        $h .= '    <span class="last-sim-recap__sub">' . HtmlSanitizer::e($subtitle) . '</span>';
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__rec">';
        $h .= '    <span class="last-sim-recap__record-w">' . HtmlSanitizer::e((string) $slate->wins) . '</span>';
        $h .= '    <span class="last-sim-recap__record-sep">–</span>';
        $h .= '    ' . HtmlSanitizer::e((string) $slate->losses);
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__meta">';
        $h .= '    <span>Net margin: <span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($netValue) . '</span></span>';
        $h .= '    <span class="last-sim-recap__meta-bw">';
        $h .= '      <span class="last-sim-recap__meta-bw-row"><span class="last-sim-recap__meta-bw-label">&nbsp;Best:</span>&nbsp;<span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($slate->bestLabel) . '</span></span>';
        $h .= '      <span class="last-sim-recap__meta-bw-row"><span class="last-sim-recap__meta-bw-label">Worst:</span>&nbsp;<span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($slate->worstLabel) . '</span></span>';
        $h .= '    </span>';
        $h .= '  </div>';
        $h .= '</header>';

        return $h;
    }

    /**
     * @param list<RecapGame> $games
     */
    private function renderTabs(array $games, int $tabCount): string
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
        $h .= '  <span class="last-sim-recap__tab-top">';
        $h .= '    <span class="last-sim-recap__tab-where">' . HtmlSanitizer::e($where) . '</span>';
        $h .= '    <span class="last-sim-recap__tab-opp">' . HtmlSanitizer::e($g->oppName) . '</span>';
        $h .= '    <span class="last-sim-recap__tab-date">' . HtmlSanitizer::e($dateLabel) . '</span>';
        $h .= '  </span>';
        $h .= '  <span class="last-sim-recap__tab-score">';
        $h .= '    <span class="last-sim-recap__tab-wl">' . ($g->won ? 'W' : 'L') . '</span>';
        $h .= '    <span class="last-sim-recap__tab-num">' . HtmlSanitizer::e((string) $g->yourScore) . '–' . HtmlSanitizer::e((string) $g->oppScore) . '</span>';
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

    private function renderPanel(RecapSlate $slate, RecapGame $g, int $idx): string
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
        $h .= $this->renderBattlesRow($g);
        $h .= '</div>';

        return $h;
    }

    private function renderVerdictStrip(RecapGame $g): string
    {
        $resultMod = $g->won ? 'win' : 'loss';
        $sign = $g->margin >= 0 ? '+' : '−';
        $abs = abs($g->margin);
        $venueWord = $g->home ? 'vs' : '@';
        $marginLabel = $sign . $abs . ($g->ot ? ' / OT' : '');
        $dateText = $this->formatLongDate($g->date);

        $h  = '<div class="last-sim-recap__strip last-sim-recap__strip--' . $resultMod . '">';
        $h .= '  <span class="last-sim-recap__verdict">';
        $h .= '    ' . ($g->won ? 'W' : 'L') . ' <span class="last-sim-recap__verdict-margin">' . HtmlSanitizer::e($marginLabel) . '</span>';
        $h .= '  </span>';
        $h .= '  <span class="last-sim-recap__vs">' . HtmlSanitizer::e($venueWord . ' ' . $g->oppName) . '</span>';
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
        $h .= $this->renderInjuryCell($slate, $g);
        $h .= '</div>';

        return $h;
    }

    private function renderFinalCell(RecapSlate $slate, RecapGame $g): string
    {
        $yourRec = $slate->teamWins . '–' . $slate->teamLosses;
        $oppRec = $g->oppPreWins . '–' . $g->oppPreLosses;
        $yourLogo = 'images/logo/new' . $slate->teamTid . '.png';
        $oppLogo = 'images/logo/new' . $g->oppTid . '.png';
        $yourRowMod = $g->won ? ' last-sim-recap__final-row--win' : '';
        $oppRowMod = $g->won ? '' : ' last-sim-recap__final-row--win';

        $h  = '<div class="last-sim-recap__cell">';
        $h .= '  <h4 class="last-sim-recap__cell-head">Final</h4>';
        $h .= '  <div class="last-sim-recap__final">';

        $h .= '    <div class="last-sim-recap__final-row' . $yourRowMod . '">';
        $h .= '      <img src="' . HtmlSanitizer::e($yourLogo) . '" alt="" class="last-sim-recap__team-mark" width="30" height="30" loading="lazy">';
        $h .= '      <span class="last-sim-recap__final-name">' . HtmlSanitizer::e($slate->teamName);
        $h .= '        <span class="last-sim-recap__final-rec">' . HtmlSanitizer::e($yourRec) . '</span>';
        $h .= '      </span>';
        $h .= '      <span class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $g->yourScore) . '</span>';
        $h .= '    </div>';

        $h .= '    <div class="last-sim-recap__final-row' . $oppRowMod . '">';
        $h .= '      <img src="' . HtmlSanitizer::e($oppLogo) . '" alt="" class="last-sim-recap__team-mark" width="30" height="30" loading="lazy">';
        $h .= '      <span class="last-sim-recap__final-name">' . HtmlSanitizer::e($g->oppName);
        $h .= '        <span class="last-sim-recap__final-rec">' . HtmlSanitizer::e($oppRec) . '</span>';
        $h .= '      </span>';
        $h .= '      <span class="last-sim-recap__final-pts">' . HtmlSanitizer::e((string) $g->oppScore) . '</span>';
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
        $maxH = 32;

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

    private function renderInjuryCell(RecapSlate $slate, RecapGame $g): string
    {
        $h  = '<div class="last-sim-recap__cell">';
        $h .= '  <h4 class="last-sim-recap__cell-head">Injury report</h4>';
        $h .= '  <div class="last-sim-recap__inj">';

        $h .= '    <div class="last-sim-recap__inj-group">';
        $h .= '      <div class="last-sim-recap__inj-grouphead">';
        $h .= '        <span class="last-sim-recap__inj-dot last-sim-recap__inj-dot--you"></span>';
        $h .= '        ' . HtmlSanitizer::e($slate->teamName);
        $h .= '      </div>';
        $h .= $this->renderInjuryList($g->yourInjuries);
        $h .= '    </div>';

        $h .= '    <div class="last-sim-recap__inj-divider"></div>';

        $h .= '    <div class="last-sim-recap__inj-group">';
        $h .= '      <div class="last-sim-recap__inj-grouphead">';
        $h .= '        <span class="last-sim-recap__inj-dot last-sim-recap__inj-dot--opp"></span>';
        $h .= '        ' . HtmlSanitizer::e($g->oppName);
        $h .= '      </div>';
        $h .= $this->renderInjuryList($g->oppInjuries);
        $h .= '    </div>';

        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    /**
     * @param list<RecapInjury> $injuries
     */
    private function renderInjuryList(array $injuries): string
    {
        if ($injuries === []) {
            return '<div class="last-sim-recap__inj-row last-sim-recap__inj-row--empty">'
                . '<div class="last-sim-recap__inj-pname">No injuries</div>'
                . '<div></div>'
                . '</div>';
        }

        // Already sorted new-first by service.
        $h = '';
        foreach ($injuries as $inj) {
            $h .= $this->renderInjuryRow($inj);
        }
        return $h;
    }

    private function renderInjuryRow(RecapInjury $inj): string
    {
        $rowMod = $inj->isNew ? ' last-sim-recap__inj-row--new' : '';
        $h  = '<div class="last-sim-recap__inj-row' . $rowMod . '">';
        $h .= '  <div class="last-sim-recap__inj-pname">';
        $h .= '    <span class="last-sim-recap__inj-pos">' . HtmlSanitizer::e($inj->pos) . '</span>';
        $h .= '    ' . HtmlSanitizer::e($inj->name);
        if ($inj->isNew) {
            $h .= '    <span class="last-sim-recap__inj-new" aria-label="New injury this game">!</span>';
        }
        if ($inj->description !== '') {
            $h .= '    <span class="last-sim-recap__inj-why">' . HtmlSanitizer::e($inj->description) . '</span>';
        }
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__inj-eta">';
        if ($inj->isNew) {
            $h .= '<span class="last-sim-recap__eta-num">DTD</span>';
        } else {
            $h .= '<span class="last-sim-recap__eta-num">' . HtmlSanitizer::e((string) $inj->daysRemaining) . '</span>';
            $h .= '<span class="last-sim-recap__eta-unit">d</span>';
        }
        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    private function renderBattlesRow(RecapGame $g): string
    {
        $h = '<div class="last-sim-recap__battles">';
        foreach ($g->starters as $starter) {
            $h .= $this->renderBattle($g, $starter);
        }
        $h .= '</div>';

        return $h;
    }

    private function renderBattle(RecapGame $g, RecapStarter $s): string
    {
        $h  = '<div class="last-sim-recap__battle">';
        $h .= '  <div class="last-sim-recap__poslbl">';
        $h .= '    <span class="last-sim-recap__pos-chip">' . HtmlSanitizer::e($s->pos) . '</span>';
        $h .= '  </div>';
        $h .= $this->renderPlayerRow(
            isYou: true,
            pid: $s->youPid,
            name: $s->youName,
            pts: $s->youPts,
            hurt: $s->youHurt,
        );
        $h .= $this->renderPlayerRow(
            isYou: false,
            pid: $s->oppPid,
            name: $s->oppName,
            pts: $s->oppPts,
            hurt: false,
        );
        $h .= '</div>';

        return $h;
    }

    private function renderPlayerRow(bool $isYou, int $pid, string $name, int $pts, bool $hurt): string
    {
        $youMod = $isYou ? ' last-sim-recap__player--you' : '';
        $parts = explode(' ', $name);
        $lastName = end($parts);

        $h  = '<div class="last-sim-recap__player' . $youMod . '">';
        $h .= '  <span class="last-sim-recap__avatar-wrap">';
        $h .= '    ' . PlayerImageHelper::renderThumbnail($pid);
        if ($hurt) {
            $h .= '    <span class="last-sim-recap__injdot" aria-label="Injured">!</span>';
        }
        $h .= '  </span>';
        $h .= '  <span class="last-sim-recap__player-name">' . HtmlSanitizer::e($name) . '</span>';
        $h .= '  <span class="last-sim-recap__player-lname">' . HtmlSanitizer::e($lastName) . '</span>';
        $h .= '  <span class="last-sim-recap__player-pts">' . HtmlSanitizer::e((string) $pts) . '</span>';
        $h .= '</div>';

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

    /**
     * "2026-05-01" + "2026-05-13" → "May 1 – May 13, 2026"
     * "2026-05-01" + "2026-06-02" → "May 1 – Jun 2, 2026"
     */
    private function formatDateRange(string $start, string $end): string
    {
        $sTs = strtotime($start);
        $eTs = strtotime($end);
        if ($sTs === false || $eTs === false) {
            return $start . ' – ' . $end;
        }
        $startLabel = date('M j', $sTs);
        $endLabel = date('M j', $eTs);
        $year = date('Y', $eTs);
        return $startLabel . ' – ' . $endLabel . ', ' . $year;
    }
}
