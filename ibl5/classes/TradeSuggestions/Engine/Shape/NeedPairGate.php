<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\NeedPair;

/**
 * The matcher-pattern checks the player-for-player shapes share. A shape asks
 * the pool whether its pattern is present and yields nothing when it is not.
 */
final class NeedPairGate
{
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
                $userGives[$pair->offset] = true;
            } elseif ($pair->giverTid === $partnerTid && $pair->receiverTid === $userTid) {
                $partnerGives[$pair->offset] = true;
            }
        }

        return array_intersect_key($userGives, $partnerGives) !== [];
    }

    /**
     * True when `$giverTid` has a slot surplus that matches `$receiverTid`'s
     * slot deficit.
     */
    public static function slotNeed(AssetPool $pool, int $giverTid, int $receiverTid): bool
    {
        foreach ($pool->needPairs as $pair) {
            if ($pair->kind === NeedPair::KIND_SLOT && $pair->giverTid === $giverTid && $pair->receiverTid === $receiverTid) {
                return true;
            }
        }

        return false;
    }
}
