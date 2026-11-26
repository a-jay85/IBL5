<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The F4 age curve. Rating change per year, from age alone.
 *
 * | family   | grow             | hold  | decline starts | first-year delta        | cap  |
 * |----------|------------------|-------|----------------|-------------------------|------|
 * | athletic | <= 24 (+2.0/yr)  | 25-27 | 28             | -1.0, steeper by 0.5/yr | -4.0 |
 * | skill    | <= 26 (+1.5/yr)  | 27-30 | 31             | -0.5, steeper by 0.5/yr | -3.0 |
 *
 * `talent` scales growth only while age <= 24, by a factor in [0.5, 1.5]. It
 * never scales decline. `skill`, `intangibles`, `peak` and `stamina` play no part.
 */
final class AgeCurve
{
    public const int ATHLETIC_GROW_UNTIL = 24;
    public const int ATHLETIC_HOLD_UNTIL = 27;
    public const float ATHLETIC_GROWTH = 2.0;
    public const float ATHLETIC_DECLINE_BASE = -1.0;
    public const float ATHLETIC_DECLINE_STEP = -0.5;
    public const float ATHLETIC_DECLINE_CAP = -4.0;

    public const int SKILL_GROW_UNTIL = 26;
    public const int SKILL_HOLD_UNTIL = 30;
    public const float SKILL_GROWTH = 1.5;
    public const float SKILL_DECLINE_BASE = -0.5;
    public const float SKILL_DECLINE_STEP = -0.5;
    public const float SKILL_DECLINE_CAP = -3.0;

    public const int TALENT_MODULATES_UNTIL = 24;
    public const float TALENT_LEAGUE_MIDPOINT = 50.0;
    public const float TALENT_FACTOR_MIN = 0.5;
    public const float TALENT_FACTOR_MAX = 1.5;
    public const float TALENT_SPAN = 50.0;

    /**
     * The delta applied when a player of `$ageAtStepStart` ages one year.
     */
    public static function stepDelta(string $key, int $ageAtStepStart, int $talent): float
    {
        if (Rating::family($key) === Rating::FAMILY_ATHLETIC) {
            $growUntil = self::ATHLETIC_GROW_UNTIL;
            $holdUntil = self::ATHLETIC_HOLD_UNTIL;
            $growth = self::ATHLETIC_GROWTH;
            $base = self::ATHLETIC_DECLINE_BASE;
            $step = self::ATHLETIC_DECLINE_STEP;
            $cap = self::ATHLETIC_DECLINE_CAP;
        } else {
            $growUntil = self::SKILL_GROW_UNTIL;
            $holdUntil = self::SKILL_HOLD_UNTIL;
            $growth = self::SKILL_GROWTH;
            $base = self::SKILL_DECLINE_BASE;
            $step = self::SKILL_DECLINE_STEP;
            $cap = self::SKILL_DECLINE_CAP;
        }

        if ($ageAtStepStart <= $growUntil) {
            if ($ageAtStepStart <= self::TALENT_MODULATES_UNTIL) {
                return $growth * self::talentFactor($talent);
            }

            return $growth;
        }
        if ($ageAtStepStart <= $holdUntil) {
            return 0.0;
        }

        return max($cap, $base + $step * ($ageAtStepStart - $holdUntil - 1));
    }

    /**
     * The summed delta over ages `$age .. $age + $offset - 1`. Offset 0 is 0.0.
     */
    public static function cumulativeDelta(string $key, int $age, int $talent, int $offset): float
    {
        $total = 0.0;
        for ($i = 0; $i < $offset; $i++) {
            $total += self::stepDelta($key, $age + $i, $talent);
        }

        return $total;
    }

    public static function talentFactor(int $talent): float
    {
        $factor = 1.0 + ($talent - self::TALENT_LEAGUE_MIDPOINT) / self::TALENT_SPAN;

        return max(self::TALENT_FACTOR_MIN, min(self::TALENT_FACTOR_MAX, $factor));
    }
}
