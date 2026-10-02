<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Literal oracle: four grouping-column methods x the two allowed columns.
     *
     * @return array<string, array{string, string}>
     */
    public static function groupingMethodAndColumnProvider(): array
    {
        $data = [];
        foreach (['fetchTeamsByRegion', 'fetchTopTeamsByWins', 'fetchLeastLosingTeam', 'isRegionSeasonOver'] as $method) {
            foreach (['conference', 'division'] as $column) {
                $data[$method . ' ' . $column] = [$method, $column];
            }
        }

        return $data;
    }

    private function callGroupingMethod(string $method, string $column): void
    {
        match ($method) {
            'fetchTeamsByRegion' => $this->repository->fetchTeamsByRegion($column, 'Eastern'),
            'fetchTopTeamsByWins' => $this->repository->fetchTopTeamsByWins($column, 'Eastern'),
            'fetchLeastLosingTeam' => $this->repository->fetchLeastLosingTeam('Sentinels', $column, 'Eastern'),
            'isRegionSeasonOver' => $this->repository->isRegionSeasonOver($column, 'Eastern'),
            default => self::fail('unknown method ' . $method),
        };
    }

    /**
     * Assert the raw prepared SQL (backticks intact) contains the needle.
     */
    private function assertPreparedSqlContains(string $needle): void
    {
        $found = false;
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            if (str_contains($query, $needle)) {
                $found = true;
                break;
            }
        }
        self::assertTrue(
            $found,
            "No prepared query contained '" . $needle . "'. Prepared: " . implode(' | ', $this->mockDb->getPreparedQueries())
        );
    }

    #[DataProvider('groupingMethodAndColumnProvider')]
    public function testEachValidGroupingColumnIsSplicedBacktickQuoted(string $method, string $column): void
    {
        $this->callGroupingMethod($method, $column);

        $this->assertPreparedSqlContains('WHERE `' . $column . '` = ?');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function magicNumberColumnProvider(): array
    {
        return [
            'conf_magic_number' => ['conf_magic_number'],
            'div_magic_number' => ['div_magic_number'],
        ];
    }

    #[DataProvider('magicNumberColumnProvider')]
    public function testEachValidMagicNumberColumnIsSplicedBacktickQuoted(string $column): void
    {
        $this->repository->updateMagicNumber(3, 12, $column);

        $this->assertPreparedSqlContains('SET `' . $column . '` = ?');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function clinchedColumnProvider(): array
    {
        return [
            'clinched_conference' => ['clinched_conference'],
            'clinched_division' => ['clinched_division'],
            'clinched_playoffs' => ['clinched_playoffs'],
            'clinched_league' => ['clinched_league'],
        ];
    }

    #[DataProvider('clinchedColumnProvider')]
    public function testEachValidClinchedColumnIsSplicedBacktickQuoted(string $column): void
    {
        $this->repository->updateClinchedFlag('Sentinels', $column);

        $this->assertPreparedSqlContains('SET `' . $column . '` = 1');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function groupingMethodProvider(): array
    {
        return [
            'fetchTeamsByRegion' => ['fetchTeamsByRegion'],
            'fetchTopTeamsByWins' => ['fetchTopTeamsByWins'],
            'fetchLeastLosingTeam' => ['fetchLeastLosingTeam'],
            'isRegionSeasonOver' => ['isRegionSeasonOver'],
        ];
    }

    #[DataProvider('groupingMethodProvider')]
    public function testGroupingColumnMethodsRejectUnknownColumn(string $method): void
    {
        try {
            $this->callGroupingMethod($method, 'wins; DROP TABLE x');
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid grouping column', $e->getMessage());
            self::assertSame([], $this->mockDb->getExecutedQueries());
            self::assertSame([], $this->mockDb->getPreparedQueries());
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
