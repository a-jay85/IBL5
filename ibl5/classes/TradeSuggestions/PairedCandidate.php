<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A candidate deal plus the pairing and the two needs it answers. dumpPids
 * names the players the deal sheds as salary; they never count as payment.
 */
final readonly class PairedCandidate
{
    /**
     * @param list<int> $dumpPids
     */
    public function __construct(
        public TradeCandidate $candidate,
        public Pairing $pairing,
        public Need $userNeed,
        public Need $partnerNeed,
        public array $dumpPids = [],
    ) {
    }
}
