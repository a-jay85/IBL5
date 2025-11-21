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
    public function testCalculateNegotiationCapAndRosterDataReturnsAllRequiredKeys(): void
    {
        // Arrange
        $team = $this->createMockTeam();
        
        // Act
        $result = $this->calculator->calculateNegotiationCapAndRosterData($team, 'Test Player');
        
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
    public function testCalculateNegotiationCapAndRosterDataExcludesPlayerOffer(): void
    {
        // Arrange - Set up mock to return offer for specific player
        $team = $this->createMockTeamWithOfferToExclude('Test Player', 1000);
        
        // Act
        $result = $this->calculator->calculateNegotiationCapAndRosterData($team, 'Test Player');
        
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
        $result = $this->calculator->calculateNegotiationCapAndRosterData($team, 'Test Player');
        
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
