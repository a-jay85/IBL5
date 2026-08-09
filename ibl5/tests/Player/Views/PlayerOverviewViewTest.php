<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Stats\PlayerStats;
use Player\Stats\PlayerStatsRepository;
use Player\Views\PlayerOverviewView;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;

/** @covers \Player\Views\PlayerOverviewView */
class PlayerOverviewViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * @param list<array<string, int|string>> $boxScores
     */
    private function makeView(array $boxScores = []): PlayerOverviewView
    {
        $statsRepo = self::createStub(PlayerStatsRepository::class);
        $statsRepo->method('getBoxScoresBetweenDates')->willReturn($boxScores);
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn('Test Team');
        return new PlayerOverviewView($statsRepo, $commonRepo);
    }

    private function makeSeason(string $phase = 'Regular Season'): Season
    {
        $season = self::createStub(Season::class);
        $season->phase = $phase;
        $season->beginningYear = 2030;
        $season->endingYear = 2031;
        return $season;
    }

    public function testRenderOverviewWithEmptyBoxScoresSnapshot(): void
    {
        $view = $this->makeView();
        $season = $this->makeSeason();

        $html = $view->renderOverview(1, self::createStub(Player::class), self::createStub(PlayerStats::class), $season);

        $this->assertStringContainsString('<tr><td colspan="2">', $html);
        $this->assertSnapshotMatches($html, 'PlayerOverviewView-empty.html');
    }

    public function testRenderOverviewWithBoxScoresSnapshot(): void
    {
        $view = $this->makeView($this->boxScores());
        $season = $this->makeSeason();

        $html = $view->renderOverview(1, self::createStub(Player::class), self::createStub(PlayerStats::class), $season);

        $this->assertStringContainsString('<tr><td colspan="2">', $html);
        $this->assertSnapshotMatches($html, 'PlayerOverviewView-boxscores.html');
    }

    public function testPreseasonPhaseDoesNotThrow(): void
    {
        $view = $this->makeView();
        $season = $this->makeSeason('Preseason');

        $html = $view->renderOverview(1, self::createStub(Player::class), self::createStub(PlayerStats::class), $season);

        $this->assertNotEmpty($html);
    }

    public function testHeatPhaseDoesNotThrow(): void
    {
        $view = $this->makeView();
        $season = $this->makeSeason('HEAT');

        $html = $view->renderOverview(1, self::createStub(Player::class), self::createStub(PlayerStats::class), $season);

        $this->assertNotEmpty($html);
    }

    public function testColorSchemePassedThrough(): void
    {
        $view = $this->makeView();
        $season = $this->makeSeason();
        $colorScheme = [
            'primary' => 'ff0099',
            'secondary' => '000000',
            'gradient_start' => 'ff0099',
            'gradient_mid' => '880055',
            'gradient_end' => '440022',
            'border' => 'ff0099',
            'border_rgb' => '255,0,153',
            'accent' => 'ff0099',
            'text' => 'ffffff',
            'text_muted' => 'cccccc',
        ];

        $html = $view->renderOverview(1, self::createStub(Player::class), self::createStub(PlayerStats::class), $season, $colorScheme);

        $this->assertStringContainsString('ff0099', $html);
    }

    /**
     * Box score rows with the full column shape required by renderGameLog().
     *
     * Uses the same stat values as RegularSeasonViewFixtures::boxScoresForSim12()
     * but includes the game metadata columns (game_date, home_teamid, visitor_teamid,
     * game_of_that_day, box_id) that getBoxScoresBetweenDates() returns and
     * boxScoresForSim12() does not carry.
     *
     * @return list<array<string, int|string>>
     */
    private function boxScores(): array
    {
        return [
            [
                'game_date' => '2030-01-05', 'home_teamid' => 3, 'visitor_teamid' => 7,
                'game_of_that_day' => 1, 'box_id' => 0,
                'game_min' => 34, 'game_2gm' => 7, 'game_2ga' => 15,
                'game_3gm' => 3, 'game_3ga' => 8, 'game_ftm' => 5, 'game_fta' => 6,
                'game_orb' => 2, 'game_drb' => 9, 'game_ast' => 4, 'game_stl' => 3,
                'game_tov' => 1, 'game_blk' => 2, 'game_pf' => 4,
            ],
            [
                'game_date' => '2030-01-06', 'home_teamid' => 9, 'visitor_teamid' => 5,
                'game_of_that_day' => 2, 'box_id' => 0,
                'game_min' => 29, 'game_2gm' => 4, 'game_2ga' => 11,
                'game_3gm' => 2, 'game_3ga' => 6, 'game_ftm' => 8, 'game_fta' => 10,
                'game_orb' => 3, 'game_drb' => 7, 'game_ast' => 6, 'game_stl' => 1,
                'game_tov' => 5, 'game_blk' => 1, 'game_pf' => 3,
            ],
        ];
    }
}
