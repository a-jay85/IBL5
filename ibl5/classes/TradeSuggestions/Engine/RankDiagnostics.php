<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * How many deals each `DealRanker` gate dropped. Each deal is counted once,
 * in the first gate it failed.
 */
final readonly class RankDiagnostics
{
    /**
     * @param array<string, int> $vetoedById veto id => deals it fired on; every id in the set is present
     * @param array<string, int> $validatorRejections FAIL_* detail => deals
     */
    public function __construct(
        public int $generated,
        public int $notBothPositive,
        public int $lopsided,
        public int $exposure,
        public int $vetoed,
        public array $vetoedById,
        public int $validated,
        public array $validatorRejections,
        public int $noMotive,
        public int $selected,
    ) {
    }

    /**
     * @return array{generated: int, not_both_positive: int, lopsided: int, exposure: int, vetoed: int, vetoed_by_id: array<string, int>, validated: int, validator_rejections: array<string, int>, no_motive: int, selected: int}
     */
    public function toArray(): array
    {
        return [
            'generated' => $this->generated,
            'not_both_positive' => $this->notBothPositive,
            'lopsided' => $this->lopsided,
            'exposure' => $this->exposure,
            'vetoed' => $this->vetoed,
            'vetoed_by_id' => $this->vetoedById,
            'validated' => $this->validated,
            'validator_rejections' => $this->validatorRejections,
            'no_motive' => $this->noMotive,
            'selected' => $this->selected,
        ];
    }
}
