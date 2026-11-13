<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\TradeCandidate;

/**
 * A candidate with both sides' gain tables and their before/after ranks.
 */
final readonly class ScoredDeal
{
    /**
     * @param array<int, ProfileDelta> $userDelta offset => delta
     * @param array<int, ProfileDelta> $partnerDelta offset => delta
     */
    public function __construct(
        public TradeCandidate $candidate,
        public string $shapeId,
        public SideGain $user,
        public SideGain $partner,
        public array $userDelta,
        public array $partnerDelta,
    ) {
    }

    /** The smaller side's total: a deal is only as good as its worse side. */
    public function weakerSideTotal(): float
    {
        return min($this->user->total(), $this->partner->total());
    }

    public function bothSidesPositive(): bool
    {
        return $this->user->total() > 0.0 && $this->partner->total() > 0.0;
    }
}
