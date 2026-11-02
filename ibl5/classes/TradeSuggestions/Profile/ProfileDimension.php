<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Projection\RatingDirection;

/**
 * The 31 team-profile dimensions: every rating key (ODPT included), the
 * per-36 counting stats, the shooting rates, and 3PA per FGA.
 */
final class ProfileDimension
{
    public const array STATS = ['pts', 'reb', 'ast', 'stl', 'blk', 'tov'];
    public const array RATES = ['fg_pct', 'tp_pct', 'ft_pct', 'tpa_rate'];
    public const array KEYS = [...Rating::ALL, ...self::STATS, ...self::RATES];
    public const array LOWER_IS_BETTER = [...RatingDirection::LOWER_IS_BETTER, 'tov'];
    public const array OFFENSE = ['oo', 'r_drive_off', 'po', 'r_trans_off'];
    public const array DEFENSE = ['od', 'dd', 'pd', 'td'];
    public const string OFFENSE_MEAN = 'offense_mean';
    public const string DEFENSE_MEAN = 'defense_mean';

    public static function lowerIsBetter(string $key): bool
    {
        return in_array($key, self::LOWER_IS_BETTER, true);
    }
}
