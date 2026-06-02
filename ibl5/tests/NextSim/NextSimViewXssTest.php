<?php

declare(strict_types=1);

namespace Tests\NextSim;

use LeagueSchedule\Game;
use NextSim\NextSimView;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Team\Team;

final class NextSimViewXssTest extends TestCase
{
    private NextSimView $view;

    /** @var Team&\PHPUnit\Framework\MockObject\Stub */
    private Team $userTeam;

    protected function setUp(): void
    {
        $mockSeason = self::createStub(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $this->view = new NextSimView($mockSeason);

        $this->userTeam = self::createStub(Team::class);
        $this->userTeam->teamid = 1;
        $this->userTeam->name = 'Safe Team';
        $this->userTeam->color1 = 'FF0000';
        $this->userTeam->color2 = '0000FF';
        $this->userTeam->seasonRecord = '10-5';
    }

    public function testOpposingTeamNameInScheduleStripIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $oppTeam = self::createStub(Team::class);
        $oppTeam->teamid = 2;
        $oppTeam->name = $xss;
        $oppTeam->color1 = '00FF00';
        $oppTeam->color2 = 'FFFF00';
        $oppTeam->seasonRecord = '5-5';

        $game = self::createStub(Game::class);
        $game->date = '2025-01-02';

        $games = [
            [
                'game' => $game,
                'date' => new \DateTime('2025-01-02'),
                'dayNumber' => 1,
                'opposingTeam' => $oppTeam,
                'locationPrefix' => '@',
                'opposingStarters' => [],
                'opponentTier' => '',
                'opponentPowerRanking' => 70.0,
            ],
        ];

        $output = $this->view->renderScheduleStrip($games);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
