<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

use PlrParser\PlrReconstructionResult;

/**
 * Reconstructs a missing .plr snapshot by overlaying box-score totals onto a base .plr.
 *
 * The base .plr comes from the nearest prior snapshot in the same season (template for ratings,
 * contracts, ordinals, unknown offsets, and career totals — which are frozen mid-season). The
 * following field groups are overwritten per player:
 *   - Regular-season stats (offsets 144-207) from `game_type = 1` sums
 *   - Playoff-season stats (offsets 208-267) from `game_type = 2` sums
 *   - Single-season highs + career-best highs (offsets 341-436) from MAX(...) and DD/TD counts
 *
 * Career totals (offsets 437-511) are intentionally preserved — the .plr format freezes them
 * at season start (verified: sim05 and sim07 real .plr files carry identical careerGP for every
 * player).
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
