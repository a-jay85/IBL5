<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * One player at one horizon offset.
 */
final readonly class ProjectedSeason
{
    /**
     * @param array<string, float> $per36 pts reb ast stl blk tov pf; empty when no production sample
     */
    public function __construct(
        public int $offset,
        public int $age,
        public int $salary,
        public bool $onRoster,
        public RatingSet $ratings,
        public array $per36,
    ) {
    }
}
