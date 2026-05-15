<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

interface DepthChartEntryServiceInterface
{
    /**
     * Compute the dVar58 in-game quality score for lineup selection.
     *
     * Exact formula from decompiled JSB 5.60 (FUN_004cfa50, lines 90899-90908).
     * All constants resolved from binary .rdata section:
     *
     * dVar58 = (TERM_A + TERM_B + TERM_C) / GP
     *
     * TERM_A (defense):  (OD + DD + PD + TD − 20) × 0.25 × GS × (1/48)
     * TERM_B (production): (AST×0.8 + ORB×(2/3) + (DRB−ORB)×(1/3) + STL − TVR + BLK) × 0.75
     * TERM_C (scoring):  ((FTM−2GM)×(1/6) + (MIN + FTA − (2GA−MIN)×(2/3) + 2GM − FTM×0.5)) × 1.5
     *
     * @param array<string, mixed> $player Player row from `ibl_plr`
     */
    public function computeQualityScore(array $player): float;

    /**
     * Build a PID-keyed override map from a submission's raw POST data.
     *
     * Called after a validation-failure redirect so `displayForm()` can
     * re-render the form pre-populated with the user's submitted values
     * instead of the DB values.
     *
     * @param array<array-key, mixed> $postData Raw $_POST payload.
     * @return array<int, array{dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int}>
     */
    public function buildFormOverride(array $postData): array;
}
