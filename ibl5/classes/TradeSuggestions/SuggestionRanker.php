<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Orders scored candidates and keeps up to MAX_SUGGESTIONS that pass validation.
 *
 * Sort: min(userGain, partnerGain) + SIGNIFICANCE_WEIGHT * significance desc,
 * then userGain desc, then generation order. The significance term favors deals
 * that move a real player over bench swaps.
 * At most one suggestion per partner, and no user asset is offered twice.
 * $passes is called at most MAX_VALIDATIONS times, which bounds validator SQL.
 */
final class SuggestionRanker
{
    public const int MAX_SUGGESTIONS = 3;
    public const int MAX_VALIDATIONS = 8;
    public const float SIGNIFICANCE_WEIGHT = 0.1;

    /**
     * @param list<ScoredCandidate> $scored
     * @param callable(ScoredCandidate): bool $passes
     * @return list<ScoredCandidate>
     */
    public function select(array $scored, callable $passes): array
    {
        $order = array_keys($scored);
        usort($order, static function (int $a, int $b) use ($scored): int {
            $sa = $scored[$a];
            $sb = $scored[$b];

            return [self::rankScore($sb), $sb->userGain, $a]
                <=> [self::rankScore($sa), $sa->userGain, $b];
        });

        $kept = [];
        $usedPartners = [];
        $usedPids = [];
        $usedPickIds = [];
        $validations = 0;

        foreach ($order as $i) {
            if (count($kept) >= self::MAX_SUGGESTIONS || $validations >= self::MAX_VALIDATIONS) {
                break;
            }

            $s = $scored[$i];
            $c = $s->candidate;
            if (isset($usedPartners[$c->partnerTid])) {
                continue;
            }
            foreach ($c->userPids as $pid) {
                if (isset($usedPids[$pid])) {
                    continue 2;
                }
            }
            foreach ($c->userPickIds as $pickId) {
                if (isset($usedPickIds[$pickId])) {
                    continue 2;
                }
            }

            $validations++;
            if (!$passes($s)) {
                continue;
            }

            $kept[] = $s;
            $usedPartners[$c->partnerTid] = true;
            foreach ($c->userPids as $pid) {
                $usedPids[$pid] = true;
            }
            foreach ($c->userPickIds as $pickId) {
                $usedPickIds[$pickId] = true;
            }
        }

        return $kept;
    }

    public static function rankScore(ScoredCandidate $s): float
    {
        return min($s->userGain, $s->partnerGain) + self::SIGNIFICANCE_WEIGHT * $s->significance;
    }
}
