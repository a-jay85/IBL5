<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;

/**
 * The matcher-pattern checks the player-for-player shapes share. A shape asks
 * the pool or the snapshot whether its pattern is present and yields nothing
 * when it is not.
 */
final class NeedPairGate
{
    /** Surplus slots a giver needs to fire without a buried player. */
    public const int SURPLUS_SLOTS_FOR_DEPTH = 2;

    /**
     * True when each side has a surplus that matches the other side's deficit
     * at one shared offset.
     */
    public static function mutual(AssetPool $pool, int $userTid, int $partnerTid): bool
    {
        $userGives = [];
        $partnerGives = [];
        foreach ($pool->needPairs as $pair) {
            if ($pair->giverTid === $userTid && $pair->receiverTid === $partnerTid) {
                $userGives[$pair->offset] = $pair->offset;
            } elseif ($pair->giverTid === $partnerTid && $pair->receiverTid === $userTid) {
                $partnerGives[$pair->offset] = $pair->offset;
            }
        }

        return array_intersect_key($userGives, $partnerGives) !== [];
    }

    /**
     * True when `$receiverTid` has a weak slot at offset 0
     * (`SlotStrength::isWeak`) and `$giverTid` either has a buried player at
     * that slot (`SlotStrength::$buriedPids`) or has at least two surplus
     * slots (`SlotStrength::isStrong`).
     */
    public static function buriedOrDepth(LeagueSnapshot $s, int $giverTid, int $receiverTid): bool
    {
        $giver = $s->positionStrength[0][$giverTid] ?? [];
        $hasWeak = false;
        foreach ($s->positionStrength[0][$receiverTid] ?? [] as $slot => $strength) {
            if (!$strength->isWeak()) {
                continue;
            }
            $hasWeak = true;
            if (isset($giver[$slot]) && $giver[$slot]->buriedPids !== []) {
                return true;
            }
        }
        if (!$hasWeak) {
            return false;
        }
        $surplus = 0;
        foreach ($giver as $strength) {
            if ($strength->isStrong()) {
                $surplus++;
            }
        }

        return $surplus >= self::SURPLUS_SLOTS_FOR_DEPTH;
    }
}
