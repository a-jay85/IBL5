<?php

declare(strict_types=1);

namespace RecordHolders;

/**
 * RecordStatDefinitions - Canonical source for record-holder stat definitions.
 *
 * Both RecordHoldersService and RecordBreakingDetector previously declared the
 * same player/team single-game SQL stat expressions and the same
 * {regularSeason, playoffs, heat} → game_type date filters, in different shapes
 * (RecordHoldersService uses `recordLabel => expression`; RecordBreakingDetector
 * uses `key => [expression, unit, ...]`). This class is the single definition
 * both consumers map from, so the SQL expressions, display units, record labels,
 * and date filters live in exactly one place.
 *
 * The two consumers keep their distinct array shapes — only the source of the
 * literals is centralized.
 */
final class RecordStatDefinitions
{
    /**
     * Ordered canonical stat definitions, keyed by the RecordBreakingDetector
     * player-stat key. Iteration order is significant: both consumers rely on it
     * to reproduce their existing array ordering.
     *
     * Per-entry fields:
     * - `teamKey`:     RecordBreakingDetector team-stat key, or null for a
     *                  player-only stat (turnovers). A non-null `teamKey` also
     *                  marks membership in the 8-stat team subset.
     * - `expression`:  SQL expression (a box-score column or a precomputed
     *                  `bs.calc_*` value).
     * - `unit`:        plural display noun used in record announcements.
     * - `recordLabel`: RecordHoldersService category label.
     */
    public const STATS = [
        'points'    => ['teamKey' => 'team_points',   'expression' => 'bs.calc_points',   'unit' => 'points',         'recordLabel' => 'Most Points in a Single Game'],
        'rebounds'  => ['teamKey' => 'team_rebounds', 'expression' => 'bs.calc_rebounds', 'unit' => 'rebounds',       'recordLabel' => 'Most Rebounds in a Single Game'],
        'assists'   => ['teamKey' => 'team_assists',  'expression' => 'bs.game_ast',      'unit' => 'assists',        'recordLabel' => 'Most Assists in a Single Game'],
        'steals'    => ['teamKey' => 'team_steals',   'expression' => 'bs.game_stl',      'unit' => 'steals',         'recordLabel' => 'Most Steals in a Single Game'],
        'blocks'    => ['teamKey' => 'team_blocks',   'expression' => 'bs.game_blk',      'unit' => 'blocks',         'recordLabel' => 'Most Blocks in a Single Game'],
        'turnovers' => ['teamKey' => null,            'expression' => 'bs.game_tov',      'unit' => 'turnovers',      'recordLabel' => 'Most Turnovers in a Single Game'],
        'fg_made'   => ['teamKey' => 'team_fg_made',  'expression' => 'bs.calc_fg_made',  'unit' => 'field goals',    'recordLabel' => 'Most Field Goals in a Single Game'],
        'ft_made'   => ['teamKey' => 'team_ft_made',  'expression' => 'bs.game_ftm',      'unit' => 'free throws',    'recordLabel' => 'Most Free Throws in a Single Game'],
        '3pt_made'  => ['teamKey' => 'team_3pt_made', 'expression' => 'bs.game_3gm',      'unit' => 'three pointers', 'recordLabel' => 'Most Three Pointers in a Single Game'],
    ];

    /**
     * SQL WHERE-clause fragments selecting each game type by box-score game_type.
     *
     * Regular season = 1, Playoffs = 2, HEAT = 3.
     */
    public const DATE_FILTERS = [
        'regularSeason' => 'bs.game_type = 1',
        'playoffs'      => 'bs.game_type = 2',
        'heat'          => 'bs.game_type = 3',
    ];
}
