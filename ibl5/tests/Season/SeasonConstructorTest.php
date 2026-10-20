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
            ['setting_key' => 'Current Season Phase', 'setting_value' => 'Regular Season'],
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
        $this->assertNotSame([], $scheduleQueries);
        foreach ($scheduleQueries as $q) {
            $this->assertStringContainsString('ibl_olympics_schedule', $q);
            $this->assertStringNotContainsString('FROM `ibl_schedule`', $q);
        }

        unset($_SESSION['current_league']);
    }

    /**
     * Runs in a child process because the PHPUnit bootstrap aliases Season\Season to a mock that never
     * queries. A failing last-regular-season-game-date query must now surface from the real constructor.
     */
    public function testConstructorPropagatesLastRegularSeasonGameDateFailure(): void
    {
        $result = RealSeasonProcess::run(<<<'PHP'
            $db = new class extends Tests\WideUnit\Mocks\MockDatabase {
                #[\ReturnTypeWillChange]
                public function prepare(string $query): Tests\WideUnit\Mocks\MockPreparedStatement
                {
                    if (str_contains($query, 'MAX(game_date)')) {
                        throw new \RuntimeException('simulated schedule failure', 1003);
                    }

                    return parent::prepare($query);
                }
            };
            $db->onQuery('ibl_settings', []);
            try {
                new Season\Season($db);
                echo json_encode(['outcome' => 'constructed']);
            } catch (\RuntimeException $e) {
                echo json_encode(['outcome' => 'threw', 'message' => $e->getMessage()]);
            }
            PHP);

        $this->assertSame('threw', $result['outcome']);
        $this->assertSame('simulated schedule failure', $result['message']);
    }
}
