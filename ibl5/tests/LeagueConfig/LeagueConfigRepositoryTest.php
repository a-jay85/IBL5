<?php

declare(strict_types=1);

namespace Tests\LeagueConfig;

use LeagueConfig\Contracts\LeagueConfigRepositoryInterface;
use LeagueConfig\LeagueConfigRepository;
use Tests\Integration\IntegrationTestCase;

class LeagueConfigRepositoryTest extends IntegrationTestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $repository = new LeagueConfigRepository($this->mockDb);

        $this->assertInstanceOf(LeagueConfigRepositoryInterface::class, $repository);
    }

    public function testHasConfigForSeasonReturnsFalse(): void
    {
        $this->mockDb->setMockData([]);

        $repository = new LeagueConfigRepository($this->mockDb);

        // MockDatabase returns empty data, so COUNT(*) fetchOne returns null
        $this->assertFalse($repository->hasConfigForSeason(2027));
        $this->assertQueryExecuted('SELECT COUNT(*)');
    }

    public function testHasConfigForSeasonReturnsTrueWhenDataExists(): void
    {
        $this->mockDb->setMockData([['total' => 28]]);

        $repository = new LeagueConfigRepository($this->mockDb);

        $this->assertTrue($repository->hasConfigForSeason(2007));
        $this->assertQueryExecuted('SELECT COUNT(*)');
    }

    public function testUpsertCallsExecute(): void
    {
        $repository = new LeagueConfigRepository($this->mockDb);

        $rows = [
            [
                'team_slot' => 1,
                'team_name' => 'Celtics',
                'conference' => 'Eastern',
                'division' => 'Atlantic',
                'playoff_qualifiers_per_conf' => 8,
                'playoff_round1_format' => '4 of 7',
                'playoff_round2_format' => '4 of 7',
                'playoff_round3_format' => '4 of 7',
                'playoff_round4_format' => '4 of 7',
                'team_count' => 28,
            ],
        ];

        $repository->upsertSeasonConfig(2007, $rows);

        $this->assertQueryExecuted('INSERT INTO ibl_league_config');
    }

    public function testGetConfigForSeasonExecutesQuery(): void
    {
        $this->mockDb->setMockData([
            [
                'id' => 1,
                'season_ending_year' => 2007,
                'team_slot' => 1,
                'team_name' => 'Celtics',
                'conference' => 'Eastern',
                'division' => 'Atlantic',
                'playoff_qualifiers_per_conf' => 8,
                'playoff_round1_format' => '4 of 7',
                'playoff_round2_format' => '4 of 7',
                'playoff_round3_format' => '4 of 7',
                'playoff_round4_format' => '4 of 7',
                'team_count' => 28,
                'created_at' => '2026-02-13 00:00:00',
            ],
        ]);

        $repository = new LeagueConfigRepository($this->mockDb);
        $result = $repository->getConfigForSeason(2007);

        $this->assertCount(1, $result);
        $this->assertQueryExecuted('SELECT * FROM ibl_league_config');
    }

    public function testGetFranchiseTeamsBySeasonExecutesQuery(): void
    {
        $this->mockDb->setMockData([
            ['franchise_id' => 1, 'team_name' => 'Celtics'],
            ['franchise_id' => 2, 'team_name' => 'Heat'],
        ]);

        $repository = new LeagueConfigRepository($this->mockDb);
        $result = $repository->getFranchiseTeamsBySeason(2007);

        $this->assertSame([1 => 'Celtics', 2 => 'Heat'], $result);
        $this->assertQueryExecuted('SELECT franchise_id, team_name FROM ibl_franchise_seasons');
    }

    public function testGetFranchiseTeamsBySeasonReturnsEmptyForNoData(): void
    {
        $this->mockDb->setMockData([]);

        $repository = new LeagueConfigRepository($this->mockDb);
        $result = $repository->getFranchiseTeamsBySeason(2027);

        $this->assertSame([], $result);
    }
}
