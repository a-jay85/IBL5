<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\TestCase;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\OlympicsStandingsView;

/**
 * @covers \Standings\OlympicsStandingsView
 */
class OlympicsStandingsViewTest extends TestCase
{
    /** @var StandingsRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private StandingsRepositoryInterface $stubRepository;

    protected function setUp(): void
    {
        $this->stubRepository = $this->createStub(StandingsRepositoryInterface::class);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeBulkRow(array $overrides = []): array
    {
        return array_merge([
            'teamid' => 1,
            'team_name' => 'Eagles',
            'league_record' => '3-1',
            'pct' => '0.750',
            'conf_gb' => '0.0',
            'div_gb' => '0.0',
            'conf_record' => '0-0',
            'div_record' => '0-0',
            'home_record' => '2-0',
            'away_record' => '1-1',
            'games_unplayed' => 4,
            'conf_magic_number' => 0,
            'div_magic_number' => 0,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 3,
            'homeGames' => 2,
            'awayGames' => 2,
            'conference' => 'Group A',
            'division' => '',
            'color1' => '002868',
            'color2' => 'BF0A30',
        ], $overrides);
    }

    public function testRendersTableWithCorrectColumns(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1]);
        $html = $view->render();

        $this->assertStringContainsString('Rank', $html);
        $this->assertStringContainsString('Team', $html);
        $this->assertStringContainsString('W-L', $html);
        $this->assertStringContainsString('Win%', $html);
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('Away', $html);
        $this->assertStringContainsString('Games Left', $html);
    }

    public function testFiltersOutFillerTeams(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(['teamid' => 1, 'team_name' => 'Eagles']),
            $this->makeBulkRow(['teamid' => 99, 'team_name' => 'Filler']),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1]);
        $html = $view->render();

        $this->assertStringContainsString('Eagles', $html);
        $this->assertStringNotContainsString('Filler', $html);
    }

    public function testSortsByWinPercentageDescending(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(['teamid' => 1, 'team_name' => 'Low', 'pct' => '0.250', 'wins' => 1]),
            $this->makeBulkRow(['teamid' => 2, 'team_name' => 'High', 'pct' => '0.750', 'wins' => 3]),
            $this->makeBulkRow(['teamid' => 3, 'team_name' => 'Mid', 'pct' => '0.500', 'wins' => 2]),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1, 2, 3]);
        $html = $view->render();

        $highPos = strpos($html, 'High');
        $midPos = strpos($html, 'Mid');
        $lowPos = strpos($html, 'Low');

        $this->assertNotFalse($highPos);
        $this->assertNotFalse($midPos);
        $this->assertNotFalse($lowPos);
        $this->assertLessThan($midPos, $highPos);
        $this->assertLessThan($lowPos, $midPos);
    }

    public function testNoConferenceDivisionMagicOrClinchContent(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1]);
        $html = $view->render();

        $this->assertStringNotContainsString('Eastern Conference', $html);
        $this->assertStringNotContainsString('Western Conference', $html);
        $this->assertStringNotContainsString('Atlantic', $html);
        $this->assertStringNotContainsString('clinch', strtolower($html));
        $this->assertStringNotContainsString('magic', strtolower($html));
    }

    public function testXssEscapesTeamNames(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(['teamid' => 1, 'team_name' => '<script>alert(1)</script>']),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1]);
        $html = $view->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRendersOlympicsStandingsTitle(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, []);
        $html = $view->render();

        $this->assertStringContainsString('2025 Olympics Standings', $html);
        $this->assertStringContainsString('ibl-title', $html);
    }

    public function testWinsTiebreakerWhenPercentageEqual(): void
    {
        $this->stubRepository->method('getAllStandings')->willReturn([
            $this->makeBulkRow(['teamid' => 1, 'team_name' => 'FewerWins', 'pct' => '0.500', 'wins' => 2]),
            $this->makeBulkRow(['teamid' => 2, 'team_name' => 'MoreWins', 'pct' => '0.500', 'wins' => 5]),
        ]);

        $view = new OlympicsStandingsView($this->stubRepository, 2025, [1, 2]);
        $html = $view->render();

        $morePos = strpos($html, 'MoreWins');
        $fewerPos = strpos($html, 'FewerWins');

        $this->assertNotFalse($morePos);
        $this->assertNotFalse($fewerPos);
        $this->assertLessThan($fewerPos, $morePos);
    }
}
