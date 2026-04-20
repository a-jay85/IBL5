<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Cache\Contracts\DatabaseCacheInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;

/**
 * CachedSeasonLeaderboardsRepository - Caching decorator for SeasonLeaderboardsRepositoryInterface.
 *
 * Caches the full unsorted leaders result set, years list, and teams list.
 * On cache hit, filters by year/team, sorts by the requested stat, and
 * slices for limit — all in PHP. This avoids materializing the expensive
 * ibl_hist VIEW on every sort/filter change.
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

        // Filter by year
        $yearFilter = (string) ($filters['year'] ?? '');
        if ($yearFilter !== '') {
            $yearInt = (int) $yearFilter;
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['year'] === $yearInt
            ));
        }

        // Filter by team
        $teamId = (int) ($filters['team'] ?? 0);
        if ($teamId !== 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['teamid'] === $teamId
            ));
        }

        // Sort by the requested stat DESC
        $sortBy = (string) ($filters['sortby'] ?? 'PPG');
        usort($rows, static function (array $a, array $b) use ($sortBy): int {
            $aVal = self::getSortValue($a, $sortBy);
            $bVal = self::getSortValue($b, $sortBy);
            return $bVal <=> $aVal;
        });

        // Apply limit
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
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

    /**
     * Compute the sort value for a row, matching the SQL expressions in
     * SeasonLeaderboardsRepository::getSortColumn().
     *
     * @param HistRow $row
     */
    private static function getSortValue(array $row, string $sortBy): float
    {
        $games = $row['games'];
        if ($games === 0 && $sortBy !== 'GAMES') {
            return 0.0;
        }

        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];
        $tga = $row['tga'];

        return match ($sortBy) {
            'PPG' => (2 * $fgm + $ftm + $tgm) / $games,
            'REB' => $row['reb'] / $games,
            'OREB' => $row['orb'] / $games,
            'DREB' => ($row['reb'] - $row['orb']) / $games,
            'AST' => $row['ast'] / $games,
            'STL' => $row['stl'] / $games,
            'BLK' => $row['blk'] / $games,
            'TO' => $row['tvr'] / $games,
            'FOUL' => $row['pf'] / $games,
            'QA' => self::computeQaPerGame($row, $games),
            'FGM' => $fgm / $games,
            'FGA' => $fga / $games,
            'FGP' => $fga > 0 ? $fgm / $fga : 0.0,
            'FTM' => $ftm / $games,
            'FTA' => $fta / $games,
            'FTP' => $fta > 0 ? $ftm / $fta : 0.0,
            'TGM' => $tgm / $games,
            'TGA' => $tga / $games,
            'TGP' => $tga > 0 ? $tgm / $tga : 0.0,
            'GAMES' => (float) $games,
            'MIN' => $row['minutes'] / $games,
            default => (2 * $fgm + $ftm + $tgm) / $games,
        };
    }

    /**
     * QA per game — matches SQL expression:
     * ((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf)) / games
     *
     * @param HistRow $row
     */
    private static function computeQaPerGame(array $row, int $games): float
    {
        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];

        $pts = 2 * $fgm + $ftm + $tgm;
        $positive = $pts + $row['reb'] + (2 * $row['ast']) + (2 * $row['stl']) + (2 * $row['blk']);
        $negative = ($fga - $fgm) + ($fta - $ftm) + $row['tvr'] + $row['pf'];

        return ($positive - $negative) / $games;
    }
}
