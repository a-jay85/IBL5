<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use Cache\Contracts\DatabaseCacheInterface;
use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;

/**
 * CachedHeadToHeadRecordsRepository - Caching decorator for HeadToHeadRecordsRepositoryInterface.
 *
 * Wraps an inner repository and caches each matrix payload via DatabaseCacheInterface.
 * On cache hit, returns data from the cache without calling the inner repository.
 * On cache miss, delegates to the inner repository, stores the result, and returns it.
 *
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
class CachedHeadToHeadRecordsRepository implements HeadToHeadRecordsRepositoryInterface
{
    private const TTL_SECONDS = 86400; // 24 hours
    private const KEY_PREFIX = 'h2h_records';

    private HeadToHeadRecordsRepositoryInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(HeadToHeadRecordsRepositoryInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * Build the franchises head-to-head matrix, served from cache when available.
     *
     * @return MatrixPayload
     */
    public function buildFranchisesMatrix(string $phase, string $scope): array
    {
        $key = $this->key('franchises', $phase, $scope);
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var MatrixPayload $hit */
            return $hit;
        }

        $data = $this->inner->buildFranchisesMatrix($phase, $scope);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * Build the teams head-to-head matrix, served from cache when available.
     *
     * @return MatrixPayload
     */
    public function buildTeamsMatrix(string $phase, string $scope): array
    {
        $key = $this->key('teams', $phase, $scope);
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var MatrixPayload $hit */
            return $hit;
        }

        $data = $this->inner->buildTeamsMatrix($phase, $scope);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * Build the GMs head-to-head matrix, served from cache when available.
     *
     * @return MatrixPayload
     */
    public function buildGmsMatrix(string $phase, string $scope): array
    {
        $key = $this->key('gms', $phase, $scope);
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var MatrixPayload $hit */
            return $hit;
        }

        $data = $this->inner->buildGmsMatrix($phase, $scope);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * Pass-through: no caching — this check is cheap and must reflect live DB state.
     */
    public function currentSeasonHasGames(): bool
    {
        return $this->inner->currentSeasonHasGames();
    }

    /**
     * Rebuild all 24 cache entries (2 scopes × 3 dimensions × 4 phases).
     *
     * Calls the inner repository for every dimension/phase/scope combination
     * and stores each result in the cache with the standard TTL.
     *
     * @return int Number of cache keys written (always 24).
     */
    public function rebuildCache(): int
    {
        $written = 0;

        foreach (['current', 'all'] as $scope) {
            foreach (['heat', 'regular', 'playoffs', 'all'] as $phase) {
                $key = $this->key('franchises', $phase, $scope);
                $this->cache->set($key, $this->inner->buildFranchisesMatrix($phase, $scope), self::TTL_SECONDS);
                $written++;

                $key = $this->key('teams', $phase, $scope);
                $this->cache->set($key, $this->inner->buildTeamsMatrix($phase, $scope), self::TTL_SECONDS);
                $written++;

                $key = $this->key('gms', $phase, $scope);
                $this->cache->set($key, $this->inner->buildGmsMatrix($phase, $scope), self::TTL_SECONDS);
                $written++;
            }
        }

        return $written;
    }

    /**
     * Delete all 24 cached matrix entries.
     */
    public function invalidate(): void
    {
        foreach (['current', 'all'] as $scope) {
            foreach (['franchises', 'teams', 'gms'] as $dimension) {
                foreach (['heat', 'regular', 'playoffs', 'all'] as $phase) {
                    $this->cache->delete($this->key($dimension, $phase, $scope));
                }
            }
        }
    }

    /**
     * Build the cache key for the given dimension/phase/scope triple.
     */
    private function key(string $dimension, string $phase, string $scope): string
    {
        return self::KEY_PREFIX . ":{$dimension}:{$phase}:{$scope}";
    }
}
