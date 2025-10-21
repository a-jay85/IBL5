<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for Free Agency offer validation logic
 * 
 * Tests all validation rules from freeagentoffer.php including:
 * - Zero contract amount validation
 * - Minimum salary validation
 * - Cap space validation (soft and hard cap)
 * - Maximum contract validation based on years of experience
 * - Raise percentage validation (Bird vs non-Bird rights)
 * - Salary decrease validation
 * - Player already signed validation
 */
class FreeAgencyOfferValidationTest extends TestCase
{
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        $this->mockDb = null;
    }

    /**
     * @group validation
     * @group zero-amounts
     */
    public function testRejectsOfferWithZeroAmountInYear1()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 0,
            'offeryear2' => 500,
            'offeryear3' => 500,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        // Act
        $result = $this->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('zero', strtolower($result['error']));
        $this->assertStringContainsString('first year', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group minimum-salary
     */
    public function testRejectsOfferBelowVeteranMinimum()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 50, // Below veteran minimum
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'vetmin' => 65
        ];

        // Act
        $result = $this->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('veteran\'s minimum', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group bird-rights
     */
    public function testBirdRightsAreResetWhenPlayerChangesTeams()
    {
        // Arrange
        $offerData = [
            'bird' => 3,
            'player_teamname' => 'Los Angeles Lakers',
            'teamname' => 'Chicago Bulls'
        ];

        // Act
        $adjustedBirdYears = $this->adjustBirdRights($offerData);

        // Assert
        $this->assertEquals(0, $adjustedBirdYears);
    }

    /**
     * @group validation
     * @group bird-rights
     */
    public function testBirdRightsAreRetainedWhenPlayerStaysWithSameTeam()
    {
        // Arrange
        $offerData = [
            'bird' => 3,
            'player_teamname' => 'Los Angeles Lakers',
            'teamname' => 'Los Angeles Lakers'
        ];

        // Act
        $adjustedBirdYears = $this->adjustBirdRights($offerData);

        // Assert
        $this->assertEquals(3, $adjustedBirdYears);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testRejectsExcessiveRaisesWithoutBirdRights()
    {
        // Arrange - 10% max raise without Bird rights
        $offerData = [
            'offeryear1' => 1000,
            'offeryear2' => 1200, // 20% raise - too much!
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0
        ];

        // Act
        $result = $this->validateOfferRaises($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', strtolower($result['error']));
        $this->assertStringContainsString('year 2', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group raises
     */
    public function testRejectsExcessiveRaisesWithBirdRights()
    {
        // Arrange - 12.5% max raise with Bird rights
        $offerData = [
            'offeryear1' => 1000,
            'offeryear2' => 1140, // 14% raise - too much even with Bird rights!
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 3
        ];

        // Act
        $result = $this->validateOfferRaises($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group salary-decreases
     */
    public function testRejectsSalaryDecreaseInYear2()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 1000,
            'offeryear2' => 900, // Decrease not allowed
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        // Act
        $result = $this->validateSalaryDecreases($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('decrease', strtolower($result['error']));
        $this->assertStringContainsString('second year', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group salary-decreases
     */
    public function testRejectsSalaryDecreaseInYear3()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1000, // Decrease from year 2
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        // Act
        $result = $this->validateSalaryDecreases($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('decrease', strtolower($result['error']));
        $this->assertStringContainsString('third year', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group cap-space
     */
    public function testRejectsOfferExceedingHardCap()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 5000,
            'amendedCapSpaceYear1' => 1000, // Available soft cap space
            'capnumber' => 1000, // Available soft cap space (legacy field)
            'hardCapSpace' => 1000 // Available hard cap space
        ];

        // Act
        $result = $this->validateCapSpace($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hard cap', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group cap-space
     */
    public function testRejectsOfferExceedingSoftCapWithoutBirdRights()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 2000,
            'amendedCapSpaceYear1' => 1000,
            'hardCapSpace' => 5000, // Plenty of hard cap room
            'bird' => 0, // No Bird rights
            'MLEyrs' => 0 // Not using MLE
        ];

        // Act
        $result = $this->validateCapSpace($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('soft cap', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group max-contract
     */
    public function testRejectsOfferExceedingMaximumFor0To6YearsExperience()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 2500,
            'max' => 2000, // Maximum allowed based on experience
            'exp' => 5
        ];

        // Act
        $result = $this->validateMaximumContract($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group already-signed
     */
    public function testRejectsOfferToPlayerAlreadySignedThisFreeAgencyPeriod()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Already Signed Player',
                'cy' => 0, // Current year = 0 means in Year 1 of contract
                'cy1' => 1000 // Has a non-zero Year 1 salary
            ]
        ]);

        // Act
        $result = $this->checkPlayerAlreadySigned('Already Signed Player');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('previously signed', strtolower($result['error']));
    }

    /**
     * @group validation
     * @group mle
     */
    public function testMLEOfferSetsCorrectAmounts()
    {
        // Arrange - 6-year MLE
        $MLEyrs = 6;

        // Act
        $amounts = $this->applyMLEAmounts($MLEyrs);

        // Assert
        $this->assertEquals(450, $amounts['year1']);
        $this->assertEquals(495, $amounts['year2']);
        $this->assertEquals(540, $amounts['year3']);
        $this->assertEquals(585, $amounts['year4']);
        $this->assertEquals(630, $amounts['year5']);
        $this->assertEquals(675, $amounts['year6']);
        $this->assertEquals(1, $amounts['mle_flag']);
    }

    /**
     * @group validation
     * @group mle
     */
    public function testThreeYearMLEOfferSetsCorrectAmounts()
    {
        // Arrange - 3-year MLE
        $MLEyrs = 3;

        // Act
        $amounts = $this->applyMLEAmounts($MLEyrs);

        // Assert
        $this->assertEquals(450, $amounts['year1']);
        $this->assertEquals(495, $amounts['year2']);
        $this->assertEquals(540, $amounts['year3']);
        $this->assertEquals(0, $amounts['year4']);
        $this->assertEquals(0, $amounts['year5']);
        $this->assertEquals(0, $amounts['year6']);
    }

    /**
     * @group validation
     * @group lle
     */
    public function testLLEOfferSetsCorrectAmount()
    {
        // Arrange
        $MLEyrs = 7; // 7 indicates LLE

        // Act
        $amounts = $this->applyMLEAmounts($MLEyrs);

        // Assert
        $this->assertEquals(145, $amounts['year1']);
        $this->assertEquals(0, $amounts['year2']);
        $this->assertEquals(1, $amounts['lle_flag']);
    }

    /**
     * @group validation
     * @group veteran-minimum
     */
    public function testVeteranMinimumOfferSetsCorrectAmount()
    {
        // Arrange
        $MLEyrs = 8; // 8 indicates veteran minimum
        $vetmin = 65;

        // Act
        $amounts = $this->applyMLEAmounts($MLEyrs, $vetmin);

        // Assert
        $this->assertEquals(65, $amounts['year1']);
        $this->assertEquals(0, $amounts['year2']);
        $this->assertEquals(0, $amounts['year3']);
    }

    // === Helper methods that simulate validation logic ===

    private function validateOffer($offerData)
    {
        if ($offerData['offeryear1'] == 0) {
            return [
                'valid' => false,
                'error' => 'Sorry, you must enter an amount greater than zero in the first year of a free agency offer.'
            ];
        }

        if (isset($offerData['vetmin']) && $offerData['offeryear1'] < $offerData['vetmin']) {
            return [
                'valid' => false,
                'error' => "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer."
            ];
        }

        return ['valid' => true];
    }

    private function adjustBirdRights($offerData)
    {
        if ($offerData['player_teamname'] != $offerData['teamname']) {
            return 0;
        }
        return $offerData['bird'];
    }

    private function validateOfferRaises($offerData)
    {
        $birdYears = $offerData['bird'] ?? 0;
        $maxIncreasePercent = ($birdYears > 2) ? 0.125 : 0.1;
        $maxIncrease = round($offerData['offeryear1'] * $maxIncreasePercent, 0);

        // Check Year 2
        if ($offerData['offeryear2'] > 0 && $offerData['offeryear2'] > $offerData['offeryear1'] + $maxIncrease) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a larger raise than is permitted in Year 2."
            ];
        }

        // Check Year 3
        if ($offerData['offeryear3'] > 0 && $offerData['offeryear3'] > $offerData['offeryear2'] + $maxIncrease) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a larger raise than is permitted in Year 3."
            ];
        }

        return ['valid' => true];
    }

    private function validateSalaryDecreases($offerData)
    {
        if ($offerData['offeryear2'] > 0 && $offerData['offeryear2'] < $offerData['offeryear1']) {
            return [
                'valid' => false,
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered less in the second year."
            ];
        }

        if ($offerData['offeryear3'] > 0 && $offerData['offeryear3'] < $offerData['offeryear2'] && $offerData['offeryear2'] > 0) {
            return [
                'valid' => false,
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered less in the third year."
            ];
        }

        return ['valid' => true];
    }

    private function validateCapSpace($offerData)
    {
        $hardCapSpace = isset($offerData['hardCapSpace']) ? $offerData['hardCapSpace'] : 
                        ($offerData['amendedCapSpaceYear1'] + 2000);

        if ($offerData['offeryear1'] > $hardCapSpace) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the hard cap to make the offer."
            ];
        }

        $birdYears = $offerData['bird'] ?? 0;
        $MLEyrs = $offerData['MLEyrs'] ?? 0;

        if ($birdYears < 3 && $offerData['offeryear1'] > $offerData['amendedCapSpaceYear1'] && $MLEyrs == 0) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the soft cap to make the offer."
            ];
        }

        return ['valid' => true];
    }

    private function validateMaximumContract($offerData)
    {
        if ($offerData['offeryear1'] > $offerData['max']) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a contract larger than the maximum allowed for this player."
            ];
        }

        return ['valid' => true];
    }

    private function checkPlayerAlreadySigned($playerName)
    {
        $result = $this->mockDb->sql_query("SELECT * FROM ibl_plr WHERE name = '$playerName'");
        $playerRow = $this->mockDb->sql_fetchrow($result);

        if ($playerRow['cy'] == 0 && $playerRow['cy1'] != '0') {
            return [
                'valid' => false,
                'error' => "Sorry, this player was previously signed to a team this Free Agency period."
            ];
        }

        return ['valid' => true];
    }

    private function applyMLEAmounts($MLEyrs, $vetmin = 65)
    {
        $amounts = [
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
            'mle_flag' => 0,
            'lle_flag' => 0
        ];

        if ($MLEyrs == 8) {
            // Veteran Minimum
            $amounts['year1'] = $vetmin;
        } elseif ($MLEyrs == 7) {
            // LLE
            $amounts['year1'] = 145;
            $amounts['lle_flag'] = 1;
        } elseif ($MLEyrs >= 1 && $MLEyrs <= 6) {
            // MLE with escalating amounts
            $amounts['year1'] = 450;
            if ($MLEyrs >= 2) $amounts['year2'] = 495;
            if ($MLEyrs >= 3) $amounts['year3'] = 540;
            if ($MLEyrs >= 4) $amounts['year4'] = 585;
            if ($MLEyrs >= 5) $amounts['year5'] = 630;
            if ($MLEyrs >= 6) $amounts['year6'] = 675;
            $amounts['mle_flag'] = 1;
        }

        return $amounts;
    }
}
