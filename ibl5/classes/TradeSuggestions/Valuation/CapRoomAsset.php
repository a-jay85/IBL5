<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use League\League;
use TradeSuggestions\Profile\RankUtility;

/**
 * The F8 payroll and future cap-room components, in weighted rank places.
 * Pure. Cash considerations count toward payroll the way
 * `Team\TeamCapCalculator::getSalaryCapArray` counts them.
 */
final class CapRoomAsset
{
    /**
     * Getting from over the hard cap to under it restores the ability to sign
     * anyone at all (LEAGUE_RULES.md § Waivers), so it is worth three
     * bottom-tier rank places.
     */
    public const float HARD_CAP_FIX_PLACES = 3.0;

    /**
     * A sentinel far below any reachable gain. Unit 3's legality pre-filter
     * removes breaches before scoring; this guarantees a leaked one never ranks.
     */
    public const float HARD_CAP_BREACH_PLACES = -100.0;

    /**
     * A later-season payroll 10% of the hard cap over it costs half a place and
     * never more than two. The GM can still fix it with next summer's moves, so
     * it is a concern and not a wall.
     */
    public const float MILD_OVERAGE_PLACES_PER_TENTH_CAP = 0.5;
    public const float MILD_OVERAGE_CAP = 2.0;

    /**
     * Room movement counts at the `RankUtility` tier weights unscaled. The
     * per-season weight comes from `GainWeights::seasonWeights`.
     */
    public const float FUTURE_ROOM_WEIGHT = 1.0;

    /**
     * @param list<int> $salariesAtOffset
     */
    public static function payroll(array $salariesAtOffset, int $cashAtOffset): int
    {
        return (int) array_sum($salariesAtOffset) + $cashAtOffset;
    }

    /**
     * Rank 1 is the LOWEST payroll, so `RankUtility::gain` reads more room as
     * positive. Ties go to the lower tid. Ordinal, no gaps.
     *
     * @param array<int, int> $payrollByTid
     * @return array<int, int> tid => rank
     */
    public static function payrollRanks(array $payrollByTid): array
    {
        ksort($payrollByTid);
        asort($payrollByTid);

        $ranks = [];
        $rank = 1;
        foreach (array_keys($payrollByTid) as $tid) {
            $ranks[$tid] = $rank++;
        }

        return $ranks;
    }

    /**
     * Exactly the cap is legal: zero room is not a breach.
     */
    public static function breachesHardCapNow(int $payrollAfter): bool
    {
        return $payrollAfter > League::HARD_CAP_MAX;
    }

    public static function places(int $offset, int $payrollBefore, int $payrollAfter, int $rankBefore, int $rankAfter): float
    {
        $rankGain = self::FUTURE_ROOM_WEIGHT * RankUtility::gain($rankBefore, $rankAfter);

        if ($offset >= 1) {
            return $rankGain - (self::overagePenalty($payrollAfter) - self::overagePenalty($payrollBefore));
        }

        if (self::breachesHardCapNow($payrollAfter)) {
            return self::HARD_CAP_BREACH_PLACES;
        }

        return $rankGain + (self::breachesHardCapNow($payrollBefore) ? self::HARD_CAP_FIX_PLACES : 0.0);
    }

    /**
     * Signed money measured against the soft cap, for reason text.
     */
    public static function roomAt(int $payroll): int
    {
        return League::SOFT_CAP_MAX - $payroll;
    }

    /**
     * Signed money measured against the hard cap, for reason text.
     */
    public static function hardRoomAt(int $payroll): int
    {
        return League::HARD_CAP_MAX - $payroll;
    }

    private static function overagePenalty(int $payroll): float
    {
        $overage = max(0, $payroll - League::HARD_CAP_MAX);

        return min(self::MILD_OVERAGE_CAP, self::MILD_OVERAGE_PLACES_PER_TENTH_CAP * $overage / (League::HARD_CAP_MAX / 10));
    }
}
