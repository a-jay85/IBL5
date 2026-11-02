<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * The moments chosen for one sim, plus the window metadata used to pick them.
 */
final readonly class OnThisDaySelection
{
    /**
     * @param list<Moment> $moments
     */
    public function __construct(
        public array $moments,
        public int $currentSeasonYear,
        public string $centerDate,
        public int $radiusUsed,
        public int $sim,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->moments === [];
    }
}
