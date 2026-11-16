<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

/**
 * How strong one team is at one slot (F7). An empty slot (no starter) is weak.
 */
final readonly class SlotStrength
{
    public const int STRONG_MAX_RANK = 8;
    public const int WEAK_MIN_RANK = 21;

    /**
     * @param list<int> $buriedPids bench players at the slot who would start elsewhere
     */
    public function __construct(
        public string $slot,
        public ?int $starterRank,
        public ?int $bestBenchRank,
        public int $playableDepth,
        public bool $multiPosition,
        public array $buriedPids,
    ) {
    }

    public function isStrong(): bool
    {
        return $this->starterRank !== null && $this->starterRank <= self::STRONG_MAX_RANK;
    }

    public function isWeak(): bool
    {
        return $this->starterRank === null || $this->starterRank >= self::WEAK_MIN_RANK;
    }
}
