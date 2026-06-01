<?php

declare(strict_types=1);

namespace Tests\SeasonLeaderboards;

use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use SeasonLeaderboards\SeasonLeaderboardsService;
use SeasonLeaderboards\SeasonLeaderboardsView;

final class SeasonLeaderboardsViewXssTest extends TestCase
{
    private SeasonLeaderboardsView $view;

    protected function setUp(): void
    {
        $stubRepo = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $service = new SeasonLeaderboardsService($stubRepo);
        $this->view = new SeasonLeaderboardsView($service);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{pid: int, name: string, year: int, teamname: string, teamid: int, team_city: string, color1: string, color2: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, points: int, fgp: string, ftp: string, tgp: string, mpg: string, fgmpg: string, fgapg: string, ftmpg: string, ftapg: string, tgmpg: string, tgapg: string, orbpg: string, drebpg: string, rpg: string, apg: string, spg: string, tpg: string, bpg: string, fpg: string, ppg: string, qa: string}
     */
    private function makeStats(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, year: int, teamname: string, teamid: int, team_city: string, color1: string, color2: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, points: int, fgp: string, ftp: string, tgp: string, mpg: string, fgmpg: string, fgapg: string, ftmpg: string, ftapg: string, tgmpg: string, tgapg: string, orbpg: string, drebpg: string, rpg: string, apg: string, spg: string, tpg: string, bpg: string, fpg: string, ppg: string, qa: string} */
        return array_merge([
            'pid' => 1,
            'name' => 'Safe Player',
            'year' => 2024,
            'teamname' => 'Safe Team',
            'teamid' => 1,
            'team_city' => 'Safe City',
            'color1' => 'FF0000',
            'color2' => '000000',
            'games' => 50,
            'minutes' => 1500,
            'fgm' => 200,
            'fga' => 400,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
            'orb' => 80,
            'reb' => 300,
            'ast' => 200,
            'stl' => 50,
            'tvr' => 80,
            'blk' => 30,
            'pf' => 100,
            'points' => 550,
            'fgp' => '0.500',
            'ftp' => '0.833',
            'tgp' => '0.385',
            'mpg' => '30.0',
            'fgmpg' => '4.0',
            'fgapg' => '8.0',
            'ftmpg' => '2.0',
            'ftapg' => '2.4',
            'tgmpg' => '1.0',
            'tgapg' => '2.6',
            'orbpg' => '1.6',
            'drebpg' => '4.4',
            'rpg' => '6.0',
            'apg' => '4.0',
            'spg' => '1.0',
            'tpg' => '1.6',
            'bpg' => '0.6',
            'fpg' => '2.0',
            'ppg' => '11.0',
            'qa' => '18.5',
        ], $overrides);
    }

    public function testPlayerNameWithScriptPayloadIsEscaped(): void
    {
        $stats = $this->makeStats(['name' => '<script>alert(1)</script>']);
        $html = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testTeamNameWithImgPayloadIsEscaped(): void
    {
        $stats = $this->makeStats(['teamname' => '<img src=x onerror=alert(1)>']);
        $html = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function testStatValuesAreNumericOrEscaped(): void
    {
        $stats = $this->makeStats(['ppg' => '<script>alert(1)</script>']);
        $html = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testGamesPlayedCastsToInt(): void
    {
        $stats = $this->makeStats(['games' => 42]);
        $html = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringContainsString('>42<', $html);
    }

    public function testFilterFormTeamNameIsEscaped(): void
    {
        $teams = [
            ['teamid' => 1, 'Team' => '<script>alert("team")</script>'],
        ];
        $years = [2024];
        $filters = ['team' => 0, 'year' => '', 'sortby' => 'PPG', 'limit' => ''];

        $html = $this->view->renderFilterForm($teams, $years, $filters);

        $this->assertStringNotContainsString('<script>alert("team")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
