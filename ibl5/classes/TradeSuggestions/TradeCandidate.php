<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A one-for-one deal between the user's team and a partner. Each side holds at
 * most one player or one pick in v1.
 */
final readonly class TradeCandidate
{
    /**
     * @param list<int> $userPids
     * @param list<int> $userPickIds
     * @param list<int> $partnerPids
     * @param list<int> $partnerPickIds
     */
    public function __construct(
        public int $userTid,
        public int $partnerTid,
        public array $userPids,
        public array $userPickIds,
        public array $partnerPids,
        public array $partnerPickIds,
    ) {
    }
}
