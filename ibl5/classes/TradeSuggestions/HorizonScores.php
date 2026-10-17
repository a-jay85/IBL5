<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A team's need scores on three horizons: now, short (1-2 seasons), long (3+ seasons).
 */
final readonly class HorizonScores
{
    /**
     * @param array<string, int> $positionHoles Shortfall per position group inside the rotation, e.g. ['big' => 1]
     */
    public function __construct(
        public float $now,
        public float $short,
        public float $long,
        public array $positionHoles,
    ) {
    }

    /**
     * Per-horizon weights for a team mode. The single source for the mode weighting.
     *
     * @return array{now: float, short: float, long: float}
     */
    public static function weights(TeamMode $mode): array
    {
        return match ($mode) {
            TeamMode::WinNow => ['now' => 0.6, 'short' => 0.3, 'long' => 0.1],
            TeamMode::Rebuild => ['now' => 0.1, 'short' => 0.3, 'long' => 0.6],
        };
    }

    public function weighted(TeamMode $mode): float
    {
        $weights = self::weights($mode);

        return $weights['now'] * $this->now
            + $weights['short'] * $this->short
            + $weights['long'] * $this->long;
    }
}
