<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use TradeSuggestions\TeamMode;

/**
 * F11 pick worth in weighted rank places per mode (F8), so it adds to the same
 * side total as lineup and profile movements. Pure.
 *
 * - The first overall pick is worth four bottom-tier places (a starter-grade
 *   upgrade at one slot) and a late first is worth one. A second is worth a fifth
 *   to four-fifths of a place (a rotation body). The ratios mirror the v2
 *   `PickValuer` spread, rescaled into rank places.
 * - `UNKNOWN_SLOT_T`: a pick whose team is not in the projection, or a pick more
 *   than zero years away, prices at the league middle.
 * - `YEARS_AWAY_WEIGHTS`: rebuilders want near picks, because prospects arrive
 *   inside their window. WinNow discounts every pick by half and more as it
 *   recedes.
 * - `GLUT_FACTOR`: a pick the team has no roster spot for inside its contract
 *   window keeps a quarter of its worth. It can be traded on, but not played.
 */
final class PickWorth
{
    public const float R1_FLOOR_PLACES = 1.0;
    public const float R1_SPREAD_PLACES = 3.0;
    public const float R2_FLOOR_PLACES = 0.2;
    public const float R2_SPREAD_PLACES = 0.6;
    public const float UNKNOWN_SLOT_T = 0.5;
    public const float GLUT_FACTOR = 0.25;

    /** @var array<string, list<float>> keyed like `GainWeights::SEASON_WEIGHTS`, indexed by years away */
    public const array YEARS_AWAY_WEIGHTS = [
        'win_now' => [0.5, 0.4, 0.3, 0.2],
        'middle' => [0.75, 0.65, 0.55, 0.45],
        'rebuild' => [1.0, 0.9, 0.8, 0.7],
    ];

    /**
     * Position 1 gives 1.0 and the last position gives 0.0. A one-team index
     * cannot divide.
     */
    public static function slotT(?int $position, int $teamCount): float
    {
        if ($position === null || $teamCount < 2) {
            return self::UNKNOWN_SLOT_T;
        }

        return ($teamCount - $position) / ($teamCount - 1);
    }

    public static function basePlaces(int $round, float $t): float
    {
        return match ($round) {
            1 => self::R1_FLOOR_PLACES + self::R1_SPREAD_PLACES * $t,
            2 => self::R2_FLOOR_PLACES + self::R2_SPREAD_PLACES * $t,
            default => 0.0,
        };
    }

    /**
     * A negative `$yearsAway` is a past draft and is worth 0.0. Years beyond the
     * table use its last entry.
     */
    public static function yearsAwayWeight(TeamMode $mode, int $yearsAway): float
    {
        if ($yearsAway < 0) {
            return 0.0;
        }

        $weights = self::YEARS_AWAY_WEIGHTS[GainWeights::modeKey($mode)];

        return $weights[min($yearsAway, count($weights) - 1)];
    }

    /**
     * `$openSpotsInWindow` is `Team::ROSTER_SPOTS_MAX` minus the players under
     * contract at the offset the pick's player would arrive. `$pickOrdinal` is
     * this pick's 1-based position among the owner's picks, ordered by
     * `basePlaces` desc, then year asc, then pickid asc. A full roster gluts
     * every pick. Unit 3 computes both.
     */
    public static function glutFactor(int $pickOrdinal, int $openSpotsInWindow): float
    {
        return $pickOrdinal > max(0, $openSpotsInWindow) ? self::GLUT_FACTOR : 1.0;
    }

    public static function worth(
        int $round,
        int $yearsAway,
        int $originalTid,
        DraftSlotIndex $index,
        TeamMode $mode,
        int $openSpotsInWindow,
        int $pickOrdinal,
    ): float {
        $position = $yearsAway === 0 ? $index->position($round, $originalTid) : null;

        return self::basePlaces($round, self::slotT($position, $index->teamCount))
            * self::yearsAwayWeight($mode, $yearsAway)
            * self::glutFactor($pickOrdinal, $openSpotsInWindow);
    }
}
