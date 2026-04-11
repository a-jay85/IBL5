<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

use PlrParser\PlrReconstructionResult;

/**
 * Reconstructs a missing .plr snapshot by overlaying box-score season totals onto a base .plr.
 *
 * The base .plr comes from the nearest prior snapshot in the same season (template for ratings,
 * contracts, ordinals, and unknown offsets). Season-stat fields (offsets 144-207) are overwritten
 * per player with sums computed from ibl_box_scores through the target end date.
 *
 * We intentionally do NOT source from .car here because .car only regenerates at season end —
 * a mid-season .car is byte-identical to the pre-season snapshot and contains no in-progress data.
 */
interface PlrReconstructionServiceInterface
{
    /**
     * @param string $basePlrPath   Path to the nearest prior snapshot's .plr (template)
     * @param int    $seasonYear    ibl_box_scores.season_year — e.g. 2007 for the 2006-07 season
     * @param string $targetEndDate YYYY-MM-DD cutoff (inclusive) — the "as of" date of the missing snapshot
     * @param string $outputPlrPath Where to write the reconstructed .plr
     */
    public function reconstruct(
        string $basePlrPath,
        int $seasonYear,
        string $targetEndDate,
        string $outputPlrPath,
    ): PlrReconstructionResult;
}
