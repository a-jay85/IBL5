<?php

declare(strict_types=1);

namespace Tests\Standings;

use Standings\StandingsUpdaterRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * @phpstan-import-type UpsertStandingsParams from \Standings\Contracts\StandingsRepositoryInterface
 */
class StandingsUpdaterRepositoryTest extends WideUnitTestCase
{
    private StandingsUpdaterRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StandingsUpdaterRepository($this->mockDb);
    }

    /** @return UpsertStandingsParams */
    private function buildUpsertParams(): array
    {
        return [
            'teamid' => 7,
            'teamName' => 'Sentinels',
            'leagueRecord' => '41-23',
            'wins' => 41,
            'losses' => 23,
            'pct' => 0.641,
            'gamesUnplayed' => 0,
            'conference' => 'Eastern',
            'confGb' => 0.0,
            'confRecord' => '22-10',
            'division' => 'Atlantic',
            'divGb' => 0.0,
            'divRecord' => '10-4',
            'homeRecord' => '22-10',
            'awayRecord' => '19-13',
            'confWins' => 22,
            'confLosses' => 10,
            'divWins' => 10,
            'divLosses' => 4,
            'homeWins' => 22,
            'homeLosses' => 10,
            'awayWins' => 19,
            'awayLosses' => 13,
        ];
    }

    public function testUpsertStandingsWritesStandingsTable(): void
    {
        $this->repository->upsertStandings($this->buildUpsertParams());

        $this->assertQueryExecuted('ibl_standings');
        $params = $this->mockDb->getLastBoundParams();
        self::assertSame(7, $params[0]);
        self::assertSame('Sentinels', $params[1]);
    }

    public function testUpdateMagicNumberBindsMagicNumberThenTeamId(): void
    {
        $this->repository->updateMagicNumber(3, 12, 'conf_magic_number');

        $this->assertQueryExecuted('ibl_standings');
        self::assertSame([12, 3], $this->mockDb->getLastBoundParams());
    }

    public function testUpdateMagicNumberRejectsUnknownColumn(): void
    {
        try {
            $this->repository->updateMagicNumber(3, 12, 'wins; DROP TABLE x');
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid magic number column', $e->getMessage());
            self::assertSame([], $this->mockDb->getExecutedQueries());
        }
    }

    public function testUpdateClinchedFlagBindsTeamName(): void
    {
        $this->repository->updateClinchedFlag('Sentinels', 'clinched_division');

        $this->assertQueryExecuted('ibl_standings');
        self::assertSame(['Sentinels'], $this->mockDb->getLastBoundParams());
    }

    public function testUpdateClinchedFlagRejectsUnknownColumn(): void
    {
        try {
            $this->repository->updateClinchedFlag('Sentinels', 'clinched_everything');
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid clinched column', $e->getMessage());
            self::assertSame([], $this->mockDb->getExecutedQueries());
        }
    }

    public function testUpsertTeamAwardWritesTeamAwardsTable(): void
    {
        $this->repository->upsertTeamAward(2026, 'Sentinels', 'Division Champions');

        $this->assertQueryExecuted('ibl_team_awards');
        $this->assertQueryNotExecuted('ibl_standings');
        self::assertSame([2026, 'Sentinels', 'Division Champions'], $this->mockDb->getLastBoundParams());
    }
}
