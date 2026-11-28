<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning\Contracts;

use TradeSuggestions\Tuning\TuningFeedback;

/**
 * Reads every GM's stored hides for the admin-only tuning page. Only the
 * tuning route constructs it; the GM panel reads through
 * `Feedback\FeedbackRepository`, which sees only the acting GM's keys.
 */
interface TuningFeedbackRepositoryInterface
{
    /**
     * Every hide for the given teams, by tid ascending, newest first within a
     * team. An empty list runs no query.
     *
     * @param list<int> $tids validated team ids, never raw request values
     * @return list<TuningFeedback>
     */
    public function forTeams(array $tids): array;
}
