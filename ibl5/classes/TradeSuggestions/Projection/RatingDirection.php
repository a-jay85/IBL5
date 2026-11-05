<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The F3 rating direction table.
 *
 * No `PlayerRating` class exists in the codebase, so this class owns the table.
 * `r_tvr` is turnover rate and `r_foul` is foul rate in the sim engine's rating
 * file, so a lower value is better. Every other rating in `Rating::ALL` is a
 * frequency or a success rate, where higher is better.
 */
final class RatingDirection
{
    /** @var list<string> */
    public const array LOWER_IS_BETTER = ['r_tvr', 'r_foul'];

    public static function higherIsBetter(string $key): bool
    {
        return !in_array($key, self::LOWER_IS_BETTER, true);
    }
}
