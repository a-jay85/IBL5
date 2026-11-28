<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\GainWeights;

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

    /** True when the larger side gains more than `GainWeights::LOPSIDED_RATIO` times the smaller. */
    public function isLopsided(): bool
    {
        $u = $this->user->total();
        $p = $this->partner->total();

        return max($u, $p) > GainWeights::LOPSIDED_RATIO * min($u, $p);
    }
}
