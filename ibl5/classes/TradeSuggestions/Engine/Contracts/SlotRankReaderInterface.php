<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Contracts;

/**
 * The four slot-rank readers `PositionStrength::build` uses on `SlotRankTable`.
 */
interface SlotRankReaderInterface
{
    public function poolRank(string $slot, int $pid): ?int;

    public function starterRank(string $slot, int $tid): ?int;

    public function bestBenchRank(string $slot, int $tid): ?int;

    public function worstStarterPoolRank(string $slot): ?int;
}
