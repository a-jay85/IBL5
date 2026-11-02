<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SuggestionPlayer;

/**
 * Turns a projected player season into the v2 `SuggestionPlayer` shape, so
 * `SlotMapper::depthFor()` and `rotationAt()` can be reused without editing v2.
 */
final class LineupAdapter
{
    public static function toSuggestionPlayer(ProjectionPlayer $p, ProjectedSeason $s): SuggestionPlayer
    {
        $later = [];
        for ($k = $s->offset + 1; $p->salaryAt($k) > 0; $k++) {
            $later[] = $p->salaryAt($k);
        }

        return new SuggestionPlayer(
            pid: $p->pid,
            name: $p->name,
            pos: $p->pos,
            age: $s->age,
            // Injury days only describe the current season.
            injured: $p->injuredDays > 0 && $s->offset === 0,
            currentSalary: $s->salary,
            yearsRemaining: $s->salary > 0 ? 1 + count($later) : 0,
            futureSalary: $later[0] ?? 0,
            tradeable: $p->tradeable,
            countsOnRoster: true,
            depth: $p->dcDepth,
            dcMinutes: $p->dcMinutes,
            exp: $p->exp,
            production: $p->production,
            laterSalaries: $later,
        );
    }
}
