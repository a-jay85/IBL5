<?php

declare(strict_types=1);

namespace Tests\LeagueStats;

use PHPUnit\Framework\TestCase;
use LeagueStats\LeagueStatsView;
use LeagueStats\Contracts\LeagueStatsViewInterface;

/**
 * Tests for LeagueStatsView
 *
 * Verifies HTML rendering of league statistics tables including
 * team highlighting and proper output sanitization.
 */
class LeagueStatsViewTest extends TestCase
{
    private LeagueStatsView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueStatsView();
    }

    /**
     * Test that view implements the interface
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(LeagueStatsViewInterface::class, $this->view);
    }

    /**
     * Test render returns string
     */
    public function testRenderReturnsString(): void
    {
        $data = $this->createMinimalViewData();

        $result = $this->view->render($data, 0);

        $this->assertIsString($result);
    }

    /**
     * Test render includes all five tables
     */
    public function testRenderIncludesFiveTables(): void
    {
        $data = $this->createMinimalViewData();

        $result = $this->view->render($data, 0);

        // Count table occurrences
        $tableCount = substr_count($result, '<table class="sortable">');
        $this->assertEquals(5, $tableCount);
    }

    /**
     * Test render includes all section headers
     */
    public function testRenderIncludesSectionHeaders(): void
    {
        $data = $this->createMinimalViewData();

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('<h1>League-wide Statistics</h1>', $result);
        $this->assertStringContainsString('<h2>Team Offense Totals</h2>', $result);
        $this->assertStringContainsString('<h2>Team Defense Totals</h2>', $result);
        $this->assertStringContainsString('<h2>Team Offense Averages</h2>', $result);
        $this->assertStringContainsString('<h2>Team Defense Averages</h2>', $result);
        $this->assertStringContainsString('<h2>Team Off/Def Average Differentials</h2>', $result);
    }

    /**
     * Test that user's team row is highlighted
     */
    public function testRenderHighlightsUserTeamRow(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics'],
            ['teamid' => 2, 'team_city' => 'Los Angeles', 'team_name' => 'Lakers'],
        ]);

        $result = $this->view->render($data, 1);

        // Team 1 should be highlighted
        $this->assertStringContainsString('bgcolor="#FFA"', $result);
    }

    /**
     * Test that non-user team rows are not highlighted
     */
    public function testRenderDoesNotHighlightOtherTeams(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics'],
            ['teamid' => 2, 'team_city' => 'Los Angeles', 'team_name' => 'Lakers'],
        ]);

        // User's team is 99 (doesn't exist)
        $result = $this->view->render($data, 99);

        // No rows should be highlighted
        $this->assertStringNotContainsString('bgcolor="#FFA"', $result);
    }

    /**
     * Test that team names are included in output
     */
    public function testRenderIncludesTeamNames(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics'],
        ]);

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('Boston', $result);
        $this->assertStringContainsString('Celtics', $result);
    }

    /**
     * Test that team links are properly formatted
     */
    public function testRenderIncludesTeamLinks(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics'],
        ]);

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=1', $result);
    }

    /**
     * Test that league totals footer is included
     */
    public function testRenderIncludesLeagueTotalsFooter(): void
    {
        $data = $this->createMinimalViewData();
        $data['league']['totals']['games'] = '820';
        $data['league']['totals']['fgm'] = '32,000';

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('LEAGUE TOTALS', $result);
        $this->assertStringContainsString('820', $result);
        $this->assertStringContainsString('32,000', $result);
    }

    /**
     * Test that league averages footer is included
     */
    public function testRenderIncludesLeagueAveragesFooter(): void
    {
        $data = $this->createMinimalViewData();
        $data['league']['averages']['fgm'] = '39.0';
        $data['league']['averages']['fgp'] = '0.457';

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('LEAGUE AVERAGES', $result);
        $this->assertStringContainsString('39.0', $result);
        $this->assertStringContainsString('0.457', $result);
    }

    /**
     * Test empty data array handling
     */
    public function testRenderHandlesEmptyData(): void
    {
        $data = [
            'teams' => [],
            'league' => ['totals' => [], 'averages' => []],
            'differentials' => [],
        ];

        $result = $this->view->render($data, 0);

        $this->assertIsString($result);
        $this->assertStringContainsString('League-wide Statistics', $result);
    }

    /**
     * Test that special characters in team names are sanitized
     */
    public function testRenderSanitizesTeamNames(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Test<script>', 'team_name' => 'Team"Alert"'],
        ]);

        $result = $this->view->render($data, 0);

        // Script tags and quotes should be escaped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('"Alert"', $result);
    }

    /**
     * Test that totals header row includes correct columns
     */
    public function testRenderTotalsHeaderIncludesCorrectColumns(): void
    {
        $data = $this->createMinimalViewData();

        $result = $this->view->render($data, 0);

        // Check for totals header columns
        $this->assertStringContainsString('<th>Team</th>', $result);
        $this->assertStringContainsString('<th>Gm</th>', $result);
        $this->assertStringContainsString('<th>FGM</th>', $result);
        $this->assertStringContainsString('<th>FGA</th>', $result);
        $this->assertStringContainsString('<th>PTS</th>', $result);
    }

    /**
     * Test that averages header row includes percentage columns
     */
    public function testRenderAveragesHeaderIncludesPercentageColumns(): void
    {
        $data = $this->createMinimalViewData();

        $result = $this->view->render($data, 0);

        // Check for percentage columns in averages header
        $this->assertStringContainsString('<th>FGP</th>', $result);
        $this->assertStringContainsString('<th>FTP</th>', $result);
        $this->assertStringContainsString('<th>3GP</th>', $result);
    }

    /**
     * Test that differentials are displayed
     */
    public function testRenderDisplaysDifferentials(): void
    {
        $data = $this->createViewDataWithTeams([
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics'],
        ]);
        $data['differentials'] = [
            [
                'teamid' => 1,
                'team_city' => 'Boston',
                'team_name' => 'Celtics',
                'color1' => '#007A33',
                'color2' => '#FFFFFF',
                'differentials' => [
                    'fgm' => '5.00',
                    'fga' => '3.50',
                    'fgp' => '0.025',
                    'ftm' => '2.00',
                    'fta' => '1.50',
                    'ftp' => '0.015',
                    'tgm' => '1.50',
                    'tga' => '2.00',
                    'tgp' => '0.010',
                    'orb' => '1.00',
                    'reb' => '4.00',
                    'ast' => '3.00',
                    'stl' => '0.50',
                    'tvr' => '-1.00',
                    'blk' => '0.75',
                    'pf' => '-0.50',
                    'pts' => '13.00',
                ],
            ],
        ];

        $result = $this->view->render($data, 0);

        $this->assertStringContainsString('Diff', $result);
        $this->assertStringContainsString('5.00', $result);
        $this->assertStringContainsString('13.00', $result);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create minimal view data for basic tests
     */
    private function createMinimalViewData(): array
    {
        return [
            'teams' => [],
            'league' => [
                'totals' => [
                    'games' => '0',
                    'fgm' => '0',
                    'fga' => '0',
                    'ftm' => '0',
                    'fta' => '0',
                    'tgm' => '0',
                    'tga' => '0',
                    'orb' => '0',
                    'reb' => '0',
                    'ast' => '0',
                    'stl' => '0',
                    'tvr' => '0',
                    'blk' => '0',
                    'pf' => '0',
                    'pts' => '0',
                ],
                'averages' => [
                    'fgm' => '0.0',
                    'fga' => '0.0',
                    'fgp' => '0.000',
                    'ftm' => '0.0',
                    'fta' => '0.0',
                    'ftp' => '0.000',
                    'tgm' => '0.0',
                    'tga' => '0.0',
                    'tgp' => '0.000',
                    'orb' => '0.0',
                    'reb' => '0.0',
                    'ast' => '0.0',
                    'stl' => '0.0',
                    'tvr' => '0.0',
                    'blk' => '0.0',
                    'pf' => '0.0',
                    'pts' => '0.0',
                ],
                'games' => 0,
            ],
            'differentials' => [],
        ];
    }

    /**
     * Create view data with specified teams
     */
    private function createViewDataWithTeams(array $teamConfigs): array
    {
        $data = $this->createMinimalViewData();

        foreach ($teamConfigs as $config) {
            $team = $this->createTeamData(
                $config['teamid'],
                $config['team_city'],
                $config['team_name']
            );
            $data['teams'][] = $team;
        }

        return $data;
    }

    /**
     * Create a team data structure for testing
     */
    private function createTeamData(int $teamId, string $city, string $name): array
    {
        $stats = [
            'games' => '82',
            'fgm' => '3,200',
            'fga' => '7,000',
            'ftm' => '1,500',
            'fta' => '2,000',
            'tgm' => '1,000',
            'tga' => '2,800',
            'orb' => '900',
            'reb' => '3,600',
            'ast' => '2,000',
            'stl' => '600',
            'tvr' => '1,200',
            'blk' => '400',
            'pf' => '1,700',
            'pts' => '8,900',
        ];

        $averages = [
            'fgm' => '39.0',
            'fga' => '85.4',
            'fgp' => '0.457',
            'ftm' => '18.3',
            'fta' => '24.4',
            'ftp' => '0.750',
            'tgm' => '12.2',
            'tga' => '34.1',
            'tgp' => '0.357',
            'orb' => '11.0',
            'reb' => '43.9',
            'ast' => '24.4',
            'stl' => '7.3',
            'tvr' => '14.6',
            'blk' => '4.9',
            'pf' => '20.7',
            'pts' => '108.5',
        ];

        return [
            'teamid' => $teamId,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => '#007A33',
            'color2' => '#FFFFFF',
            'offense_totals' => $stats,
            'offense_averages' => $averages,
            'defense_totals' => $stats,
            'defense_averages' => $averages,
            'raw_offense' => [],
            'raw_defense' => [],
            'offense_games' => 82,
            'defense_games' => 82,
        ];
    }
}
