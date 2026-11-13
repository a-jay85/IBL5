<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateBudget;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;

/**
 * Mirror of `TwoForOne`: fires only when the user has a weak slot
 * (`SlotStrength::isWeak`) and the partner has a buried player at that slot
 * (`buriedPids`) or two surplus slots (`NeedPairGate::buriedOrDepth`, Phase 4
 * docblock). Yields partner pairs (6 choose 2 = 15) for one of the user's
 * first 2 pool players (30 max).
 */
final class OneForTwo implements DealShapeInterface
{
    private const int USER_SINGLES = 2;

    public function id(): string
    {
        return 'ONE_FOR_TWO';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        if (!NeedPairGate::buriedOrDepth($s, $partnerTid, $userTid)) {
            return;
        }
        $partnerPids = array_slice($pool->partnerPids, 0, CandidateBudget::PLAYERS_PER_PARTNER);
        $count = count($partnerPids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pair = [$partnerPids[$i], $partnerPids[$j]];
                sort($pair);
                foreach (array_slice($pool->userPids, 0, self::USER_SINGLES) as $userPid) {
                    yield new TradeCandidate($userTid, $partnerTid, [$userPid], [], $pair, []);
                }
            }
        }
    }
}
