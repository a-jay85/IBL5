<?php

declare(strict_types=1);

namespace FranchiseHistory\Contracts;

/**
 * FranchiseHistoryViewInterface - Contract for franchise history view rendering
 *
 * Defines methods for generating HTML output for franchise history.
 *
 * @phpstan-import-type FranchiseRow from FranchiseHistoryRepositoryInterface
 *
 * @see \FranchiseHistory\FranchiseHistoryView For the concrete implementation
 */
interface FranchiseHistoryViewInterface
{
    /**
     * Render the complete franchise history table
     *
     * @param array<int, FranchiseRow> $franchiseData Processed franchise data
     * @return string HTML output
     */
    public function render(array $franchiseData): string;
}
