<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\TestCase;
use SeasonHighs\Contracts\SeasonHighsViewInterface;
use SeasonHighs\SeasonHighsView;

/**
 * @covers \SeasonHighs\SeasonHighsView
 */
class SeasonHighsViewTest extends TestCase
{
    private SeasonHighsView $view;

    protected function setUp(): void
    {
        $this->view = new SeasonHighsView();
    }

    public function testRenderShowsTitle(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString('Season Highs', $html);
    }

    public function testRenderShowsPlayerHighsSection(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString("Players'", $html);
        $this->assertStringContainsString('Regular Season', $html);
    }

    public function testRenderShowsTeamHighsSection(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString("Teams'", $html);
    }

    public function testRenderShowsStatName(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry()]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('POINTS', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['name' => 'LeBron James', 'pid' => 1])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('LeBron James', $html);
    }

    public function testRenderShowsStatValue(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['value' => 55])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('>55<', $html);
    }

    public function testRenderLinksPlayerToProfile(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['pid' => 42])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('pid=42', $html);
    }

    public function testRenderLinksDateToBoxScore(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['gameOfThatDay' => 3, 'gameTeamid' => 5])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('<a href="modules.php?name=GameBoxscore&amp;date=2024-12-15&amp;game=3&amp;team=5">2024-12-15</a>', $html);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString('Season Highs', $html);
    }

    // --- Home/Away Highs Tests ---

    public function testRenderHomeAwayHighsShowsHomeSectionTitle(): void
    {
        $data = self::createHomeAwayData();
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, []);

        $this->assertStringContainsString("Players' Regular Season Home Highs", $html);
    }

    public function testRenderHomeAwayHighsShowsAwaySectionTitle(): void
    {
        $data = self::createHomeAwayData();
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, []);

        $this->assertStringContainsString("Players' Regular Season Away Highs", $html);
    }

    public function testRenderHomeAwayHighsShowsPlayerLink(): void
    {
        $data = [
            'home' => ['POINTS' => [self::createHighEntry(['pid' => 99, 'name' => 'Home Star'])]],
            'away' => [],
        ];
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, []);

        $this->assertStringContainsString('pid=99', $html);
        $this->assertStringContainsString('Home Star', $html);
    }

    public function testRenderHomeAwayHighsShowsDateLink(): void
    {
        $data = [
            'home' => ['POINTS' => [self::createHighEntry(['gameOfThatDay' => 2, 'gameTeamid' => 6])]],
            'away' => [],
        ];
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, []);

        $this->assertStringContainsString('<a href="modules.php?name=GameBoxscore&amp;date=2024-12-15&amp;game=2&amp;team=6">2024-12-15</a>', $html);
    }

    public function testRenderHomeAwayHighsHidesDiscrepanciesWhenEmpty(): void
    {
        $data = self::createHomeAwayData();
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, []);

        $this->assertStringNotContainsString('discrepancy', strtolower($html));
    }

    public function testRenderHomeAwayHighsShowsDiscrepanciesWhenPresent(): void
    {
        $data = self::createHomeAwayData();
        $discrepancies = [
            [
                'context' => 'home',
                'stat' => 'POINTS',
                'boxValue' => 40,
                'boxPlayer' => 'Box Player',
                'rcbValue' => 50,
                'rcbPlayer' => 'RCB Player',
            ],
        ];
        $html = $this->view->renderHomeAwayHighs('Regular Season', $data, $discrepancies);

        $this->assertStringContainsString('Data Validation', $html);
        $this->assertStringContainsString('Box Player: 40', $html);
        $this->assertStringContainsString('RCB Player: 50', $html);
    }

    private function assertGolden(string $path, string $html): void
    {
        if (getenv('IBL_GOLDEN_CAPTURE') === '1') {
            file_put_contents($path, $html);
        }
        $this->assertStringEqualsFile($path, $html);
    }

    /**
     * Whole-page pin captured before renderStatTable was split. Reaches every
     * row shape and every date-link arm; see the plan's branch map.
     *
     * Mutations verified to turn this red: renaming `rank-cell` to `rank-celx`
     * in renderPlayerStatRow, `value-cell` to `value-celx` in renderTeamStatRow,
     * and `<a href=` to `<a hreg=` in linkDate.
     */
    public function testRenderGoldenMixedRowShapes(): void
    {
        $data = [
            'playerHighs' => [
                'FG & FT' => [
                    ['name' => "Shaq O'Neal", 'date' => '2024-12-15', 'value' => 41, 'pid' => 7, 'teamid' => 3, 'teamname' => 'Hawks', 'color1' => 'FF0000', 'color2' => '000000', 'gameOfThatDay' => 2, 'boxId' => 0],
                    ['name' => 'Unsigned Guy', 'date' => '2024-12-16', 'value' => 39, 'pid' => 8, 'teamid' => 0, 'teamname' => '', 'boxId' => 100],
                    ['name' => 'Legacy & Co', 'date' => '2024-12-17', 'value' => 35, 'boxId' => 0],
                ],
            ],
            'teamHighs' => [
                'TEAM POINTS' => [
                    ['name' => 'Hawks', 'date' => '2024-12-18', 'value' => 150, 'teamid' => 3, 'color1' => 'FF0000', 'color2' => '000000', 'boxId' => 101],
                    ['name' => 'Bulls', 'date' => '2024-12-19', 'value' => 148, 'teamid' => 4, 'color1' => 'CC0000', 'color2' => 'FFFFFF', 'boxId' => 0],
                    ['name' => 'Stray Player', 'date' => '2024-12-20', 'value' => 60, 'pid' => 9, 'teamid' => 4, 'boxId' => 0],
                ],
            ],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertGolden(__DIR__ . '/fixtures/season-highs-mixed.golden.html', $html);
    }

    /**
     * A name with no pid and no teamid is printed as safeHtmlOutput($row['name']);
     * the unlinked date is printed as safeHtmlOutput($row['date']). Both must
     * come out escaped. Removing either safeHtmlOutput call fails this test.
     */
    public function testRenderEscapesScriptInFallbackNameAndDate(): void
    {
        $data = [
            'playerHighs' => [
                'POINTS' => [
                    ['name' => '<script>alert(1)</script>', 'date' => '<script>d</script>', 'value' => 10, 'boxId' => 0],
                ],
            ],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;d&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * @return array{playerHighs: array<string, list<mixed>>, teamHighs: array<string, list<mixed>>}
     */
    private static function createEmptyData(): array
    {
        return [
            'playerHighs' => [],
            'teamHighs' => [],
        ];
    }

    /**
     * @return array{home: array<string, list<mixed>>, away: array<string, list<mixed>>}
     */
    private static function createHomeAwayData(): array
    {
        return [
            'home' => ['POINTS' => [self::createHighEntry()]],
            'away' => ['POINTS' => [self::createHighEntry()]],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{name: string, date: string, value: int, pid?: int, teamid?: int, teamname?: string, color1?: string, color2?: string, boxId?: int, gameOfThatDay?: int, gameTeamid?: int}
     */
    private static function createHighEntry(array $overrides = []): array
    {
        /** @var array{name: string, date: string, value: int, pid?: int, teamid?: int, teamname?: string, color1?: string, color2?: string, boxId?: int, gameOfThatDay?: int, gameTeamid?: int} */
        return array_merge([
            'name' => 'Test Player',
            'date' => '2024-12-15',
            'value' => 30,
            'pid' => 1,
            'teamid' => 1,
            'teamname' => 'Hawks',
            'color1' => 'FF0000',
            'color2' => '000000',
            'boxId' => 100,
        ], $overrides);
    }
}
