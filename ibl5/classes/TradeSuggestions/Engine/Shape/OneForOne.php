<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;

/**
 * Fires when both sides have a surplus matching the other's deficit at the
 * same offset (`NeedPairGate::mutual`); yields nothing otherwise. Yields every
 * (userPid, partnerPid) from the pool's first 4 per side (16 max).
 */
final class OneForOne implements DealShapeInterface
{
    private const int PER_SIDE = 4;

    public function id(): string
    {
        return 'ONE_FOR_ONE';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        if (!NeedPairGate::mutual($pool, $userTid, $partnerTid)) {
            return;
        }
        foreach (array_slice($pool->userPids, 0, self::PER_SIDE) as $userPid) {
            foreach (array_slice($pool->partnerPids, 0, self::PER_SIDE) as $partnerPid) {
                yield new TradeCandidate($userTid, $partnerTid, [$userPid], [], [$partnerPid], []);
            }
        }
    }
}
