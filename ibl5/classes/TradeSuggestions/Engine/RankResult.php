<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * The ranker's output: the deals to show, the longer ordered list a refresh
 * can page through, and the gate counts.
 */
final readonly class RankResult
{
    /**
     * @param list<RankedDeal> $selected at most the ranker's limit
     * @param list<RankedDeal> $eligible at most `DealRanker::KEEP_TOP`, in rank order
     */
    public function __construct(
        public array $selected,
        public array $eligible,
        public RankDiagnostics $diagnostics,
    ) {
    }
}
