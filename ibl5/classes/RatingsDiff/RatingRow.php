<?php

declare(strict_types=1);

namespace RatingsDiff;

/**
 * Value object representing one player's full ratings diff row.
 */
final class RatingRow
{
    /**
     * @param array<string, RatingDelta> $deltas Keyed by field name (one of RatingsDiffService::RATED_FIELDS)
     */
    public function __construct(
        public readonly int $pid,
        public readonly string $name,
        public readonly string $pos,
        public readonly int $teamid,
        public readonly ?string $teamName,
        public readonly array $deltas,
        public readonly int $maxAbsDelta,
        public readonly int $sumAbsDelta,
        public readonly bool $isNewPlayer,
    ) {
    }
}
