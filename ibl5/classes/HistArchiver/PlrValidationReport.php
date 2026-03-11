<?php

declare(strict_types=1);

namespace HistArchiver;

/**
 * Value object representing the result of comparing ibl_hist against box score aggregates.
 */
final class PlrValidationReport
{
    /**
     * @param list<array{pid: int, name: string, column: string, hist_value: int, box_score_value: int}> $discrepancies
     */
    public function __construct(
        public readonly int $totalPlayers,
        public readonly int $matchCount,
        public readonly array $discrepancies,
    ) {
    }

    public function getDiscrepancyCount(): int
    {
        return count($this->discrepancies);
    }
}
