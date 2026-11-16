<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A draft pick owned by a team, valued by PickValuer.
 */
final readonly class SuggestionPick
{
    public function __construct(
        public int $pickId,
        public int $originalTid,
        public int $year,
        public int $round,
        public float $value,
        public ?PickTier $tier = null,
    ) {
    }
}
