<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Field-offset table for the franchise team-summary rows in a JSB .plr file.
 *
 * Background: a `.plr` file contains 1441 player records (607 bytes each, ordinals
 * 0..1440) followed by 28 franchise team-summary rows (608 bytes each, ordinals
 * 1441..1468) and ~133 trailing pid=0 padding rows. The 28 franchise rows hold
 * cumulative regular-season totals for each IBL franchise as of the file's snapshot
 * date — the same totals that `ibl_box_scores_teams` aggregates per game.
 *
 * `PlrFileWriter::FIELD_MAP` covers only the player-record offsets; the team-row
 * layout was undocumented until this PR. Offsets below were reverse-engineered by
 * (a) byte-diffing `06-07_11_reg-sim05.zip` against `06-07_28_reg-sim22.zip` for
 * the Celtics row to isolate cumulative-counter regions, then (b) cross-referencing
 * each candidate value against the database aggregate
 * `SUM(...) FROM ibl_box_scores_teams WHERE (vTID=1 OR hTID=1) AND game_type=1
 * AND Date <= sim05_cutoff` (using a `ROW_NUMBER` window to dedupe the visitor/home
 * pair so the per-team contribution is counted exactly once). All 15 fields below
 * matched byte-for-byte against the database for sim05 Celtics; the same offsets
 * (with different values) match in sim22 against the corresponding cutoff.
 *
 * **Ranges still marked unknown** in the row remain byte-passthrough on writeback —
 * the goal of this PR is to land a validated subset, not to over-claim coverage.
 * Suspected purposes for the unknown ranges (NOT yet validated):
 * - bytes 208..267 (60 bytes): likely playoff team totals, mirroring the player
 *   record's playoff block at offsets 208..267.
 * - bytes 320..511: streak / record / opponent-allowed totals (uncertain).
 * - bytes 540..607: trailing tail block, possibly per-quarter point distribution.
 *
 * The franchise team rows live at fixed ordinals 1441..1468, indexed in the same
 * order as `ibl_team_info` rows 1..28. The team_name embedded in the binary is
 * sometimes stale (the user has seen incorrect names in older snapshots), so the
 * authoritative team identity comes from the row's *position* (ordinal − 1440) and
 * is cross-validated against the stat-sum from `ibl_box_scores_teams` filtered by
 * `visitorTeamID OR homeTeamID`.
 */
class PlrTeamRowLayout
{
    public const FIRST_TEAM_ORDINAL = 1441;
    public const LAST_TEAM_ORDINAL = 1468;
    public const FRANCHISE_ROW_LENGTH = 608;

    /**
     * Validated regular-season cumulative-stat offsets for franchise team rows.
     *
     * Format: [offset, width]. Each value is a right-justified ASCII integer
     * padded with leading spaces, identical to player-record field formatting.
     *
     * @var array<string, array{int, int}>
     */
    public const REGULAR_SEASON_FIELD_MAP = [
        'gp' => [148, 4],
        'gpAlt' => [152, 4],
        'twoGM' => [156, 4],
        'twoGA' => [160, 4],
        'ftm' => [164, 4],
        'fta' => [168, 4],
        'threeGM' => [172, 4],
        'threeGA' => [176, 4],
        'orb' => [180, 4],
        'drb' => [184, 4],
        'ast' => [188, 4],
        'stl' => [192, 4],
        'tov' => [196, 4],
        'blk' => [200, 4],
        'pf' => [204, 4],
    ];

    /**
     * Map a 1-indexed franchise position (1..28) to the .plr ordinal.
     */
    public static function franchiseOrdinal(int $teamId): int
    {
        return self::FIRST_TEAM_ORDINAL + ($teamId - 1);
    }

    /**
     * Whether the given ordinal is one of the 28 franchise team rows.
     */
    public static function isFranchiseOrdinal(int $ordinal): bool
    {
        return $ordinal >= self::FIRST_TEAM_ORDINAL && $ordinal <= self::LAST_TEAM_ORDINAL;
    }
}
