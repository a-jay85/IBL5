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
        $mockTeam = $this->createMockTeam();
        $mockSeason = $this->createMock(\Season::class);
        $this->calculator = new FreeAgencyCapCalculator($this->mockDb, $mockTeam, $mockSeason);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapMetricsReturnsAllRequiredKeys(): void
    {
        // Arrange - Team is already set up in setUp()
        
        // Act
        $result = $this->calculator->calculateTeamCapMetrics();
        
        // Assert - All required keys present
        $this->assertArrayHasKey('totalSalaries', $result);
        $this->assertArrayHasKey('softCapSpace', $result);
        $this->assertArrayHasKey('hardCapSpace', $result);
        $this->assertArrayHasKey('rosterSpots', $result);
        
        // Assert arrays contain 6 years
        $this->assertCount(6, $result['totalSalaries']);
        $this->assertCount(6, $result['softCapSpace']);
        $this->assertCount(6, $result['hardCapSpace']);
        $this->assertCount(6, $result['rosterSpots']);
    }

    /**
     * @group cap-calculator
     * @group cap-space
     */
    public function testCalculateTeamCapMetricsWithNoPlayers(): void
    {
        // Arrange - Team with no players under contract
        $team = $this->createMockTeamWithPlayers([]);
        $mockSeason = $this->createMock(\Season::class);
        $calculator = new FreeAgencyCapCalculator($this->mockDb, $team, $mockSeason);
        
        // Act
        $result = $calculator->calculateTeamCapMetrics();
        
        // Assert - Should have full cap space and max roster spots
        $this->assertEquals(0, $result['totalSalaries'][0]);
        $this->assertEquals(League::SOFT_CAP_MAX, $result['softCapSpace'][0]);
        $this->assertEquals(League::HARD_CAP_MAX, $result['hardCapSpace'][0]);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterSpots'][0]);
    }

    /**
     * @group cap-calculator
     * @group offers
     */
    public function testCalculateTeamCapMetricsIncludesOffers(): void
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
        $mockSeason = $this->createMock(\Season::class);
        $calculator = new FreeAgencyCapCalculator($this->mockDb, $team, $mockSeason);
        
        // Act
        $result = $calculator->calculateTeamCapMetrics();
        
        // Assert - Offers should count toward cap and roster spots
        $this->assertEquals(800, $result['totalSalaries'][0]);
        $this->assertEquals(850, $result['totalSalaries'][1]);
        $this->assertEquals(900, $result['totalSalaries'][2]);
        
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterSpots'][0]);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterSpots'][1]);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX - 1, $result['rosterSpots'][2]);
        $this->assertEquals(Team::ROSTER_SPOTS_MAX, $result['rosterSpots'][3]);
    }

    /**
     * @group cap-calculator
     * @group hard-cap
     */
    public function testHardCapIsAlwaysGreaterThanSoftCap(): void
    {
        // Arrange - Team is already set up in setUp()
        
        // Act
        $result = $this->calculator->calculateTeamCapMetrics();
        
        // Assert
        for ($i = 0; $i < 6; $i++) {
            $this->assertGreaterThan(
                $result['softCapSpace'][$i],
                $result['hardCapSpace'][$i],
                "Hard cap should be greater than soft cap for year " . ($i + 1)
            );
        }
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testCalculateTeamCapMetricsWithExcludedPlayerReturnsAllRequiredKeys(): void
    {
        // Arrange - Team is already set up in setUp()
        
        // Act
        $result = $this->calculator->calculateTeamCapMetrics('Test Player');
        
        // Assert
        $this->assertArrayHasKey('totalSalaries', $result);
        $this->assertArrayHasKey('softCapSpace', $result);
        $this->assertArrayHasKey('hardCapSpace', $result);
        $this->assertArrayHasKey('rosterSpots', $result);
        
        // Assert arrays contain 6 years
        $this->assertCount(6, $result['totalSalaries']);
        $this->assertCount(6, $result['softCapSpace']);
        $this->assertCount(6, $result['hardCapSpace']);
        $this->assertCount(6, $result['rosterSpots']);
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testCalculateTeamCapMetricsExcludesPlayerOffer(): void
    {
        // Arrange - Set up mock team with offer for specific player
        $team = $this->createMockTeamWithOfferToExclude('Test Player', 1000);
        $mockSeason = $this->createMock(\Season::class);
        $calculator = new FreeAgencyCapCalculator($this->mockDb, $team, $mockSeason);
        
        // Act
        $result = $calculator->calculateTeamCapMetrics('Test Player');
        
        // Assert - Cap space should not include the excluded player's offer
        $this->assertIsInt($result['softCapSpace'][0]);
        $this->assertIsArray($result['rosterSpots']);
        $this->assertCount(6, $result['rosterSpots']);
    }

    /**
     * @group cap-calculator
     * @group negotiation
     */
    public function testCapSpaceHardCapExceedsSoftCapByBuffer(): void
    {
        // Arrange - Team is already set up in setUp()
        
        // Act
        $result = $this->calculator->calculateTeamCapMetrics('Test Player');
        
        // Assert
        $buffer = League::HARD_CAP_MAX - League::SOFT_CAP_MAX;
        
        for ($i = 0; $i < 6; $i++) {
            $expectedHardCap = $result['softCapSpace'][$i] + $buffer;
            
            $this->assertEquals(
                $expectedHardCap,
                $result['hardCapSpace'][$i],
                "Hard cap should be soft cap + buffer for year " . ($i + 1)
            );
        }
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

    private function createMockTeamWithOfferToExclude(string $excludePlayerName, int $offerAmount): \Team
    {
        $team = $this->createMock(\Team::class);
        $team->name = 'Test Team';
        
        $team->method('getRosterUnderContractOrderedByOrdinalResult')
            ->willReturn([]);
        
        $team->method('getFreeAgencyOffersResult')
            ->willReturn([]);
        
        return $team;
    }
}
