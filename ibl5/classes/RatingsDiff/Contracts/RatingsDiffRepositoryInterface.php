<?php

declare(strict_types=1);

namespace RatingsDiff\Contracts;

/**
 * RatingsDiffRepositoryInterface — data access for ratings diff page.
 */
interface RatingsDiffRepositoryInterface
{
    /**
     * Returns the latest season_year that has end-of-season snapshots, or null if none exist.
     */
    public function getLatestEndOfSeasonYear(): ?int;

    /**
     * Returns joined ibl_plr + ibl_plr_snapshots rows for all non-retired players.
     *
     * Snapshot columns are prefixed with `s_`. Rows where the player has no
     * snapshot will have null values in all `s_*` columns (LEFT JOIN miss).
     *
     * @return list<array<string, mixed>>
     */
    public function getDiffRows(int $baselineYear, ?int $filterTid = null): array;
}
