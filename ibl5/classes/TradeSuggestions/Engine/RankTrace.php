<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Veto\VetoReport;

/**
 * Collects one outcome per scored deal while `DealRanker::select` runs. Create
 * one per `select` call; it is never shared between calls.
 */
final class RankTrace
{
    /** @var array<int, array{outcome: RankOutcome, veto: VetoReport, rank: ?int, detail: ?string}> */
    private array $entries = [];

    /**
     * @param int $index position of the deal in the ranker's input list
     * @param ?int $rank 1-based place among the passing deals
     * @param ?string $detail the validator's FAIL_* code or the side with no motive
     */
    public function record(int $index, RankOutcome $outcome, VetoReport $veto, ?int $rank, ?string $detail): void
    {
        $this->entries[$index] = ['outcome' => $outcome, 'veto' => $veto, 'rank' => $rank, 'detail' => $detail];
    }

    /**
     * @return array<int, array{outcome: RankOutcome, veto: VetoReport, rank: ?int, detail: ?string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
