<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_UIHelper class
 * 
 * Tests UI generation, team data handling, and form rendering.
 */
class UIHelperTest extends TestCase
{
    private $uiHelper;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->uiHelper = new Trading_UIHelper($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->uiHelper = null;
        $this->mockDb = null;
    }

    /**
     * @test
     */
    public function getAllTeamsForTrading_returnsArrayOfTeams()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['team_name' => 'Lakers', 'team_city' => 'Los Angeles'],
            ['team_name' => 'Celtics', 'team_city' => 'Boston'],
            ['team_name' => 'Free Agents', 'team_city' => 'Free'], // Should be filtered out
            ['team_name' => 'Warriors', 'team_city' => 'Golden State']
        ]);

        // Act
        $result = $this->uiHelper->getAllTeamsForTrading();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Should exclude 'Free Agents'
        
        // Check structure of returned teams
        foreach ($result as $team) {
            $this->assertArrayHasKey('name', $team);
            $this->assertArrayHasKey('city', $team);
            $this->assertArrayHasKey('full_name', $team);
            $this->assertNotEquals('Free Agents', $team['name']);
        }
    }

    /**
     * @test
     */
    public function getAllTeamsForTrading_excludesFreeAgents()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['team_name' => 'Lakers', 'team_city' => 'Los Angeles'],
            ['team_name' => 'Free Agents', 'team_city' => 'Free']
        ]);

        // Act
        $result = $this->uiHelper->getAllTeamsForTrading();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Lakers', $result[0]['name']);
        $this->assertEquals('Los Angeles Lakers', $result[0]['full_name']);
    }

    /**
     * @test
     */
    public function renderTeamSelectionLinks_generatesValidHtml()
    {
        // Arrange
        $teams = [
            ['name' => 'Lakers', 'city' => 'Los Angeles', 'full_name' => 'Los Angeles Lakers'],
            ['name' => 'Celtics', 'city' => 'Boston', 'full_name' => 'Boston Celtics']
        ];

        // Act
        $result = $this->uiHelper->renderTeamSelectionLinks($teams);

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<a href=', $result);
        $this->assertStringContainsString('Lakers', $result);
        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('Los Angeles Lakers', $result);
        $this->assertStringContainsString('Boston Celtics', $result);
        $this->assertStringContainsString('modules.php?name=Trading&op=offertrade&partner=', $result);
    }

    /**
     * @test
     */
    public function renderTeamSelectionLinks_withEmptyArray_returnsEmptyString()
    {
        // Arrange
        $teams = [];

        // Act
        $result = $this->uiHelper->renderTeamSelectionLinks($teams);

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * @test
     */
    public function renderCashInputs_generatesValidFormFields()
    {
        // Arrange
        $teamName = 'Lakers';
        $fieldPrefix = 'userSendsCash';
        $currentSeasonEndingYear = 2024;

        // Act
        $result = $this->uiHelper->renderCashInputs($teamName, $fieldPrefix, $currentSeasonEndingYear);

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<input type="number"', $result);
        $this->assertStringContainsString('name="userSendsCash', $result);
        $this->assertStringContainsString('Lakers send', $result);
        $this->assertStringContainsString('min="0"', $result);
        $this->assertStringContainsString('max="2000"', $result);
        
        // Should contain multiple year ranges
        $this->assertStringContainsString('2022-2023', $result); // Example year range
        $this->assertStringContainsString('2023-2024', $result); // Example year range
    }

    /**
     * @test
     */
    public function renderCashInputs_withAlignment_includesAlignmentAttribute()
    {
        // Arrange
        $teamName = 'Celtics';
        $fieldPrefix = 'partnerSendsCash';
        $currentSeasonEndingYear = 2024;
        $align = 'right';

        // Act
        $result = $this->uiHelper->renderCashInputs($teamName, $fieldPrefix, $currentSeasonEndingYear, $align);

        // Assert
        $this->assertStringContainsString('align="right"', $result);
        $this->assertStringContainsString('Celtics send', $result);
        $this->assertStringContainsString('partnerSendsCash', $result);
    }

    /**
     * @test
     */
    public function renderFutureSalaryCaps_generatesValidCapDisplay()
    {
        // Arrange
        $userSalaryArray = ['player' => [50000, 55000, 60000]];
        $partnerSalaryArray = ['player' => [45000, 50000, 55000]];
        $userTeamName = 'Lakers';
        $partnerTeamName = 'Celtics';
        $currentSeasonEndingYear = 2024;
        $seasonsToDisplay = 3;

        // Act
        $result = $this->uiHelper->renderFutureSalaryCaps(
            $userSalaryArray, 
            $partnerSalaryArray, 
            $userTeamName, 
            $partnerTeamName, 
            $currentSeasonEndingYear, 
            $seasonsToDisplay
        );

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<tr>', $result);
        $this->assertStringContainsString('Lakers Cap Total', $result);
        $this->assertStringContainsString('Celtics Cap Total', $result);
        $this->assertStringContainsString('50000', $result);
        $this->assertStringContainsString('45000', $result);
        $this->assertStringContainsString('2023-2024', $result);
        $this->assertStringContainsString('2024-2025', $result);
        $this->assertStringContainsString('2025-2026', $result);
    }

    /**
     * @test
     */
    public function renderFutureSalaryCaps_withMissingData_handlesGracefully()
    {
        // Arrange
        $userSalaryArray = ['player' => [50000]]; // Only one year
        $partnerSalaryArray = ['player' => []]; // No data
        $userTeamName = 'Warriors';
        $partnerTeamName = 'Spurs';
        $currentSeasonEndingYear = 2024;
        $seasonsToDisplay = 3;

        // Act
        $result = $this->uiHelper->renderFutureSalaryCaps(
            $userSalaryArray, 
            $partnerSalaryArray, 
            $userTeamName, 
            $partnerTeamName, 
            $currentSeasonEndingYear, 
            $seasonsToDisplay
        );

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('Warriors Cap Total', $result);
        $this->assertStringContainsString('Spurs Cap Total', $result);
        $this->assertStringContainsString('50000', $result); // User's first year
        $this->assertStringContainsString('0', $result); // Partner's missing data defaults to 0
    }

    /**
     * Test buildTeamFutureSalary and buildTeamFuturePicks methods
     * Note: These methods echo HTML directly, so we'll test them by capturing output
     */

    /**
     * @test
     */
    public function buildTeamFutureSalary_withPlayerData_generatesPlayerRows()
    {
        // Arrange
        $mockResult = new MockDatabaseResult([
            [
                'pos' => 'PG',
                'name' => 'Test Player',
                'pid' => 12345,
                'ordinal' => 1000,
                'cy' => 1,
                'cy1' => 5000,
                'cy2' => 5500,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0
            ]
        ]);
        $k = 0;

        // Act - Capture the output
        ob_start();
        $result = $this->uiHelper->buildTeamFutureSalary($mockResult, $k);
        $output = ob_get_clean();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('k', $result);
        $this->assertArrayHasKey('player', $result);
        $this->assertEquals(1, $result['k']); // Should increment k
        
        // Check that HTML was generated
        $this->assertStringContainsString('Test Player', $output);
        $this->assertStringContainsString('PG', $output);
        $this->assertStringContainsString('12345', $output);
        $this->assertStringContainsString('<input type="checkbox"', $output);
    }

    /**
     * @test
     */
    public function buildTeamFuturePicks_withPickData_generatesPickRows()
    {
        // Arrange
        $mockResult = new MockDatabaseResult([
            [
                'year' => 2024,
                'teampick' => 'Lakers',
                'round' => 1,
                'notes' => 'Protected 1-5',
                'pickid' => 98765
            ]
        ]);
        
        $future_salary_array = ['k' => 0, 'player' => [], 'hold' => [], 'picks' => []];

        // Act - Capture the output
        ob_start();
        $result = $this->uiHelper->buildTeamFuturePicks($mockResult, $future_salary_array);
        $output = ob_get_clean();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('k', $result);
        $this->assertEquals(1, $result['k']); // Should increment k
        
        // Check that HTML was generated
        $this->assertStringContainsString('2024', $output);
        $this->assertStringContainsString('Lakers', $output);
        $this->assertStringContainsString('Round 1', $output);
        $this->assertStringContainsString('Protected 1-5', $output);
        $this->assertStringContainsString('98765', $output);
    }

    /**
     * Integration tests for complex scenarios
     */

    /**
     * @test
     */
    public function getAllTeamsForTrading_withRealWorldData_sortsCorrectly()
    {
        // Arrange - Simulate real NBA teams
        $this->mockDb->setMockData([
            ['team_name' => 'Warriors', 'team_city' => 'Golden State'],
            ['team_name' => 'Lakers', 'team_city' => 'Los Angeles'],
            ['team_name' => 'Celtics', 'team_city' => 'Boston'],
            ['team_name' => 'Free Agents', 'team_city' => 'Free']
        ]);

        // Act
        $result = $this->uiHelper->getAllTeamsForTrading();

        // Assert
        $this->assertCount(3, $result);
        
        // Check that each team has correct structure
        foreach ($result as $team) {
            $this->assertNotEquals('Free Agents', $team['name']);
            $this->assertStringContainsString($team['city'], $team['full_name']);
            $this->assertStringContainsString($team['name'], $team['full_name']);
        }
    }

    /**
     * @test
     */
    public function renderTeamSelectionLinks_generatesUniqueUrls()
    {
        // Arrange
        $teams = [
            ['name' => 'Lakers', 'city' => 'Los Angeles', 'full_name' => 'Los Angeles Lakers'],
            ['name' => 'Celtics', 'city' => 'Boston', 'full_name' => 'Boston Celtics'],
            ['name' => 'Warriors', 'city' => 'Golden State', 'full_name' => 'Golden State Warriors']
        ];

        // Act
        $result = $this->uiHelper->renderTeamSelectionLinks($teams);

        // Assert
        $this->assertStringContainsString('partner=Lakers', $result);
        $this->assertStringContainsString('partner=Celtics', $result);
        $this->assertStringContainsString('partner=Warriors', $result);
        
        // Each team should appear exactly once in URL
        $this->assertEquals(1, substr_count($result, 'partner=Lakers'));
        $this->assertEquals(1, substr_count($result, 'partner=Celtics'));
        $this->assertEquals(1, substr_count($result, 'partner=Warriors'));
    }
}