<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * The facts behind a team's win-now/rebuild guess.
 *
 * strengthRank is 1 for the team with the best top-8 average ovr. lockedShare
 * is the share of top-8 ovr held by players with at least two years left.
 */
final readonly class ModeInputs
{
    public function __construct(
        public int $wins,
        public int $losses,
        public int $priorWins,
        public int $priorLosses,
        public float $rotationOvr,
        public int $strengthRank,
        public int $teamCount,
        public float $rotationAge,
        public float $lockedShare,
    ) {
    }
}
