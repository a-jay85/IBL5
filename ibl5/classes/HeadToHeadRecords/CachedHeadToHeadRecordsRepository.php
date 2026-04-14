<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use Cache\Contracts\DatabaseCacheInterface;
use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;

/**
 * @phpstan-import-type Dimension from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Phase from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Scope from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
class CachedHeadToHeadRecordsRepository implements HeadToHeadRecordsRepositoryInterface
{
    private const CACHE_KEY_PREFIX = 'head_to_head_records:';
    private const TTL_SECONDS = 86400;

    /** @var list<Scope> */
    private const SCOPES = ['current', 'all_time'];

    /** @var list<Dimension> */
    private const DIMENSIONS = ['active_teams', 'all_time_teams', 'gms'];

    /** @var list<Phase> */
    private const PHASES = ['regular', 'playoffs', 'heat', 'all'];

    private HeadToHeadRecordsRepositoryInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(HeadToHeadRecordsRepositoryInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * @see HeadToHeadRecordsRepositoryInterface::getMatrix()
     *
     * @param Scope $scope
     * @param Dimension $dimension
     * @param Phase $phase
     * @return MatrixPayload
     */
    public function getMatrix(string $scope, string $dimension, string $phase, int $currentSeasonYear): array
    {
        $key = self::buildCacheKey($scope, $dimension, $phase);

        /** @var MatrixPayload|null $cached */
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->inner->getMatrix($scope, $dimension, $phase, $currentSeasonYear);
        $this->cache->set($key, $result, self::TTL_SECONDS);

        return $result;
    }

    /**
     * @see HeadToHeadRecordsRepositoryInterface::getPairsForActiveTeams()
     *
     * @return list<array{self: int, opponent: int, wins: int, losses: int}>
     */
    public function getPairsForActiveTeams(int $currentSeasonYear): array
    {
        return $this->inner->getPairsForActiveTeams($currentSeasonYear);
    }

    /**
     * Rebuild all 24 cache entries (2 scopes x 3 dimensions x 4 phases).
     */
    public function rebuildCache(int $currentSeasonYear): void
    {
        foreach (self::SCOPES as $scope) {
            foreach (self::DIMENSIONS as $dimension) {
                foreach (self::PHASES as $phase) {
                    $key = self::buildCacheKey($scope, $dimension, $phase);
                    $result = $this->inner->getMatrix($scope, $dimension, $phase, $currentSeasonYear);
                    $this->cache->set($key, $result, self::TTL_SECONDS);
                }
            }
        }
    }

    public function invalidateCache(): void
    {
        foreach (self::SCOPES as $scope) {
            foreach (self::DIMENSIONS as $dimension) {
                foreach (self::PHASES as $phase) {
                    $this->cache->delete(self::buildCacheKey($scope, $dimension, $phase));
                }
            }
        }
    }

    /**
     * @param Scope $scope
     * @param Dimension $dimension
     * @param Phase $phase
     */
    private static function buildCacheKey(string $scope, string $dimension, string $phase): string
    {
        return self::CACHE_KEY_PREFIX . $scope . ':' . $dimension . ':' . $phase;
    }
}
