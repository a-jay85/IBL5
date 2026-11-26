<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\TradeCandidate;

/**
 * Fires when the giving side's offset-1 payroll rank is in the bottom tier
 * (`>= RankUtility::MIDDLE_MAX_RANK + 1`, rank 1 = lowest payroll) and the
 * partner's is in the top tier (Phase 4 docblock). Yields a user player with a
 * negative `LeagueSnapshot::surplusBySeason` sum at offsets 1-3, plus one user
 * pick (round 2 first), for one partner player with `salaryAt(1) === 0` or
 * nobody. An empty partner side is allowed: picks have no cap or roster effect
 * (LEAGUE_RULES, Trades).
 */
final class DumpPaidWithPick implements DealShapeInterface
{
    public function id(): string
    {
        return 'DUMP_PAID_WITH_PICK';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        $userRank = $s->payrollRanks[1][$userTid] ?? null;
        $partnerRank = $s->payrollRanks[1][$partnerTid] ?? null;
        if ($userRank === null || $partnerRank === null) {
            return;
        }
        if ($userRank < RankUtility::MIDDLE_MAX_RANK + 1 || $partnerRank > RankUtility::TOP_MAX_RANK) {
            return;
        }

        $pick = null;
        foreach ([2, 1] as $round) {
            foreach ($pool->userPicks as $candidate) {
                if ($candidate->round === $round) {
                    $pick = $candidate;
                    break 2;
                }
            }
        }
        if ($pick === null) {
            return;
        }

        $partnerSides = [[]];
        foreach ($pool->partnerPids as $partnerPid) {
            if (($s->players[$partnerPid] ?? null)?->salaryAt(1) === 0) {
                $partnerSides[] = [$partnerPid];
            }
        }

        foreach ($pool->userPids as $userPid) {
            $surplus = $s->surplusBySeason($userPid);
            if ($surplus[1] + $surplus[2] + $surplus[3] >= 0) {
                continue;
            }
            foreach ($partnerSides as $partnerSide) {
                yield new TradeCandidate($userTid, $partnerTid, [$userPid], [$pick->pickId], $partnerSide, []);
            }
        }
    }
}
