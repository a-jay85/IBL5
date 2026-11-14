<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use TradeSuggestions\Projection\Horizon;

/**
 * Rookie-scale control years, derived from draft round and experience with the
 * rule `Player\Contract\PlayerContractValidator` applies. Pure.
 *
 * A cheap controlled season is worth half a bottom-tier rank place
 * (`RankUtility::WEIGHT_BOTTOM` = 1.0), because the team keeps that slot's rank
 * without having to buy it on the market. The option year counts half again,
 * because the team may decline it. Neither figure is fitted to a fixture.
 */
final class RookieControl
{
    /** @var array<int, int> */
    public const array CONTRACT_YEARS = [1 => 3, 2 => 2];

    /** @var array<int, int> */
    public const array OPTION_SALARY_YEAR = [1 => 4, 2 => 3];

    public const int MAX_EXPERIENCE = 3;
    public const float CONTROL_PLACE_PER_SEASON = 0.5;
    public const float OPTION_YEAR_WEIGHT = 0.5;

    public static function isRookieScale(int $draftRound, int $exp): bool
    {
        return isset(self::CONTRACT_YEARS[$draftRound]) && $exp <= self::MAX_EXPERIENCE;
    }

    public static function contractYears(int $draftRound): int
    {
        return self::CONTRACT_YEARS[$draftRound] ?? 0;
    }

    public static function optionSalaryYear(int $draftRound): ?int
    {
        return self::OPTION_SALARY_YEAR[$draftRound] ?? null;
    }

    /**
     * The `+ 1` is the option year. Round 1 at exp 3 has only the option left.
     * Round 2 at exp 3 has none, because its option year is already behind him.
     * The `isRookieScale` check caps exp at 3, so `max(0, ...)` is a guard that
     * no current input reaches. It keeps the result non-negative if the caps change.
     */
    public static function controlYearsLeft(int $draftRound, int $exp): int
    {
        return self::isRookieScale($draftRound, $exp)
            ? max(0, self::contractYears($draftRound) + 1 - $exp)
            : 0;
    }

    public static function optionYearIsNext(int $draftRound, int $exp): bool
    {
        return self::controlYearsLeft($draftRound, $exp) === 1;
    }

    /**
     * Weighted rank places (F8) for the cheap controlled seasons inside the
     * horizon. It is a property of the contract, never a player quality figure.
     *
     * @param array<int, int> $surplusBySeason `ContractValue::surplusBySeason` output
     */
    public static function assetPlaces(int $controlYearsLeft, array $surplusBySeason): float
    {
        $places = 0.0;
        foreach (Horizon::OFFSETS as $offset) {
            if ($offset >= $controlYearsLeft || $surplusBySeason[$offset] <= 0) {
                continue;
            }
            $places += $offset === $controlYearsLeft - 1
                ? self::CONTROL_PLACE_PER_SEASON * self::OPTION_YEAR_WEIGHT
                : self::CONTROL_PLACE_PER_SEASON;
        }

        return $places;
    }
}
