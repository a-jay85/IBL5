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
     * Used to reconstruct the single-season highs block in .plr: regular-season highs
     * (offsets 341-354) when called with `game_type = 1`, and playoff highs (355-364) when
     * called with `game_type = 2`. Double/triple-double counts are populated on both passes
     * but only written into the .plr for the regular-season pass — the format has no slots
     * for playoff double/triple counts.
     *
     * Double-double = exactly 2 of {points>=10, rebounds>=10, assists>=10, steals>=10,
     * blocks>=10}; triple+ = 3 or more. Both counts exclude DNP rows (gameMIN = 0).
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
     * Sum team box-score stats for regular season through an end date.
     *
     * Aggregates from ibl_box_scores_teams using ROW_NUMBER deduplication
     * (rn=1 = visitor row, rn=2 = home row). Returns stats keyed by team ID (1-28),
     * with keys matching PlrTeamRowLayout::REGULAR_SEASON_FIELD_MAP.
     *
     * @return array<int, array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>
     */
    public function sumTeamRegularSeasonStatsThroughDate(int $seasonYear, string $endDate): array;

    /**
     * Sum team box-score stats for playoffs through an end date.
     *
     * Same deduplication pattern as sumTeamRegularSeasonStatsThroughDate() but
     * for game_type=2. Keys match PlrTeamRowLayout::PLAYOFF_SEASON_FIELD_MAP.
     *
     * @return array<int, array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>
     */
    public function sumTeamPlayoffStatsThroughDate(int $seasonYear, string $endDate): array;

    /**
     * All `ibl_sim_dates` End Date values that fall within a season window.
     *
     * Used to step from a known base end date to the next sim's end date.
     *
     * @return list<string> ordered ascending, one date per sim row
     */
    public function simEndDatesForSeason(int $seasonYear): array;
}
