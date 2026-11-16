<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape\Contracts;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\TradeCandidate;

/**
 * One deal shape. It reads only the snapshot and the asset pool, so it can
 * never widen the generation budget. A shape whose pattern is absent yields
 * nothing and throws nothing. Every candidate it yields has both pid lists
 * sorted ascending and at most two assets per side.
 */
interface DealShapeInterface
{
    /**
     * Stable upper-snake id, e.g. `ONE_FOR_ONE`.
     */
    public function id(): string;

    /**
     * @return iterable<TradeCandidate>
     */
    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable;
}
