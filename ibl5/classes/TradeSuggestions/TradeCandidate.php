<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A deal between the user's team and a partner: up to two players and/or picks
 * a side, as CandidateGenerator builds them.
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

    /**
     * Plain shape label, e.g. "2 players for 1 player" or "1 player + 1 pick for 1 player".
     */
    public function shape(): string
    {
        return self::sideLabel(count($this->userPids), count($this->userPickIds))
            . ' for '
            . self::sideLabel(count($this->partnerPids), count($this->partnerPickIds));
    }

    private static function sideLabel(int $players, int $picks): string
    {
        $parts = [];
        if ($players > 0) {
            $parts[] = $players . ($players === 1 ? ' player' : ' players');
        }
        if ($picks > 0) {
            $parts[] = $picks . ($picks === 1 ? ' pick' : ' picks');
        }

        return implode(' + ', $parts);
    }
}
