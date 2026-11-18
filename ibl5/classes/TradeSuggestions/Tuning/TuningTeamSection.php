<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use TradeSuggestions\Engine\GenerationResult;
use TradeSuggestions\Engine\RankDiagnostics;
use TradeSuggestions\TeamMode;

/**
 * One team's engine run on the tuning page.
 */
final readonly class TuningTeamSection
{
    /**
     * @param list<int> $eligiblePartners
     * @param list<TuningRow> $rows at most `TuningBudget::MAX_ROWS_PER_TEAM`
     * @param list<TuningFeedback> $unmatchedFeedback the team's hides that matched no deal in this run
     */
    public function __construct(
        public int $tid,
        public string $teamName,
        public TeamMode $mode,
        public array $eligiblePartners,
        public GenerationResult $generation,
        public RankDiagnostics $diagnostics,
        public array $rows,
        public int $omittedRows,
        public array $unmatchedFeedback = [],
    ) {
    }
}
