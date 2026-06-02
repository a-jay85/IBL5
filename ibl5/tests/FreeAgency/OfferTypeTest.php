<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\OfferType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OfferType class
 * 
 * Tests all constants, helper methods, and edge cases for offer type identification.
 */
class OfferTypeTest extends TestCase
{
    /**
     * Test MLE_OFFERS constant
     */
    public function testMLEOffersConstant(): void
    {
        $expected = [450, 495, 540, 585, 630, 675];
        $this->assertEquals($expected, \ContractRules::MLE_OFFERS);
        $this->assertCount(6, \ContractRules::MLE_OFFERS);
    }

    /**
     * Test isMLE() returns true for MLE offer types (1-6)
     */
    public function testIsMLEReturnsTrueForMLEOffers(): void
    {
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_1_YEAR));
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_2_YEAR));
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_3_YEAR));
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_4_YEAR));
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_5_YEAR));
        $this->assertTrue(OfferType::isMLE(OfferType::MLE_6_YEAR));
    }

    /**
     * Test isMLE() returns false for non-MLE offer types
     */
    public function testIsMLEReturnsFalseForNonMLEOffers(): void
    {
        $this->assertFalse(OfferType::isMLE(OfferType::CUSTOM));
        $this->assertFalse(OfferType::isMLE(OfferType::LOWER_LEVEL_EXCEPTION));
        $this->assertFalse(OfferType::isMLE(OfferType::VETERAN_MINIMUM));
        $this->assertFalse(OfferType::isMLE(0));
        $this->assertFalse(OfferType::isMLE(7));
        $this->assertFalse(OfferType::isMLE(8));
        $this->assertFalse(OfferType::isMLE(99));
    }

    /**
     * Test isLLE() returns true only for LLE offer type
     */
    public function testIsLLEReturnsTrueForLLE(): void
    {
        $this->assertTrue(OfferType::isLLE(OfferType::LOWER_LEVEL_EXCEPTION));
        $this->assertTrue(OfferType::isLLE(7));
    }

    /**
     * Test isLLE() returns false for non-LLE offer types
     */
    public function testIsLLEReturnsFalseForNonLLE(): void
    {
        $this->assertFalse(OfferType::isLLE(OfferType::CUSTOM));
        $this->assertFalse(OfferType::isLLE(OfferType::MLE_1_YEAR));
        $this->assertFalse(OfferType::isLLE(OfferType::MLE_6_YEAR));
        $this->assertFalse(OfferType::isLLE(OfferType::VETERAN_MINIMUM));
        $this->assertFalse(OfferType::isLLE(0));
        $this->assertFalse(OfferType::isLLE(8));
    }

    /**
     * Test isVeteranMinimum() returns true only for Veteran's Minimum offer type
     */
    public function testIsVeteranMinimumReturnsTrueForVetMin(): void
    {
        $this->assertTrue(OfferType::isVeteranMinimum(OfferType::VETERAN_MINIMUM));
        $this->assertTrue(OfferType::isVeteranMinimum(8));
    }

    /**
     * Test isVeteranMinimum() returns false for non-VetMin offer types
     */
    public function testIsVeteranMinimumReturnsFalseForNonVetMin(): void
    {
        $this->assertFalse(OfferType::isVeteranMinimum(OfferType::CUSTOM));
        $this->assertFalse(OfferType::isVeteranMinimum(OfferType::MLE_1_YEAR));
        $this->assertFalse(OfferType::isVeteranMinimum(OfferType::MLE_6_YEAR));
        $this->assertFalse(OfferType::isVeteranMinimum(OfferType::LOWER_LEVEL_EXCEPTION));
        $this->assertFalse(OfferType::isVeteranMinimum(0));
        $this->assertFalse(OfferType::isVeteranMinimum(7));
    }

    /**
     * Test getName() returns correct names for all offer types
     */
    public function testGetNameReturnsCorrectNames(): void
    {
        $this->assertSame('Custom Offer', OfferType::getName(OfferType::CUSTOM));
        $this->assertSame('1-Year MLE', OfferType::getName(OfferType::MLE_1_YEAR));
        $this->assertSame('2-Year MLE', OfferType::getName(OfferType::MLE_2_YEAR));
        $this->assertSame('3-Year MLE', OfferType::getName(OfferType::MLE_3_YEAR));
        $this->assertSame('4-Year MLE', OfferType::getName(OfferType::MLE_4_YEAR));
        $this->assertSame('5-Year MLE', OfferType::getName(OfferType::MLE_5_YEAR));
        $this->assertSame('6-Year MLE', OfferType::getName(OfferType::MLE_6_YEAR));
        $this->assertSame('Lower-Level Exception', OfferType::getName(OfferType::LOWER_LEVEL_EXCEPTION));
        $this->assertSame("Veteran's Minimum", OfferType::getName(OfferType::VETERAN_MINIMUM));
    }

    /**
     * Test getName() returns 'Unknown' for invalid offer types
     */
    public function testGetNameReturnsUnknownForInvalidTypes(): void
    {
        $this->assertSame('Unknown', OfferType::getName(99));
        $this->assertSame('Unknown', OfferType::getName(-1));
        $this->assertSame('Unknown', OfferType::getName(10));
    }

    /**
     * Test edge case: negative offer type values
     */
    public function testHelperMethodsHandleNegativeValues(): void
    {
        $this->assertFalse(OfferType::isMLE(-1));
        $this->assertFalse(OfferType::isLLE(-1));
        $this->assertFalse(OfferType::isVeteranMinimum(-1));
    }

    /**
     * Test edge case: very large offer type values
     */
    public function testHelperMethodsHandleLargeValues(): void
    {
        $this->assertFalse(OfferType::isMLE(999));
        $this->assertFalse(OfferType::isLLE(999));
        $this->assertFalse(OfferType::isVeteranMinimum(999));
    }

    /**
     * Test that all helper methods are mutually exclusive
     */
    public function testHelperMethodsAreMutuallyExclusive(): void
    {
        // Test all valid offer types
        for ($offerType = 0; $offerType <= 8; $offerType++) {
            $isMLE = OfferType::isMLE($offerType);
            $isLLE = OfferType::isLLE($offerType);
            $isVetMin = OfferType::isVeteranMinimum($offerType);
            
            // Count how many helper methods return true
            $trueCount = (int)$isMLE + (int)$isLLE + (int)$isVetMin;
            
            // At most one should be true (custom offers have all false)
            $this->assertLessThanOrEqual(1, $trueCount,
                "Offer type $offerType should match at most one helper method");
        }
    }

    // ── calculateYears ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('calculateYearsProvider')]
    public function testCalculateYears(int $expected, int $o1, int $o2, int $o3, int $o4, int $o5, int $o6): void
    {
        $result = OfferType::calculateYears([
            'offer1' => $o1, 'offer2' => $o2, 'offer3' => $o3,
            'offer4' => $o4, 'offer5' => $o5, 'offer6' => $o6,
        ]);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{int, int, int, int, int, int, int}>
     */
    public static function calculateYearsProvider(): array
    {
        return [
            '1-year offer' => [1, 500, 0, 0, 0, 0, 0],
            '2-year offer' => [2, 500, 550, 0, 0, 0, 0],
            '3-year offer' => [3, 500, 550, 600, 0, 0, 0],
            '4-year offer' => [4, 500, 550, 600, 650, 0, 0],
            '5-year offer' => [5, 500, 550, 600, 650, 700, 0],
            '6-year offer' => [6, 500, 550, 600, 650, 700, 750],
            'all zeros returns 1' => [1, 0, 0, 0, 0, 0, 0],
            'trailing zeros trimmed' => [3, 100, 200, 300, 0, 0, 0],
            'vet min single year' => [1, 70, 0, 0, 0, 0, 0],
        ];
    }
}
