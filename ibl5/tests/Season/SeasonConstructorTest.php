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
            ['setting_key' => 'Current Season Phase', 'value' => 'Regular Season'],
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

        // Drive the executeQuery() rewrite path (the production mechanism), not
        // the removed resolveTable() property. With an Olympics context the
        // backtick-quoted `ibl_schedule` is rewritten to its Olympics equivalent.
        $repo->getLastRegularSeasonGameDate(2025);

        $scheduleQueries = array_filter(
            $mockDb->getExecutedQueries(),
            static fn (string $q): bool => stripos($q, 'schedule') !== false,
        );
        $this->assertNotEmpty($scheduleQueries);
        foreach ($scheduleQueries as $q) {
            $this->assertStringContainsString('ibl_olympics_schedule', $q);
            $this->assertStringNotContainsString('FROM `ibl_schedule`', $q);
        }

        unset($_SESSION['current_league']);
    }
}
