<?php

declare(strict_types=1);

namespace Rivalries;

use Cache\Contracts\DatabaseCacheInterface;
use Rivalries\Contracts\RivalriesRepositoryInterface;

/**
 * CachedRivalriesRepository - Caching decorator for RivalriesRepositoryInterface.
 *
 * Mirrors CachedHeadToHeadRecordsRepository. Stats keys carry the recency
 * window year so a season rollover never serves stale recency counts. Pair
 * keys are canonical (lower key first); GM pair keys are sha1-hashed so they
 * stay at 64 chars or fewer whatever the names contain.
 *
 * @phpstan-import-type PairStat from RivalriesRepositoryInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type SeriesRow from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerTotal from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerHigh from RivalriesRepositoryInterface
 */
final class CachedRivalriesRepository implements RivalriesRepositoryInterface
{
    private const TTL_SECONDS = 86400; // 24 hours
    private const KEY_PREFIX = 'rivalries';
    private const PAIR_PARTS = ['games', 'series', 'performers'];

    public function __construct(
        private readonly RivalriesRepositoryInterface $inner,
        private readonly DatabaseCacheInterface $cache,
    ) {
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchisePairStats()
     */
    public function getFranchisePairStats(int $recentFromSeasonYear): array
    {
        $key = self::KEY_PREFIX . ":franchises:{$recentFromSeasonYear}";
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<PairStat> $hit */
            return $hit;
        }

        $data = $this->inner->getFranchisePairStats($recentFromSeasonYear);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPairStats()
     */
    public function getGmPairStats(int $recentFromSeasonYear): array
    {
        $key = self::KEY_PREFIX . ":gms:{$recentFromSeasonYear}";
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<PairStat> $hit */
            return $hit;
        }

        $data = $this->inner->getGmPairStats($recentFromSeasonYear);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchisePairGames()
     */
    public function getFranchisePairGames(int $a, int $b): array
    {
        [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
        $key = $this->teamsKey($lo, $hi, 'games');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<GameRow> $hit */
            return $hit;
        }

        $data = $this->inner->getFranchisePairGames($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPairGames()
     */
    public function getGmPairGames(string $a, string $b): array
    {
        [$lo, $hi] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
        $key = $this->gmsKey($lo, $hi, 'games');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<GameRow> $hit */
            return $hit;
        }

        $data = $this->inner->getGmPairGames($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchisePlayoffSeries()
     */
    public function getFranchisePlayoffSeries(int $a, int $b): array
    {
        [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
        $key = $this->teamsKey($lo, $hi, 'series');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<SeriesRow> $hit */
            return $hit;
        }

        $data = $this->inner->getFranchisePlayoffSeries($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPlayoffSeries()
     */
    public function getGmPlayoffSeries(string $a, string $b): array
    {
        [$lo, $hi] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
        $key = $this->gmsKey($lo, $hi, 'series');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var list<SeriesRow> $hit */
            return $hit;
        }

        $data = $this->inner->getGmPlayoffSeries($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchiseTopPerformers()
     */
    public function getFranchiseTopPerformers(int $a, int $b): array
    {
        [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
        $key = $this->teamsKey($lo, $hi, 'performers');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var array{totals: list<PerformerTotal>, highs: list<PerformerHigh>} $hit */
            return $hit;
        }

        $data = $this->inner->getFranchiseTopPerformers($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * @see RivalriesRepositoryInterface::getGmTopPerformers()
     */
    public function getGmTopPerformers(string $a, string $b): array
    {
        [$lo, $hi] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
        $key = $this->gmsKey($lo, $hi, 'performers');
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            /** @var array{totals: list<PerformerTotal>, highs: list<PerformerHigh>} $hit */
            return $hit;
        }

        $data = $this->inner->getGmTopPerformers($lo, $hi);
        $this->cache->set($key, $data, self::TTL_SECONDS);

        return $data;
    }

    /**
     * Pass-through: one indexed-row lookup, and caching a miss would hide a GM
     * added by the next import.
     */
    public function gmExists(string $gmName): bool
    {
        return $this->inner->gmExists($gmName);
    }

    /**
     * Refresh both stats keys and drop every pair key for the pairs in the fresh
     * stats, so pair pages repopulate lazily on the next request.
     *
     * @return int Keys set plus keys deleted.
     */
    public function rebuildCache(int $recentFromSeasonYear): int
    {
        $franchiseStats = $this->inner->getFranchisePairStats($recentFromSeasonYear);
        $this->cache->set(self::KEY_PREFIX . ":franchises:{$recentFromSeasonYear}", $franchiseStats, self::TTL_SECONDS);
        $gmStats = $this->inner->getGmPairStats($recentFromSeasonYear);
        $this->cache->set(self::KEY_PREFIX . ":gms:{$recentFromSeasonYear}", $gmStats, self::TTL_SECONDS);
        $count = 2;

        foreach ($franchiseStats as $stat) {
            foreach (self::PAIR_PARTS as $part) {
                $this->cache->delete($this->teamsKey((int) $stat['a'], (int) $stat['b'], $part));
                $count++;
            }
        }
        foreach ($gmStats as $stat) {
            foreach (self::PAIR_PARTS as $part) {
                $this->cache->delete($this->gmsKey($stat['a'], $stat['b'], $part));
                $count++;
            }
        }

        return $count;
    }

    private function teamsKey(int $lo, int $hi, string $part): string
    {
        return self::KEY_PREFIX . ":pair:teams:{$lo}-{$hi}:{$part}";
    }

    /**
     * 128 bits of sha1 keeps the longest key (`performers`) at 62 chars. The
     * names are re-sorted with strcmp here because rebuildCache passes them in
     * SQL LEAST/GREATEST collation order, which can differ from strcmp order.
     */
    private function gmsKey(string $a, string $b, string $part): string
    {
        [$lo, $hi] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];

        return self::KEY_PREFIX . ":pair:gms:{$part}:" . substr(sha1($lo . "\0" . $hi), 0, 32);
    }
}
