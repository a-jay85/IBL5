<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * The facts behind a team's win-now/rebuild guess.
 *
 * The rotation is the top 8 by depth-chart minutes. lockedShare is the share
 * of rotation dc_minutes held by players with at least two years left.
 */
final readonly class ModeInputs
{
    public function __construct(
        public int $wins,
        public int $losses,
        public int $priorWins,
        public int $priorLosses,
        public float $rotationAge,
        public float $lockedShare,
    ) {
    }
}
