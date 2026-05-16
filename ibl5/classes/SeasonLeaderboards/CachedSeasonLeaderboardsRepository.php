<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Cache\Contracts\DatabaseCacheInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;

/**
 * CachedSeasonLeaderboardsRepository - Caching decorator for SeasonLeaderboardsRepositoryInterface.
 *
 * Pure cache pass-through: caches the full result set from the inner repository.
 * Filtering, sorting, and limiting are handled by SeasonLeaderboardsService.
 *
 * @phpstan-import-type HistRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardFilters from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardResult from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type TeamRow from SeasonLeaderboardsRepositoryInterface
 */
class CachedSeasonLeaderboardsRepository implements SeasonLeaderboardsRepositoryInterface
{
    private const CACHE_KEY_LEADERS = 'season_leaderboards:leaders';
    private const CACHE_KEY_YEARS = 'season_leaderboards:years';
    private const CACHE_KEY_TEAMS = 'season_leaderboards:teams';
    private const TTL_SECONDS = 86400; // 24 hours

    private SeasonLeaderboardsRepositoryInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(SeasonLeaderboardsRepositoryInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getSeasonLeaders()
     *
     * @param LeaderboardFilters $filters
     * @return LeaderboardResult
     */
    public function getSeasonLeaders(array $filters, int $limit = 0): array
    {
        /** @var list<HistRow>|null $rows */
        $rows = $this->cache->get(self::CACHE_KEY_LEADERS);

        if ($rows === null) {
            $innerResult = $this->inner->getSeasonLeaders([], 0);
            $rows = $innerResult['results'];
            $this->cache->set(self::CACHE_KEY_LEADERS, $rows, self::TTL_SECONDS);
        }

        return [
            'results' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getTeams()
     *
     * @return list<TeamRow>
     */
    public function getTeams(): array
    {
        /** @var list<TeamRow>|null $teams */
        $teams = $this->cache->get(self::CACHE_KEY_TEAMS);

        if ($teams === null) {
            $teams = $this->inner->getTeams();
            $this->cache->set(self::CACHE_KEY_TEAMS, $teams, self::TTL_SECONDS);
        }

        return $teams;
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getYears()
     *
     * @return list<int>
     */
    public function getYears(): array
    {
        /** @var list<int>|null $years */
        $years = $this->cache->get(self::CACHE_KEY_YEARS);

        if ($years === null) {
            $years = $this->inner->getYears();
            $this->cache->set(self::CACHE_KEY_YEARS, $years, self::TTL_SECONDS);
        }

        return $years;
    }

    /**
     * Rebuild cache for all 3 keys.
     *
     * Called by the warm-cache CLI script and optionally after game simulations.
     */
    public function rebuildCache(): void
    {
        $innerResult = $this->inner->getSeasonLeaders([], 0);
        $this->cache->set(self::CACHE_KEY_LEADERS, $innerResult['results'], self::TTL_SECONDS);

        $this->cache->set(self::CACHE_KEY_YEARS, $this->inner->getYears(), self::TTL_SECONDS);
        $this->cache->set(self::CACHE_KEY_TEAMS, $this->inner->getTeams(), self::TTL_SECONDS);
    }

    /**
     * Invalidate cache for all 3 keys.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY_LEADERS);
        $this->cache->delete(self::CACHE_KEY_YEARS);
        $this->cache->delete(self::CACHE_KEY_TEAMS);
    }
}
