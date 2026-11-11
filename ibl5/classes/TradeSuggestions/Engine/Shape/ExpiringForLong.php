<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\ContractValue;

/**
 * Fires on a player with `salaryAt(1) === 0` on one side and a player with
 * `ContractValue::isTrulyBadLongMoney` on the other (Phase 4 docblock).
 * Yields a user expiring for a partner player who passes the guard plus one
 * partner pick; the mirror gives a partner expiring for a user player plus one
 * user pick. It yields nothing when the long contract is not truly bad.
 */
final class ExpiringForLong implements DealShapeInterface
{
    public function id(): string
    {
        return 'EXPIRING_FOR_LONG';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        $partnerPick = $pool->partnerPicks[0] ?? null;
        if ($partnerPick !== null) {
            foreach ($pool->userPids as $expiring) {
                if (($s->players[$expiring] ?? null)?->salaryAt(1) !== 0) {
                    continue;
                }
                foreach ($pool->partnerPids as $long) {
                    if (ContractValue::isTrulyBadLongMoney($s->surplusBySeason($long))) {
                        yield new TradeCandidate($userTid, $partnerTid, [$expiring], [], [$long], [$partnerPick->pickId]);
                    }
                }
            }
        }

        $userPick = $pool->userPicks[0] ?? null;
        if ($userPick !== null) {
            foreach ($pool->partnerPids as $expiring) {
                if (($s->players[$expiring] ?? null)?->salaryAt(1) !== 0) {
                    continue;
                }
                foreach ($pool->userPids as $long) {
                    if (ContractValue::isTrulyBadLongMoney($s->surplusBySeason($long))) {
                        yield new TradeCandidate($userTid, $partnerTid, [$long], [$userPick->pickId], [$expiring], []);
                    }
                }
            }
        }
    }
}
