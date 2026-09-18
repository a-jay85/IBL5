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
     * Prefers the latest playoffs snapshot ('finals', 'playoffs', then the conf-finals
     * and playoffs-rd* archive phases, latest round first). Falls back to
     * 'end-of-season', then 'mid-season'. Returns null if none of those phases has
     * rows for that year.
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
