<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\PositionStrength;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\SlotMapper;

/**
 * `Profile\PositionStrength::build` over the rank-reader interface, so a
 * `SlotRankState` can feed it. Pinned equal to `PositionStrength::build` over a
 * full `SlotRankTable`.
 */
final class AfterPositionStrength
{
    /**
     * @return array<string, SlotStrength>
     */
    public static function build(int $tid, SeasonLineup $lineup, SlotRankReaderInterface $ranks): array
    {
        $out = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $rotation = $lineup->rotation[$slot] ?? [];
            $playable = 0;
            foreach ($lineup->players as $pid => $player) {
                if (!in_array($slot, $player->slots(), true)) {
                    continue;
                }
                $minutes = $lineup->minutesWeight[$pid] ?? 0.0;
                if ($minutes >= PositionStrength::PLAYABLE_MIN_MINUTES || in_array($pid, $rotation, true)) {
                    $playable++;
                }
            }
            $multiPosition = false;
            foreach ($rotation as $pid) {
                if (isset($lineup->players[$pid]) && count($lineup->players[$pid]->slots()) >= 2) {
                    $multiPosition = true;
                    break;
                }
            }
            $out[$slot] = new SlotStrength(
                $slot,
                $ranks->starterRank($slot, $tid),
                $ranks->bestBenchRank($slot, $tid),
                $playable,
                $multiPosition,
                self::buriedAt($slot, $lineup, $ranks),
            );
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private static function buriedAt(string $slot, SeasonLineup $lineup, SlotRankReaderInterface $ranks): array
    {
        $worst = $ranks->worstStarterPoolRank($slot);
        $starter = $lineup->starterPid($slot);
        $starterRank = $starter === null ? null : $ranks->poolRank($slot, $starter);
        if ($worst === null || $starterRank === null) {
            return [];
        }
        $buried = [];
        foreach ($lineup->benchPids($slot) as $pid) {
            $rank = $ranks->poolRank($slot, $pid);
            if ($rank !== null && $rank <= $worst && $starterRank < $rank) {
                $buried[] = $pid;
            }
        }

        return $buried;
    }
}
