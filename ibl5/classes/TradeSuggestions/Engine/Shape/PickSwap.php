<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;

/**
 * Fires when both sides own a round-1 pick and the modes differ (any two of
 * WinNow, Middle and Rebuild; Phase 4 docblock). Yields one user round-1 pick
 * for one partner round-1 pick with a different `yearsAway`, plus optionally
 * one round-2 pick on the side moving the nearer pick. Yields nothing when the
 * modes match.
 */
final class PickSwap implements DealShapeInterface
{
    public function id(): string
    {
        return 'PICK_SWAP';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        if ($s->mode($userTid) === $s->mode($partnerTid)) {
            return;
        }

        $userRound2 = self::firstRound2($pool->userPicks);
        $partnerRound2 = self::firstRound2($pool->partnerPicks);

        foreach ($pool->userPicks as $userPick) {
            if ($userPick->round !== 1) {
                continue;
            }
            foreach ($pool->partnerPicks as $partnerPick) {
                if ($partnerPick->round !== 1 || $partnerPick->yearsAway === $userPick->yearsAway) {
                    continue;
                }
                yield new TradeCandidate($userTid, $partnerTid, [], [$userPick->pickId], [], [$partnerPick->pickId]);
                if ($userPick->yearsAway < $partnerPick->yearsAway) {
                    if ($userRound2 !== null) {
                        yield new TradeCandidate($userTid, $partnerTid, [], [$userPick->pickId, $userRound2], [], [$partnerPick->pickId]);
                    }
                } elseif ($partnerRound2 !== null) {
                    yield new TradeCandidate($userTid, $partnerTid, [], [$userPick->pickId], [], [$partnerPick->pickId, $partnerRound2]);
                }
            }
        }
    }

    /**
     * @param list<PickAsset> $picks
     */
    private static function firstRound2(array $picks): ?int
    {
        foreach ($picks as $pick) {
            if ($pick->round === 2) {
                return $pick->pickId;
            }
        }

        return null;
    }
}
