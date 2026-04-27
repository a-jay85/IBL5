<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyDemandCalculator;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use Player\Player;

/**
 * Mock repository for testing without database dependencies.
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

    public function getPlayerDemands(int $playerID): array
    {
        return $this->playerDemands;
    }
}

/**
 * Tests for FreeAgencyDemandCalculator — the perceived value formula.
 *
 * All tests are based on the ORIGINAL pre-refactor implementation in
 * freeagentoffer.php (commit 188bd3f4c^). The formula is:
 *
 *   modifier = 1 + playForWinner + tradition + loyalty + security + playingTime
 *   random = rand(5, -5)              // integer -5 to +5
 *   modRandom = (100 + random) / 100  // 0.95 to 1.05
 *   perceivedValue = offerAverage * modifier * modRandom
 *
 * The calculator MUST return all three components: modifier, random, perceivedValue.
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

    // ================================================================
    // RETURN TYPE STRUCTURE
    // ================================================================

    public function testReturnsArrayWithModifierRandomAndPerceivedValue(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(),
            yearsInOffer: 1
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('modifier', $result);
        $this->assertArrayHasKey('random', $result);
        $this->assertArrayHasKey('perceivedValue', $result);
        $this->assertIsFloat($result['modifier']);
        $this->assertIsInt($result['random']);
        $this->assertIsFloat($result['perceivedValue']);
    }

    // ================================================================
    // FORMULA IDENTITY: perceivedValue === offerAvg * modifier * modRandom
    // ================================================================

    public function testPerceivedValueEqualsOfferAverageTimesModifierTimesModRandom(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(3);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 3, loyalty: 3),
            yearsInOffer: 3
        );

        $expected = 1000 * $result['modifier'] * ((100 + $result['random']) / 100);
        $this->assertEqualsWithDelta($expected, $result['perceivedValue'], 0.001);
    }

    public function testFormulaIdentityHoldsWithNegativeRandom(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(-5);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 800,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 5),
            yearsInOffer: 2
        );

        $expected = 800 * $result['modifier'] * ((100 + $result['random']) / 100);
        $this->assertEqualsWithDelta($expected, $result['perceivedValue'], 0.001);
    }

    // ================================================================
    // NEUTRAL BASELINE (all preferences = 1 → modifier = 1.0)
    // ================================================================

    public function testNeutralPreferencesProduceModifierOfOne(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(),
            yearsInOffer: 1
        );

        $this->assertEqualsWithDelta(1.0, $result['modifier'], 0.0001);
        $this->assertSame(0, $result['random']);
        $this->assertEqualsWithDelta(1000.0, $result['perceivedValue'], 0.01);
    }

    // ================================================================
    // PLAY-FOR-WINNER FACTOR
    // Original: 0.000153 * (teamWins - teamLosses) * (playerWinner - 1)
    // ================================================================

    public function testPlayForWinnerExactValue(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 60, 'losses' => 22,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 5),
            yearsInOffer: 1
        );

        // factor = 0.000153 * (60-22) * (5-1) = 0.000153 * 38 * 4 = 0.023256
        // modifier = 1 + 0.023256 = 1.023256
        $this->assertEqualsWithDelta(1.023256, $result['modifier'], 0.0001);
        $this->assertEqualsWithDelta(1023.256, $result['perceivedValue'], 0.1);
    }

    public function testPlayForWinnerDecreasesForLosingTeam(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 20, 'losses' => 62,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 5),
            yearsInOffer: 1
        );

        // factor = 0.000153 * (20-62) * (5-1) = 0.000153 * -42 * 4 = -0.025704
        $this->assertEqualsWithDelta(1 - 0.025704, $result['modifier'], 0.0001);
        $this->assertLessThan(1000.0, $result['perceivedValue']);
    }

    public function testPlayForWinnerNeutralWhenPreferenceIsOne(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 60, 'losses' => 22,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 1),
            yearsInOffer: 1
        );

        // (playerWinner - 1) = 0, so factor = 0
        $this->assertEqualsWithDelta(1.0, $result['modifier'], 0.0001);
    }

    // ================================================================
    // TRADITION FACTOR
    // Original: 0.000153 * (tradWins - tradLosses) * (playerTradition - 1)
    // ================================================================

    public function testTraditionExactValue(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 700, 'tradLosses' => 300,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(tradition: 5),
            yearsInOffer: 1
        );

        // factor = 0.000153 * (700-300) * (5-1) = 0.000153 * 400 * 4 = 0.2448
        $this->assertEqualsWithDelta(1.2448, $result['modifier'], 0.0001);
        $this->assertGreaterThan(1000.0, $result['perceivedValue']);
    }

    // ================================================================
    // LOYALTY FACTOR
    // Original: staying = +0.025 * (playerLoyalty - 1)
    //           leaving = -0.025 * (playerLoyalty - 1)
    // ================================================================

    public function testLoyaltyBonusForStayingWithCurrentTeam(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Current Team',
            player: $this->createPlayer(loyalty: 5, currentTeam: 'Current Team'),
            yearsInOffer: 1
        );

        // factor = +0.025 * (5-1) = +0.1
        // modifier = 1 + 0.1 = 1.1
        $this->assertEqualsWithDelta(1.1, $result['modifier'], 0.0001);
        $this->assertEqualsWithDelta(1100.0, $result['perceivedValue'], 0.1);
    }

    public function testLoyaltyPenaltyForLeavingCurrentTeam(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Different Team',
            player: $this->createPlayer(loyalty: 5, currentTeam: 'Current Team'),
            yearsInOffer: 1
        );

        // factor = -0.025 * (5-1) = -0.1
        // modifier = 1 - 0.1 = 0.9
        $this->assertEqualsWithDelta(0.9, $result['modifier'], 0.0001);
        $this->assertEqualsWithDelta(900.0, $result['perceivedValue'], 0.1);
    }

    public function testLoyaltyNeutralWhenPreferenceIsOne(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Different Team',
            player: $this->createPlayer(loyalty: 1, currentTeam: 'Current Team'),
            yearsInOffer: 1
        );

        // (playerLoyalty - 1) = 0, so factor = 0
        $this->assertEqualsWithDelta(1.0, $result['modifier'], 0.0001);
    }

    // ================================================================
    // SECURITY FACTOR
    // Original: (0.01 * (yearsInOffer - 1) - 0.025) * (playerSecurity - 1)
    // ================================================================

    public function testSecurityExactValueFor6YearOffer(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(security: 5),
            yearsInOffer: 6
        );

        // factor = (0.01 * (6-1) - 0.025) * (5-1) = (0.05 - 0.025) * 4 = 0.025 * 4 = 0.1
        // modifier = 1 + 0.1 = 1.1
        $this->assertEqualsWithDelta(1.1, $result['modifier'], 0.0001);
    }

    public function testSecurityNegativeFor1YearOffer(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(security: 5),
            yearsInOffer: 1
        );

        // factor = (0.01 * (1-1) - 0.025) * (5-1) = (0 - 0.025) * 4 = -0.1
        // modifier = 1 - 0.1 = 0.9
        $this->assertEqualsWithDelta(0.9, $result['modifier'], 0.0001);
    }

    public function testSecurityLongerContractsBeatShorterOnes(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(0);

        $short = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(security: 5),
            yearsInOffer: 1
        );

        $long = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(security: 5),
            yearsInOffer: 6
        );

        $this->assertGreaterThan($short['perceivedValue'], $long['perceivedValue']);
    }

    // ================================================================
    // PLAYING TIME FACTOR
    // Original: -(0.0025 * positionSalary / 100 - 0.025) * (playerPlayingTime - 1)
    // positionSalary capped at 2000
    // ================================================================

    public function testPlayingTimeExactValueWithLowSalary(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 500;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        // factor = -(0.0025 * 500 / 100 - 0.025) * (5-1) = -(0.0125 - 0.025) * 4 = -(-0.0125) * 4 = 0.05
        // modifier = 1 + 0.05 = 1.05
        $this->assertEqualsWithDelta(1.05, $result['modifier'], 0.0001);
    }

    public function testPlayingTimeExactValueWithHighSalary(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1500;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        // factor = -(0.0025 * 1500 / 100 - 0.025) * (5-1) = -(0.0375 - 0.025) * 4 = -0.0125 * 4 = -0.05
        // modifier = 1 - 0.05 = 0.95
        $this->assertEqualsWithDelta(0.95, $result['modifier'], 0.0001);
    }

    public function testPlayingTimeSalaryCappedAt2000(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 5000; // Exceeds 2000 cap
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        // Capped at 2000: factor = -(0.0025 * 2000 / 100 - 0.025) * (5-1) = -(0.05 - 0.025) * 4 = -0.1
        // modifier = 1 - 0.1 = 0.9
        $this->assertEqualsWithDelta(0.9, $result['modifier'], 0.0001);
    }

    public function testLessMoneyAtPositionMeansHigherValue(): void
    {
        $this->calculator->setRandomFactor(0);

        // Low salary at position
        $lowRepo = new MockDemandRepository();
        $lowRepo->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $lowRepo->positionSalaryCommitment = 500;
        $calcLow = new FreeAgencyDemandCalculator($lowRepo);
        $calcLow->setRandomFactor(0);
        $lowResult = $calcLow->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        // High salary at position
        $highRepo = new MockDemandRepository();
        $highRepo->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $highRepo->positionSalaryCommitment = 1500;
        $calcHigh = new FreeAgencyDemandCalculator($highRepo);
        $calcHigh->setRandomFactor(0);
        $highResult = $calcHigh->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        $this->assertGreaterThan(
            $highResult['perceivedValue'],
            $lowResult['perceivedValue']
        );
    }

    // ================================================================
    // COMBINED FACTORS
    // ================================================================

    public function testCombinedFactorsAreAdditive(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 60, 'losses' => 22,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Current Team',
            player: $this->createPlayer(
                playForWinner: 5,
                loyalty: 5,
                currentTeam: 'Current Team'
            ),
            yearsInOffer: 1
        );

        // playForWinner = 0.000153 * 38 * 4 = 0.023256
        // loyalty staying = +0.025 * 4 = +0.1
        // security 1yr = (0 - 0.025) * 0 = 0  (security preference = 1)
        // playingTime = -(0.0025 * 1000 / 100 - 0.025) * 0 = 0  (playingTime preference = 1)
        // modifier = 1 + 0.023256 + 0.1 = 1.123256
        $this->assertEqualsWithDelta(1.123256, $result['modifier'], 0.001);
    }

    // ================================================================
    // RANDOM VARIANCE
    // ================================================================

    public function testRandomFactorStoredInResult(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(3);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(),
            yearsInOffer: 1
        );

        $this->assertSame(3, $result['random']);
    }

    public function testNegativeRandomFactorStoredInResult(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(-5);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(),
            yearsInOffer: 1
        );

        $this->assertSame(-5, $result['random']);
        // With modifier = 1.0 and random = -5: perceivedValue = 1000 * 1.0 * 0.95 = 950
        $this->assertEqualsWithDelta(950.0, $result['perceivedValue'], 0.01);
    }

    public function testRandomVarianceProducesRange(): void
    {
        $this->setupNeutralTeam();
        // Do NOT set random factor — let actual randomness run

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->calculator->calculatePerceivedValue(
                offerAverage: 1000,
                teamName: 'Test Team',
                player: $this->createPlayer(),
                yearsInOffer: 1
            );
        }

        $values = array_map(static fn (array $r): float => $r['perceivedValue'], $results);
        $randoms = array_map(static fn (array $r): int => $r['random'], $results);

        // Should produce variance
        $this->assertGreaterThan(1, count(array_unique($values)));

        // All randoms must be -5 to +5
        foreach ($randoms as $r) {
            $this->assertGreaterThanOrEqual(-5, $r);
            $this->assertLessThanOrEqual(5, $r);
        }

        // All perceived values within ±5% of base
        foreach ($values as $v) {
            $this->assertGreaterThanOrEqual(950.0, $v);
            $this->assertLessThanOrEqual(1050.0, $v);
        }
    }

    public function testSetRandomFactorNullReEnablesRandomness(): void
    {
        $this->setupNeutralTeam();
        $this->calculator->setRandomFactor(3);
        $this->calculator->setRandomFactor(null);

        // Should produce varied results now
        $results = [];
        for ($i = 0; $i < 50; $i++) {
            $r = $this->calculator->calculatePerceivedValue(
                offerAverage: 1000,
                teamName: 'Test Team',
                player: $this->createPlayer(),
                yearsInOffer: 1
            );
            $results[] = $r['random'];
        }

        // With actual randomness, we should see more than one unique value in 50 tries
        $this->assertGreaterThan(1, count(array_unique($results)));
    }

    // ================================================================
    // EDGE CASES
    // ================================================================

    public function testZeroWinsAndLossesDoesNotCauseDivisionByZero(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 0, 'losses' => 0,
            'tradWins' => 0, 'tradLosses' => 0,
        ];
        $this->mockRepository->positionSalaryCommitment = 0;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Expansion Team',
            player: $this->createPlayer(),
            yearsInOffer: 1
        );

        $this->assertIsArray($result);
        $this->assertIsFloat($result['perceivedValue']);
        $this->assertGreaterThan(0.0, $result['perceivedValue']);
    }

    // ================================================================
    // CHARACTERIZATION TESTS — pin current formula before unification
    // ================================================================

    /**
     * Playing time modifier at mc=500, pref=5, all others neutral.
     * formula: -(0.0025*500/100 - 0.025) * (5-1) = -(0.0125 - 0.025)*4 = 0.05
     * modifier = 1 + 0.05 = 1.05
     */
    public function testPlayingTimeModifierAtMc500Pref5(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 500;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        $this->assertEqualsWithDelta(1.05, $result['modifier'], 0.000001);
    }

    /**
     * Playing time modifier at mc=1500, pref=5, all others neutral.
     * formula: -(0.0025*1500/100 - 0.025) * (5-1) = -(0.0375 - 0.025)*4 = -0.05
     * modifier = 1 - 0.05 = 0.95
     */
    public function testPlayingTimeModifierAtMc1500Pref5(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1500;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playingTime: 5),
            yearsInOffer: 1
        );

        $this->assertEqualsWithDelta(0.95, $result['modifier'], 0.000001);
    }

    /**
     * Winner modifier with raw differential: 60W/22L, pref=5, all others neutral.
     * formula: 0.000153 * (60-22) * (5-1) = 0.000153 * 38 * 4 = 0.023256
     * modifier = 1 + 0.023256 = 1.023256
     */
    public function testWinnerModifierExactValueWithRawDifferential(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 60, 'losses' => 22,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
        $this->calculator->setRandomFactor(0);

        $result = $this->calculator->calculatePerceivedValue(
            offerAverage: 1000,
            teamName: 'Test Team',
            player: $this->createPlayer(playForWinner: 5),
            yearsInOffer: 1
        );

        $this->assertEqualsWithDelta(1.023256, $result['modifier'], 0.000001);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function setupNeutralTeam(): void
    {
        $this->mockRepository->teamPerformance = [
            'wins' => 41, 'losses' => 41,
            'tradWins' => 500, 'tradLosses' => 500,
        ];
        $this->mockRepository->positionSalaryCommitment = 1000;
    }

    private function createPlayer(
        int $playForWinner = 1,
        int $tradition = 1,
        int $loyalty = 1,
        int $security = 1,
        int $playingTime = 1,
        string $position = 'PG',
        int $playerID = 1,
        string $currentTeam = 'Test Team'
    ): Player {
        $player = $this->createStub(Player::class);

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
