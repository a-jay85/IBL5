<?php

declare(strict_types=1);

namespace Tests\Season;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Season\SeasonQueryRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class SeasonConstructorTest extends TestCase
{
    public function testSeasonQueryRepositoryDefaultsToIblTablesWithoutContext(): void
    {
        $mockDb = new MockDatabase();
        $repo = new SeasonQueryRepository($mockDb);

        $mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
        ]);

        $settings = $repo->getBulkSettings(['Current Season Phase']);

        $this->assertSame('Regular Season', $settings['Current Season Phase']);

        $found = false;
        foreach ($mockDb->getExecutedQueries() as $q) {
            if (str_contains($q, 'ibl_settings')) {
                $found = true;
            }
            $this->assertStringNotContainsString('ibl_olympics_settings', $q);
        }
        $this->assertTrue($found, 'Expected query against ibl_settings');
    }

    public function testQueryRepoResolvesOlympicsTablesWithLeagueContext(): void
    {
        $mockDb = new MockDatabase();
        $_SESSION['current_league'] = 'olympics';
        $leagueContext = new LeagueContext();

        $repo = new SeasonQueryRepository($mockDb, $leagueContext);

        $refProp = new \ReflectionProperty(SeasonQueryRepository::class, 'scheduleTable');
        $scheduleTable = $refProp->getValue($repo);

        $this->assertSame('ibl_olympics_schedule', $scheduleTable);

        unset($_SESSION['current_league']);
    }
}
