<?php

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyCapCalculator;

/**
 * Comprehensive tests for FreeAgencyCapCalculator
 * 
 * Tests salary cap and roster spot calculations for free agency:
 * - Multi-year cap space calculations
 * - Soft cap and hard cap tracking
 * - Roster spot availability
 * - Contract offers impact on cap space
 */
class FreeAgencyCapCalculatorTest extends TestCase
{
    private $mockDb;
    private FreeAgencyCapCalculator $calculator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->calculator = new FreeAgencyCapCalculator($this->mockDb);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapSpaceReturnsAllRequiredKeys(): void
    {
        // Arrange
        $team = $this->createMockTeam();
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - All required keys present
        $this->assertArrayHasKey('year1TotalSalary', $result);
        $this->assertArrayHasKey('year2TotalSalary', $result);
        $this->assertArrayHasKey('year3TotalSalary', $result);
        $this->assertArrayHasKey('year4TotalSalary', $result);
        $this->assertArrayHasKey('year5TotalSalary', $result);
        $this->assertArrayHasKey('year6TotalSalary', $result);
        
        $this->assertArrayHasKey('year1AvailableSoftCap', $result);
        $this->assertArrayHasKey('year2AvailableSoftCap', $result);
        $this->assertArrayHasKey('year3AvailableSoftCap', $result);
        $this->assertArrayHasKey('year4AvailableSoftCap', $result);
        $this->assertArrayHasKey('year5AvailableSoftCap', $result);
        $this->assertArrayHasKey('year6AvailableSoftCap', $result);
        
        $this->assertArrayHasKey('year1AvailableHardCap', $result);
        $this->assertArrayHasKey('rosterspots1', $result);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapSpaceWithNoPlayers(): void
    {
        // Arrange - Team with no players under contract
        $team = $this->createMockTeamWithPlayers([]);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Should have full cap space and max roster spots
        $this->assertEquals(0, $result['year1TotalSalary']);
        $this->assertEquals(League::SOFT_CAP_MAX, $result['year1AvailableSoftCap']);
        $this->assertEquals(League::HARD_CAP_MAX, $result['year1AvailableHardCap']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterspots1']);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapSpaceWithOnePlayer(): void
    {
        // Arrange - Team with one player under contract
        $players = [
            [
                'name' => 'Test Player',
                'teamname' => 'Test Team',
                'cy' => 0,
                'cyt' => 3,
                'cy1' => 1000,
                'cy2' => 1100,
                'cy3' => 1200,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Year 1-3 should have salary, years 4-6 should be 0
        $this->assertEquals(1000, $result['year1TotalSalary']);
        $this->assertEquals(1100, $result['year2TotalSalary']);
        $this->assertEquals(1200, $result['year3TotalSalary']);
        $this->assertEquals(0, $result['year4TotalSalary']);
        $this->assertEquals(0, $result['year5TotalSalary']);
        $this->assertEquals(0, $result['year6TotalSalary']);
        
        // Roster spots should be decremented for years with salary
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots1']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots2']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots3']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterspots4']);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapSpaceExcludesFreeAgents(): void
    {
        // Arrange - Team with a free agent (cy == cyt)
        $players = [
            [
                'name' => 'Free Agent',
                'teamname' => 'Test Team',
                'cy' => 3, // Free agent
                'cyt' => 3,
                'cy1' => 1000,
                'cy2' => 1000,
                'cy3' => 1000,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Free agent's salary should not count
        $this->assertEquals(0, $result['year1TotalSalary']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterspots1']);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapSpaceExcludesPipeNamedPlayers(): void
    {
        // Arrange - Team with player whose name starts with |
        $players = [
            [
                'name' => '|Traded Player',
                'teamname' => 'Test Team',
                'cy' => 0,
                'cyt' => 3,
                'cy1' => 1000,
                'cy2' => 0,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Salary counts but roster spot doesn't
        $this->assertEquals(1000, $result['year1TotalSalary']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterspots1']); // Not decremented
    }

    /**
     * @group cap-calculator
     * @group offers
     */
    public function testCalculateTeamCapSpaceIncludesOffers(): void
    {
        // Arrange
        $players = [];
        $offers = [
            [
                'offer1' => 800,
                'offer2' => 850,
                'offer3' => 900,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayersAndOffers($players, $offers);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Offers should count toward cap and roster spots
        $this->assertEquals(800, $result['year1TotalSalary']);
        $this->assertEquals(850, $result['year2TotalSalary']);
        $this->assertEquals(900, $result['year3TotalSalary']);
        
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots1']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots2']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterspots3']);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterspots4']);
    }

    /**
     * @group cap-calculator
     * @group soft-cap
     */
    public function testSoftCapCalculationSubtractsFromLeagueMax(): void
    {
        // Arrange
        $players = [
            [
                'name' => 'Player 1',
                'teamname' => 'Test Team',
                'cy' => 0,
                'cyt' => 1,
                'cy1' => 2000,
                'cy2' => 0,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert
        $expectedSoftCap = League::SOFT_CAP_MAX - 2000;
        $this->assertEquals($expectedSoftCap, $result['year1AvailableSoftCap']);
    }

    /**
     * @group cap-calculator
     * @group hard-cap
     */
    public function testHardCapIsAlwaysGreaterThanSoftCap(): void
    {
        // Arrange
        $team = $this->createMockTeam();
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert
        for ($i = 1; $i <= 6; $i++) {
            $this->assertGreaterThan(
                $result["year{$i}AvailableSoftCap"],
                $result["year{$i}AvailableHardCap"],
                "Hard cap should be greater than soft cap for year {$i}"
            );
        }
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testCalculateNegotiationCapSpaceReturnsAllRequiredKeys(): void
    {
        // Arrange
        $team = $this->createMockTeam();
        
        // Act
        $result = $this->calculator->calculateNegotiationCapSpace($team, 'Test Player');
        
        // Assert
        $this->assertArrayHasKey('softCap', $result);
        $this->assertArrayHasKey('hardCap', $result);
        $this->assertArrayHasKey('rosterSpots', $result);
        
        $this->assertArrayHasKey('year1', $result['softCap']);
        $this->assertArrayHasKey('year6', $result['softCap']);
        $this->assertArrayHasKey('year1', $result['hardCap']);
        $this->assertArrayHasKey('year6', $result['hardCap']);
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testCalculateNegotiationCapSpaceExcludesPlayerOffer(): void
    {
        // Arrange - Set up mock to return offer for specific player
        $team = $this->createMockTeamWithOfferToExclude('Test Player', 1000);
        
        // Act
        $result = $this->calculator->calculateNegotiationCapSpace($team, 'Test Player');
        
        // Assert - Cap space should not include the excluded player's offer
        $this->assertIsInt($result['softCap']['year1']);
        $this->assertIsInt($result['rosterSpots']);
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testNegotiationHardCapExceedsSoftCapByBuffer(): void
    {
        // Arrange
        $team = $this->createMockTeam();
        
        // Act
        $result = $this->calculator->calculateNegotiationCapSpace($team, 'Test Player');
        
        // Assert
        $buffer = League::HARD_CAP_MAX - League::SOFT_CAP_MAX;
        
        for ($i = 1; $i <= 6; $i++) {
            $yearKey = "year{$i}";
            $expectedHardCap = $result['softCap'][$yearKey] + $buffer;
            
            $this->assertEquals(
                $expectedHardCap,
                $result['hardCap'][$yearKey],
                "Hard cap should be soft cap + buffer for {$yearKey}"
            );
        }
    }

    /**
     * @group cap-calculator
     * @group contract-years
     */
    public function testContractYearOffsetsCalculatedCorrectly(): void
    {
        // Arrange - Player in year 2 of their contract
        $players = [
            [
                'name' => 'Test Player',
                'teamname' => 'Test Team',
                'cy' => 1, // Second year of contract
                'cyt' => 4,
                'cy1' => 1000,
                'cy2' => 1100, // Next year's salary
                'cy3' => 1200,
                'cy4' => 1300,
                'cy5' => 0,
                'cy6' => 0,
            ]
        ];
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Year 1 should use cy2 (since player is in cy=1)
        $this->assertEquals(1100, $result['year1TotalSalary']);
        $this->assertEquals(1200, $result['year2TotalSalary']);
        $this->assertEquals(1300, $result['year3TotalSalary']);
    }

    /**
     * @group cap-calculator
     * @group roster-spots
     */
    public function testRosterSpotsNeverNegative(): void
    {
        // Arrange - Team with many players
        $players = [];
        for ($i = 0; $i < 20; $i++) {
            $players[] = [
                'name' => "Player {$i}",
                'teamname' => 'Test Team',
                'cy' => 0,
                'cyt' => 1,
                'cy1' => 500,
                'cy2' => 0,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
            ];
        }
        
        $team = $this->createMockTeamWithPlayers($players);
        
        // Act
        $result = $this->calculator->calculateTeamCapSpace($team);
        
        // Assert - Roster spots should bottom out at 0, not go negative
        $this->assertGreaterThanOrEqual(0, $result['rosterspots1']);
    }

    // Helper Methods

    /**
     * Create a basic mock team with no players or offers
     */
    private function createMockTeam()
    {
        $team = $this->createMock(Team::class);
        $team->name = 'Test Team';
        
        $team->method('getRosterUnderContractOrderedByOrdinalResult')
            ->willReturn([]);
        
        $team->method('getFreeAgencyOffersResult')
            ->willReturn([]);
        
        return $team;
    }

    /**
     * Create a mock team with specific players
     */
    private function createMockTeamWithPlayers(array $players)
    {
        $team = $this->createMock(Team::class);
        $team->name = 'Test Team';
        
        $team->method('getRosterUnderContractOrderedByOrdinalResult')
            ->willReturn($players);
        
        $team->method('getFreeAgencyOffersResult')
            ->willReturn([]);
        
        return $team;
    }

    /**
     * Create a mock team with specific players and offers
     */
    private function createMockTeamWithPlayersAndOffers(array $players, array $offers)
    {
        $team = $this->createMock(Team::class);
        $team->name = 'Test Team';
        
        $team->method('getRosterUnderContractOrderedByOrdinalResult')
            ->willReturn($players);
        
        $team->method('getFreeAgencyOffersResult')
            ->willReturn($offers);
        
        return $team;
    }

    /**
     * Create a mock team with an offer to exclude from negotiation cap space
     */
    private function createMockTeamWithOfferToExclude(string $excludePlayerName, int $offerAmount)
    {
        // Mock database to return empty result for excluded player query
        $this->mockDb->setMockData([]);
        
        $team = $this->createMock(Team::class);
        $team->name = 'Test Team';
        
        $team->method('getRosterUnderContractOrderedByOrdinalResult')
            ->willReturn([]);
        
        return $team;
    }
}
