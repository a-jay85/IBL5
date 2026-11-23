<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Projection\DominanceComparator;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SlotMapper;

/**
 * Request-scoped memo of `DominanceComparator::record` net results. One packed
 * int key per (offset, slot, lower index, higher index). Each pid gets a dense
 * index on first sight, so any pid fits the key's 20-bit fields. One league
 * snapshot holds a few thousand distinct pids, far under 2^20. The stored
 * value is the lower pid's net record against the higher pid.
 */
final class PairRecordMemo
{
    /** @var array<int, int> */
    private array $memo = [];

    /** @var array<int, int> pid => dense index */
    private array $indexByPid = [];

    /**
     * `record['wins'] - record['losses']` of `$a` against `$b` at `$slot`.
     */
    public function diff(int $offset, string $slot, int $pidA, RatingSet $a, int $pidB, RatingSet $b): int
    {
        if ($pidA === $pidB) {
            return 0;
        }
        $slotIndex = array_search($slot, SlotMapper::SLOTS, true);
        if (!is_int($slotIndex)) {
            throw new \InvalidArgumentException("Unknown slot {$slot}");
        }
        $swapped = $pidA > $pidB;
        $lo = $this->index($swapped ? $pidB : $pidA);
        $hi = $this->index($swapped ? $pidA : $pidB);
        $key = (($offset * 8 + $slotIndex) << 40) | ($lo << 20) | $hi;

        if (!isset($this->memo[$key])) {
            $record = DominanceComparator::record($swapped ? $b : $a, $swapped ? $a : $b, $slot);
            $this->memo[$key] = $record['wins'] - $record['losses'];
        }

        return $swapped ? -$this->memo[$key] : $this->memo[$key];
    }

    private function index(int $pid): int
    {
        if (isset($this->indexByPid[$pid])) {
            return $this->indexByPid[$pid];
        }
        return $this->indexByPid[$pid] = count($this->indexByPid);
    }

    public function size(): int
    {
        return count($this->memo);
    }
}
