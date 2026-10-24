<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyMarketDemandCalculatorInterface;
use Player\Player;

/**
 * Stub calculator that returns known modifier/random/perceivedValue.
 */
class StubDemandCalculator implements FreeAgencyMarketDemandCalculatorInterface
{
    private float $modifier;
    private int $random;

    public function __construct(float $modifier = 1.0, int $random = 0)
    {
        $this->modifier = $modifier;
        $this->random = $random;
    }

    public function setRandomFactor(?int $factor): void
    {
        // no-op for stub
    }

    /**
     * @return array{modifier: float, random: int, perceivedValue: float}
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): array {
        $modRandom = (100 + $this->random) / 100;
        return [
            'modifier' => $this->modifier,
            'random' => $this->random,
            'perceivedValue' => $offerAverage * $this->modifier * $modRandom,
        ];
    }
}
