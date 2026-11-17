<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Veto\DealView;

/**
 * A deal that passed every gate, with its 1-based place among the eligible
 * deals and the reason each side would say yes.
 */
final readonly class RankedDeal
{
    public function __construct(
        public ScoredDeal $deal,
        public DealView $view,
        public int $rank,
        public ?string $userReason,
        public ?string $partnerReason,
    ) {
    }
}
