<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->mockDb = new \MockDatabase();
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
    /**
     * Pin exact demand values for known inputs to catch arithmetic mutations.
     *
     * With 21 player ratings all=50, market maxes all=100 (21 matching keys):
     * rawScore = 21 × 50 = 1050; adjusted = 1050 - 700 = 350
     * avgDemands = 350 × 3 = 1050; totalDemands = 1050 × 5 = 5250
     * baseDemands = 5250 / 6 = 875; maxRaise = floor(875 × 0.10) = 87
     * modifier = 1.10 (loyalty 0.05 + playingTime 0.05 from preferences=3)
     */
    public function testCalculateDemandsReturnsExactValuesForAveragePlayer(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Assert exact modifier
        $this->assertEqualsWithDelta(1.10, $demands['modifier'], 0.001);

        // Assert exact year demands (after modifier division + rounding)
        $this->assertEquals(795, $demands['year1']);
        $this->assertEquals(875, $demands['year2']);
        $this->assertEquals(954, $demands['year3']);
        $this->assertEquals(1033, $demands['year4']);
        $this->assertEquals(1112, $demands['year5']);
        $this->assertEquals(0, $demands['year6']);

        // Assert total and years
        $this->assertEquals(4769, $demands['total']);
        $this->assertSame(5, $demands['years']);
    }

    /**
     * Verify raise progression is exactly 10% of base demands.
     */
    public function testRaiseProgressionMatchesStandardPercentage(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // year2 - year1 should equal year3 - year2 (linear raise, not compound)
        $raise = $demands['year2'] - $demands['year1'];
        $this->assertEqualsWithDelta($demands['year3'] - $demands['year2'], $raise, 1);
        $this->assertEqualsWithDelta($demands['year4'] - $demands['year3'], $raise, 1);
        $this->assertEqualsWithDelta($demands['year5'] - $demands['year4'], $raise, 1);
    }

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
        // Return all MAX column aliases used by the single bulk query in getMarketMaximums()
        $this->mockDb->setMockData([
            [
                'fga' => $maxValue, 'fgp' => $maxValue, 'fta' => $maxValue, 'ftp' => $maxValue,
                'tga' => $maxValue, 'tgp' => $maxValue, 'orb' => $maxValue, 'drb' => $maxValue,
                'ast' => $maxValue, 'stl' => $maxValue, 'r_tvr' => $maxValue, 'blk' => $maxValue,
                'foul' => $maxValue, 'oo' => $maxValue, 'od' => $maxValue, 'r_drive_off' => $maxValue,
                'dd' => $maxValue, 'po' => $maxValue, 'pd' => $maxValue, 'r_trans_off' => $maxValue,
                'td' => $maxValue,
            ]
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

    // --- Merged from NegotiationDemandCalculatorEdgeCaseTest ---

    // ============================================
    // ZERO/NEAR-ZERO MODIFIER EDGE CASES
    // ============================================

    public function testHandlesNearZeroModifierScenario(): void
    {
        // Extreme case where all factors push modifier very low
        $player = $this->createPlayerWithMinimalPreferences();
        $this->setupMarketMaximums(100);

        // Team factors that would create very low modifier
        $teamFactors = [
            'wins' => 0,
            'losses' => 82, // Very losing team
            'tradition_wins' => 0,
            'tradition_losses' => 82,
            'money_committed_at_position' => 10000 // Very crowded
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should not throw errors and return valid structure
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('year1', $demands);
        $this->assertGreaterThanOrEqual(0, $demands['year1']);
    }

    public function testHandlesExtremeLoyaltyWithWinningTeam(): void
    {
        // Maximum loyalty + winning team could create very high modifier
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyLoyalty = 5; // Maximum
        $player->freeAgencyPlayForWinner = 5;
        $player->freeAgencyTradition = 5;
        $player->freeAgencyPlayingTime = 1; // Doesn't care about PT

        $this->setupMarketMaximums(100);

        $teamFactors = [
            'wins' => 82,
            'losses' => 0,
            'tradition_wins' => 82,
            'tradition_losses' => 0,
            'money_committed_at_position' => 0
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Higher modifier means lower demands
        $this->assertIsArray($demands);
        $this->assertGreaterThan(1.0, $demands['modifier']);
    }

    // ============================================
    // ALL ZERO PLAYER RATINGS EDGE CASES
    // ============================================

    public function testHandlesAllZeroPlayerRatings(): void
    {
        $player = $this->createPlayerWithRatings(0);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should return valid structure even with 0 ratings
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('year1', $demands);
        // Demands might be negative or low due to baseline subtraction
    }

    public function testHandlesAllMaxPlayerRatings(): void
    {
        $player = $this->createPlayerWithRatings(100);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Maximum ratings should result in very high demands
        $this->assertIsArray($demands);
        $this->assertGreaterThan(0, $demands['year1']);
    }

    public function testHandlesPlayerRatingsAboveMarketMaximum(): void
    {
        // Edge case: player rating exceeds market max (shouldn't happen, but test it)
        $player = $this->createPlayerWithRatings(150);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should still calculate (player at 150% of market)
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('year1', $demands);
    }

    // ============================================
    // MARKET MAXIMUM EDGE CASES
    // ============================================

    public function testHandlesAllZeroMarketMaximums(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(0); // All zeros
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should handle gracefully (division by zero protected)
        $this->assertIsArray($demands);
    }

    public function testHandlesVeryLargeMarketMaximums(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(1000000);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Very large market max makes player seem relatively weak
        $this->assertIsArray($demands);
    }

    // ============================================
    // TEAM FACTORS EDGE CASES
    // ============================================

    public function testHandlesZeroTotalGames(): void
    {
        // Use minimal preferences so modifier is 1.0 when factors are 0
        $player = $this->createPlayerWithMinimalPreferences();
        $this->setupMarketMaximums(100);

        // Zero games - edge case
        $teamFactors = [
            'wins' => 0,
            'losses' => 0,
            'tradition_wins' => 0,
            'tradition_losses' => 0,
            'money_committed_at_position' => 0
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should handle division by zero gracefully
        // With minimal preferences (all 1), factors are (pref-1) * coeff = 0
        $this->assertIsArray($demands);
        $this->assertEquals(1.0, $demands['modifier']);
    }

    public function testHandlesMissingTeamFactors(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);

        // Missing keys - should use defaults
        $teamFactors = [];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should use default values
        $this->assertIsArray($demands);
    }

    public function testHandlesNegativeMoneyCommitted(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayingTime = 5; // High PT preference
        $this->setupMarketMaximums(100);

        // Technically shouldn't be negative, but test robustness
        $teamFactors = [
            'wins' => 41,
            'losses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => -1000
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        $this->assertIsArray($demands);
    }

    public function testHandlesVeryLargeMoneyCommitted(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayingTime = 5;
        $this->setupMarketMaximums(100);

        $teamFactors = [
            'wins' => 41,
            'losses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => 100000 // Very crowded position
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Very crowded should result in higher demands
        $this->assertIsArray($demands);
    }

    // ============================================
    // PLAYER PREFERENCES EDGE CASES
    // ============================================

    public function testHandlesNullPlayerPreferences(): void
    {
        $player = $this->createPlayerWithRatings(50);
        // Leave preferences as null (default)
        $player->freeAgencyPlayForWinner = null;
        $player->freeAgencyTradition = null;
        $player->freeAgencyLoyalty = null;
        $player->freeAgencyPlayingTime = null;

        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Should use default preference of 1
        $this->assertIsArray($demands);
    }

    public function testHandlesAllMaximumPreferences(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayForWinner = 5;
        $player->freeAgencyTradition = 5;
        $player->freeAgencyLoyalty = 5;
        $player->freeAgencyPlayingTime = 5;

        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        $this->assertIsArray($demands);
    }

    public function testHandlesAllMinimumPreferences(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayForWinner = 1;
        $player->freeAgencyTradition = 1;
        $player->freeAgencyLoyalty = 1;
        $player->freeAgencyPlayingTime = 1;

        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // With all preferences at 1, modifier should be exactly 1.0
        $this->assertEquals(1.0, $demands['modifier']);
    }

    // ============================================
    // YEARS DEMANDED EDGE CASES
    // ============================================

    public function testYearsAlwaysReturnsAtLeastOne(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        $this->assertGreaterThanOrEqual(1, $demands['years']);
    }

    public function testYear6AlwaysZeroForExtensions(): void
    {
        $player = $this->createPlayerWithRatings(100);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Extensions are max 5 years
        $this->assertEquals(0, $demands['year6']);
    }

    // ============================================
    // RAISE PROGRESSION EDGE CASES
    // ============================================

    public function testDemandsIncreaseByStandardRaisePercentage(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        // Each year should increase (10% standard raise applied to base)
        if ($demands['year1'] > 0) {
            $this->assertGreaterThan($demands['year1'], $demands['year2']);
            $this->assertGreaterThan($demands['year2'], $demands['year3']);
            $this->assertGreaterThan($demands['year3'], $demands['year4']);
            $this->assertGreaterThan($demands['year4'], $demands['year5']);
        }
    }

    // ============================================
    // TOTAL CALCULATION EDGE CASES
    // ============================================

    public function testTotalSumsOnlyYearsOneToFive(): void
    {
        $player = $this->createPlayerWithRatings(50);
        $this->setupMarketMaximums(100);
        $teamFactors = $this->getDefaultTeamFactors();

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        $expectedTotal = $demands['year1'] + $demands['year2'] + $demands['year3']
                       + $demands['year4'] + $demands['year5'];

        $this->assertEquals($expectedTotal, $demands['total']);
        // Year6 is not included in total
        $this->assertEquals(0, $demands['year6']);
    }

    // ============================================
    // WIN/LOSS DIFFERENTIAL EDGE CASES
    // ============================================

    #[DataProvider('winLossDifferentialProvider')]
    public function testHandlesVariousWinLossDifferentials(int $wins, int $losses): void
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayForWinner = 5;
        $this->setupMarketMaximums(100);

        $teamFactors = [
            'wins' => $wins,
            'losses' => $losses,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
            'money_committed_at_position' => 0
        ];

        $demands = $this->calculator->calculateDemands($player, $teamFactors);

        $this->assertIsArray($demands);
        $this->assertArrayHasKey('modifier', $demands);
    }

    public static function winLossDifferentialProvider(): array
    {
        return [
            'perfect season' => [82, 0],
            'worst season' => [0, 82],
            'exactly .500' => [41, 41],
            'one game over' => [42, 41],
            'one game under' => [41, 42],
            'few games' => [5, 3],
            'many games' => [100, 50],
        ];
    }

    private function createPlayerWithMinimalPreferences(): Player
    {
        $player = $this->createPlayerWithRatings(50);
        $player->freeAgencyPlayForWinner = 1;
        $player->freeAgencyTradition = 1;
        $player->freeAgencyLoyalty = 1;
        $player->freeAgencyPlayingTime = 1;
        return $player;
    }
}
