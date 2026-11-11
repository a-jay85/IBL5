<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use Cache\Contracts\DatabaseCacheInterface;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\Contracts\SnapshotKeyRepositoryInterface;

/**
 * Caching decorator over the snapshot loader, one entry per league state. A
 * malformed cached payload is treated as a miss. It holds no team id: per-GM
 * work never passes through it.
 */
final class CachedLeagueSnapshotProvider implements LeagueSnapshotProviderInterface
{
    private const int TTL_SECONDS = 3600;

    private ?string $lastKey = null;

    public function __construct(
        private readonly LeagueSnapshotProviderInterface $inner,
        private readonly DatabaseCacheInterface $cache,
        private readonly SnapshotKeyRepositoryInterface $keys,
        private readonly ModeResolver $modes,
        private readonly \Season\Season $season,
    ) {
    }

    /**
     * @see LeagueSnapshotProviderInterface::snapshot()
     */
    public function snapshot(): LeagueSnapshot
    {
        $key = $this->currentKey();
        $this->lastKey = $key;

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            try {
                return SnapshotCodec::decode($cached);
            } catch (\UnexpectedValueException | \TypeError) {
                // A malformed payload is a miss; the rebuild below overwrites it.
            }
        }

        $snapshot = $this->inner->snapshot();
        $this->cache->set($key, SnapshotCodec::encode($snapshot), self::TTL_SECONDS);

        return $snapshot;
    }

    /**
     * @see LeagueSnapshotProviderInterface::lastKey()
     */
    public function lastKey(): ?string
    {
        return $this->lastKey;
    }

    /**
     * The key `snapshot()` would use now, without reading the cache or building.
     */
    public function currentKey(): string
    {
        $phase = $this->season->phase;

        return SnapshotCacheKey::build(
            $phase . ':' . $this->season->lastSimNumber,
            $phase,
            $this->keys->rosterDigest(),
            $this->keys->tradeMarker(),
            $this->modes->resolveAll(),
        );
    }
}
