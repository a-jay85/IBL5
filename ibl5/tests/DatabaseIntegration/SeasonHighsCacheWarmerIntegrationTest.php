<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Cache\DatabaseCache;
use League\LeagueContext;
use PHPUnit\Framework\Attributes\Group;
use SeasonHighs\CachedSeasonHighsRepository;
use SeasonHighs\SeasonHighsCacheWarmer;
use SeasonHighs\SeasonHighsRepository;

/**
 * Runs the Season Highs warmer against the real SQL and the real `cache` table.
 */
#[Group('database')]
final class SeasonHighsCacheWarmerIntegrationTest extends DatabaseTestCase
{
    public function testRebuildThenInvalidateRoundTripsAgainstRealCache(): void
    {
        $cache = new DatabaseCache($this->db);
        $repository = new CachedSeasonHighsRepository(
            new SeasonHighsRepository($this->db),
            $cache,
            LeagueContext::LEAGUE_IBL
        );
        $warmer = new SeasonHighsCacheWarmer($repository, new \Season\Season($this->db));
        $expected = $warmer->expectedKeys();

        $written = $warmer->rebuild();

        self::assertGreaterThan(0, $written);
        self::assertSame(count($expected), $written);
        foreach ($expected as $key) {
            self::assertNotNull($cache->get($key), 'rebuild did not store ' . $key);
        }

        $warmer->invalidate();

        foreach ($expected as $key) {
            self::assertNull($cache->get($key), 'invalidate left ' . $key);
        }
    }
}
