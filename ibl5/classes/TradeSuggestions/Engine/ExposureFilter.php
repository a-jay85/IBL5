<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Valuation\GainWeights;

/**
 * F17 exposure cap: at most K user teams may target any one partner per
 * snapshot. The order is a seeded hash of the sim key, so it reshuffles exactly
 * when the snapshot does. Ties cannot occur: distinct tids give distinct hashes
 * (a SHA-1 collision aside).
 */
final class ExposureFilter
{
    public static function rankOf(string $simKey, int $partnerTid, int $userTid): string
    {
        return sha1($simKey . '|' . $partnerTid . '|' . $userTid);
    }

    /**
     * True when `$userTid` is among the first `$k` teams in the partner's
     * hash order. A team never targets itself.
     *
     * @param list<int> $allTids
     */
    public static function mayTarget(string $simKey, int $partnerTid, int $userTid, array $allTids, int $k = GainWeights::EXPOSURE_K): bool
    {
        $others = array_filter($allTids, static fn (int $tid): bool => $tid !== $partnerTid);
        usort($others, static fn (int $a, int $b): int
            => strcmp(self::rankOf($simKey, $partnerTid, $a), self::rankOf($simKey, $partnerTid, $b)));
        $index = array_search($userTid, $others, true);

        return $index !== false && $index < $k;
    }

    /**
     * Partners the user may target, sorted by tid. A user missing from
     * `$allTids` gets none.
     *
     * @param list<int> $allTids
     * @return list<int>
     */
    public static function eligiblePartners(string $simKey, int $userTid, array $allTids, int $k = GainWeights::EXPOSURE_K): array
    {
        $partners = [];
        foreach ($allTids as $partnerTid) {
            if (self::mayTarget($simKey, $partnerTid, $userTid, $allTids, $k)) {
                $partners[] = $partnerTid;
            }
        }
        sort($partners);

        return $partners;
    }
}
