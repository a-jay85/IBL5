<?php

declare(strict_types=1);

namespace Tests\Updater;

use Cache\Contracts\DatabaseCacheInterface;
use Clock\ClockInterface;
use PHPUnit\Framework\TestCase;
use Season\Season;
use SeasonHighs\CachedSeasonHighsRepository;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use SeasonHighs\SeasonHighsCacheWarmer;
use SeasonHighs\SeasonHighsService;
use Tests\SeasonHighs\CountingSeasonHighsInner;
use Tests\SeasonHighs\JsonRoundTripCache;
use Tests\SeasonHighs\ThrowingDeleteCache;
use Tests\SeasonHighs\ThrowingOnNthCallSeasonHighsInner;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshSeasonHighsCacheStep;

/**
 * @covers \Updater\Steps\RefreshSeasonHighsCacheStep
 */
final class RefreshSeasonHighsCacheStepTest extends TestCase
{
    private const STARTED_AT = 1000.0;

    private Season $season;

    protected function setUp(): void
    {
        $this->season = self::createStub(Season::class);
        $this->season->beginningYear = 2025;
        $this->season->endingYear = 2026;
    }

    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshSeasonHighsCacheStep::class)
        );
    }

    public function testSuccessInvalidatesThenWarmsEveryKey(): void
    {
        $cache = new JsonRoundTripCache();
        $inner = new CountingSeasonHighsInner();
        $warmer = $this->buildWarmer($inner, $cache);
        $expected = $warmer->expectedKeys();
        $this->seedLazily($cache);
        $cache->clearLog();
        $innerCallsBefore = $inner->calls;

        $result = $this->buildStep($warmer, 10.0)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        $this->assertGreaterThan($innerCallsBefore, $inner->calls);
        foreach ($expected as $key) {
            $this->assertSame(2592000, $cache->ttlOf($key));
        }

        $methods = array_map(static fn (array $entry): string => $entry[0], $cache->log);
        $lastDelete = array_keys($methods, 'delete', true);
        $firstSet = array_search('set', $methods, true);
        $this->assertNotSame([], $lastDelete);
        $this->assertNotFalse($firstSet);
        $this->assertLessThan($firstSet, max($lastDelete));
        $this->assertEqualsCanonicalizing($expected, $cache->keysOf('delete'));
    }

    public function testBudgetSkipStillDeletesEveryKey(): void
    {
        $cache = new JsonRoundTripCache();
        $inner = new CountingSeasonHighsInner();
        $warmer = $this->buildWarmer($inner, $cache);
        $expected = $warmer->expectedKeys();
        $this->seedLazily($cache);
        foreach ($expected as $key) {
            $this->assertNotNull($cache->get($key), 'pre-seed missed ' . $key);
        }
        $cache->clearLog();

        $result = $this->buildStep($warmer, 250.0)->execute();

        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('250.0 s', $result->detail);
        $this->assertSame([], $cache->keysOf('set'));
        $this->assertSame(0, $inner->calls);
        foreach ($expected as $key) {
            $this->assertNull($cache->get($key), 'key survived the skip: ' . $key);
        }
    }

    public function testElapsedEqualToBudgetStillWarms(): void
    {
        $cache = new JsonRoundTripCache();
        $warmer = $this->buildWarmer(new CountingSeasonHighsInner(), $cache);
        $expected = $warmer->expectedKeys();

        $result = $this->buildStep($warmer, RefreshSeasonHighsCacheStep::DEFAULT_BUDGET_SECONDS)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        foreach ($expected as $key) {
            $this->assertSame(2592000, $cache->ttlOf($key));
        }
    }

    public function testRebuildFailureDeletesEveryKeyIncludingWrittenOnes(): void
    {
        $cache = new JsonRoundTripCache();
        $inner = new ThrowingOnNthCallSeasonHighsInner(3);
        $warmer = $this->buildWarmer($inner, $cache);
        $expected = $warmer->expectedKeys();
        $this->assertGreaterThan(2, count($expected));

        $result = $this->buildStep($warmer, 10.0)->execute();

        $this->assertFalse($result->success);
        $this->assertFalse($result->skipped);
        $this->assertSame('Season Highs cache warm failed: inner failed on call 3', $result->errorMessage);
        $this->assertSame(2, $cache->setCount, 'two keys should be written before the inner throws');
        foreach ($expected as $key) {
            $this->assertNull($cache->get($key), 'key survived the failure: ' . $key);
        }
    }

    public function testInvalidateFailureReturnsFailure(): void
    {
        $cache = new ThrowingDeleteCache();
        $inner = new CountingSeasonHighsInner();
        $warmer = $this->buildWarmer($inner, $cache);

        $result = $this->buildStep($warmer, 10.0)->execute();

        $this->assertFalse($result->success);
        $this->assertSame('Season Highs cache invalidation failed: cache delete failed', $result->errorMessage);
        $this->assertSame(0, $inner->calls);
        $this->assertSame(1, $cache->deleteAttempts, 'no cleanup or warm should follow a failed first invalidate');
    }

    private function buildWarmer(SeasonHighsRepositoryInterface $inner, DatabaseCacheInterface $cache): SeasonHighsCacheWarmer
    {
        return new SeasonHighsCacheWarmer(new CachedSeasonHighsRepository($inner, $cache, 'ibl'), $this->season);
    }

    /**
     * Fill the cache the way page misses do: a read-mode pass over every warm phase (ttl 900).
     */
    private function seedLazily(JsonRoundTripCache $cache): void
    {
        $service = new SeasonHighsService(
            new CachedSeasonHighsRepository(new CountingSeasonHighsInner(), $cache, 'ibl'),
            $this->season
        );
        foreach (['Regular Season', 'Playoffs', 'Preseason', 'HEAT'] as $phase) {
            $service->getHomeAwayHighs($phase);
            $service->getSeasonHighsData($phase);
        }
    }

    private function buildStep(SeasonHighsCacheWarmer $warmer, float $elapsed): RefreshSeasonHighsCacheStep
    {
        $clock = self::createStub(ClockInterface::class);
        $clock->method('microtime')->willReturn(self::STARTED_AT + $elapsed);

        return new RefreshSeasonHighsCacheStep($warmer, $clock, self::STARTED_AT, 240.0);
    }
}
