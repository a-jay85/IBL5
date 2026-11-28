<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use Team\Team;
use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A side ends the deal with more players than the league allows. Any player
 * can play any position, so the GM decides who plays where, and there is no
 * per-position or minimum-roster check.
 */
final class RosterOverMaxAfter implements VetoInterface
{
    public function id(): string
    {
        return 'ROSTER_OVER_MAX_AFTER';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            if ($side->after[0]->rosterCount > Team::ROSTER_SPOTS_MAX) {
                return true;
            }
        }

        return false;
    }
}
