<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\PeriodAverages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization pins for PeriodAverages::render().
 *
 * What the mock cannot reach, and what this class leaves untested:
 * - The `Prepare failed` exception is unreachable through MockDatabase, because
 *   MockDatabase::prepare() always returns a MockPreparedStatement.
 * - The `Execute failed` and `Failed to get result set` exceptions are not exercised.
 *   A SELECT matched by an onQuery route always yields a MockDatabaseResult, and this
 *   class does not edit the shared mock to inject a failure.
 * - MockPreparedStatement::bind_param records bound values only and discards the type
 *   string, so the `'sssii' . str_repeat('i', n)` bind types are pinned by reading the
 *   source only. The tests pin the bound values and their count.
 * - SQL aggregation (ROUND, COUNT(DISTINCT ...), ORDER BY p.name) runs in MySQL, so the
 *   mock returns rows verbatim and these tests do not pin it. The rendered goldens pin
 *   everything render() does with the rows it receives.
 * - Goldens under fixtures/ were captured once from the unmodified class. Never
 *   regenerate them to make a failing test pass.
 *
 * @covers \BasketballStats\Tables\PeriodAverages
 */
final class PeriodAveragesTest extends TestCase
{
    private function makeTeam(): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = 'Test Team';

        return $team;
    }

    private function makeSeason(): \Season\Season
    {
        $season = self::createStub(\Season\Season::class);
        $season->lastSimStartDate = '2025-01-10';
        $season->lastSimEndDate = '2025-01-20';

        return $season;
    }

    /**
     * @param list<array<string, string|null>> $playerRows
     * @param list<array<string, string>> $bothStatsRows
     */
    private function makeDb(array $playerRows, array $bothStatsRows): MockDatabase
    {
        $db = new MockDatabase();
        $db->onQuery('tos_teamID', $bothStatsRows);
        $db->onQuery('gameMINavg', $playerRows);

        return $db;
    }

    /**
     * @param array<string, string|null> $overrides
     * @return array<string, string|null>
     */
    private function playerRow(int $pid, string $name, string $pos, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'pos' => $pos,
            'pid' => (string) $pid,
            'games' => '12',
            'gameMINavg' => '31.5',
            'gameFGMavg' => '6.20',
            'gameFGAavg' => '13.10',
            'gameFGPavg' => '0.473',
            'gameFTMavg' => '3.40',
            'gameFTAavg' => '4.10',
            'gameFTPavg' => '0.829',
            'game3GMavg' => '1.70',
            'game3GAavg' => '4.30',
            'game3GPavg' => '0.395',
            'gameORBavg' => '1.1',
            'gameREBavg' => '5.2',
            'gameASTavg' => '7.3',
            'gameSTLavg' => '1.4',
            'gameTOVavg' => '2.5',
            'gameBLKavg' => '0.6',
            'gamePFavg' => '2.7',
            'gamePTSavg' => '17.8',
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function bothStatsRow(): array
    {
        $offense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => '10',
            'fgm' => '400', 'fga' => '850', 'ftm' => '200', 'fta' => '260',
            'tgm' => '120', 'tga' => '330', 'orb' => '95', 'reb' => '420',
            'ast' => '240', 'stl' => '70', 'tvr' => '130', 'blk' => '45', 'pf' => '190',
        ];
        $defense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => '10',
            'fgm' => '380', 'fga' => '900', 'ftm' => '170', 'fta' => '230',
            'tgm' => '105', 'tga' => '310', 'orb' => '88', 'reb' => '400',
            'ast' => '215', 'stl' => '62', 'tvr' => '142', 'blk' => '39', 'pf' => '205',
        ];

        $row = [];
        foreach ($offense as $key => $value) {
            $row['tos_' . $key] = $value;
        }
        foreach ($defense as $key => $value) {
            $row['tds_' . $key] = $value;
        }

        return $row;
    }

    private function mainPreparedQuery(MockDatabase $db): string
    {
        $matches = array_values(array_filter(
            $db->getPreparedQueries(),
            static fn (string $q): bool => str_contains($q, 'gameMINavg')
        ));
        $this->assertCount(1, $matches);

        return $matches[0];
    }

    private function repoExecutedQuery(MockDatabase $db): string
    {
        $matches = array_values(array_filter(
            $db->getExecutedQueries(),
            static fn (string $q): bool => str_contains($q, 'tos_teamID')
        ));
        $this->assertCount(1, $matches);

        return $matches[0];
    }

    /**
     * @param list<int> $starterPids
     * @param list<int> $pidFilter
     */
    private function renderWithDefaultRows(
        MockDatabase $db,
        string|\DateTime|null $start,
        string|\DateTime|null $end,
        array $starterPids = [],
        array $pidFilter = []
    ): string {
        return PeriodAverages::render($db, $this->makeTeam(), $this->makeSeason(), $start, $end, $starterPids, $pidFilter);
    }

    private function defaultDb(): MockDatabase
    {
        return $this->makeDb([$this->playerRow(101, 'Alice Guard', 'PG')], [$this->bothStatsRow()]);
    }

    public function testExplicitStringDatesAreBoundToBothQueries(): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows($db, '2025-02-01', '2025-02-28');

        $this->assertSame(['2025-02-01', '2025-02-28', 7, 7, 7], $db->getLastBoundParams());
        $repoQuery = $this->repoExecutedQuery($db);
        $this->assertStringContainsString('2025-02-01', $repoQuery);
        $this->assertStringContainsString('2025-02-28', $repoQuery);
        $this->assertStringContainsString('Test Team', $repoQuery);
    }

    public function testDateTimeArgumentsAreFormattedAsYmd(): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows(
            $db,
            new \DateTime('2025-03-05 14:30:00'),
            new \DateTime('2025-03-09 08:00:00')
        );

        $this->assertSame(['2025-03-05', '2025-03-09', 7, 7, 7], $db->getLastBoundParams());
        $repoQuery = $this->repoExecutedQuery($db);
        $this->assertStringContainsString('2025-03-05', $repoQuery);
        $this->assertStringContainsString('2025-03-09', $repoQuery);
        $this->assertStringNotContainsString('14:30', $repoQuery);
    }

    public function testNullDatesDefaultToSeasonLastSimRange(): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows($db, null, null);

        $this->assertSame(['2025-01-10', '2025-01-20', 7, 7, 7], $db->getLastBoundParams());
        $repoQuery = $this->repoExecutedQuery($db);
        $this->assertStringContainsString('2025-01-10', $repoQuery);
        $this->assertStringContainsString('2025-01-20', $repoQuery);
    }

    #[DataProvider('oneNullDateCases')]
    public function testOneNullDateDoesNotFallBackToSeasonRange(?string $start, ?string $end): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows($db, $start, $end);

        $this->assertSame([$start, $end, 7, 7, 7], $db->getLastBoundParams());
        $repoQuery = $this->repoExecutedQuery($db);
        $this->assertStringNotContainsString('2025-01-10', $repoQuery);
        $this->assertStringNotContainsString('2025-01-20', $repoQuery);
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function oneNullDateCases(): array
    {
        return [
            'start only' => ['2025-02-01', null],
            'end only' => [null, '2025-02-28'],
        ];
    }

    public function testPidFilterAppendsInClauseAndBindsPids(): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows($db, null, null, [], [101, 102, 103]);

        $this->assertStringContainsString(' AND bs.pid IN (?,?,?)', $this->mainPreparedQuery($db));
        $this->assertSame(['2025-01-10', '2025-01-20', 7, 7, 7, 101, 102, 103], $db->getLastBoundParams());
    }

    public function testEmptyPidFilterAddsNoInClause(): void
    {
        $db = $this->defaultDb();

        $this->renderWithDefaultRows($db, null, null, [], []);

        $this->assertStringNotContainsString('bs.pid IN', $this->mainPreparedQuery($db));
        $this->assertCount(5, $db->getLastBoundParams());
    }

    /**
     * Each case is [start, end, starterPids, player specs, whether the repo returns a footer row].
     * A player spec is [pid, name, pos, overrides].
     *
     * @return array<string, array{
     *     0: string,
     *     1: string|null,
     *     2: string|null,
     *     3: list<int>,
     *     4: list<array{0: int, 1: string, 2: string, 3: array<string, string|null>}>,
     *     5: bool
     * }>
     */
    public static function goldenCases(): array
    {
        return [
            'starters-and-footer' => [
                'starters-and-footer',
                '2025-02-01',
                '2025-02-28',
                [101],
                [
                    [101, 'Alice Guard', 'PG', []],
                    [102, 'Bob Center', 'C', ['gameFGPavg' => null, 'gameFTPavg' => null, 'game3GPavg' => null]],
                ],
                true,
            ],
            'no-footer' => [
                'no-footer',
                null,
                null,
                [],
                [[101, 'Alice Guard', 'PG', []]],
                false,
            ],
            'no-players' => [
                'no-players',
                null,
                null,
                [],
                [],
                true,
            ],
            'hostile' => [
                'hostile',
                null,
                null,
                [],
                [
                    [103, '<script>alert(1)</script>', '<img src=x onerror=alert(2)>', []],
                    [104, 'Cash | Buyout', 'PG', []],
                ],
                true,
            ],
        ];
    }

    /**
     * @param list<int> $starterPids
     * @param list<array{0: int, 1: string, 2: string, 3: array<string, string|null>}> $playerSpecs
     */
    private function renderGoldenCase(
        ?string $start,
        ?string $end,
        array $starterPids,
        array $playerSpecs,
        bool $withBothStats
    ): string {
        $playerRows = [];
        foreach ($playerSpecs as [$pid, $name, $pos, $overrides]) {
            $playerRows[] = $this->playerRow($pid, $name, $pos, $overrides);
        }
        $db = $this->makeDb($playerRows, $withBothStats ? [$this->bothStatsRow()] : []);

        return $this->renderWithDefaultRows($db, $start, $end, $starterPids, []);
    }

    /**
     * @param list<int> $starterPids
     * @param list<array{0: int, 1: string, 2: string, 3: array<string, string|null>}> $playerSpecs
     */
    #[DataProvider('goldenCases')]
    public function testRenderMatchesGolden(
        string $case,
        ?string $start,
        ?string $end,
        array $starterPids,
        array $playerSpecs,
        bool $withBothStats
    ): void {
        $html = $this->renderGoldenCase($start, $end, $starterPids, $playerSpecs, $withBothStats);

        $goldenPath = __DIR__ . '/fixtures/period-averages-' . $case . '.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testHostileInputGoldenHasNoUnescapedPayload(): void
    {
        $case = self::goldenCases()['hostile'];
        $html = $this->renderGoldenCase($case[1], $case[2], $case[3], $case[4], $case[5]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
