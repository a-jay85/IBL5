<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff\Contracts;

use TrainingCampRatingsDiff\RatingRow;

/**
 * TrainingCampRatingsDiffServiceInterface — business logic for the ratings diff page.
 */
interface TrainingCampRatingsDiffServiceInterface
{
    /**
     * Returns player rating rows sorted by largest single rating change.
     *
     * Real rows (player has baseline) come first, sorted by maxAbsDelta DESC,
     * then sumAbsDelta DESC, then lastname ASC. New players (no baseline) follow,
     * sorted lastname ASC.
     *
     * Baseline year is $overrideYear if provided, else currentSeasonEndingYear − 1.
     * Baseline phase for that year is resolved via the repository (end-of-season →
     * mid-season → null). Returns an empty array when no baseline phase is found.
     *
     * @return list<RatingRow>
     */
    public function getDiffs(?int $overrideYear = null, ?int $filterTid = null, string $filterStatus = ''): array;

    /**
     * Returns the resolved baseline year: $overrideYear if provided, else
     * currentSeasonEndingYear − 1. Returns null when no snapshot phase exists
     * for the resolved year (i.e. no usable baseline is available).
     */
    public function getBaselineYear(?int $overrideYear = null): ?int;
}
