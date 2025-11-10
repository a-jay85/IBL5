<?php

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationDemandCalculator;
use Player\Player;

/**
 * Tests for NegotiationDemandCalculator
 * 
 * Tests contract demand calculation logic:
 * - Base demands calculated from player ratings
 * - Market maximum normalization
 * - Modifier calculation from team/player factors
 * - Yearly demands with 10% raises
 */
class NegotiationDemandCalculatorTest extends TestCase
{
    private $mockDb;
    private $calculator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->calculator = new NegotiationDemandCalculator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->calculator = null;
        $this->mockDb = null;
    }

    /**
     * @group calculation
     * @group base-demands
     */
    public function testCalculatesDemandsForAveragePlayer()
    {
        // Arrange
        $player = $this->createPlayerWithRatings(50); // Average ratings
        $this->setupMarketMaximums(100); // Max value is 100 for all stats
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Assert
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('year1', $demands);
        $this->assertArrayHasKey('year2', $demands);
        $this->assertArrayHasKey('year3', $demands);
        $this->assertArrayHasKey('year4', $demands);
        $this->assertArrayHasKey('year5', $demands);
        $this->assertArrayHasKey('year6', $demands);
        $this->assertArrayHasKey('years', $demands);
        $this->assertArrayHasKey('total', $demands);
        $this->assertArrayHasKey('modifier', $demands);
        
        // Year 6 should always be 0 for extensions
        $this->assertEquals(0, $demands['year6']);
        
        // Demands should increase each year (10% raise)
        $this->assertGreaterThan($demands['year1'], $demands['year2']);
        $this->assertGreaterThan($demands['year2'], $demands['year3']);
        $this->assertGreaterThan($demands['year3'], $demands['year4']);
        $this->assertGreaterThan($demands['year4'], $demands['year5']);
    }

    /**
     * @group calculation
     * @group base-demands
     */
    public function testCalculatesHigherDemandsForBetterPlayer()
    {
        // Arrange
        $averagePlayer = $this->createPlayerWithRatings(50);
        $starPlayer = $this->createPlayerWithRatings(90);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $averageDemands = $this->calculator->calculateDemands($averagePlayer, $teamFactors);
        $starDemands = $this->calculator->calculateDemands($starPlayer, $teamFactors);

        // Assert
        $this->assertGreaterThan($averageDemands['year1'], $starDemands['year1']);
        $this->assertGreaterThan($averageDemands['total'], $starDemands['total']);
    }

    /**
     * @group calculation
     * @group base-demands
     */
    public function testCalculatesLowerDemandsForWorsePlayer()
    {
        // Arrange
        $averagePlayer = $this->createPlayerWithRatings(50);
        $weakPlayer = $this->createPlayerWithRatings(20);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $averageDemands = $this->calculator->calculateDemands($averagePlayer, $teamFactors);
        $weakDemands = $this->calculator->calculateDemands($weakPlayer, $teamFactors);

        // Assert
        $this->assertLessThan($averageDemands['year1'], $weakDemands['year1']);
        $this->assertLessThan($averageDemands['total'], $weakDemands['total']);
    }

    /**
     * @group calculation
     * @group modifiers
     */
    public function testModifierReducesDemandsForWinningTeam()
    {
        // Arrange
        $player = $this->createPlayerWithHighWinnerPreference();
        $this->setupMarketMaximums(100);
        
        $losingTeamFactors = [
            'wins' => 20,
            'losses' => 62,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => 0
        ];
        
        $winningTeamFactors = [
            'wins' => 62,
            'losses' => 20,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => 0
        ];

        // Act
        $losingTeamDemands = $this->calculator->calculateDemands($player, $losingTeamFactors);
        $winningTeamDemands = $this->calculator->calculateDemands($player, $winningTeamFactors);

        // Assert - Winning team should get lower demands (higher modifier = lower demands)
        $this->assertLessThan($losingTeamDemands['year1'], $winningTeamDemands['year1']);
        $this->assertGreaterThan($losingTeamDemands['modifier'], $winningTeamDemands['modifier']);
    }

    /**
     * @group calculation
     * @group modifiers
     */
    public function testModifierReducesDemandsForHighTraditionTeam()
    {
        // Arrange
        $player = $this->createPlayerWithHighTraditionPreference();
        $this->setupMarketMaximums(100);
        
        $lowTraditionFactors = [
            'wins' => 41,
            'losses' => 41,
            'tradition_wins' => 30,
            'tradition_losses' => 52,
            'money_committed_at_position' => 0
        ];
        
        $highTraditionFactors = [
            'wins' => 41,
            'losses' => 41,
            'tradition_wins' => 52,
            'tradition_losses' => 30,
            'money_committed_at_position' => 0
        ];

        // Act
        $lowTraditionDemands = $this->calculator->calculateDemands($player, $lowTraditionFactors);
        $highTraditionDemands = $this->calculator->calculateDemands($player, $highTraditionFactors);

        // Assert
        $this->assertLessThan($lowTraditionDemands['year1'], $highTraditionDemands['year1']);
    }

    /**
     * @group calculation
     * @group modifiers
     */
    public function testModifierReducesDemandsForLoyalPlayer()
    {
        // Arrange
        $mercenaryPlayer = $this->createPlayerWithRatings(50);
        $mercenaryPlayer->freeAgencyLoyalty = 1; // No loyalty
        
        $loyalPlayer = $this->createPlayerWithRatings(50);
        $loyalPlayer->freeAgencyLoyalty = 5; // Maximum loyalty
        
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $mercenaryDemands = $this->calculator->calculateDemands($mercenaryPlayer, $teamFactors);
        $loyalDemands = $this->calculator->calculateDemands($loyalPlayer, $teamFactors);

        // Assert - Loyal player should have lower demands
        $this->assertLessThan($mercenaryDemands['year1'], $loyalDemands['year1']);
    }

    /**
     * @group calculation
     * @group modifiers
     */
    public function testModifierIncreasesDemandsWhenPositionCrowded()
    {
        // Arrange
        $player = $this->createPlayerWithHighPlayingTimePreference();
        $this->setupMarketMaximums(100);
        
        $uncrowdedFactors = $this->getDefaultTeamFactors();
        $uncrowdedFactors['money_committed_at_position'] = 0; // No competition
        
        $crowdedFactors = $this->getDefaultTeamFactors();
        $crowdedFactors['money_committed_at_position'] = 5000; // Lots of competition

        // Act
        $uncrowdedDemands = $this->calculator->calculateDemands($player, $uncrowdedFactors);
        $crowdedDemands = $this->calculator->calculateDemands($player, $crowdedFactors);

        // Assert - Crowded position should result in higher demands (lower modifier)
        $this->assertGreaterThan($uncrowdedDemands['year1'], $crowdedDemands['year1']);
    }

    /**
     * @group calculation
     * @group years
     */
    public function testCalculatesCorrectNumberOfYears()
    {
        // Arrange
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Assert - Should demand 5 years (dem1-dem5 all non-zero, dem6 is 0)
        $this->assertEquals(5, $demands['years']);
    }

    /**
     * @group calculation
     * @group total
     */
    public function testTotalMatchesSumOfYears()
    {
        // Arrange
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act
        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Assert
        $expectedTotal = $demands['year1'] + $demands['year2'] + $demands['year3'] + 
                        $demands['year4'] + $demands['year5'];
        $this->assertEquals($expectedTotal, $demands['total']);
    }

    /**
     * @group calculation
     * @group edge-cases
     */
    public function testHandlesZeroMarketMaximum()
    {
        // Arrange - Some stats might have 0 max in an empty database
        $player = $this->createPlayerWithRatings(50);
        $this->mockDb->setMockData([
            ['max_value' => 0] // Will prevent division by zero
        ]);
        $teamFactors = $this->getDefaultTeamFactors();

        // Act - Should not throw division by zero error
        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Assert - Should still return valid structure
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('year1', $demands);
    }

    /**
     * Helper to create a player with uniform ratings
     */
    private function createPlayerWithRatings(int $rating): Player
    {
        $player = new Player();
        
        // Set all ratings to the same value
        $player->ratingFieldGoalAttempts = $rating;
        $player->ratingFieldGoalPercentage = $rating;
        $player->ratingFreeThrowAttempts = $rating;
        $player->ratingFreeThrowPercentage = $rating;
        $player->ratingThreePointAttempts = $rating;
        $player->ratingThreePointPercentage = $rating;
        $player->ratingOffensiveRebounds = $rating;
        $player->ratingDefensiveRebounds = $rating;
        $player->ratingAssists = $rating;
        $player->ratingSteals = $rating;
        $player->ratingTurnovers = $rating;
        $player->ratingBlocks = $rating;
        $player->ratingFouls = $rating;
        $player->ratingOutsideOffense = $rating;
        $player->ratingOutsideDefense = $rating;
        $player->ratingDriveOffense = $rating;
        $player->ratingDriveDefense = $rating;
        $player->ratingPostOffense = $rating;
        $player->ratingPostDefense = $rating;
        $player->ratingTransitionOffense = $rating;
        $player->ratingTransitionDefense = $rating;
        
        // Set default preferences
        $player->freeAgencyPlayForWinner = 3;
        $player->freeAgencyTradition = 3;
        $player->freeAgencyLoyalty = 3;
        $player->freeAgencyPlayingTime = 3;
        
        return $player;
    }

    /**
     * Helper to create a player who values winning
     */
    private function createPlayerWithHighWinnerPreference(): Player
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayForWinner = 5; // Maximum preference
        return $player;
    }

    /**
     * Helper to create a player who values tradition
     */
    private function createPlayerWithHighTraditionPreference(): Player
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyTradition = 5;
        return $player;
    }

    /**
     * Helper to create a player who values playing time
     */
    private function createPlayerWithHighPlayingTimePreference(): Player
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayingTime = 5;
        return $player;
    }

    /**
     * Helper to setup market maximums in mock database
     */
    private function setupMarketMaximums(int $maxValue): void
    {
        // Return max_value for all stat queries
        $this->mockDb->setMockData([
            ['max_value' => $maxValue]
        ]);
    }

    /**
     * Helper to get default team factors
     */
    private function getDefaultTeamFactors(): array
    {
        return [
            'wins' => 41,
            'losses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => 0
        ];
    }
}
