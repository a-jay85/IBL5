<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\DominanceComparator;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SlotMapper;

/**
 * Ordinal slot ranks for one horizon offset (F3).
 *
 * Players are ordered by pairwise record: each player's wins minus losses
 * against every other member of the same group. The order breaks ties by pid
 * ascending, so equal records still get distinct, deterministic ranks. The
 * record total is a local inside the ranking step; only the ordinal is kept.
 *
 * Three groups are ranked per slot: the pool (every team's starter plus its
 * rotation at the slot), the starters alone, and each team's best bench
 * player alone.
 */
final class SlotRankTable
{
    /**
     * @param array<string, array<int, int>> $poolRanks slot => pid => rank
     * @param array<string, array<int, int>> $starterRanks slot => tid => rank
     * @param array<string, array<int, int>> $benchRanks slot => tid => rank
     * @param array<string, int> $worstStarterPoolRanks slot => largest pool rank held by a starter
     */
    private function __construct(
        private readonly array $poolRanks,
        private readonly array $starterRanks,
        private readonly array $benchRanks,
        private readonly array $worstStarterPoolRanks,
    ) {
    }

    /**
     * @param array<int, SeasonLineup> $lineups keyed by tid
     * @param array<int, RatingSet> $ratingsByPid projected ratings at this offset
     */
    public static function build(array $lineups, array $ratingsByPid): self
    {
        $poolRanks = [];
        $starterRanks = [];
        $benchRanks = [];
        $worstStarter = [];

        foreach (SlotMapper::SLOTS as $slot) {
            $pool = [];
            $starterByTid = [];
            foreach ($lineups as $tid => $lineup) {
                $starter = $lineup->starterPid($slot);
                if ($starter !== null && isset($ratingsByPid[$starter])) {
                    $starterByTid[$tid] = $starter;
                    $pool[$starter] = true;
                }
                foreach ($lineup->rotation[$slot] ?? [] as $pid) {
                    if (isset($ratingsByPid[$pid])) {
                        $pool[$pid] = true;
                    }
                }
            }

            $poolRanks[$slot] = self::rank(array_keys($pool), $ratingsByPid, $slot);
            foreach ($starterByTid as $pid) {
                $worstStarter[$slot] = max($worstStarter[$slot] ?? 0, $poolRanks[$slot][$pid]);
            }

            $bestBenchByTid = [];
            foreach ($lineups as $tid => $lineup) {
                $best = null;
                foreach ($lineup->benchPids($slot) as $pid) {
                    $rank = $poolRanks[$slot][$pid] ?? null;
                    if ($rank !== null && ($best === null || $rank < $poolRanks[$slot][$best])) {
                        $best = $pid;
                    }
                }
                if ($best !== null) {
                    $bestBenchByTid[$tid] = $best;
                }
            }

            $starterRanks[$slot] = self::byTid($starterByTid, self::rank(array_values($starterByTid), $ratingsByPid, $slot));
            $benchRanks[$slot] = self::byTid($bestBenchByTid, self::rank(array_values($bestBenchByTid), $ratingsByPid, $slot));
        }

        return new self($poolRanks, $starterRanks, $benchRanks, $worstStarter);
    }

    public function poolRank(string $slot, int $pid): ?int
    {
        return $this->poolRanks[$slot][$pid] ?? null;
    }

    public function starterRank(string $slot, int $tid): ?int
    {
        return $this->starterRanks[$slot][$tid] ?? null;
    }

    public function bestBenchRank(string $slot, int $tid): ?int
    {
        return $this->benchRanks[$slot][$tid] ?? null;
    }

    /**
     * The largest pool rank any team's starter holds at the slot. A bench
     * player ranked at or above it in the pool would start somewhere. Null
     * when no team has a starter there.
     */
    public function worstStarterPoolRank(string $slot): ?int
    {
        return $this->worstStarterPoolRanks[$slot] ?? null;
    }

    /**
     * @param list<int> $pids
     * @param array<int, RatingSet> $ratingsByPid
     * @return array<int, int> pid => 1-based ordinal
     */
    private static function rank(array $pids, array $ratingsByPid, string $slot): array
    {
        $net = [];
        foreach ($pids as $pid) {
            $net[$pid] = 0;
        }
        $count = count($pids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $record = DominanceComparator::record($ratingsByPid[$pids[$i]], $ratingsByPid[$pids[$j]], $slot);
                $diff = $record['wins'] - $record['losses'];
                $net[$pids[$i]] += $diff;
                $net[$pids[$j]] -= $diff;
            }
        }

        $ordered = $pids;
        usort($ordered, static fn (int $a, int $b): int => [$net[$b], $a] <=> [$net[$a], $b]);

        $ranks = [];
        foreach ($ordered as $index => $pid) {
            $ranks[$pid] = $index + 1;
        }

        return $ranks;
    }

    /**
     * @param array<int, int> $pidByTid
     * @param array<int, int> $rankByPid
     * @return array<int, int> tid => rank
     */
    private static function byTid(array $pidByTid, array $rankByPid): array
    {
        $out = [];
        foreach ($pidByTid as $tid => $pid) {
            $out[$tid] = $rankByPid[$pid];
        }

        return $out;
    }
}
