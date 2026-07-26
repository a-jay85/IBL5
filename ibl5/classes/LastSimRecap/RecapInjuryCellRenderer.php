<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use Security\HtmlSanitizer;
use UI\Components\TooltipLabel;

/**
 * Renders the injury-report cell: one group per side, each with an optional
 * "Healthy" badge and one row per injured player.
 */
final class RecapInjuryCellRenderer
{
    public function render(RecapSlate $slate, RecapGame $g): string
    {
        $h  = '<div class="last-sim-recap__cell">';
        $h .= '  <h4 class="last-sim-recap__cell-head">Injury report</h4>';
        $h .= '  <div class="last-sim-recap__inj">';

        $yourTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $slate->teamTid;
        $oppTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $g->oppTid;

        $h .= $this->renderInjuryGroup($yourTeamUrl, $slate->teamName, $g->yourInjuries, 'you');

        $h .= '    <div class="last-sim-recap__inj-divider"></div>';

        $h .= $this->renderInjuryGroup($oppTeamUrl, $g->oppName, $g->oppInjuries, 'opp');

        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }

    /**
     * @param list<RecapInjury> $injuries
     */
    private function renderInjuryGroup(string $teamUrl, string $teamName, array $injuries, string $side): string
    {
        $dotClass = 'last-sim-recap__inj-dot last-sim-recap__inj-dot--' . $side;
        $healthy = $injuries === [];

        $h  = '    <div class="last-sim-recap__inj-group">';
        $h .= '      <div class="last-sim-recap__inj-grouphead">';
        $h .= '        <span class="' . $dotClass . '"></span>';
        $h .= '        <a href="' . $teamUrl . '" class="last-sim-recap__team-link">' . HtmlSanitizer::e($teamName) . '</a>';
        if ($healthy) {
            $h .= '        <span class="last-sim-recap__inj-healthy">Healthy</span>';
        }
        $h .= '      </div>';

        foreach ($injuries as $inj) {
            $h .= $this->renderInjuryRow($inj);
        }

        $h .= '    </div>';
        return $h;
    }

    private function renderInjuryRow(RecapInjury $inj): string
    {
        $playerUrl = 'modules.php?name=Player&amp;pa=showpage&amp;pid=' . $inj->pid;
        $rowMod = $inj->isNew ? ' last-sim-recap__inj-row--new' : '';
        $h  = '<div class="last-sim-recap__inj-row' . $rowMod . '">';
        $h .= '  <div class="last-sim-recap__inj-pname">';
        $h .= '    <span class="last-sim-recap__inj-pos">' . HtmlSanitizer::e($inj->pos) . '</span>';
        $h .= '    <a href="' . $playerUrl . '" class="last-sim-recap__player-link">' . HtmlSanitizer::e($inj->name) . '</a>';
        if ($inj->isNew) {
            $h .= '    <span class="last-sim-recap__inj-new" aria-label="New injury this game">!</span>';
        }
        if ($inj->description !== '') {
            $h .= '    <span class="last-sim-recap__inj-why">' . HtmlSanitizer::e($inj->description) . '</span>';
        }
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__inj-eta">';
        $num = HtmlSanitizer::e((string) $inj->daysRemaining);
        $unit = '<span class="last-sim-recap__eta-unit">d</span>';
        if ($inj->daysRemaining > 0 && $inj->returnDate !== '') {
            $h .= TooltipLabel::render($num . $unit, 'Returns: ' . $inj->returnDate, 'last-sim-recap__eta-num');
        } else {
            $h .= '<span class="last-sim-recap__eta-num">' . $num . '</span>' . $unit;
        }
        $h .= '  </div>';
        $h .= '</div>';

        return $h;
    }
}
