<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use Cache\Contracts\DatabaseCacheInterface;
use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;

/**
 * CachedHeadToHeadRecordsRepository - Caching decorator for HeadToHeadRecordsRepositoryInterface.
 *
 * Wraps an inner repository and caches each matrix payload via DatabaseCacheInterface.
 * Each entry is stored as an envelope stamped with the inner repository's
 * sourceFingerprint(). A hit is served only when that stamp matches the live
 * fingerprint; a mismatch, a corrupt envelope, or a legacy bare payload counts as
 * a miss, so no cache migration is needed at deploy.
 * On a miss, delegates to the inner repository, stores a fresh envelope, and returns the payload.
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
        return $this->throughCache('franchises', $phase, $scope, fn (): array => $this->inner->buildFranchisesMatrix($phase, $scope));
    }

    /**
     * Build the teams head-to-head matrix, served from cache when available.
     *
     * @return MatrixPayload
     */
    public function buildTeamsMatrix(string $phase, string $scope): array
    {
        return $this->throughCache('teams', $phase, $scope, fn (): array => $this->inner->buildTeamsMatrix($phase, $scope));
    }

    /**
     * Build the GMs head-to-head matrix, served from cache when available.
     *
     * @return MatrixPayload
     */
    public function buildGmsMatrix(string $phase, string $scope): array
    {
        return $this->throughCache('gms', $phase, $scope, fn (): array => $this->inner->buildGmsMatrix($phase, $scope));
    }

    /**
     * Pass-through: no caching — this check is cheap and must reflect live DB state.
     */
    public function currentSeasonHasGames(): bool
    {
        return $this->inner->currentSeasonHasGames();
    }

    /**
     * Pass-through: the fingerprint is the live value the stamped entries are compared against.
     */
    public function sourceFingerprint(): string
    {
        return $this->inner->sourceFingerprint();
    }

    /**
     * Rebuild all 24 cache entries (2 scopes × 3 dimensions × 4 phases).
     *
     * Calls the inner repository for every dimension/phase/scope combination
     * and stores each result, stamped with the live source fingerprint, with the standard TTL.
     *
     * @return int Number of cache keys written (always 24).
     */
    public function rebuildCache(): int
    {
        $live = $this->inner->sourceFingerprint();
        $written = 0;

        foreach (['current', 'all'] as $scope) {
            foreach (['heat', 'regular', 'playoffs', 'all'] as $phase) {
                $key = $this->key('franchises', $phase, $scope);
                $this->cache->set($key, self::envelope($live, $this->inner->buildFranchisesMatrix($phase, $scope)), self::TTL_SECONDS);
                $written++;

                $key = $this->key('teams', $phase, $scope);
                $this->cache->set($key, self::envelope($live, $this->inner->buildTeamsMatrix($phase, $scope)), self::TTL_SECONDS);
                $written++;

                $key = $this->key('gms', $phase, $scope);
                $this->cache->set($key, self::envelope($live, $this->inner->buildGmsMatrix($phase, $scope)), self::TTL_SECONDS);
                $written++;
            }
        }

        return $written;
    }

    /**
     * Serve a payload from the cache only when its stamped fingerprint matches the
     * live source fingerprint; otherwise rebuild it through $build and re-stamp.
     *
     * @param callable(): MatrixPayload $build
     * @return MatrixPayload
     */
    private function throughCache(string $dimension, string $phase, string $scope, callable $build): array
    {
        $key = $this->key($dimension, $phase, $scope);
        $live = $this->inner->sourceFingerprint();
        $hit = $this->cache->get($key);

        if (
            $hit !== null
            && isset($hit['fingerprint'], $hit['payload'])
            && $hit['fingerprint'] === $live
            && is_array($hit['payload'])
        ) {
            /** @var MatrixPayload $payload */
            $payload = $hit['payload'];
            return $payload;
        }

        $data = $build();
        $this->cache->set($key, self::envelope($live, $data), self::TTL_SECONDS);

        return $data;
    }

    /**
     * @param MatrixPayload $payload
     * @return array{fingerprint: string, payload: MatrixPayload}
     */
    private static function envelope(string $fingerprint, array $payload): array
    {
        return ['fingerprint' => $fingerprint, 'payload' => $payload];
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
