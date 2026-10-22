<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * What one asset is worth to a team in a given mode. Pure.
 *
 * Player base value grows with the square of ovr above a replacement level, so
 * one 70 is worth far more than two 57s. Win-now teams pay for the present
 * (age barely matters until 31); rebuilding teams pay for youth, discount
 * veterans hard, count a veteran's salary owed after this season as a cost,
 * and discount anyone whose deal ends this season (no years of control). Picks use
 * PickValuer's value, scaled up for rebuilding teams and down for win-now ones.
 */
final class AssetValuer
{
    public const int REPLACEMENT_OVR = 45;
    public const float SCALE = 10.0;
    public const float REBUILD_SALARY_COST = 0.003;
    public const int REBUILD_SALARY_COST_MIN_AGE = 27;
    public const float REBUILD_EXPIRING_FACTOR = 0.75;
    public const float REBUILD_PICK_FACTOR = 1.3;
    public const float WIN_NOW_PICK_FACTOR = 0.6;

    public static function baseValue(int $ovr): float
    {
        $above = max(0, $ovr - self::REPLACEMENT_OVR);

        return $above * $above / self::SCALE;
    }

    public static function ageFactor(int $age, TeamMode $mode): float
    {
        return match ($mode) {
            TeamMode::WinNow => match (true) {
                $age <= 30 => 1.0,
                $age <= 32 => 0.85,
                default => 0.7,
            },
            TeamMode::Rebuild => match (true) {
                $age <= 23 => 1.3,
                $age <= 26 => 1.0,
                $age <= 29 => 0.6,
                default => 0.35,
            },
        };
    }

    public function playerValue(SuggestionPlayer $p, TeamMode $mode): float
    {
        $value = self::baseValue($p->ovr) * self::ageFactor($p->age, $mode);
        if ($mode === TeamMode::Rebuild) {
            if ($p->age >= self::REBUILD_SALARY_COST_MIN_AGE) {
                $value -= $p->futureSalary * self::REBUILD_SALARY_COST;
            }
            if ($p->yearsRemaining <= 1) {
                $value *= self::REBUILD_EXPIRING_FACTOR;
            }
        }

        return $value;
    }

    public function pickValue(SuggestionPick $pick, TeamMode $mode): float
    {
        return $pick->value * ($mode === TeamMode::Rebuild ? self::REBUILD_PICK_FACTOR : self::WIN_NOW_PICK_FACTOR);
    }
}
