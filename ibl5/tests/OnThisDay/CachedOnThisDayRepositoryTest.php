<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use Cache\Contracts\DatabaseCacheInterface;
use OnThisDay\CachedOnThisDayRepository;
use OnThisDay\Contracts\OnThisDayRepositoryInterface;
use OnThisDay\Moment;
use OnThisDay\MomentKind;
use PHPUnit\Framework\TestCase;

final class CachedOnThisDayRepositoryTest extends TestCase
{
    private const KEY_2005_FEB_14 = 'on_this_day:fifty_point:2005:02-14';

    private function makeMoment(string $key = 'fifty_point:2000-02-14:7'): Moment
    {
        return new Moment(
            kind: MomentKind::FiftyPointGame,
            key: $key,
            date: '2000-02-14',
            seasonYear: 2000,
            pid: 7,
            playerName: 'Test Player',
            teamId: 3,
            teamName: 'Team A',
            opponentTeamId: 4,
            opponentName: 'Team B',
            facts: ['points' => 55, 'rebounds' => 8, 'assists' => 3, 'steals' => 1, 'blocks' => 0],
            statLabel: null,
        );
    }

    public function testCacheMissCallsInnerAndStores(): void
    {
        $moment = $this->makeMoment();
        $cache = new OnThisDayInMemoryCache();

        $inner = $this->createMock(OnThisDayRepositoryInterface::class);
        $inner->expects($this->once())
            ->method('findFiftyPointGames')
            ->with(['02-14'], 2005)
            ->willReturn([$moment]);

        $repo = new CachedOnThisDayRepository($inner, $cache);

        $this->assertEquals([$moment], $repo->findFiftyPointGames(['02-14'], 2005));
        $this->assertSame([$moment->toArray()], $cache->get(self::KEY_2005_FEB_14));
        $this->assertSame(86400, $cache->ttlOf(self::KEY_2005_FEB_14));
    }

    public function testCacheHitSkipsInnerAndRehydratesMoments(): void
    {
        $moment = $this->makeMoment();
        $cache = new OnThisDayInMemoryCache();

        $inner = $this->createMock(OnThisDayRepositoryInterface::class);
        $inner->expects($this->once())
            ->method('findFiftyPointGames')
            ->willReturn([$moment]);

        $repo = new CachedOnThisDayRepository($inner, $cache);

        $first = $repo->findFiftyPointGames(['02-14'], 2005);
        $second = $repo->findFiftyPointGames(['02-14'], 2005);

        $this->assertEquals($first, $second);
        $this->assertEquals([$moment], $second);
    }

    public function testCorruptCacheEntryFallsThroughToInner(): void
    {
        $moment = $this->makeMoment();
        $cache = new OnThisDayInMemoryCache();
        $cache->set(self::KEY_2005_FEB_14, [['kind' => 'not_a_kind', 'key' => 'x']], 86400);

        $inner = $this->createMock(OnThisDayRepositoryInterface::class);
        $inner->expects($this->once())
            ->method('findFiftyPointGames')
            ->willReturn([$moment]);

        $repo = new CachedOnThisDayRepository($inner, $cache);

        $this->assertEquals([$moment], $repo->findFiftyPointGames(['02-14'], 2005));
        $this->assertSame([$moment->toArray()], $cache->get(self::KEY_2005_FEB_14));
    }

    public function testDifferentWindowUsesDifferentKey(): void
    {
        $moment = $this->makeMoment();
        $cache = new OnThisDayInMemoryCache();

        $inner = $this->createMock(OnThisDayRepositoryInterface::class);
        $inner->expects($this->exactly(2))
            ->method('findFiftyPointGames')
            ->willReturn([$moment]);

        $repo = new CachedOnThisDayRepository($inner, $cache);

        $repo->findFiftyPointGames(['02-14'], 2005);
        $repo->findFiftyPointGames(['02-13', '02-14', '02-15'], 2005);

        $this->assertNotNull($cache->get(self::KEY_2005_FEB_14));
        $this->assertNotNull($cache->get('on_this_day:fifty_point:2005:02-13,02-14,02-15'));
    }
}

/**
 * In-memory DatabaseCacheInterface fake for OnThisDay tests.
 */
class OnThisDayInMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array{data: array<mixed>, ttl: int}> */
    private array $store = [];

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->store[$key]['data'] ?? null;
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return $this->store[$key]['data'] ?? null;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = ['data' => $data, 'ttl' => $ttlSeconds];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        return true;
    }

    public function releaseLock(string $key): void
    {
        // no-op
    }

    public function ttlOf(string $key): ?int
    {
        return $this->store[$key]['ttl'] ?? null;
    }
}
