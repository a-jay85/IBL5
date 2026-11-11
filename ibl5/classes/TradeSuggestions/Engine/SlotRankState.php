<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\SeasonLineup;
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
     */
    private function __construct(
        private readonly SlotNetTallies $tallies,
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

        return new self($t, $poolRanks, $starterRanks, $benchRanks, $worst);
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
