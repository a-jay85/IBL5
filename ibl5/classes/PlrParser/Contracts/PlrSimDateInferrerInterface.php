<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Infers the "as of" end date for a .plr snapshot by matching its season stats
 * against cumulative ibl_box_scores for a reference player.
 *
 * Why: the CLI would otherwise need the user to hand-roll `--target-end-date`
 * per snapshot. The inference lets `--snapshot=06-07/..._reg-sim06.zip` auto-resolve
 * the right date by looking at the nearest prior .plr.
 */
interface PlrSimDateInferrerInterface
{
    /**
     * Given a base .plr file, infer the end date it was snapshotted at.
     *
     * Walks the top minutes-played pid's cumulative box-score totals and returns
     * the date where the cumulative sums equal the base's season stats.
     *
     * @return string|null YYYY-MM-DD, or null if no match found (data drift or bad base)
     */
    public function inferBaseEndDate(string $basePlrPath, int $seasonYear): ?string;

    /**
     * Given a known base end date, return the next `ibl_sim_dates` End Date in the season.
     *
     * Steps forward `$stepsAhead` sim rows (typically 1). Returns null if past end of season.
     */
    public function inferNextSimEndDate(string $baseEndDate, int $seasonYear, int $stepsAhead = 1): ?string;

    /**
     * Helper for error diagnostics — returns the latest box-score date for a season, or null
     * if the season has zero rows (e.g., not yet ingested into ibl_box_scores).
     */
    public function getBoxScoreCoverageForSeason(int $seasonYear): ?string;
}
