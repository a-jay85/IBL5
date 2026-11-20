<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\SlotMapper;

/**
 * Builds each team's per-slot strength from its lineup and the league slot
 * ranks (F7).
 */
final class PositionStrength
{
    public const int PLAYABLE_MIN_MINUTES = 15;

    /**
     * @return array<string, SlotStrength> keyed by SlotMapper::SLOTS
     */
    public static function build(int $tid, SeasonLineup $lineup, SlotRankTable $ranks): array
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
                if ($minutes >= self::PLAYABLE_MIN_MINUTES || in_array($pid, $rotation, true)) {
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
     * @param array<int, SeasonLineup> $lineups keyed by tid
     * @return array<int, array<string, SlotStrength>>
     */
    public static function buildLeague(array $lineups, SlotRankTable $ranks): array
    {
        $out = [];
        foreach ($lineups as $tid => $lineup) {
            $out[$tid] = self::build($tid, $lineup, $ranks);
        }

        return $out;
    }

    /**
     * Bench players whose pool rank is at least as good as the league's worst
     * starter at the slot (so they would start somewhere) while the team's
     * own starter ranks better in the pool.
     *
     * @return list<int>
     */
    private static function buriedAt(string $slot, SeasonLineup $lineup, SlotRankTable $ranks): array
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
