<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\TradeCandidate;

/**
 * What one generation pass produced. The counts feed the tuning page and the
 * PR evidence.
 */
final readonly class GenerationResult
{
    /**
     * @param list<TradeCandidate> $candidates legal candidates, in generation order
     * @param array<string, int> $rejectedByReason LegalityPreFilter reason => count
     * @param list<int> $partners partners that had at least one need pair
     * @param array<string, string> $shapeIdByKey CandidateKey::of => id of the first shape that yielded it, kept candidates only
     */
    public function __construct(
        public array $candidates,
        public int $rawCount,
        public int $prefilterRejected,
        public array $rejectedByReason,
        public array $partners,
        public bool $capped,
        public array $shapeIdByKey,
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0, 0, [], [], false, []);
    }
}
