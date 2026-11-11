<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\TestCase;
use Season\Season;
use SeasonHighs\CachedSeasonHighsRepository;
use SeasonHighs\SeasonHighsCacheWarmer;
use SeasonHighs\SeasonHighsService;

final class SeasonHighsCacheWarmerTest extends TestCase
{
    /** The phase strings the Records tab can resolve to a distinct date range. Kept apart from the production constant on purpose. */
    private const PHASES = ['Regular Season', 'Playoffs', 'Preseason', 'HEAT'];

    private JsonRoundTripCache $cache;
    private CountingSeasonHighsInner $inner;
    private CachedSeasonHighsRepository $repository;
    private Season $season;
    private SeasonHighsCacheWarmer $warmer;

    protected function setUp(): void
    {
        $this->cache = new JsonRoundTripCache();
        $this->inner = new CountingSeasonHighsInner();
        $this->repository = new CachedSeasonHighsRepository($this->inner, $this->cache, 'ibl');
        $this->season = self::createStub(Season::class);
        $this->season->beginningYear = 2025;
        $this->season->endingYear = 2026;
        $this->warmer = new SeasonHighsCacheWarmer($this->repository, $this->season);
    }

    public function testExpectedKeysMatchReadPathKeysForEveryWarmPhase(): void
    {
        $readCache = new JsonRoundTripCache();
        $readRepository = new CachedSeasonHighsRepository(new CountingSeasonHighsInner(), $readCache, 'ibl');
        $readService = new SeasonHighsService($readRepository, $this->season);

        foreach (self::PHASES as $phase) {
            $readService->getHomeAwayHighs($phase);
            $readService->getSeasonHighsData($phase);
        }

        $readKeys = array_values(array_unique($readCache->keysOf('get')));
        $expected = $this->warmer->expectedKeys();
        sort($readKeys);
        sort($expected);

        $this->assertNotSame([], $expected);
        $this->assertSame($readKeys, $expected);
    }

    public function testWarmPhasesResolveToDistinctKeySets(): void
    {
        $sets = [];
        foreach (SeasonHighsCacheWarmer::WARM_PHASES as $phase) {
            $service = new SeasonHighsService($this->repository, $this->season);
            $keys = $this->repository->planKeys(static function () use ($service, $phase): void {
                $service->getHomeAwayHighs($phase);
                $service->getSeasonHighsData($phase);
            });
            $this->assertNotSame([], $keys, $phase . ' requested no keys');
            $sets[$phase] = $keys;
        }

        $phases = array_keys($sets);
        foreach ($phases as $i => $first) {
            foreach (array_slice($phases, $i + 1) as $second) {
                $this->assertSame(
                    [],
                    array_intersect($sets[$first], $sets[$second]),
                    $first . ' and ' . $second . ' share keys'
                );
            }
        }
    }

    public function testUnlistedPhaseStringSharesRegularSeasonKeys(): void
    {
        $service = new SeasonHighsService($this->repository, $this->season);
        $draftKeys = $this->repository->planKeys(static function () use ($service): void {
            $service->getHomeAwayHighs('Draft');
            $service->getSeasonHighsData('Draft');
        });

        $this->assertNotSame([], $draftKeys);
        $this->assertSame([], array_diff($draftKeys, $this->warmer->expectedKeys()));
    }

    public function testRebuildWritesEveryExpectedKeyWithThirtyDayTtl(): void
    {
        $expected = $this->warmer->expectedKeys();

        $written = $this->warmer->rebuild();

        $this->assertSame(count($expected), $written);
        foreach ($expected as $key) {
            $this->assertSame(2592000, $this->cache->ttlOf($key));
        }
    }

    public function testInvalidateDeletesEveryExpectedKey(): void
    {
        $this->warmer->rebuild();
        $expected = $this->warmer->expectedKeys();

        $deleted = $this->warmer->invalidate();

        $this->assertSame(count($expected), $deleted);
        foreach ($expected as $key) {
            $this->assertNull($this->cache->get($key));
        }
    }

    public function testInvalidateMakesNoInnerRepositoryCalls(): void
    {
        $this->warmer->invalidate();

        $this->assertSame(0, $this->inner->calls);
    }
}
