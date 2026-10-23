<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A candidate deal plus the pairing and the two needs it answers.
 */
final readonly class PairedCandidate
{
    public function __construct(
        public TradeCandidate $candidate,
        public Pairing $pairing,
        public Need $userNeed,
        public Need $partnerNeed,
    ) {
    }
}
