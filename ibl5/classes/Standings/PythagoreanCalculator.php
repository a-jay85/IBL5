<?php

declare(strict_types=1);

namespace Standings;

/**
 * PythagoreanCalculator - Derives a team's total points scored and points allowed
 * from raw season shooting totals.
 *
 * Stateless: holds no DB connection and no state. Used by StandingsRepository to
 * turn raw offense/defense shooting rows into the {pointsScored, pointsAllowed}
 * shape consumed by standings views. This computes the point totals only -- the
 * Pythagorean win-percentage formula itself lives in
 * \BasketballStats\StatsFormatter::calculatePythagoreanWinPercentage().
 */
class PythagoreanCalculator
{
    /**
     * Derive points scored and points allowed from raw shooting totals.
     *
     * @param array{off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int, ...<string, mixed>} $stats
     * @return array{pointsScored: int, pointsAllowed: int}
     */
    public function calculate(array $stats): array
    {
        $pointsScored = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['off_fgm'],
            $stats['off_ftm'],
            $stats['off_tgm']
        );

        $pointsAllowed = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['def_fgm'],
            $stats['def_ftm'],
            $stats['def_tgm']
        );

        return [
            'pointsScored' => $pointsScored,
            'pointsAllowed' => $pointsAllowed,
        ];
    }
}
