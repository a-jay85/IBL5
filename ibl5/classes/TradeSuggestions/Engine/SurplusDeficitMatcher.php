<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\SlotMapper;

/**
 * Finds where one team's surplus meets another's deficit (F6/F7). This class is
 * the one place that reads a profile pattern; the Phase 5 shapes consume its
 * output. Which shape fires for which pattern:
 *
 * - `OneForOne`: any pair where both sides have a surplus matching the other's
 *   deficit at the same offset.
 * - `TwoForOne` / `OneForTwo`: the receiving side has a weak slot
 *   (`SlotStrength::isWeak`) and the giving side has a buried player at that
 *   slot (`buriedPids`) or two surplus slots.
 * - `DumpPaidWithPick`: the giving side's offset-1 payroll rank is in the
 *   bottom tier (`>= RankUtility::MIDDLE_MAX_RANK + 1`) and the partner's is in
 *   the top tier.
 * - `StarForYoungAndPicks`: the giver is Rebuild, the partner is WinNow, and the
 *   giver holds a starter with `starterRank <= SlotStrength::STRONG_MAX_RANK`
 *   at offset 0.
 * - `ExpiringForLong`: a player with `salaryAt(1) === 0` on one side and a
 *   player with `ContractValue::isTrulyBadLongMoney` on the other.
 * - `PickSwap`: both sides own a round-1 pick and the modes differ (any two of
 *   WinNow, Middle and Rebuild).
 * - `ChangeOfScenery`: a buried player on one side and a weak slot on the other
 *   at the same slot.
 *
 * Asset ordering is always one dimension at a time. It never combines ratings.
 */
final class SurplusDeficitMatcher
{
    /**
     * Pairs in both directions, sorted by `overlapWeight` desc, then offset
     * asc, then key asc, then giver tid asc.
     *
     * @return list<NeedPair>
     */
    public static function needPairs(LeagueSnapshot $s, int $userTid, int $partnerTid): array
    {
        $pairs = [];
        foreach ($s->profiles as $offset => $profiles) {
            $user = $profiles[$userTid] ?? null;
            $partner = $profiles[$partnerTid] ?? null;
            if ($user === null || $partner === null) {
                continue;
            }
            foreach (ProfileDimension::KEYS as $key) {
                if ($user->isSurplus($key) && $partner->isDeficit($key)) {
                    $pairs[] = self::pair($offset, NeedPair::KIND_DIMENSION, $key, $userTid, $partnerTid, $user->ranks[$key], $partner->ranks[$key]);
                }
                if ($partner->isSurplus($key) && $user->isDeficit($key)) {
                    $pairs[] = self::pair($offset, NeedPair::KIND_DIMENSION, $key, $partnerTid, $userTid, $partner->ranks[$key], $user->ranks[$key]);
                }
            }
        }
        foreach ($s->positionStrength as $offset => $byTid) {
            $user = $byTid[$userTid] ?? null;
            $partner = $byTid[$partnerTid] ?? null;
            if ($user === null || $partner === null) {
                continue;
            }
            foreach (SlotMapper::SLOTS as $slot) {
                $u = $user[$slot];
                $p = $partner[$slot];
                if ($u->isStrong() && $p->isWeak()) {
                    $pairs[] = self::pair($offset, NeedPair::KIND_SLOT, $slot, $userTid, $partnerTid, self::slotRank($u->starterRank), self::slotRank($p->starterRank));
                }
                if ($p->isStrong() && $u->isWeak()) {
                    $pairs[] = self::pair($offset, NeedPair::KIND_SLOT, $slot, $partnerTid, $userTid, self::slotRank($p->starterRank), self::slotRank($u->starterRank));
                }
            }
        }

        usort($pairs, static fn (NeedPair $a, NeedPair $b): int
            => [$b->overlapWeight, $a->offset, $a->key, $a->giverTid] <=> [$a->overlapWeight, $b->offset, $b->key, $b->giverTid]);

        return $pairs;
    }

    /**
     * Up to `$limit` of the giver's rotation pids at the pair's offset, most
     * relevant first. A slot pair lists the starter, then the rest of that
     * slot's rotation by pool rank. A dimension pair orders every rotation
     * player by that one dimension.
     *
     * @return list<int>
     */
    public static function assetsFor(LeagueSnapshot $s, int $giverTid, NeedPair $pair, int $limit): array
    {
        $lineup = $s->lineups[$pair->offset][$giverTid] ?? null;
        if ($lineup === null || $limit <= 0) {
            return [];
        }

        if ($pair->kind === NeedPair::KIND_SLOT) {
            $starter = $lineup->starterPid($pair->key);
            $ranks = $s->slotRanks[$pair->offset] ?? null;
            $rest = array_values(array_filter(
                $lineup->rotation[$pair->key] ?? [],
                static fn (int $pid): bool => $pid !== $starter,
            ));
            usort($rest, static function (int $a, int $b) use ($ranks, $pair): int {
                $ra = $ranks?->poolRank($pair->key, $a) ?? PHP_INT_MAX;
                $rb = $ranks?->poolRank($pair->key, $b) ?? PHP_INT_MAX;

                return [$ra, $a] <=> [$rb, $b];
            });
            $ordered = $starter !== null && isset($lineup->players[$starter]) ? [$starter, ...$rest] : $rest;

            return array_slice($ordered, 0, $limit);
        }

        $pids = [];
        foreach ($lineup->rotation as $slotPids) {
            foreach ($slotPids as $pid) {
                $pids[$pid] = true;
            }
        }
        $values = [];
        foreach (array_keys($pids) as $pid) {
            $values[$pid] = self::dimensionValue($s, $pid, $pair->offset, $pair->key);
        }
        $lowerIsBetter = ProfileDimension::lowerIsBetter($pair->key);
        $ordered = array_keys($values);
        usort($ordered, static function (int $a, int $b) use ($values, $lowerIsBetter): int {
            $va = $values[$a];
            $vb = $values[$b];
            if ($va === null || $vb === null) {
                return [$va === null, $a] <=> [$vb === null, $b];
            }
            $byValue = $lowerIsBetter ? $va <=> $vb : $vb <=> $va;

            return $byValue !== 0 ? $byValue : $a <=> $b;
        });

        return array_slice($ordered, 0, $limit);
    }

    /**
     * One player's value on one dimension at one offset. Null when he has no
     * production line for a rate key.
     */
    private static function dimensionValue(LeagueSnapshot $s, int $pid, int $offset, string $key): ?float
    {
        $season = $s->projections[$pid][$offset] ?? null;
        if (in_array($key, ProfileDimension::STATS, true)) {
            return $season?->per36[$key] ?? 0.0;
        }
        if (in_array($key, ProfileDimension::RATES, true)) {
            $production = $s->players[$pid]->production ?? null;
            if ($production === null) {
                return null;
            }

            return match ($key) {
                'fg_pct' => self::ratio($production->fgm, $production->fga),
                'tp_pct' => self::ratio($production->tgm, $production->tga),
                'ft_pct' => self::ratio($production->ftm, $production->fta),
                default => self::ratio($production->tga, $production->fga),
            };
        }

        return ($season !== null ? $season->ratings : $s->players[$pid]->ratings)->get($key);
    }

    private static function pair(int $offset, string $kind, string $key, int $giver, int $receiver, int $giverRank, int $receiverRank): NeedPair
    {
        return new NeedPair(
            $offset,
            $kind,
            $key,
            $giver,
            $receiver,
            $giverRank,
            $receiverRank,
            RankUtility::gain($receiverRank, max(1, $receiverRank - 1)),
        );
    }

    private static function slotRank(?int $starterRank): int
    {
        return min(RankUtility::MAX_RANK, $starterRank ?? RankUtility::MAX_RANK);
    }

    private static function ratio(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : $numerator / $denominator;
    }
}
