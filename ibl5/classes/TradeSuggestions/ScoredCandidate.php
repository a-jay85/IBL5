<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A candidate that leaves both sides better off by their own needs, with the
 * plain-language "why" for each side.
 */
final readonly class ScoredCandidate
{
    public function __construct(
        public TradeCandidate $candidate,
        public float $userGain,
        public float $partnerGain,
        public string $userReason,
        public string $partnerReason,
    ) {
    }
}
