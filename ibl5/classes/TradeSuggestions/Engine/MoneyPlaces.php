<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use League\League;
use TradeSuggestions\Valuation\CapRoomAsset;

/**
 * The engine's one money-to-places exchange rate.
 *
 * Unit 2 already prices money against places in one spot:
 * `CapRoomAsset::overagePenalty` charges `MILD_OVERAGE_PLACES_PER_TENTH_CAP`
 * (0.5 places) per tenth of the hard cap (`League::HARD_CAP_MAX / 10` = 700
 * salary units) of later-season overage. Reusing that ratio makes a salary
 * unit of contract surplus and a salary unit of cap overage worth the same
 * places: 0.5 / 700 = 1/1400 places per unit.
 *
 * Sanity check: a mid round-1 pick from `PickWorth` (`R1_FLOOR` 1.0 plus half
 * of `R1_SPREAD` 3.0, about 2.5 places) trades level with about 3,500 units of
 * surplus, roughly two seasons of a fair mid-rotation contract's gap.
 */
final class MoneyPlaces
{
    public const float PLACES_PER_SALARY_UNIT = CapRoomAsset::MILD_OVERAGE_PLACES_PER_TENTH_CAP / (League::HARD_CAP_MAX / 10);

    public static function placesPerSalaryUnit(): float
    {
        return self::PLACES_PER_SALARY_UNIT;
    }

    public static function fromSalary(int $money): float
    {
        return $money * self::PLACES_PER_SALARY_UNIT;
    }
}
