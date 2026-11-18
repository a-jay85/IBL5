<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

/**
 * The tuning page's work bound. The engine's own caps
 * (`CandidateBudget::MAX_RAW_CANDIDATES`, `DealRanker::MAX_VALIDATIONS`) bound
 * each team; these two bound the page. The time budget is checked between
 * teams, so the first team always completes.
 */
final class TuningBudget
{
    /** Rows shown per team: the 3 selected, up to 9 more eligible, then the first drops. */
    public const int MAX_ROWS_PER_TEAM = 60;

    /** Seconds after which no further team starts. */
    public const float TIME_BUDGET_SECONDS = 20.0;

    /**
     * @return array{max_rows_per_team: int, time_budget_seconds: float}
     */
    public static function toArray(): array
    {
        return [
            'max_rows_per_team' => self::MAX_ROWS_PER_TEAM,
            'time_budget_seconds' => self::TIME_BUDGET_SECONDS,
        ];
    }
}
