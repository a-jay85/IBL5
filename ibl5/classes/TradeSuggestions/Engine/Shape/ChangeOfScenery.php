<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\TradeCandidate;

/**
 * Fires on a buried player on one side and a weak slot on the other at the
 * same slot (Phase 4 docblock). Yields a user buried player
 * (`SlotStrength::$buriedPids`, offset 0) for a partner buried player at a
 * different slot, or for the partner's weakest-slot bench player.
 */
final class ChangeOfScenery implements DealShapeInterface
{
    public function id(): string
    {
        return 'CHANGE_OF_SCENERY';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        $userBuried = self::buriedInPool($s->positionStrength[0][$userTid] ?? [], $pool->userPids);
        if ($userBuried === []) {
            return;
        }

        $partnerStrength = $s->positionStrength[0][$partnerTid] ?? [];
        $partnerBuried = self::buriedInPool($partnerStrength, $pool->partnerPids);

        $weakestBench = null;
        $weakestRank = -1;
        foreach ($partnerStrength as $slot => $strength) {
            $rank = $strength->starterRank ?? RankUtility::MAX_RANK + 1;
            if ($rank <= $weakestRank) {
                continue;
            }
            foreach (($s->lineups[0][$partnerTid] ?? null)?->benchPids($slot) ?? [] as $pid) {
                if (in_array($pid, $pool->partnerPids, true)) {
                    $weakestBench = $pid;
                    $weakestRank = $rank;
                    break;
                }
            }
        }

        foreach ($userBuried as $userPid => $userSlot) {
            $targets = [];
            foreach ($partnerBuried as $partnerPid => $partnerSlot) {
                if ($partnerSlot !== $userSlot) {
                    $targets[$partnerPid] = true;
                }
            }
            if ($weakestBench !== null) {
                $targets[$weakestBench] = true;
            }
            foreach (array_keys($targets) as $partnerPid) {
                yield new TradeCandidate($userTid, $partnerTid, [$userPid], [], [$partnerPid], []);
            }
        }
    }

    /**
     * @param array<string, SlotStrength> $strengths
     * @param list<int> $poolPids
     * @return array<int, string> pid => slot of his first buried listing
     */
    private static function buriedInPool(array $strengths, array $poolPids): array
    {
        $buried = [];
        foreach ($strengths as $slot => $strength) {
            foreach ($strength->buriedPids as $pid) {
                if (in_array($pid, $poolPids, true) && !isset($buried[$pid])) {
                    $buried[$pid] = $slot;
                }
            }
        }

        return $buried;
    }
}
