<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * F5: which ratings drive each per-36 stat, and how strongly a rating change
 * moves the stat. Each rating point of mean change scales the stat by 1%.
 */
final class StatRatingMap
{
    /** @var array<string, list<string>> */
    public const array MAP = [
        'pts' => ['r_fga', 'r_fgp', 'r_3gp', 'r_ftp', 'oo', 'r_drive_off', 'po'],
        'reb' => ['r_orb', 'r_drb'],
        'ast' => ['r_ast'],
        'stl' => ['r_stl'],
        'blk' => ['r_blk'],
        'tov' => ['r_tvr'],
        'pf' => ['r_foul'],
    ];

    public const float SENSITIVITY = 0.01;

    /**
     * 1 + SENSITIVITY * mean(projected - base) over the stat's ratings, floored
     * at 0. An unmapped stat has factor 1.
     */
    public static function factor(string $stat, RatingSet $base, RatingSet $projected): float
    {
        $keys = self::MAP[$stat] ?? [];
        if ($keys === []) {
            return 1.0;
        }

        $sum = 0.0;
        foreach ($keys as $key) {
            $sum += $projected->get($key) - $base->get($key);
        }

        return max(0.0, 1.0 + self::SENSITIVITY * ($sum / count($keys)));
    }
}
