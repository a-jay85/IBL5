<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The rating vocabulary the v3 engine reads from `ibl_plr`.
 *
 * Every key is an exact `ibl_plr` column name. Ratings are split into two
 * age-curve families (F4): athletic ratings peak early, skill ratings late.
 */
final class Rating
{
    /** @var list<string> */
    public const array ATHLETIC = ['r_drive_off', 'r_trans_off', 'dd', 'td', 'r_stl', 'r_blk', 'r_orb', 'r_drb'];

    /** @var list<string> */
    public const array SKILL = ['r_fgp', 'r_ftp', 'r_3gp', 'r_ast', 'oo', 'po', 'od', 'pd', 'r_tvr', 'r_foul', 'r_fga', 'r_fta', 'r_3ga'];

    /** @var list<string> */
    public const array ALL = [...self::ATHLETIC, ...self::SKILL];

    /** @var list<string> */
    public const array ODPT = ['oo', 'od', 'dd', 'td', 'po', 'pd', 'r_drive_off', 'r_trans_off'];

    public const string FAMILY_ATHLETIC = 'athletic';
    public const string FAMILY_SKILL = 'skill';

    /**
     * @return 'athletic'|'skill'
     */
    public static function family(string $key): string
    {
        if (in_array($key, self::ATHLETIC, true)) {
            return self::FAMILY_ATHLETIC;
        }
        if (in_array($key, self::SKILL, true)) {
            return self::FAMILY_SKILL;
        }

        throw new \InvalidArgumentException("Unknown rating key: {$key}");
    }

    public static function isKnown(string $key): bool
    {
        return in_array($key, self::ALL, true);
    }
}
