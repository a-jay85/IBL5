<?php

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyDemandCalculator;
use FreeAgency\FreeAgencyDemandRepositoryInterface;
use Player\Player;

/**
 * Mock repository class for testing without database dependencies
 */
class MockDemandRepository implements FreeAgencyDemandRepositoryInterface
{
    public array $teamPerformance = [];
    public int $positionSalaryCommitment = 0;
    public array $playerDemands = [];

    public function getTeamPerformance(string $teamName): array
    {
        return $this->teamPerformance;
    }

    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int
    {
        return $this->positionSalaryCommitment;
    }

    public function getPlayerDemands(string $playerName): array
    {
        return $this->playerDemands;
    }
}

/**
 * Comprehensive tests for FreeAgencyDemandCalculator
 * 
 * Tests the calculation of perceived contract value based on:
 * - Team performance (wins/losses)
 * - Team tradition
 * - Player preferences (loyalty, security, playing time, winner, tradition) - Scale 1-5
 * - Position salary commitment
 * - Random variance
 */
class FreeAgencyDemandCalculatorTest extends TestCase
{
    private MockDemandRepository $mockRepository;
    private FreeAgencyDemandCalculator $calculator;

    protected function setUp(): void
    {
        $this->mockRepository = new MockDemandRepository();
        $this->calculator = new FreeAgencyDemandCalculator($this->mockRepository);
    }

    /**
     * @group demand-calculator
     * @group modifier
     */
    public function testCalculatePerceivedValueWithNeutralModifiers(): void
    {
        // Arrange - Player with neutral preferences (all 1s mean no preference impact)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 1,
            loyalty: 1,
            security: 1,
            playingTime: 1
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - With neutral preferences and no random variance, result should equal base offer
        $this->assertEquals(1000.0, $result);
    }

    /**
     * @group demand-calculator
     * @group play-for-winner
     */
    public function testPlayForWinnerFactorIncreasesValueForWinningTeam(): void
    {
        // Arrange - Player who highly values winning (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 5,
            tradition: 1,
            loyalty: 1,
            security: 1,
            playingTime: 1
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 60,  // Winning team
            'losses' => 22,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Winning Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Winning team should increase perceived value
        // With differential of +38 wins and max preference (5-1=4),
        // factor = 0.000153 * 38 * 4 = 0.023256
        // So value should be ~2.3% higher than base
        $this->assertGreaterThan(1000, $result);
    }

    /**
     * @group demand-calculator
     * @group play-for-winner
     */
    public function testPlayForWinnerFactorDecreasesValueForLosingTeam(): void
    {
        // Arrange - Player who highly values winning (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 5,
            tradition: 1,
            loyalty: 1,
            security: 1,
            playingTime: 1
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 20,  // Losing team
            'losses' => 62,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Losing Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Losing team should decrease perceived value
        $this->assertLessThan(1000, $result);
    }

    /**
     * @group demand-calculator
     * @group tradition
     */
    public function testTraditionFactorIncreasesValueForHistoricallySuccessfulTeam(): void
    {
        // Arrange - Player who values tradition (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 5,
            loyalty: 1,
            security: 1,
            playingTime: 1
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 700,  // Historically successful
            'tradLosses' => 300,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Historic Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - High tradition should increase perceived value
        $this->assertGreaterThan(1000, $result);
    }

    /**
     * @group demand-calculator
     * @group loyalty
     */
    public function testLoyaltyBonusForStayingWithCurrentTeam(): void
    {
        // Arrange - Loyal player staying with current team (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 1,
            loyalty: 5,  // Very loyal
            security: 1,
            playingTime: 1,
            currentTeam: 'Current Team'
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Current Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Loyalty bonus should increase perceived value
        // Bonus = 0.025 * (5-1) = 0.1 = 10% increase
        $this->assertGreaterThan(1000, $result);
    }

    /**
     * @group demand-calculator
     * @group loyalty
     */
    public function testLoyaltyPenaltyForLeavingCurrentTeam(): void
    {
        // Arrange - Loyal player considering different team (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 1,
            loyalty: 5,  // Very loyal
            security: 1,
            playingTime: 1,
            currentTeam: 'Current Team'
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Different Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Loyalty penalty should decrease perceived value
        $this->assertLessThan(1000, $result);
    }

    /**
     * @group demand-calculator
     * @group security
     */
    public function testSecurityFactorIncreasesValueForLongerContracts(): void
    {
        // Arrange - Player who values security (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 1,
            loyalty: 1,
            security: 5,  // Values security
            playingTime: 1
        );

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $resultLongContract = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $player,
            yearsInOffer: 6
        );

        $resultShortContract = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Longer contract should have higher perceived value
        $this->assertGreaterThan($resultShortContract, $resultLongContract);
    }

    /**
     * @group demand-calculator
     * @group playing-time
     */
    public function testPlayingTimeFactorIncreasesValueWhenLessSalaryCommitted(): void
    {
        // Arrange - Player who values playing time (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 1,
            tradition: 1,
            loyalty: 1,
            security: 1,
            playingTime: 5  // Values playing time
        );

        // Create separate calculators with different mock repositories
        $mockRepositoryLowSalary = new MockDemandRepository();
        $mockRepositoryLowSalary->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];
        $mockRepositoryLowSalary->positionSalaryCommitment = 500;
        
        $calculatorLowSalary = new FreeAgencyDemandCalculator($mockRepositoryLowSalary);

        $mockRepositoryHighSalary = new MockDemandRepository();
        $mockRepositoryHighSalary->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];
        $mockRepositoryHighSalary->positionSalaryCommitment = 1500;
        
        $calculatorHighSalary = new FreeAgencyDemandCalculator($mockRepositoryHighSalary);

        // Act - Set random factor to 0 for deterministic testing
        $calculatorLowSalary->setRandomFactor(0);
        $calculatorHighSalary->setRandomFactor(0);
        
        $lowSalaryResult = $calculatorLowSalary->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Low Salary Team',
            player: $player,
            yearsInOffer: 1
        );
        
        $highSalaryResult = $calculatorHighSalary->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'High Salary Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Less money committed means more playing time opportunity
        $this->assertGreaterThan($highSalaryResult, $lowSalaryResult);
    }

    /**
     * @group demand-calculator
     * @group position-salary
     */
    public function testPositionSalaryCappedAtMaximum(): void
    {
        // Arrange
        $player = $this->createPlayerWithPreferences();

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        // Repository returns value over cap (2000)
        $this->mockRepository->positionSalaryCommitment = 5000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Should still calculate (cap is applied internally)
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * @group demand-calculator
     * @group random-variance
     */
    public function testRandomVarianceAffectsPerceivedValue(): void
    {
        // Arrange
        $player = $this->createPlayerWithPreferences();

        $this->mockRepository->teamPerformance = [
            'wins' => 41,
            'losses' => 41,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Enable actual randomness by not setting random factor
        // This test explicitly does NOT call setRandomFactor(0)
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->calculator->calculatePerceivedValue(
                offerAverage: 1000,
                teamName: 'Test Team',
                player: $player,
                yearsInOffer: 1
            );
        }

        // Assert - Results should vary due to randomness
        $uniqueResults = array_unique($results);
        $this->assertGreaterThan(1, count($uniqueResults), 'Random variance should produce different results');

        // All results should be within ±5% range
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(950, $result);
            $this->assertLessThanOrEqual(1050, $result);
        }
    }

    /**
     * @group demand-calculator
     * @group player-demands
     */
    public function testGetPlayerDemandsReturnsRepositoryData(): void
    {
        // Arrange
        $expectedDemands = [
            'dem1' => 1000,
            'dem2' => 1050,
            'dem3' => 1100,
            'dem4' => 1150,
            'dem5' => 1200,
            'dem6' => 1250,
        ];

        $this->mockRepository->playerDemands = $expectedDemands;

        // Act
        $result = $this->calculator->getPlayerDemands('Test Player');

        // Assert
        $this->assertEquals($expectedDemands, $result);
    }

    /**
     * @group demand-calculator
     * @group combined-factors
     */
    public function testCombinedFactorsMultiplyCorrectly(): void
    {
        // Arrange - Player with multiple strong preferences (5/5)
        $player = $this->createPlayerWithPreferences(
            playForWinner: 5,  // Values winning
            tradition: 1,
            loyalty: 5,  // Very loyal
            security: 1,
            playingTime: 1,
            currentTeam: 'Current Team'
        );

        // Winning team with current team loyalty bonus
        $this->mockRepository->teamPerformance = [
            'wins' => 60,
            'losses' => 22,
            'tradWins' => 500,
            'tradLosses' => 500,
        ];

        $this->mockRepository->positionSalaryCommitment = 1000;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Current Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Multiple positive factors should compound
        // Play-for-winner: ~2.3% + Loyalty: ~10% = ~12%+ increase
        $this->assertGreaterThan(1100, $result); // At least 10% increase
    }

    /**
     * @group demand-calculator
     * @group edge-cases
     */
    public function testZeroWinsAndLossesDoesNotCauseDivisionByZero(): void
    {
        // Arrange - Expansion team with no history
        $player = $this->createPlayerWithPreferences();

        $this->mockRepository->teamPerformance = [
            'wins' => 0,
            'losses' => 0,
            'tradWins' => 0,
            'tradLosses' => 0,
        ];

        $this->mockRepository->positionSalaryCommitment = 0;

        // Act - Set random factor to 0 for deterministic testing
        $this->calculator->setRandomFactor(0);
        
        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Expansion Team',
            player: $player,
            yearsInOffer: 1
        );

        // Assert - Should calculate without errors
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    // Helper Methods

    /**
     * Create a mock Player with specified free agency preferences
     * All preferences are on a 1-5 scale
     */
    private function createPlayerWithPreferences(
        int $playForWinner = 1,
        int $tradition = 1,
        int $loyalty = 1,
        int $security = 1,
        int $playingTime = 1,
        string $position = 'PG',
        int $playerID = 1,
        string $currentTeam = 'Test Team'
    ): Player {
        $player = $this->createMock(Player::class);
        
        $player->freeAgencyPlayForWinner = $playForWinner;
        $player->freeAgencyTradition = $tradition;
        $player->freeAgencyLoyalty = $loyalty;
        $player->freeAgencySecurity = $security;
        $player->freeAgencyPlayingTime = $playingTime;
        $player->position = $position;
        $player->playerID = $playerID;
        $player->teamName = $currentTeam;

        return $player;
    }
}
