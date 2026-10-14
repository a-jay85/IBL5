<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use BasketballStats\Tables\SeasonTotals;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \BasketballStats\Tables\SeasonTotals
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonTotalsTest extends TestCase
{
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;
        $team->name = 'Test Team';
        return $team;
    }

    private function createMockDb(): \mysqli
    {
        $mockResult = self::createStub(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturn(null);

        $mockStmt = self::createStub(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);

        $mockDb = self::createStub(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        return $mockDb;
    }

    /**
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $html = SeasonTotals::render($this->createMockDb(), [], $this->createMockTeam(), '', [], '');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    private function makeTeam(string $name = 'Test Team', string $color1 = 'FF0000'): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = $color1;
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = $name;

        return $team;
    }

    /**
     * @return array<string, string>
     */
    private static function bothStatsRow(int $games): array
    {
        $offense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
            'fgm' => '400', 'fga' => '850', 'ftm' => '200', 'fta' => '260',
            'tgm' => '120', 'tga' => '330', 'orb' => '95', 'reb' => '420',
            'ast' => '240', 'stl' => '70', 'tvr' => '130', 'blk' => '45', 'pf' => '190',
        ];
        $defense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
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

    /**
     * A null row set registers no route, so that repository call returns no row.
     *
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    private function makeDb(?array $regularRows, ?array $fallbackRows, int $year): MockDatabase
    {
        $db = new MockDatabase();
        if ($regularRows !== null) {
            // regular-season call only (contains the game_type clause)
            $db->onQuery('bst\.game_type = 1 AND bst\.season_year = ' . $year, $regularRows);
        }
        if ($fallbackRows !== null) {
            // fallback call only (negative lookahead rejects the regular-season SQL; [\s\S] spans newlines)
            $db->onQuery('^(?![\s\S]*game_type = 1)[\s\S]*bst\.season_year = ' . $year, $fallbackRows);
        }

        return $db;
    }

    /**
     * @return array<string, array{
     *     0: string,
     *     1: string,
     *     2: string,
     *     3: string,
     *     4: string,
     *     5: list<array<string, string>>|null,
     *     6: list<array<string, string>>|null,
     *     7: int
     * }>
     */
    public static function goldenCases(): array
    {
        return [
            'explicit-year' => [
                'explicit-year', '2023', 'Season totals', 'Test Team', 'FF0000',
                [self::bothStatsRow(10)], [self::bothStatsRow(11)], 2023,
            ],
            'mock-year' => [
                'mock-year', '', '', 'Test Team', 'FF0000',
                [self::bothStatsRow(10)], [self::bothStatsRow(11)], 2024,
            ],
            'fallback' => [
                'fallback', '2023', '', 'Test Team', 'FF0000',
                null, [self::bothStatsRow(11)], 2023,
            ],
            'no-stats' => [
                'no-stats', '2023', '', 'Test Team', 'FF0000',
                null, null, 2023,
            ],
            'hostile' => [
                'hostile', '2023', '"><script>alert(1)</script>', "O'Brien <b>Hack</b>", '"><img src=x>',
                [self::bothStatsRow(10)], null, 2023,
            ],
        ];
    }

    /**
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    private function renderGoldenCase(
        string $yr,
        string $ariaLabel,
        string $teamName,
        string $color1,
        ?array $regularRows,
        ?array $fallbackRows,
        int $year
    ): string {
        $db = $this->makeDb($regularRows, $fallbackRows, $year);

        return SeasonTotals::render($db, [], $this->makeTeam($teamName, $color1), $yr, [], '', $ariaLabel);
    }

    /**
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    #[DataProvider('goldenCases')]
    public function testRenderMatchesGolden(
        string $case,
        string $yr,
        string $ariaLabel,
        string $teamName,
        string $color1,
        ?array $regularRows,
        ?array $fallbackRows,
        int $year
    ): void {
        $html = $this->renderGoldenCase($yr, $ariaLabel, $teamName, $color1, $regularRows, $fallbackRows, $year);

        $goldenPath = __DIR__ . '/fixtures/season-totals-' . $case . '.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testHostileInputGoldenHasNoUnescapedPayload(): void
    {
        $case = self::goldenCases()['hostile'];
        $html = $this->renderGoldenCase($case[1], $case[2], $case[3], $case[4], $case[5], $case[6], $case[7]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<b>Hack</b>', $html);
    }

    public function testFallbackAndRegularRowsRenderDifferently(): void
    {
        $regular = self::goldenCases()['explicit-year'];
        $fallback = self::goldenCases()['fallback'];

        $regularHtml = $this->renderGoldenCase($regular[1], $regular[2], $regular[3], $regular[4], $regular[5], $regular[6], $regular[7]);
        $fallbackHtml = $this->renderGoldenCase($fallback[1], $fallback[2], $fallback[3], $fallback[4], $fallback[5], $fallback[6], $fallback[7]);

        $this->assertNotSame($regularHtml, $fallbackHtml);
    }
}
