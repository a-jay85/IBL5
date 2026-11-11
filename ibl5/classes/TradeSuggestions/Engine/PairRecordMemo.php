<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Projection\DominanceComparator;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SlotMapper;

/**
 * Request-scoped memo of `DominanceComparator::record` net results. One packed
 * int key per (offset, slot, lower pid, higher pid). The stored value is the
 * lower pid's net record against the higher pid.
 */
final class PairRecordMemo
{
    private const int PID_LIMIT = 1 << 20;

    /** @var array<int, int> */
    private array $memo = [];

    /**
     * `record['wins'] - record['losses']` of `$a` against `$b` at `$slot`.
     */
    public function diff(int $offset, string $slot, int $pidA, RatingSet $a, int $pidB, RatingSet $b): int
    {
        if ($pidA >= self::PID_LIMIT || $pidB >= self::PID_LIMIT) {
            throw new \OverflowException("Pid above the memo key range at slot {$slot}");
        }
        if ($pidA === $pidB) {
            return 0;
        }
        $slotIndex = array_search($slot, SlotMapper::SLOTS, true);
        if (!is_int($slotIndex)) {
            throw new \InvalidArgumentException("Unknown slot {$slot}");
        }
        $swapped = $pidA > $pidB;
        $lo = $swapped ? $pidB : $pidA;
        $hi = $swapped ? $pidA : $pidB;
        $key = (($offset * 8 + $slotIndex) << 40) | ($lo << 20) | $hi;

        if (!isset($this->memo[$key])) {
            $record = DominanceComparator::record($swapped ? $b : $a, $swapped ? $a : $b, $slot);
            $this->memo[$key] = $record['wins'] - $record['losses'];
        }

        return $swapped ? -$this->memo[$key] : $this->memo[$key];
    }

    public function size(): int
    {
        return count($this->memo);
    }
}
