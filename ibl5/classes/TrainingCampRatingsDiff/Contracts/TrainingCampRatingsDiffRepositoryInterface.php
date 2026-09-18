<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff\Contracts;

/**
 * TrainingCampRatingsDiffRepositoryInterface — data access for ratings diff page.
 */
interface TrainingCampRatingsDiffRepositoryInterface
{
    /**
     * Returns the snapshot phase to use as the baseline for the given season year.
     *
     * Returns 'end-of-season' if any snapshot rows exist for (year, 'end-of-season').
     * Returns 'mid-season'   if no end-of-season rows exist but mid-season rows do.
     * Returns null           if neither phase has rows for that year.
     *
     * Never falls back to a different year.
     */
    public function getBaselinePhase(int $seasonYear): ?string;

    /**
     * Returns joined ibl_plr + ibl_plr_snapshots rows for all non-retired players.
     *
     * Snapshot columns are prefixed with `s_`. Rows where the player has no
     * snapshot will have null values in all `s_*` columns (LEFT JOIN miss).
     *
     * @return list<array<string, mixed>>
     */
    public function getDiffRows(int $baselineYear, string $baselinePhase, ?int $filterTid = null, string $filterStatus = ''): array;
}
