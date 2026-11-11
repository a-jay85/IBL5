<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\ProjectionPlayer;

/**
 * F9 contract money per season. Every output is money or a boolean; no method
 * returns a per-player quality figure.
 */
final class ContractValue
{
    /**
     * Two-thirds of one MLE season (`ContractRules::MLE_OFFERS[0]` = 450) and
     * about 6% of the soft cap. An overpay beyond the current season that adds
     * up to less is a normal mispricing; more is money a GM would pay to move.
     * It is not tuned to any fixture.
     */
    public const int BAD_CONTRACT_FLOOR = 300;

    private const int LAST_SALARY_YEAR = 6;

    /**
     * Off the books is 0, never positive.
     */
    public static function surplusAt(int $fairSalary, int $salary): int
    {
        return $salary > 0 ? $fairSalary - $salary : 0;
    }

    /**
     * @param array<int, int> $salaryByOffset
     * @param array<int, int> $fairByOffset
     * @return array<int, int> keyed by `Horizon::OFFSETS`
     */
    public static function surplusBySeason(array $salaryByOffset, array $fairByOffset): array
    {
        $surplus = [];
        foreach (Horizon::OFFSETS as $offset) {
            $surplus[$offset] = isset($salaryByOffset[$offset])
                ? self::surplusAt($fairByOffset[$offset] ?? 0, $salaryByOffset[$offset])
                : 0;
        }

        return $surplus;
    }

    public static function raiseAt(ProjectionPlayer $p, int $offset): int
    {
        if ($offset < 1) {
            return 0;
        }

        $salary = $p->salaryAt($offset);
        $previous = $p->salaryAt($offset - 1);

        return $salary > 0 && $previous > 0 ? $salary - $previous : 0;
    }

    /**
     * @return array<int, int> offsets 1..3
     */
    public static function upcomingRaises(ProjectionPlayer $p): array
    {
        $raises = [];
        foreach ([1, 2, 3] as $offset) {
            $raises[$offset] = self::raiseAt($p, $offset);
        }

        return $raises;
    }

    /**
     * A non-negative magnitude over offsets 1 and up. Positive seasons never
     * offset negative ones, because F15's `REBUILDER_OLD_LONG_MONEY_UNPAID`
     * veto compares pick worth against this exact figure.
     *
     * @param array<int, int> $surplusBySeason
     */
    public static function negativeSurplusBeyondCurrent(array $surplusBySeason): int
    {
        $total = 0;
        foreach ($surplusBySeason as $offset => $surplus) {
            if ($offset >= 1) {
                $total -= min(0, $surplus);
            }
        }

        return $total;
    }

    /**
     * Strictly greater: exactly the floor is not bad.
     *
     * @param array<int, int> $surplusBySeason
     */
    public static function isTrulyBadLongMoney(array $surplusBySeason): bool
    {
        return self::negativeSurplusBeyondCurrent($surplusBySeason) > self::BAD_CONTRACT_FLOOR;
    }

    /**
     * Mean over on-the-books years from the effective contract year to
     * salary_yr6. F15's `YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE` veto reads it.
     */
    public static function averageRemainingSalary(ProjectionPlayer $p): float
    {
        $salaries = array_filter(
            $p->salaryByYear,
            static fn (int $salary, int $year): bool => $salary > 0
                && $year >= $p->effectiveCy
                && $year <= self::LAST_SALARY_YEAR,
            ARRAY_FILTER_USE_BOTH,
        );

        return $salaries === [] ? 0.0 : array_sum($salaries) / count($salaries);
    }
}
