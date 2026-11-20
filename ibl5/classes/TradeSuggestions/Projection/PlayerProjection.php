<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * Projects a player across the F2 horizon: ratings by the age curve, salary
 * by contract year, and per-36 production scaled by the mapped rating change.
 */
final class PlayerProjection
{
    /**
     * @return array<int, ProjectedSeason> keyed by offset 0..3
     */
    public static function project(ProjectionPlayer $p, RatingScale $scale): array
    {
        $base = $p->ratings;
        $clampedBase = self::clampAll($base, $scale);
        $basePer36 = self::basePer36($p);

        $seasons = [];
        foreach (Horizon::OFFSETS as $offset) {
            $ratings = $base;
            foreach (Rating::ALL as $key) {
                $projected = $base->get($key) + AgeCurve::cumulativeDelta($key, $p->age, $p->talent, $offset);
                $ratings = $ratings->with($key, $scale->clamp($key, $projected));
            }

            $per36 = [];
            foreach ($basePer36 as $stat => $value) {
                $per36[$stat] = $value * StatRatingMap::factor($stat, $clampedBase, $ratings);
            }

            $seasons[$offset] = new ProjectedSeason(
                offset: $offset,
                age: $p->ageAt($offset),
                salary: $p->salaryAt($offset),
                onRoster: $p->onRosterAt($offset),
                ratings: $ratings,
                per36: $per36,
            );
        }

        return $seasons;
    }

    /**
     * @return array<string, float>
     */
    private static function basePer36(ProjectionPlayer $p): array
    {
        $line = $p->production;
        if ($line === null) {
            return [];
        }

        return [
            'pts' => $line->pointsPer36(),
            'reb' => $line->reboundsPer36(),
            'ast' => $line->assistsPer36(),
            'stl' => self::per36($line->steals, $line->minutes),
            'blk' => self::per36($line->blocks, $line->minutes),
            'tov' => self::per36($line->turnovers, $line->minutes),
            'pf' => self::per36($p->productionFouls, $line->minutes),
        ];
    }

    private static function per36(int $total, int $minutes): float
    {
        if ($minutes === 0) {
            return 0.0;
        }

        return $total * 36 / $minutes;
    }

    private static function clampAll(RatingSet $ratings, RatingScale $scale): RatingSet
    {
        $clamped = $ratings;
        foreach (Rating::ALL as $key) {
            $clamped = $clamped->with($key, $scale->clamp($key, $ratings->get($key)));
        }

        return $clamped;
    }
}
