<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\TestCase;
use SeriesRecords\Contracts\SeriesRecordsServiceInterface;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\StandingsView;

final class StandingsViewXssTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeStandingsRow(array $overrides = []): array
    {
        return array_merge([
            'teamid' => 1,
            'team_name' => 'Safe Team',
            'league_record' => '50-32',
            'pct' => '0.610',
            'gamesBack' => '0',
            'conf_record' => '30-18',
            'div_record' => '12-6',
            'home_record' => '28-13',
            'away_record' => '22-19',
            'games_unplayed' => 0,
            'magicNumber' => 0,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'homeGames' => 41,
            'awayGames' => 41,
            'color1' => 'FF0000',
            'color2' => '000000',
        ], $overrides);
    }

    public function testTeamNameWithScriptPayloadIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $stubRepo = $this->createStub(StandingsRepositoryInterface::class);
        $stubRepo->method('getStandingsByRegion')->willReturn([
            $this->makeStandingsRow(['team_name' => $xss]),
        ]);
        $stubRepo->method('getAllStreakData')->willReturn([]);
        $stubRepo->method('getAllPythagoreanStats')->willReturn([]);
        $stubRepo->method('getSeriesRecords')->willReturn([]);

        $stubSeriesService = $this->createStub(SeriesRecordsServiceInterface::class);
        $stubSeriesService->method('buildSeriesMatrix')->willReturn([]);

        $view = new StandingsView($stubRepo, 2025, $stubSeriesService);
        $html = $view->renderRegion('Eastern');

        $this->assertStringContainsString($escaped, $html);
        $this->assertStringNotContainsString($xss, $html);
    }
}
