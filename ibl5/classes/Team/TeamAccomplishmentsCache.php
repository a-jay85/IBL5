<?php

declare(strict_types=1);

namespace Team;

use Cache\Contracts\DatabaseCacheInterface;
use Team\Contracts\TeamRepositoryInterface;

/**
 * Per-team cache of TeamRepository::getTeamAccomplishments() rows.
 *
 * Updater\Steps\RefreshTeamAccomplishmentsStep rebuilds every real team's row after each IBL
 * sim, or deletes them when it runs over budget or fails. An expired row is rebuilt inline on the
 * next view and never served stale, so a write outside the updater shows within TTL_SECONDS.
 * The Olympics team page bypasses this cache (forLeague()).
 *
 * @phpstan-import-type TeamAwardRow from TeamRepositoryInterface
 */
final class TeamAccomplishmentsCache
{
    public const KEY_PREFIX = 'team_accomplishments:';
    public const TTL_SECONDS = 86400;

    public function __construct(
        private readonly TeamRepositoryInterface $repository,
        private readonly DatabaseCacheInterface $cache,
    ) {
    }

    public static function forLeague(bool $isOlympics, TeamRepositoryInterface $repository, DatabaseCacheInterface $cache): ?self
    {
        if ($isOlympics) {
            return null;
        }

        return new self($repository, $cache);
    }

    public static function keyFor(string $teamName): string
    {
        return self::KEY_PREFIX . sha1($teamName);
    }

    /**
     * @return list<TeamAwardRow>
     */
    public function get(string $teamName): array
    {
        $fresh = $this->cache->get(self::keyFor($teamName));
        if ($fresh !== null) {
            /** @var list<TeamAwardRow> $fresh */
            return $fresh;
        }

        return $this->rebuild($teamName);
    }

    /**
     * @return list<TeamAwardRow>
     */
    public function rebuild(string $teamName): array
    {
        $rows = $this->repository->getTeamAccomplishments($teamName);
        $this->cache->set(self::keyFor($teamName), $rows, self::TTL_SECONDS);

        return $rows;
    }

    public function invalidate(string $teamName): void
    {
        $this->cache->delete(self::keyFor($teamName));
    }
}
