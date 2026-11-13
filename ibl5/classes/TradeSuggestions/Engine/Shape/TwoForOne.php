<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateBudget;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;

/**
 * Fires only when the partner has a weak slot (`SlotStrength::isWeak`) and
 * the user has a buried player at that slot (`buriedPids`) or two surplus
 * slots (`NeedPairGate::buriedOrDepth`, Phase 4 docblock). Yields user pairs
 * (6 choose 2 = 15) for one of the partner's first 2 pool players (30 max).
 * `OneForTwo` is the mirror.
 */
final class TwoForOne implements DealShapeInterface
{
    private const int PARTNER_SINGLES = 2;

    public function id(): string
    {
        return 'TWO_FOR_ONE';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        if (!NeedPairGate::buriedOrDepth($s, $userTid, $partnerTid)) {
            return;
        }
        $userPids = array_slice($pool->userPids, 0, CandidateBudget::PLAYERS_PER_PARTNER);
        foreach ($userPids as $i => $first) {
            foreach (array_slice($userPids, $i + 1) as $second) {
                $pair = [$first, $second];
                sort($pair);
                foreach (array_slice($pool->partnerPids, 0, self::PARTNER_SINGLES) as $partnerPid) {
                    yield new TradeCandidate($userTid, $partnerTid, $pair, [], [$partnerPid], []);
                }
            }
        }
    }
}
