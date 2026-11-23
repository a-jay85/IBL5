<?php

declare(strict_types=1);

namespace Tests\Team;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamRepositoryInterface;
use Team\TeamAccomplishmentsCache;

/**
 * Unit tests for TeamAccomplishmentsCache.
 */
class TeamAccomplishmentsCacheTest extends TestCase
{
    /** @return list<array{year: int, name: string, award: string, id: int}> */
    private static function sampleRows(): array
    {
        return [
            ['year' => 2011, 'name' => 'Bulls', 'award' => 'IBL Champions', 'id' => 0],
            ['year' => 2009, 'name' => 'Bulls', 'award' => 'Best Record', 'id' => 3],
        ];
    }

    public function testFreshHitReturnsCachedRowsWithoutQuery(): void
    {
        $rows = self::sampleRows();
        $cache = self::createStub(DatabaseCacheInterface::class);
        $cache->method('get')->willReturn($rows);
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->never())->method('getTeamAccomplishments');

        $result = (new TeamAccomplishmentsCache($repo, $cache))->get('Bulls');

        $this->assertSame($rows, $result);
    }

    public function testMissQueriesRepositoryStoresWithDayTtlAndReturnsRows(): void
    {
        $rows = self::sampleRows();
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(TeamAccomplishmentsCache::keyFor('Bulls'), $rows, 86400);
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->once())->method('getTeamAccomplishments')->with('Bulls')->willReturn($rows);

        $result = (new TeamAccomplishmentsCache($repo, $cache))->get('Bulls');

        $this->assertSame($rows, $result);
    }

    public function testExpiredEntryRebuildsInsteadOfServingStale(): void
    {
        $oldRows = [['year' => 1999, 'name' => 'Bulls', 'award' => 'Old', 'id' => 1]];
        $newRows = self::sampleRows();
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('getStale')->willReturn($oldRows);
        $cache->expects($this->never())->method('getStale');
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getTeamAccomplishments')->willReturn($newRows);

        $result = (new TeamAccomplishmentsCache($repo, $cache))->get('Bulls');

        $this->assertSame($newRows, $result);
    }

    public function testEmptyRowsAreCachedAndServedWithoutRequery(): void
    {
        $cache = self::createStub(DatabaseCacheInterface::class);
        $cache->method('get')->willReturn([]);
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->never())->method('getTeamAccomplishments');

        $result = (new TeamAccomplishmentsCache($repo, $cache))->get('Celtics');

        $this->assertSame([], $result);
    }

    public function testKeyIsHashedPerTeamAndDistinctForCaseVariants(): void
    {
        $this->assertSame('team_accomplishments:' . sha1('Bulls'), TeamAccomplishmentsCache::keyFor('Bulls'));
        $this->assertNotSame(TeamAccomplishmentsCache::keyFor('Bulls'), TeamAccomplishmentsCache::keyFor('bulls'));
        $this->assertLessThanOrEqual(255, strlen(TeamAccomplishmentsCache::keyFor(str_repeat('x', 500))));
    }

    public function testForLeagueReturnsNullForOlympics(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $cache = self::createStub(DatabaseCacheInterface::class);

        $this->assertNull(TeamAccomplishmentsCache::forLeague(true, $repo, $cache));
        $this->assertInstanceOf(TeamAccomplishmentsCache::class, TeamAccomplishmentsCache::forLeague(false, $repo, $cache));
    }

    public function testInvalidateDeletesTeamKey(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->once())->method('delete')->with(TeamAccomplishmentsCache::keyFor('Bulls'));

        (new TeamAccomplishmentsCache($repo, $cache))->invalidate('Bulls');
    }
}
