<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A team's total worth to itself in its mode, with the position-group counts
 * inside its top eight by ovr.
 */
final readonly class TeamScore
{
    /**
     * @param array<string, int> $groupCounts Players per position group in the top eight, e.g. ['guard' => 3, 'wing' => 3, 'big' => 2]
     * @param array<string, int> $positionHoles Shortfall per group below TeamNeedsScorer::MIN_PER_GROUP, e.g. ['big' => 1]
     */
    public function __construct(
        public float $total,
        public array $groupCounts,
        public array $positionHoles,
    ) {
    }
}
