<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SlotMapper;

/**
 * Net pairwise records for one horizon offset, per slot group. The groups match
 * `SlotRankTable::build`: the league-wide rotation pool, each team's starter and
 * each team's best bench player. A tally is a sort key, so there is no public
 * per-pid getter (F3). `SlotRankState` reads the groups through `toArray()`.
 *
 * @phpstan-type TallyArray array{offset: int, pool: array<string, array<int, int>>, starters: array<string, array<int, int>>, bench: array<string, array<int, int>>}
 */
final class SlotNetTallies
{
    /**
     * @param array<string, array<int, int>> $pool slot => pid => net
     * @param array<string, array<int, int>> $starters slot => pid => net
     * @param array<string, array<int, int>> $bench slot => pid => net
     */
    private function __construct(
        private readonly int $offset,
        private readonly array $pool,
        private readonly array $starters,
        private readonly array $bench,
    ) {
    }

    /**
     * @param array<int, SeasonLineup> $lineups tid => lineup at `$offset`
     * @param array<int, RatingSet> $ratingsByPid projected ratings at `$offset`
     */
    public static function build(int $offset, array $lineups, array $ratingsByPid, PairRecordMemo $memo): self
    {
        $pool = [];
        $starters = [];
        $bench = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $poolPids = [];
            $starterPids = [];
            foreach ($lineups as $lineup) {
                $starter = $lineup->starterPid($slot);
                if ($starter !== null && isset($ratingsByPid[$starter])) {
                    $starterPids[] = $starter;
                    $poolPids[$starter] = true;
                }
                foreach ($lineup->rotation[$slot] ?? [] as $pid) {
                    if (isset($ratingsByPid[$pid])) {
                        $poolPids[$pid] = true;
                    }
                }
            }
            $pool[$slot] = self::tally($offset, $slot, array_keys($poolPids), $ratingsByPid, $memo);
            $poolRanks = SlotRankState::rankByNet($pool[$slot]);

            $benchPids = [];
            foreach ($lineups as $lineup) {
                $best = self::bestBench($lineup, $slot, $poolRanks);
                if ($best !== null) {
                    $benchPids[] = $best;
                }
            }
            $starters[$slot] = self::tally($offset, $slot, $starterPids, $ratingsByPid, $memo);
            $bench[$slot] = self::tally($offset, $slot, $benchPids, $ratingsByPid, $memo);
        }

        return new self($offset, $pool, $starters, $bench);
    }

    /**
     * The bench pid with the lowest pool rank, or null.
     *
     * @param array<int, int> $poolRanks pid => pool rank at the slot
     */
    public static function bestBench(SeasonLineup $lineup, string $slot, array $poolRanks): ?int
    {
        $best = null;
        foreach ($lineup->benchPids($slot) as $pid) {
            $rank = $poolRanks[$pid] ?? null;
            if ($rank !== null && ($best === null || $rank < $poolRanks[$best])) {
                $best = $pid;
            }
        }

        return $best;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * @return TallyArray
     */
    public function toArray(): array
    {
        return ['offset' => $this->offset, 'pool' => $this->pool, 'starters' => $this->starters, 'bench' => $this->bench];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $offset = $data['offset'] ?? null;
        if (!is_int($offset)) {
            throw new \UnexpectedValueException('Tallies have no offset');
        }

        return new self(
            $offset,
            self::groupFrom($data['pool'] ?? null),
            self::groupFrom($data['starters'] ?? null),
            self::groupFrom($data['bench'] ?? null),
        );
    }

    /**
     * @return array<string, array<int, int>>
     */
    private static function groupFrom(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new \UnexpectedValueException('Tally group is not an array');
        }
        $out = [];
        foreach ($raw as $slot => $nets) {
            if (!is_string($slot) || !is_array($nets)) {
                throw new \UnexpectedValueException('Tally slot is malformed');
            }
            $out[$slot] = [];
            foreach ($nets as $pid => $net) {
                if (!is_int($pid) || !is_int($net)) {
                    throw new \UnexpectedValueException('Tally entry is malformed');
                }
                $out[$slot][$pid] = $net;
            }
        }

        return $out;
    }

    /**
     * One O(n²) pass over the group through the memo.
     *
     * @param list<int> $pids
     * @param array<int, RatingSet> $ratingsByPid
     * @return array<int, int> pid => net
     */
    private static function tally(int $offset, string $slot, array $pids, array $ratingsByPid, PairRecordMemo $memo): array
    {
        $net = [];
        foreach ($pids as $pid) {
            $net[$pid] = 0;
        }
        $count = count($pids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $pids[$i];
                $b = $pids[$j];
                $diff = $memo->diff($offset, $slot, $a, $ratingsByPid[$a], $b, $ratingsByPid[$b]);
                $net[$a] += $diff;
                $net[$b] -= $diff;
            }
        }

        return $net;
    }
}
