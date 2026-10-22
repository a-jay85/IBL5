<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\SplitStats;
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
    private function playerRow(int $pid, string $name, string $pos): array
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

    public function testRenderMatchesGolden(): void
    {
        $html = SplitStats::render([$this->playerRow(7, 'Ann Starter', 'PG')], $this->makeTeam(), 'Home', [7]);

        $goldenPath = __DIR__ . '/fixtures/split-stats-basic.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testEmptyRowsStillEmitsOpenTag(): void
    {
        $html = SplitStats::render([], $this->makeTeam(), 'Away');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable" style="', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }
}
