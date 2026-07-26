<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapStarter;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;

/**
 * Renders the `last-sim-recap__battles` row: one positional matchup per
 * starter, each with a "you" player row and an opponent player row.
 */
final class RecapBattlesRenderer
{
    public function render(RecapGame $g): string
    {
        $h = '<div class="last-sim-recap__battles">';
        foreach ($g->starters as $starter) {
            $h .= $this->renderBattle($starter);
        }
        $h .= '</div>';

        return $h;
    }

    private function renderBattle(RecapStarter $s): string
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
            reb: $s->youReb,
            ast: $s->youAst,
            stl: $s->youStl,
            blk: $s->youBlk,
            hurt: $s->youHurt,
        );
        $h .= $this->renderPlayerRow(
            isYou: false,
            pid: $s->oppPid,
            name: $s->oppName,
            pts: $s->oppPts,
            reb: $s->oppReb,
            ast: $s->oppAst,
            stl: $s->oppStl,
            blk: $s->oppBlk,
            hurt: false,
        );
        $h .= '</div>';

        return $h;
    }

    private function renderPlayerRow(bool $isYou, int $pid, string $name, int $pts, int $reb, int $ast, int $stl, int $blk, bool $hurt): string
    {
        $youMod = $isYou ? ' last-sim-recap__player--you' : '';
        $nameParts = explode(' ', $name);
        $lastName = end($nameParts);
        $playerUrl = 'modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid;
        $parts = [$pts . ' pts'];
        if ($reb >= 5) {
            $parts[] = $reb . ' reb';
        }
        if ($ast >= 5) {
            $parts[] = $ast . ' ast';
        }
        if ($stl >= 2) {
            $parts[] = $stl . ' stl';
        }
        if ($blk >= 2) {
            $parts[] = $blk . ' blk';
        }
        $statline = implode(', ', $parts);

        $h  = '<div class="last-sim-recap__player' . $youMod . '">';
        $h .= '  <a href="' . $playerUrl . '" class="last-sim-recap__avatar-wrap">';
        $h .= '    ' . PlayerImageHelper::renderThumbnail($pid);
        if ($hurt) {
            $h .= '    <span class="last-sim-recap__injdot" aria-label="Injured">!</span>';
        }
        $h .= '  </a>';
        $h .= '  <a href="' . $playerUrl . '" class="last-sim-recap__player-name">' . HtmlSanitizer::e($lastName) . '</a>';
        $h .= '  <span class="last-sim-recap__player-statline">' . HtmlSanitizer::e($statline) . '</span>';
        $h .= '</div>';

        return $h;
    }
}
