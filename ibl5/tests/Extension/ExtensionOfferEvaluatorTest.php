<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionOfferEvaluator;
use Extension\Contracts\ExtensionOfferEvaluatorInterface;

/**
 * ExtensionOfferEvaluatorTest - Tests for ExtensionOfferEvaluator
 */
class ExtensionOfferEvaluatorTest extends TestCase
{
    private ExtensionOfferEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExtensionOfferEvaluator();
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

        $result = $this->evaluator->calculateOfferValue($offer);

        $this->assertIsArray($result);
    }

    public function testCalculateOfferValueHasAveragePerYearKey(): void
    {
        $offer = [1 => 5000000, 2 => 5500000];

        $result = $this->evaluator->calculateOfferValue($offer);

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
}
