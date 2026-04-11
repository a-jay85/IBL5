<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Aggregates ibl_box_scores into per-player season-stat totals for .plr reconstruction.
 *
 * Used by PlrReconstructionService as the source of truth for in-progress season stats,
 * since .car files only get regenerated at season end (and are byte-identical across
 * all mid-season snapshots within the same season).
 */
interface PlrBoxScoreRepositoryInterface
{
    public const GAME_TYPE_REGULAR_SEASON = 1;
    public const GAME_TYPE_PLAYOFFS = 2;

    /**
     * Sum box-score stats per pid for a specific game type, through an end date.
     *
     * @param int $gameType Either GAME_TYPE_REGULAR_SEASON or GAME_TYPE_PLAYOFFS
     * @return array<int, array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>
     */
    public function sumStatsByGameTypeThroughDate(int $seasonYear, int $gameType, string $endDate): array;

    /**
     * Per-pid single-game maximums and double/triple-double counts for a season window.
     *
     * Used to reconstruct the season-highs block (offsets 341-363 in .plr). Double-double =
     * exactly 2 of {points>=10, rebounds>=10, assists>=10, steals>=10, blocks>=10}; triple+
     * = 3 or more. Both counts exclude DNP rows.
     *
     * @return array<int, array{high_pts: int, high_reb: int, high_ast: int, high_stl: int, high_blk: int, doubles: int, triples: int}>
     */
    public function getSingleGameMaximumsThroughDate(int $seasonYear, int $gameType, string $endDate): array;

    /**
     * Find the latest game date in a season (used for sanity bounds).
     *
     * @return string|null YYYY-MM-DD, or null if the season has no games yet
     */
    public function latestGameDate(int $seasonYear, int $gameType): ?string;

    /**
     * Cumulative per-date regular-season running totals for a single pid.
     *
     * Powers date-inference: given a base .plr's season stats for a known-active player,
     * walk this list until the cumulative totals match; that date is the base's "as of".
     * Hardcoded to `game_type = 1` — playoff-mode inference is not supported.
     *
     * @return list<array{date: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>
     */
    public function cumulativeRegularSeasonStatsByDate(int $pid, int $seasonYear): array;

    /**
     * All `ibl_sim_dates` End Date values that fall within a season window.
     *
     * Used to step from a known base end date to the next sim's end date.
     *
     * @return list<string> ordered ascending, one date per sim row
     */
    public function simEndDatesForSeason(int $seasonYear): array;
}
