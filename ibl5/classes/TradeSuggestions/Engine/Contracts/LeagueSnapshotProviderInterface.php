<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Contracts;

use TradeSuggestions\Engine\LeagueSnapshot;

/**
 * Source of the league snapshot. The uncached loader has no key; the cached
 * provider reports the key of its most recent `snapshot()` call.
 */
interface LeagueSnapshotProviderInterface
{
    public function snapshot(): LeagueSnapshot;

    /**
     * Cache key of the most recent `snapshot()` call; null before the first
     * call and always null for a provider with no cache.
     */
    public function lastKey(): ?string;
}
