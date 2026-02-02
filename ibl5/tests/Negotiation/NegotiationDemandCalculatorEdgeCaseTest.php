<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Negotiation\NegotiationDemandCalculator;
use Player\Player;

/**
 * Edge case tests for NegotiationDemandCalculator
 *
 * Tests boundary conditions, unusual states, and edge cases for demand calculations.
 *
 * @covers \Negotiation\NegotiationDemandCalculator
 */
class NegotiationDemandCalculatorEdgeCaseTest extends TestCase
{
    private \MockDatabase $mockDb;
    private NegotiationDemandCalculator $calculator;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->calculator = new NegotiationDemandCalculator($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($this->calculator, $this->mockDb);
    }

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

    // ============================================
    // HELPER METHODS
    // ============================================

    private function createPlayerWithRatings(int $rating): Player
    {
        $player = new Player();

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

        $player->freeAgencyPlayForWinner = 3;
        $player->freeAgencyTradition = 3;
        $player->freeAgencyLoyalty = 3;
        $player->freeAgencyPlayingTime = 3;

        return $player;
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

    private function setupMarketMaximums(int $maxValue): void
    {
        $this->mockDb->setMockData([
            ['max_value' => $maxValue]
        ]);
    }

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
