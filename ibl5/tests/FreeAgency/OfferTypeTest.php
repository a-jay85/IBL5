<?php

namespace FreeAgency;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OfferType class
 * 
 * Tests all constants, helper methods, and edge cases for offer type identification.
 */
class OfferTypeTest extends TestCase
{
    /**
     * Test that all offer type constants have correct values
     */
    public function testOfferTypeConstants(): void
    {
        $this->assertEquals(0, OfferType::CUSTOM);
        $this->assertEquals(1, OfferType::MLE_1_YEAR);
        $this->assertEquals(2, OfferType::MLE_2_YEAR);
        $this->assertEquals(3, OfferType::MLE_3_YEAR);
        $this->assertEquals(4, OfferType::MLE_4_YEAR);
        $this->assertEquals(5, OfferType::MLE_5_YEAR);
        $this->assertEquals(6, OfferType::MLE_6_YEAR);
        $this->assertEquals(7, OfferType::LOWER_LEVEL_EXCEPTION);
        $this->assertEquals(8, OfferType::VETERAN_MINIMUM);
    }

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
     * Test LLE_OFFER constant
     */
    public function testLLEOfferConstant(): void
    {
        $this->assertEquals(145, \ContractRules::LLE_OFFER);
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
        $this->assertEquals('Custom Offer', OfferType::getName(OfferType::CUSTOM));
        $this->assertEquals('1-Year MLE', OfferType::getName(OfferType::MLE_1_YEAR));
        $this->assertEquals('2-Year MLE', OfferType::getName(OfferType::MLE_2_YEAR));
        $this->assertEquals('3-Year MLE', OfferType::getName(OfferType::MLE_3_YEAR));
        $this->assertEquals('4-Year MLE', OfferType::getName(OfferType::MLE_4_YEAR));
        $this->assertEquals('5-Year MLE', OfferType::getName(OfferType::MLE_5_YEAR));
        $this->assertEquals('6-Year MLE', OfferType::getName(OfferType::MLE_6_YEAR));
        $this->assertEquals('Lower-Level Exception', OfferType::getName(OfferType::LOWER_LEVEL_EXCEPTION));
        $this->assertEquals("Veteran's Minimum", OfferType::getName(OfferType::VETERAN_MINIMUM));
    }

    /**
     * Test getName() returns 'Unknown' for invalid offer types
     */
    public function testGetNameReturnsUnknownForInvalidTypes(): void
    {
        $this->assertEquals('Unknown', OfferType::getName(99));
        $this->assertEquals('Unknown', OfferType::getName(-1));
        $this->assertEquals('Unknown', OfferType::getName(10));
    }

    /**
     * Test that MLE offers have correct salary amounts
     */
    public function testMLEOffersHaveCorrectAmounts(): void
    {
        $offers = \ContractRules::MLE_OFFERS;
        
        // Verify the exact MLE amounts
        $this->assertEquals(450, $offers[0], 'Year 1 MLE should be $450');
        $this->assertEquals(495, $offers[1], 'Year 2 MLE should be $495');
        $this->assertEquals(540, $offers[2], 'Year 3 MLE should be $540');
        $this->assertEquals(585, $offers[3], 'Year 4 MLE should be $585');
        $this->assertEquals(630, $offers[4], 'Year 5 MLE should be $630');
        $this->assertEquals(675, $offers[5], 'Year 6 MLE should be $675');
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
}
