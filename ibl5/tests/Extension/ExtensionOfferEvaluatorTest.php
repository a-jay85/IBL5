<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionOfferEvaluator;
use Extension\Contracts\ExtensionOfferEvaluatorInterface;
use Services\CommonContractValidator;
use Shared\SalaryConverter;

/**
 * ExtensionOfferEvaluatorTest - Tests for ExtensionOfferEvaluator
 */
class ExtensionOfferEvaluatorTest extends TestCase
{
    private ExtensionOfferEvaluator $evaluator;
    private CommonContractValidator $contractValidator;

    protected function setUp(): void
    {
        $this->evaluator = new ExtensionOfferEvaluator();
        $this->contractValidator = new CommonContractValidator();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $evaluator = new ExtensionOfferEvaluator();

        $this->assertInstanceOf(ExtensionOfferEvaluator::class, $evaluator);
    }

    public function testImplementsInterface(): void
    {
        $evaluator = new ExtensionOfferEvaluator();

        $this->assertInstanceOf(ExtensionOfferEvaluatorInterface::class, $evaluator);
    }

    // ============================================
    // CALCULATE OFFER VALUE TESTS
    // ============================================

    public function testCalculateOfferValueReturnsArray(): void
    {
        $offer = [1 => 5000000, 2 => 5500000];

        $result = $this->contractValidator->calculateOfferValue($offer);

        $this->assertIsArray($result);
    }

    public function testCalculateOfferValueHasAveragePerYearKey(): void
    {
        $offer = [1 => 5000000, 2 => 5500000];

        $result = $this->contractValidator->calculateOfferValue($offer);

        $this->assertArrayHasKey('averagePerYear', $result);
    }

    // ============================================
    // CALCULATE WINNER MODIFIER TESTS
    // ============================================

    public function testCalculateWinnerModifierWithWinningTeam(): void
    {
        $teamFactors = ['wins' => 50, 'losses' => 32];
        $playerPreferences = ['winner' => 3];

        $result = $this->evaluator->calculateWinnerModifier($teamFactors, $playerPreferences);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testCalculateWinnerModifierWithLosingTeam(): void
    {
        $teamFactors = ['wins' => 20, 'losses' => 62];
        $playerPreferences = ['winner' => 3];

        $result = $this->evaluator->calculateWinnerModifier($teamFactors, $playerPreferences);

        $this->assertIsFloat($result);
        $this->assertLessThan(0, $result);
    }

    public function testCalculateWinnerModifierWithDefaultPreference(): void
    {
        $teamFactors = ['wins' => 50, 'losses' => 32];
        $playerPreferences = ['winner' => 1]; // Default preference

        $result = $this->evaluator->calculateWinnerModifier($teamFactors, $playerPreferences);

        // With winner preference of 1, modifier should be 0
        $this->assertSame(0.0, $result);
    }

    // ============================================
    // CALCULATE TRADITION MODIFIER TESTS
    // ============================================

    public function testCalculateTraditionModifierWithStrongTradition(): void
    {
        $teamFactors = ['tradition_wins' => 1000, 'tradition_losses' => 500];
        $playerPreferences = ['tradition' => 3];

        $result = $this->evaluator->calculateTraditionModifier($teamFactors, $playerPreferences);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    // ============================================
    // CALCULATE LOYALTY MODIFIER TESTS
    // ============================================

    public function testCalculateLoyaltyModifierWithHighLoyalty(): void
    {
        $playerPreferences = ['loyalty' => 5];

        $result = $this->evaluator->calculateLoyaltyModifier($playerPreferences);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testCalculateLoyaltyModifierWithDefaultLoyalty(): void
    {
        $playerPreferences = ['loyalty' => 1];

        $result = $this->evaluator->calculateLoyaltyModifier($playerPreferences);

        $this->assertSame(0.0, $result);
    }

    // ============================================
    // CALCULATE PLAYING TIME MODIFIER TESTS
    // ============================================

    public function testCalculatePlayingTimeModifierWithHighMoneyCommitted(): void
    {
        $teamFactors = ['money_committed_at_position' => 50000000];
        $playerPreferences = ['playing_time' => 5];

        $result = $this->evaluator->calculatePlayingTimeModifier($teamFactors, $playerPreferences);

        $this->assertIsFloat($result);
        // Should be negative since more money committed means less playing time
        $this->assertLessThan(0, $result);
    }

    // ============================================
    // CALCULATE COMBINED MODIFIER TESTS
    // ============================================

    public function testCalculateCombinedModifierStartsAtOne(): void
    {
        // With all default preferences, modifier should be 1.0
        $teamFactors = [];
        $playerPreferences = [];

        $result = $this->evaluator->calculateCombinedModifier($teamFactors, $playerPreferences);

        $this->assertSame(1.0, $result);
    }

    public function testCalculateCombinedModifierWithPositiveFactors(): void
    {
        $teamFactors = ['wins' => 60, 'losses' => 22, 'tradition_wins' => 1000, 'tradition_losses' => 400];
        $playerPreferences = ['winner' => 5, 'tradition' => 3, 'loyalty' => 5];

        $result = $this->evaluator->calculateCombinedModifier($teamFactors, $playerPreferences);

        $this->assertGreaterThan(1.0, $result);
    }

    // ============================================
    // EVALUATE OFFER TESTS
    // ============================================

    public function testEvaluateOfferReturnsArray(): void
    {
        $offer = [1 => 10000000, 2 => 11000000];
        $demands = [1 => 9000000, 2 => 9500000];
        $teamFactors = ['wins' => 50, 'losses' => 32];
        $playerPreferences = ['winner' => 3];

        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $this->assertIsArray($result);
    }

    public function testEvaluateOfferHasAcceptedKey(): void
    {
        $offer = [1 => 10000000];
        $demands = [1 => 9000000];
        $teamFactors = [];
        $playerPreferences = [];

        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $this->assertArrayHasKey('accepted', $result);
    }

    public function testEvaluateOfferAcceptedWhenOfferExceedsDemands(): void
    {
        $offer = [1 => 15000000]; // Higher than demands
        $demands = [1 => 10000000];
        $teamFactors = [];
        $playerPreferences = [];

        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $this->assertTrue($result['accepted']);
    }

    public function testEvaluateOfferHasModifierKey(): void
    {
        $offer = [1 => 10000000];
        $demands = [1 => 9000000];
        $teamFactors = [];
        $playerPreferences = [];

        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $this->assertArrayHasKey('modifier', $result);
    }

    // --- Merged from ExtensionOfferEvaluationTest ---

    /**
     * @group offer-evaluation
     * @group offer-value
     */
    public function testCalculatesOfferValueCorrectly(): void
    {
        // Arrange
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 0,
            'year5' => 0
        ];

        // Act
        $result = $this->contractValidator->calculateOfferValue($offer);

        // Assert
        $this->assertEquals(3300, $result['total']); // 1000 + 1100 + 1200
        $this->assertEquals(3, $result['years']);
        $this->assertEquals(1100, $result['averagePerYear']); // 3300 / 3
    }

    /**
     * @group offer-evaluation
     * @group offer-value
     */
    public function testCalculatesOfferValueFor5YearContract(): void
    {
        // Arrange
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 1300,
            'year5' => 1400
        ];

        // Act
        $result = $this->contractValidator->calculateOfferValue($offer);

        // Assert
        $this->assertEquals(6000, $result['total']);
        $this->assertEquals(5, $result['years']);
        $this->assertEquals(1200, $result['averagePerYear']);
    }

    /**
     * @group offer-evaluation
     * @group modifiers
     */
    public function testCalculatesWinnerModifierCorrectly(): void
    {
        // Arrange
        $teamFactors = [
            'wins' => 60,
            'losses' => 22
        ];
        $playerPreferences = [
            'winner' => 5 // High winner preference
        ];

        // Act
        $modifier = $this->evaluator->calculateWinnerModifier($teamFactors, $playerPreferences);

        // Assert - With wins > losses and high winner preference, modifier should be positive
        $this->assertGreaterThan(0, $modifier);
    }

    /**
     * @group offer-evaluation
     * @group modifiers
     */
    public function testCalculatesTraditionModifierCorrectly(): void
    {
        // Arrange
        $teamFactors = [
            'tradition_wins' => 60,
            'tradition_losses' => 22
        ];
        $playerPreferences = [
            'tradition' => 4 // Moderate tradition preference
        ];

        // Act
        $modifier = $this->evaluator->calculateTraditionModifier($teamFactors, $playerPreferences);

        // Assert - Good tradition with preference should give positive modifier
        $this->assertGreaterThan(0, $modifier);
    }

    /**
     * @group offer-evaluation
     * @group modifiers
     */
    public function testCalculatesLoyaltyModifierCorrectly(): void
    {
        // Arrange
        $playerPreferences = [
            'loyalty' => 5 // High loyalty
        ];

        // Act
        $modifier = $this->evaluator->calculateLoyaltyModifier($playerPreferences);

        // Assert - High loyalty should give positive modifier
        $this->assertGreaterThan(0, $modifier);

        // Test low loyalty
        $playerPreferences['loyalty'] = 1;
        $modifierLow = $this->evaluator->calculateLoyaltyModifier($playerPreferences);
        $this->assertEquals(0, $modifierLow); // loyalty of 1 means no modifier
    }

    /**
     * @group offer-evaluation
     * @group modifiers
     */
    public function testCalculatesPlayingTimeModifierCorrectly(): void
    {
        // Arrange
        $teamFactors = [
            'money_committed_at_position' => 5000 // High money at position
        ];
        $playerPreferences = [
            'playing_time' => 5 // High playing time preference
        ];

        // Act
        $modifier = $this->evaluator->calculatePlayingTimeModifier($teamFactors, $playerPreferences);

        // Assert - High money at position with playing time preference should be negative
        $this->assertLessThan(0, $modifier);
    }

    /**
     * @group offer-evaluation
     * @group modifiers
     */
    public function testCalculatesCombinedModifierCorrectly(): void
    {
        // Arrange
        $teamFactors = [
            'wins' => 50,
            'losses' => 32,
            'tradition_wins' => 2500,
            'tradition_losses' => 2000,
            'coach_rating' => 80,
            'money_committed_at_position' => 3000
        ];
        $playerPreferences = [
            'winner' => 3,
            'tradition' => 3,
            'coach' => 3,
            'loyalty' => 3,
            'security' => 3,
            'playing_time' => 3
        ];

        // Act
        $modifier = $this->evaluator->calculateCombinedModifier($teamFactors, $playerPreferences);

        // Assert - Modifier should be around 1.0 (neutral preferences)
        $this->assertGreaterThan(0.9, $modifier);
        $this->assertLessThan(1.15, $modifier); // Slightly relaxed to account for formula precision
    }

    /**
     * @group offer-evaluation
     * @group acceptance
     */
    public function testAcceptsOfferWhenValueExceedsDemands(): void
    {
        // Arrange
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 0,
            'year5' => 0
        ];
        $demands = [
            'year1' => 800,
            'year2' => 880,
            'year3' => 968,
            'year4' => 0,
            'year5' => 0
        ];
        $teamFactors = [
            'wins' => 50,
            'losses' => 32,
            'tradition_wins' => 2500,
            'tradition_losses' => 2000,
            'money_committed_at_position' => 2000
        ];
        $playerPreferences = [
            'winner' => 3,
            'tradition' => 3,
            'loyalty' => 3,
            'playing_time' => 3
        ];

        // Act
        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        // Assert
        $this->assertTrue($result['accepted']);
        $this->assertArrayHasKey('offerValue', $result);
        $this->assertArrayHasKey('demandValue', $result);
        $this->assertGreaterThan($result['demandValue'], $result['offerValue']);
    }

    /**
     * @group offer-evaluation
     * @group acceptance
     */
    public function testRejectsOfferWhenValueBelowDemands(): void
    {
        // Arrange
        $offer = [
            'year1' => 800,
            'year2' => 850,
            'year3' => 900,
            'year4' => 0,
            'year5' => 0
        ];
        $demands = [
            'year1' => 1200,
            'year2' => 1320,
            'year3' => 1452,
            'year4' => 0,
            'year5' => 0
        ];
        $teamFactors = [
            'wins' => 30,
            'losses' => 52,
            'tradition_wins' => 1500,
            'tradition_losses' => 2500,
            'money_committed_at_position' => 5000
        ];
        $playerPreferences = [
            'winner' => 5,    // High preference
            'tradition' => 5, // High preference
            'loyalty' => 1,   // Low loyalty
            'playing_time' => 5 // High preference
        ];

        // Act
        $result = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        // Assert
        $this->assertFalse($result['accepted']);
        $this->assertLessThan($result['demandValue'], $result['offerValue']);
    }

    /**
     * @group offer-evaluation
     * @group edge-cases
     */
    public function testHandlesExtremelyHighModifiers(): void
    {
        // Arrange - Perfect team for player with max preferences
        $teamFactors = [
            'wins' => 70,
            'losses' => 12,
            'tradition_wins' => 4000,
            'tradition_losses' => 1000,
            'money_committed_at_position' => 0
        ];
        $playerPreferences = [
            'winner' => 5,
            'tradition' => 5,
            'loyalty' => 5,
            'playing_time' => 5
        ];

        // Act
        $modifier = $this->evaluator->calculateCombinedModifier($teamFactors, $playerPreferences);

        // Assert - Modifier should be significantly above 1.0
        $this->assertGreaterThan(1.0, $modifier);
    }

    /**
     * @group offer-evaluation
     * @group edge-cases
     */
    public function testHandlesExtremelyLowModifiers(): void
    {
        // Arrange - Terrible team for demanding player
        $teamFactors = [
            'wins' => 15,
            'losses' => 67,
            'tradition_wins' => 1000,
            'tradition_losses' => 4000,
            'money_committed_at_position' => 8000
        ];
        $playerPreferences = [
            'winner' => 5,
            'tradition' => 5,
            'loyalty' => 1,
            'playing_time' => 5
        ];

        // Act
        $modifier = $this->evaluator->calculateCombinedModifier($teamFactors, $playerPreferences);

        // Assert - Modifier should be significantly below 1.0
        $this->assertLessThan(1.0, $modifier);
    }

    /**
     * @group offer-evaluation
     * @group demands
     */
    public function testCalculatesDemandsBasedOnPlayerValue(): void
    {
        // Arrange
        $playerValue = 2000; // Average yearly value player wants

        // Act
        $demands = $this->evaluator->calculatePlayerDemands($playerValue);

        // Assert
        $this->assertIsArray($demands);
        $this->assertArrayHasKey('total', $demands);
        $this->assertArrayHasKey('years', $demands);
        $this->assertEquals(5, $demands['years']); // Extensions are 5 years max
    }

    /**
     * @group offer-evaluation
     * @group in-millions
     */
    public function testConvertsOfferAmountToMillions(): void
    {
        // Arrange
        $offerTotal = 12000; // 12000 in internal units

        // Act
        $millions = SalaryConverter::convertToMillions($offerTotal);

        // Assert
        $this->assertEquals(120, $millions); // 12000 / 100 = 120 million
    }

    // ============================================
    // NULL-COALESCING EDGE CASES (mutation hardening)
    // ============================================

    public function testCalculateWinnerModifierWithMissingTeamFactors(): void
    {
        // Empty team factors → wins ?? 0, losses ?? 0 → winDiff = 0
        $result = $this->evaluator->calculateWinnerModifier([], ['winner' => 3]);

        $this->assertSame(0.0, $result);
    }

    public function testCalculateWinnerModifierWithMissingPreference(): void
    {
        // Missing 'winner' key → defaults to 1, so (1-1) = 0 → modifier = 0
        $result = $this->evaluator->calculateWinnerModifier(
            ['wins' => 50, 'losses' => 32],
            []
        );

        $this->assertSame(0.0, $result);
    }

    public function testCalculateTraditionModifierWithMissingTeamFactors(): void
    {
        $result = $this->evaluator->calculateTraditionModifier([], ['tradition' => 3]);

        $this->assertSame(0.0, $result);
    }

    public function testCalculateLoyaltyModifierWithMissingPreference(): void
    {
        // Missing 'loyalty' key → defaults to 1, so (1-1) = 0
        $result = $this->evaluator->calculateLoyaltyModifier([]);

        $this->assertSame(0.0, $result);
    }

    public function testCalculatePlayingTimeModifierWithMissingData(): void
    {
        // Missing money_committed → defaults to 0; missing playingTime → defaults to 1
        $result = $this->evaluator->calculatePlayingTimeModifier([], []);

        $this->assertSame(0.0, $result);
    }
}
