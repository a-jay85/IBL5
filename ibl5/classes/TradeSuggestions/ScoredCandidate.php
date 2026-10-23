<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A candidate that leaves both sides better off by their own needs, with the
 * plain-language "why" for each side.
 *
 * weakerSideGain is min(userGain, partnerGain) from the judge. paired and
 * judgment carry the deal's motive and numbers for the reason text.
 */
final readonly class ScoredCandidate
{
    public function __construct(
        public TradeCandidate $candidate,
        public int $weakerSideGain,
        public string $userReason,
        public string $partnerReason,
        public ?PairedCandidate $paired = null,
        public ?Judgment $judgment = null,
    ) {
    }
}
