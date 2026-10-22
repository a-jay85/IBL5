<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\SplitStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization pins for SplitStats::render().
 *
 * The golden under fixtures/ was captured once from the unmodified class. Never
 * regenerate it to make a failing test pass.
 *
 * @covers \BasketballStats\Tables\SplitStats
 */
final class SplitStatsTest extends TestCase
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

    /**
     * @return array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}
     */
    private static function playerRow(int $pid, string $name, string $pos): array
    {
        return [
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'games' => 12,
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
        ];
    }

    /**
     * @return array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}
     */
    private static function nullStatsRow(int $pid, string $name, string $pos): array
    {
        return [
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'games' => 0,
            'gameMINavg' => null,
            'gameFGMavg' => null,
            'gameFGAavg' => null,
            'gameFGPavg' => null,
            'gameFTMavg' => null,
            'gameFTAavg' => null,
            'gameFTPavg' => null,
            'game3GMavg' => null,
            'game3GAavg' => null,
            'game3GPavg' => null,
            'gameORBavg' => null,
            'gameREBavg' => null,
            'gameASTavg' => null,
            'gameSTLavg' => null,
            'gameTOVavg' => null,
            'gameBLKavg' => null,
            'gamePFavg' => null,
            'gamePTSavg' => null,
        ];
    }

    /**
     * @return array<string, array{string, list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}>, string, list<int>}>
     */
    public static function characterizationCases(): array
    {
        return [
            'hostile-rows' => [
                'hostile-rows',
                [
                    self::playerRow(7, '<script>alert("n")</script> & O\'Neil', '<b>PG</b>'),
                    self::nullStatsRow(8, 'Null Stats', 'C'),
                    self::playerRow(9, 'Cash | Buyout', 'SF'),
                ],
                'Away <img src=x onerror=alert(1)>',
                [7],
            ],
            'empty-hostile-label' => [
                'empty-hostile-label',
                [],
                '<img src=x onerror="alert(1)"> & Road',
                [],
            ],
        ];
    }

    public function testRenderMatchesGolden(): void
    {
        $html = SplitStats::render([self::playerRow(7, 'Ann Starter', 'PG')], $this->makeTeam(), 'Home', [7]);

        $goldenPath = __DIR__ . '/fixtures/split-stats-basic.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testEmptyRowsStillEmitsOpenTag(): void
    {
        $html = SplitStats::render([], $this->makeTeam(), 'Away');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable" style="', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    /**
     * @param list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}> $rows
     * @param list<int> $starterPids
     */
    #[DataProvider('characterizationCases')]
    public function testRenderMatchesCharacterizationGolden(string $case, array $rows, string $splitLabel, array $starterPids): void
    {
        $html = SplitStats::render($rows, $this->makeTeam(), $splitLabel, $starterPids);

        $this->assertStringEqualsFile(__DIR__ . '/fixtures/split-stats-' . $case . '.golden.html', $html);
    }

    public function testHostilePlayerNameAndPositionAreEscaped(): void
    {
        $html = SplitStats::render(self::characterizationCases()['hostile-rows'][1], $this->makeTeam(), 'Away', [7]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>PG</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;PG&lt;/b&gt;', $html);
    }

    public function testHostileSplitLabelIsEscapedInEmptyRowsMessage(): void
    {
        $html = SplitStats::render([], $this->makeTeam(), '<img src=x onerror="alert(1)"> & Road');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; Road', $html);
        $this->assertStringContainsString('class="table-empty-message"', $html);
    }

    public function testNullStatsRowRendersEachDefaultLiteral(): void
    {
        $html = SplitStats::render([self::nullStatsRow(8, 'Null Stats', 'C')], $this->makeTeam(), 'Home');

        $this->assertSame(8, substr_count($html, '<td>0.0</td>'));
        $this->assertSame(6, substr_count($html, '<td>0.00</td>'));
        $this->assertSame(3, substr_count($html, '0.000</td>'));
        $this->assertSame(1, substr_count($html, 'sep-r-team">0.0</td>'));
    }

    public function testNonEmptyTableOmitsEmptyRowsMessage(): void
    {
        $html = SplitStats::render(self::characterizationCases()['hostile-rows'][1], $this->makeTeam(), 'Away <img src=x onerror=alert(1)>', [7]);

        $this->assertStringNotContainsString('table-empty-message', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }
}
