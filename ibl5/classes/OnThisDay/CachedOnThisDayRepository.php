<?php

declare(strict_types=1);

namespace OnThisDay;

use Cache\Contracts\DatabaseCacheInterface;
use OnThisDay\Contracts\OnThisDayRepositoryInterface;

/**
 * CachedOnThisDayRepository - Caching decorator for OnThisDayRepositoryInterface.
 *
 * Each source is cached per (league, source, season year, month/day window). The
 * league is in the key because the inner repository rewrites box score tables per
 * league. The window is part of the key, so every sim with a new date gets a fresh entry. Only past
 * seasons are queried, so a 24-hour TTL never serves a stale fact. A corrupt cache
 * entry is treated as a miss and overwritten.
 */
class CachedOnThisDayRepository implements OnThisDayRepositoryInterface
{
    private const TTL_SECONDS = 86400; // 24 hours
    private const KEY_PREFIX = 'on_this_day';

    private OnThisDayRepositoryInterface $inner;
    private DatabaseCacheInterface $cache;
    private string $league;

    public function __construct(
        OnThisDayRepositoryInterface $inner,
        DatabaseCacheInterface $cache,
        string $league = \League\LeagueContext::LEAGUE_IBL,
    ) {
        $this->inner = $inner;
        $this->cache = $cache;
        $this->league = $league;
    }

    /**
     * @see OnThisDayRepositoryInterface::findFiftyPointGames()
     */
    public function findFiftyPointGames(array $monthDays, int $beforeSeasonYear): array
    {
        $key = $this->key('fifty_point', $monthDays, $beforeSeasonYear);

        return $this->fromCache($key)
            ?? $this->store($key, $this->inner->findFiftyPointGames($monthDays, $beforeSeasonYear));
    }

    /**
     * @see OnThisDayRepositoryInterface::findTripleDoubles()
     */
    public function findTripleDoubles(array $monthDays, int $beforeSeasonYear): array
    {
        $key = $this->key('triple_double', $monthDays, $beforeSeasonYear);

        return $this->fromCache($key)
            ?? $this->store($key, $this->inner->findTripleDoubles($monthDays, $beforeSeasonYear));
    }

    /**
     * @see OnThisDayRepositoryInterface::findRecordGames()
     */
    public function findRecordGames(array $monthDays, int $beforeSeasonYear): array
    {
        $key = $this->key('record_game', $monthDays, $beforeSeasonYear);

        return $this->fromCache($key)
            ?? $this->store($key, $this->inner->findRecordGames($monthDays, $beforeSeasonYear));
    }

    /**
     * @see OnThisDayRepositoryInterface::findSeriesClinchers()
     */
    public function findSeriesClinchers(array $monthDays, int $beforeSeasonYear): array
    {
        $key = $this->key('series_clincher', $monthDays, $beforeSeasonYear);

        return $this->fromCache($key)
            ?? $this->store($key, $this->inner->findSeriesClinchers($monthDays, $beforeSeasonYear));
    }

    /**
     * @see OnThisDayRepositoryInterface::findTrades()
     */
    public function findTrades(array $monthDays, int $beforeSeasonYear): array
    {
        $key = $this->key('trade', $monthDays, $beforeSeasonYear);

        return $this->fromCache($key)
            ?? $this->store($key, $this->inner->findTrades($monthDays, $beforeSeasonYear));
    }

    /**
     * Pass-through: no caching.
     *
     * @see OnThisDayRepositoryInterface::findSimEndDate()
     */
    public function findSimEndDate(int $sim): ?string
    {
        return $this->inner->findSimEndDate($sim);
    }

    /**
     * Rehydrate moments from a cache hit; null on a miss or a corrupt entry.
     *
     * @return list<Moment>|null
     */
    private function fromCache(string $key): ?array
    {
        $hit = $this->cache->get($key);
        if ($hit === null) {
            return null;
        }

        try {
            $moments = [];
            foreach ($hit as $row) {
                if (!is_array($row)) {
                    return null;
                }
                $moments[] = Moment::fromArray($row);
            }

            return $moments;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param list<Moment> $moments
     * @return list<Moment>
     */
    private function store(string $key, array $moments): array
    {
        $this->cache->set($key, array_map(static fn (Moment $m): array => $m->toArray(), $moments), self::TTL_SECONDS);

        return $moments;
    }

    /**
     * @param list<string> $monthDays
     */
    private function key(string $source, array $monthDays, int $beforeSeasonYear): string
    {
        return self::KEY_PREFIX . ':' . $this->league . ':' . $source . ':' . $beforeSeasonYear . ':' . implode(',', $monthDays);
    }
}
