<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SlotMapper;

/**
 * Slot ranks for one offset, sorted from stored net tallies. It sorts with the
 * `SlotRankTable` comparator (net desc, pid asc) and makes no
 * `DominanceComparator` call.
 */
final class SlotRankState implements SlotRankReaderInterface
{
    /**
     * @param array<string, array<int, int>> $poolRanks slot => pid => rank
     * @param array<string, array<int, int>> $starterRanks slot => tid => rank
     * @param array<string, array<int, int>> $benchRanks slot => tid => rank
     * @param array<string, int> $worstStarter slot => largest pool rank held by a starter
     * @param array<int, SeasonLineup> $lineups tid => lineup the ranks were sorted for
     */
    private function __construct(
        private readonly SlotNetTallies $tallies,
        private readonly array $lineups,
        private readonly array $poolRanks,
        private readonly array $starterRanks,
        private readonly array $benchRanks,
        private readonly array $worstStarter,
    ) {
    }

    /**
     * @param array<int, SeasonLineup> $lineups tid => lineup at the tallies' offset
     */
    public static function fromTallies(SlotNetTallies $t, array $lineups): self
    {
        $data = $t->toArray();
        $poolRanks = [];
        $starterRanks = [];
        $benchRanks = [];
        $worst = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $poolRanks[$slot] = self::rankByNet($data['pool'][$slot] ?? []);
            $starterByPid = self::rankByNet($data['starters'][$slot] ?? []);
            $benchByPid = self::rankByNet($data['bench'][$slot] ?? []);
            $starterRanks[$slot] = [];
            $benchRanks[$slot] = [];
            foreach ($lineups as $tid => $lineup) {
                $starter = $lineup->starterPid($slot);
                if ($starter !== null && isset($starterByPid[$starter])) {
                    $starterRanks[$slot][$tid] = $starterByPid[$starter];
                    $worst[$slot] = max($worst[$slot] ?? 0, $poolRanks[$slot][$starter] ?? 0);
                }
                foreach ($lineup->benchPids($slot) as $pid) {
                    if (isset($benchByPid[$pid])) {
                        $benchRanks[$slot][$tid] = $benchByPid[$pid];
                        break;
                    }
                }
            }
        }

        return new self($t, $lineups, $poolRanks, $starterRanks, $benchRanks, $worst);
    }

    /**
     * The state after the teams in `$changedLineups` take their new lineups,
     * updated through the tallies instead of a full pairwise pass. Per slot it
     * diffs each group's membership (pool, starters, best bench of every
     * team), subtracts each removed pid's records from the members that stay,
     * adds each new pid's records, then re-sorts. Equal to `SlotRankTable::build`
     * on the post-deal lineups.
     *
     * @param array<int, SeasonLineup> $changedLineups tid => new lineup
     * @param array<int, RatingSet> $ratingsByPid ratings at the offset for every pid in the old and new groups
     */
    public function withLineups(array $changedLineups, array $ratingsByPid, PairRecordMemo $memo): self
    {
        $lineups = array_replace($this->lineups, $changedLineups);
        $old = $this->tallies->toArray();
        $offset = $old['offset'];
        $pool = $old['pool'];
        $starters = $old['starters'];
        $bench = $old['bench'];
        foreach (SlotMapper::SLOTS as $slot) {
            $poolMembers = [];
            $starterMembers = [];
            foreach ($lineups as $lineup) {
                $starter = $lineup->starterPid($slot);
                if ($starter !== null && isset($ratingsByPid[$starter])) {
                    $starterMembers[] = $starter;
                    $poolMembers[$starter] = $starter;
                }
                foreach ($lineup->rotation[$slot] ?? [] as $pid) {
                    if (isset($ratingsByPid[$pid])) {
                        $poolMembers[$pid] = $pid;
                    }
                }
            }
            $pool[$slot] = self::moveMembers($offset, $slot, $pool[$slot] ?? [], array_keys($poolMembers), $ratingsByPid, $memo);
            $poolRanks = self::rankByNet($pool[$slot]);
            $benchMembers = [];
            foreach ($lineups as $lineup) {
                $best = SlotNetTallies::bestBench($lineup, $slot, $poolRanks);
                if ($best !== null) {
                    $benchMembers[] = $best;
                }
            }
            $starters[$slot] = self::moveMembers($offset, $slot, $starters[$slot] ?? [], $starterMembers, $ratingsByPid, $memo);
            $bench[$slot] = self::moveMembers($offset, $slot, $bench[$slot] ?? [], $benchMembers, $ratingsByPid, $memo);
        }

        return self::fromTallies(
            SlotNetTallies::fromArray(['offset' => $offset, 'pool' => $pool, 'starters' => $starters, 'bench' => $bench]),
            $lineups,
        );
    }

    /**
     * @param array<int, int> $net pid => net for the old members
     * @param list<int> $members the new member pids
     * @param array<int, RatingSet> $ratingsByPid
     * @return array<int, int> pid => net for the new members
     */
    private static function moveMembers(int $offset, string $slot, array $net, array $members, array $ratingsByPid, PairRecordMemo $memo): array
    {
        $keep = array_fill_keys($members, true);
        foreach (array_keys($net) as $removed) {
            if (isset($keep[$removed])) {
                continue;
            }
            unset($net[$removed]);
            foreach (array_keys($net) as $pid) {
                $net[$pid] -= $memo->diff($offset, $slot, $pid, $ratingsByPid[$pid], $removed, $ratingsByPid[$removed]);
            }
        }
        foreach ($members as $added) {
            if (isset($net[$added])) {
                continue;
            }
            $sum = 0;
            foreach (array_keys($net) as $pid) {
                $diff = $memo->diff($offset, $slot, $added, $ratingsByPid[$added], $pid, $ratingsByPid[$pid]);
                $sum += $diff;
                $net[$pid] -= $diff;
            }
            $net[$added] = $sum;
        }

        return $net;
    }

    /**
     * The `SlotRankTable` ordering: net desc, then pid asc. Ordinal from 1.
     *
     * @param array<int, int> $net pid => net
     * @return array<int, int> pid => rank
     */
    public static function rankByNet(array $net): array
    {
        $ordered = array_keys($net);
        usort($ordered, static fn (int $a, int $b): int => [$net[$b], $a] <=> [$net[$a], $b]);
        $ranks = [];
        foreach ($ordered as $index => $pid) {
            $ranks[$pid] = $index + 1;
        }

        return $ranks;
    }

    public function tallies(): SlotNetTallies
    {
        return $this->tallies;
    }

    /**
     * @return array<string, array<int, int>> slot => pid => pool rank
     */
    public function poolRanks(): array
    {
        return $this->poolRanks;
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

    public function worstStarterPoolRank(string $slot): ?int
    {
        return $this->worstStarter[$slot] ?? null;
    }
}
