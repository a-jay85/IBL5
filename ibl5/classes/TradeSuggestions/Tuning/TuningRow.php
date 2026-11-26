<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use TradeSuggestions\Engine\RankOutcome;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\TradeCandidate;

/**
 * One scored deal on the tuning page, with the ranker's outcome for it.
 */
final readonly class TuningRow
{
    /**
     * @param list<string> $firedVetoIds
     */
    public function __construct(
        public int $tid,
        public int $partnerTid,
        public string $shapeId,
        public TradeCandidate $candidate,
        public string $dealKey,
        public string $coreKey,
        public SideGain $user,
        public SideGain $partner,
        public float $weakerSideTotal,
        public RankOutcome $outcome,
        public ?int $rank,
        public array $firedVetoIds,
        public ?string $detail,
        public bool $exposureEligible,
        public ?TuningFeedback $feedback,
    ) {
    }
}
