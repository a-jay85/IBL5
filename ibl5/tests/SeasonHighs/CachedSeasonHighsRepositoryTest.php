<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use SeasonHighs\CachedSeasonHighsRepository;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * @phpstan-import-type SeasonHighEntry from \SeasonHighs\Contracts\SeasonHighsServiceInterface
 */
final class CachedSeasonHighsRepositoryTest extends TestCase
{
    private const STATS = [
        'Points' => '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)',
        'Rebounds' => '(`game_orb` + `game_drb`)',
    ];

    private JsonRoundTripCache $cache;

    protected function setUp(): void
    {
        $this->cache = new JsonRoundTripCache();
    }

    public function testCacheMissDelegatesToInnerAndStoresResult(): void
    {
        $fixture = $this->createFixture();
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($fixture);

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');
        $result = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');

        $this->assertSame($fixture, $result);
        $this->assertSame(1, $this->cache->setCount);
    }

    public function testCacheHitSkipsInner(): void
    {
        $fixture = $this->createFixture();
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($fixture);

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');
        $first = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');
        $second = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');

        $this->assertSame($fixture, $first);
        $this->assertSame($fixture, $second);
    }

    public function testCachedResultSurvivesJsonRoundTripWithStrictTypes(): void
    {
        $fixture = $this->createFixture();
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($fixture);

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');
        $uncached = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');
        $cached = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');

        $this->assertSame($uncached, $cached);
        $this->assertIsInt($cached['Points'][0]['value']);
        $this->assertIsInt($cached['Points'][0]['boxId'] ?? null);
        $this->assertIsInt($cached['Points'][0]['gameOfThatDay'] ?? null);
        $this->assertIsInt($cached['Points'][0]['pid'] ?? null);
        $this->assertIsInt($cached['Points'][1]['value']);
        $this->assertArrayNotHasKey('boxId', $cached['Points'][1]);
        $this->assertArrayNotHasKey('gameOfThatDay', $cached['Points'][1]);
        $this->assertSame([], $cached['Rebounds']);
    }

    public function testDifferentLeaguesProduceDifferentCacheKeys(): void
    {
        $iblFixture = $this->createFixture();
        $olympicsFixture = $this->createFixture(77);

        $iblInner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $iblInner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($iblFixture);
        $olympicsInner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $olympicsInner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($olympicsFixture);

        $ibl = new CachedSeasonHighsRepository($iblInner, $this->cache, 'ibl');
        $olympics = new CachedSeasonHighsRepository($olympicsInner, $this->cache, 'olympics');

        for ($i = 0; $i < 2; $i++) {
            $this->assertSame($iblFixture, $ibl->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30'));
            $this->assertSame($olympicsFixture, $olympics->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30'));
        }
    }

    public function testDifferentStatsMapsProduceDifferentCacheKeys(): void
    {
        $otherStats = self::STATS;
        $otherStats['Rebounds'] = '(`game_orb` + `game_drb` + 1)';

        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->exactly(2))->method('getSeasonHighsBatch')->willReturn($this->createFixture());

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');
        $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');
        $repository->getSeasonHighsBatch($otherStats, '', '2026-01-01', '2026-06-30');
    }

    public function testDistinctLocationFilterAndDateRangeAreSeparateEntries(): void
    {
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->exactly(3))->method('getSeasonHighsBatch')->willReturn($this->createFixture());

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');

        for ($i = 0; $i < 2; $i++) {
            $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30', 15, null);
            $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30', 15, 'home');
            $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-07-31', 15, null);
        }
    }

    public function testCacheKeyUsesVersionedPrefixAndTtl(): void
    {
        $inner = self::createStub(SeasonHighsRepositoryInterface::class);
        $inner->method('getSeasonHighsBatch')->willReturn($this->createFixture());

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');

        $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');
        $this->assertStringStartsWith('season_highs:v1:ibl:players:', $this->cache->lastKey);
        $this->assertSame(900, $this->cache->lastTtl);

        $repository->getSeasonHighsBatch(self::STATS, '_teams', '2026-01-01', '2026-06-30');
        $this->assertStringContainsString(':teams:', $this->cache->lastKey);
    }

    public function testMalformedCachedEntryFallsThroughToInner(): void
    {
        $fixture = $this->createFixture();
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->once())->method('getSeasonHighsBatch')->willReturn($fixture);

        // Learn the exact key from a throwaway miss, then seed a truncated row under it.
        $probeInner = self::createStub(SeasonHighsRepositoryInterface::class);
        $probeInner->method('getSeasonHighsBatch')->willReturn($fixture);
        $probeCache = new JsonRoundTripCache();
        (new CachedSeasonHighsRepository($probeInner, $probeCache, 'ibl'))
            ->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');

        $this->cache->seed($probeCache->lastKey, ['Points' => $fixture['Points']]);

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');
        $result = $repository->getSeasonHighsBatch(self::STATS, '', '2026-01-01', '2026-06-30');

        $this->assertSame($fixture, $result);
        $this->assertSame(1, $this->cache->setCount);
    }

    public function testPassThroughMethodsDelegateUncached(): void
    {
        $inner = $this->createMock(SeasonHighsRepositoryInterface::class);
        $inner->expects($this->exactly(2))->method('getSeasonHighs')->willReturn([]);
        $inner->expects($this->exactly(2))->method('getRcbSeasonHighs')->willReturn([]);

        $repository = new CachedSeasonHighsRepository($inner, $this->cache, 'ibl');

        for ($i = 0; $i < 2; $i++) {
            $this->assertSame([], $repository->getSeasonHighs('1', 'Points', '', '2026-01-01', '2026-06-30'));
            $this->assertSame([], $repository->getRcbSeasonHighs(2026, 'home'));
        }

        $this->assertSame(0, $this->cache->setCount);
    }

    /**
     * @return array<string, list<SeasonHighEntry>>
     */
    private function createFixture(int $baseValue = 50): array
    {
        return [
            'Points' => [
                [
                    'name' => 'Test Player',
                    'date' => '2026-02-14',
                    'value' => $baseValue,
                    'pid' => 1234,
                    'teamid' => 7,
                    'teamname' => 'Sample Team',
                    'team_city' => 'Sample City',
                    'color1' => 'FF0000',
                    'color2' => '0000FF',
                    'boxId' => 99001,
                    'gameOfThatDay' => 3,
                    'sortId' => 5,
                ],
                [
                    'name' => 'Other Player',
                    'date' => '2026-03-01',
                    'value' => $baseValue - 4,
                ],
            ],
            'Rebounds' => [],
        ];
    }
}

/**
 * Cache double that JSON-encodes on set and decodes on get, matching what
 * Cache\DatabaseCache does against the `cache` table.
 */
final class JsonRoundTripCache implements DatabaseCacheInterface
{
    /** @var array<string, string> */
    private array $store = [];

    public string $lastKey = '';
    public int $lastTtl = 0;
    public int $setCount = 0;

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        $decoded = json_decode($this->store[$key], true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = json_encode($data, JSON_THROW_ON_ERROR);
        $this->lastKey = $key;
        $this->lastTtl = $ttlSeconds;
        $this->setCount++;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return $this->get($key);
    }

    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        return true;
    }

    public function releaseLock(string $key): void
    {
    }

    /**
     * @param array<mixed> $data
     */
    public function seed(string $key, array $data): void
    {
        $this->store[$key] = json_encode($data, JSON_THROW_ON_ERROR);
    }
}
