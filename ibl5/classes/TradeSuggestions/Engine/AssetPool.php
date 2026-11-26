<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * The assets one partner pair offers the deal shapes. Every shape reads only
 * this pool and the snapshot, so a shape can never widen the budget.
 */
final readonly class AssetPool
{
    /**
     * @param list<int> $userPids at most `CandidateBudget::PLAYERS_PER_PARTNER`, most relevant first (`SurplusDeficitMatcher::assetsFor`)
     * @param list<int> $partnerPids at most `CandidateBudget::PLAYERS_PER_PARTNER`, most relevant first
     * @param list<PickAsset> $userPicks owned round 1-2 picks
     * @param list<PickAsset> $partnerPicks owned round 1-2 picks
     * @param list<NeedPair> $needPairs
     */
    public function __construct(
        public array $userPids = [],
        public array $partnerPids = [],
        public array $userPicks = [],
        public array $partnerPicks = [],
        public array $needPairs = [],
    ) {
    }
}
