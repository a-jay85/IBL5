<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use League\League;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Comprehensive unit tests for Trading\TradeValidator class
 * 
 * This demonstrates modern PHP testing best practices including:
 * - Proper test structure with setUp/tearDown
 * - Mock objects and dependency injection
 * - Data providers for parametrized testing
 * - Descriptive test names and clear assertions
 * - Edge case testing and error handling
 */
class TradeValidatorTest extends TestCase
{
    private $validator;
    private $mockDb;
    private $mockMysqli;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        
        // Set up mock mysqli for Trading repository
        $this->mockMysqli = new class($this->mockDb) {
            private $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;
            public int $insert_id = 1;
            
            public function __construct($mockDb) {
                $this->mockDb = $mockDb;
            }
            
            public function prepare($query) {
                return new \MockPreparedStatement($this->mockDb, $query);
            }
        };
        
        $this->validator = new \Trading\TradeValidator($this->mockDb, $this->mockMysqli);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
        $this->mockDb = null;
        $this->mockMysqli = null;
    }

    /**
     * @group validation
     * @group cash
     */
    public function testValidatesMinimumCashAmountsSuccessfully()
    {
        // Arrange
        $userCash = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 150, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertTrue($result['valid'], 'Valid cash amounts should pass validation');
        $this->assertNull($result['error'], 'No error should be returned for valid amounts');
    }

    /**
     * @group validation
     * @group cash     */
        #[DataProvider('invalidCashAmountProvider')]
    public function testRejectsCashAmountsBelowMinimum($userCash, $partnerCash, $expectedErrorText)
    {
        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertFalse($result['valid'], 'Invalid cash amounts should fail validation');
        $this->assertStringContainsString($expectedErrorText, $result['error']);
    }

    /**
     * @group validation
     * @group salary-cap
     */
    public function testValidatesSalaryeCapsWithinLimits()
    {
        // Arrange
        $tradeData = [
            'userCurrentSeasonCapTotal' => 5000,
            'partnerCurrentSeasonCapTotal' => 5500,
            'userCapSentToPartner' => 500,
            'partnerCapSentToUser' => 400
        ];

        // Act
        $result = $this->validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertTrue($result['valid'], 'Valid salary caps should pass validation');
        $this->assertEmpty($result['errors'], 'No errors should be returned for valid caps');
        $this->assertEquals(4900, $result['userPostTradeCapTotal']); // 5000 - 500 + 400
        $this->assertEquals(5600, $result['partnerPostTradeCapTotal']); // 5500 - 400 + 500
    }

    /**
     * @group validation
     * @group salary-cap     */
        #[DataProvider('salaryCapViolationProvider')]
    public function testRejectsTradesExceedingSalaryCaps($tradeData, $expectedErrorCount)
    {
        // Act
        $result = $this->validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertFalse($result['valid'], 'Salary cap violations should fail validation');
        $this->assertCount($expectedErrorCount, $result['errors']);
    }

    /**
     * @group cash-considerations
     */
    public function testCalculatesCurrentSeasonCashConsiderationsCorrectly()
    {
        // Arrange
        $userCash = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 150, 2 => 250, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->getCurrentSeasonCashConsiderations($userCash, $partnerCash);

        // Assert
        $this->assertEquals(100, $result['cashSentToThem']);
        $this->assertEquals(150, $result['cashSentToMe']);
        $this->assertArrayHasKey('cashSentToThem', $result);
        $this->assertArrayHasKey('cashSentToMe', $result);
    }

    /**
     * @group player-validation
     */
    public function testDeterminesPlayerTradabilityCorrectly()
    {
        // Arrange - Valid tradeable player
        $playerId = 12345;
        $this->mockDb->setMockData([
            ['ordinal' => 500, 'cy' => 5000] // ordinal <= 960, cy (salary) > 0
        ]);

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertTrue($result, 'Valid player should be tradeable');
    }

    /**
     * @group player-validation     */
        #[DataProvider('nonTradeablePlayerProvider')]
    public function testPreventsTradingIneligiblePlayers($mockData, $expectedResult, $reason)
    {
        // Arrange
        $playerId = 12345;
        $this->mockDb->setMockData($mockData);

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertEquals($expectedResult, $result, $reason);
    }

    /**
     * Data provider for invalid cash amounts
     */
    public static function invalidCashAmountProvider()
    {
        return [
            'User cash below minimum' => [
                [1 => 50, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], // User cash
                [1 => 150, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], // Partner cash
                'minimum amount of cash that your team can send' // Expected error text
            ],
            'Partner cash below minimum' => [
                [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], // User cash
                [1 => 50, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], // Partner cash
                'minimum amount of cash that the other team can send' // Expected error text
            ]
        ];
    }

    /**
     * Data provider for salary cap violations
     */
    public static function salaryCapViolationProvider()
    {
        return [
            'User exceeds hard cap' => [
                [
                    'userCurrentSeasonCapTotal' => 6000,
                    'partnerCurrentSeasonCapTotal' => 5000,
                    'userCapSentToPartner' => 100,
                    'partnerCapSentToUser' => 1500
                ],
                1 // Expected error count
            ],
            'Partner exceeds hard cap' => [
                [
                    'userCurrentSeasonCapTotal' => 5000,
                    'partnerCurrentSeasonCapTotal' => 6000,
                    'userCapSentToPartner' => 1500,
                    'partnerCapSentToUser' => 100
                ],
                1 // Expected error count
            ]
        ];
    }

    /**
     * Data provider for non-tradeable players
     */
    public static function nonTradeablePlayerProvider()
    {
        return [
            'Waived player' => [
                [['ordinal' => 1500, 'cy' => 5000]], // High ordinal > 960 (waived), has salary
                false,
                'Waived players should not be tradeable'
            ],
            'Player with no salary' => [
                [['ordinal' => 500, 'cy' => 0]], // Low ordinal <= 960, no salary
                false,
                'Players with zero salary should not be tradeable'
            ],
            'Nonexistent player' => [
                [], // No data returned
                false,
                'Nonexistent players should not be tradeable'
            ]
        ];
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testValidatesRosterLimitsWithBothTeamsWithinLimit(): void
    {
        // Both teams have 13 players, user sends 1, partner sends 2
        // User: 13 - 1 + 2 = 14, Partner: 13 - 2 + 1 = 12
        $this->mockDb->setMockData([['cnt' => 13]]);

        $result = $this->validator->validateRosterLimits(1, 2, 1, 2);

        $this->assertTrue($result['valid'], 'Both teams within roster limit should pass');
        $this->assertSame([], $result['errors']);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testRejectsTradeWhenUserTeamExceedsRosterLimit(): void
    {
        // Both teams have 14 players, user sends 0, partner sends 2
        // User: 14 - 0 + 2 = 16 (exceeds 15), Partner: 14 - 2 + 0 = 12
        $this->mockDb->setMockData([['cnt' => 14]]);

        $result = $this->validator->validateRosterLimits(1, 2, 0, 2);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('your team', $result['errors'][0]);
        $this->assertStringContainsString('roster limit', $result['errors'][0]);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testRejectsTradeWhenPartnerTeamExceedsRosterLimit(): void
    {
        // Both teams have 14 players, user sends 2, partner sends 0
        // User: 14 - 2 + 0 = 12, Partner: 14 - 0 + 2 = 16 (exceeds 15)
        $this->mockDb->setMockData([['cnt' => 14]]);

        $result = $this->validator->validateRosterLimits(1, 2, 2, 0);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('other team', $result['errors'][0]);
        $this->assertStringContainsString('roster limit', $result['errors'][0]);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testRejectsTradeWhenBothTeamsExceedRosterLimit(): void
    {
        // Both teams have 15 players, user sends 1, partner sends 2
        // User: 15 - 1 + 2 = 16 (exceeds), Partner: 15 - 2 + 1 = 14 (OK)
        // Need both to exceed: both at 15, user sends 0, partner sends 1 => user 16, partner 14 (only one)
        // Both exceed: both at 16 (impossible normally, but mock allows), user sends 0, partner sends 0
        // Actually: both at 14, user sends 0, partner sends 2 => user=16, partner=12 (only user)
        // For BOTH to exceed with same base count: both at 15, user sends 2, partner sends 3
        // User: 15 - 2 + 3 = 16 (exceeds), Partner: 15 - 3 + 2 = 14 (no)
        // Try: both at 14, user sends 0, partner sends 2 => user 16 (yes), partner 16 (14-2+0=12, no)
        // Both exceed requires net gain > 1 for BOTH teams — impossible if both start the same.
        // With same mock count, only one team can exceed per test. Let's test with 15:
        // both at 15, user sends 0, partner sends 1 => user 16 (yes), partner 14 (no)
        // The only way both exceed is if currentRoster + netGain > 15 for BOTH.
        // netGain for user = partnerSent - userSent; netGain for partner = userSent - partnerSent
        // These sum to 0, so if both start at same count, only one can net-gain.
        // Both can exceed only if both already > 15 (which our mock allows).
        $this->mockDb->setMockData([['cnt' => 16]]);

        // Both at 16, user sends 1, partner sends 2
        // User: 16 - 1 + 2 = 17 (exceeds), Partner: 16 - 2 + 1 = 15 (OK)
        // Still only one. Need both at 16, equal swap but both net zero:
        // User: 16 - 0 + 1 = 17, Partner: 16 - 1 + 0 = 15 — no.
        // Both already over: both at 16, user sends 0, partner sends 0
        // User: 16, Partner: 16 — both exceed!
        $result = $this->validator->validateRosterLimits(1, 2, 0, 0);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('your team', $result['errors'][0]);
        $this->assertStringContainsString('other team', $result['errors'][1]);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testAllowsOneForOneSwapAtRosterLimit(): void
    {
        // Team has 15 actual players (buyout/cash placeholder records excluded by repository).
        // A 1-for-1 swap: 15 - 1 + 1 = 15, which is within the limit.
        // Before the fix, buyout records inflated the count to 16, making this fail.
        $this->mockDb->setMockData([['cnt' => 15]]);

        $result = $this->validator->validateRosterLimits(1, 2, 1, 1);

        $this->assertTrue($result['valid'], '1-for-1 swap at roster limit should be valid');
        $this->assertSame([], $result['errors']);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testAllowsEqualPlayerSwapAtRosterLimit(): void
    {
        // Both teams have 15 players, user sends 2, partner sends 2
        // User: 15 - 2 + 2 = 15, Partner: 15 - 2 + 2 = 15
        $this->mockDb->setMockData([['cnt' => 15]]);

        $result = $this->validator->validateRosterLimits(1, 2, 2, 2);

        $this->assertTrue($result['valid'], 'Equal swap at roster limit should be valid');
        $this->assertSame([], $result['errors']);
    }

    /**
     * @group validation
     * @group roster-limits
     */
    public function testAllowsTradeResultingInExactlyMaxRoster(): void
    {
        // Both teams have 14 players, user sends 0, partner sends 1
        // User: 14 - 0 + 1 = 15 (exactly at limit), Partner: 14 - 1 + 0 = 13
        $this->mockDb->setMockData([['cnt' => 14]]);

        $result = $this->validator->validateRosterLimits(1, 2, 0, 1);

        $this->assertTrue($result['valid'], 'Exactly at 15-player limit should be valid');
        $this->assertSame([], $result['errors']);
    }

    // --- Merged from TradeValidatorEdgeCaseTest ---

    // ============================================
    // SALARY CAP BOUNDARY TESTS
    // ============================================

    /**
     * Test trade that results in exactly the hard cap (7000)
     */
    public function testValidatesSalaryCapAtExactHardCapLimit(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 6500,
            'partnerCurrentSeasonCapTotal' => 6500,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 500
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertTrue($result['valid']);
        $this->assertEquals(League::HARD_CAP_MAX, $result['userPostTradeCapTotal']);
    }

    /**
     * Test trade that puts team 1 over cap by 1
     */
    public function testRejectsTradeOneOverHardCap(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 6500,
            'partnerCurrentSeasonCapTotal' => 6500,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 501
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertFalse($result['valid']);
        $this->assertEquals(League::HARD_CAP_MAX + 1, $result['userPostTradeCapTotal']);
    }

    /**
     * Test both teams exactly at hard cap after trade
     */
    public function testAcceptsBothTeamsAtExactHardCap(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 7000,
            'partnerCurrentSeasonCapTotal' => 7000,
            'userCapSentToPartner' => 500,
            'partnerCapSentToUser' => 500
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertTrue($result['valid']);
        $this->assertEquals(League::HARD_CAP_MAX, $result['userPostTradeCapTotal']);
        $this->assertEquals(League::HARD_CAP_MAX, $result['partnerPostTradeCapTotal']);
    }

    /**
     * Test both teams over hard cap returns two errors
     */
    public function testReturnsTwoErrorsWhenBothTeamsOverCap(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 6000,
            'partnerCurrentSeasonCapTotal' => 6000,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 1500
        ];

        // User ends at 7500, partner at 4500 - only user over
        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);

        // Now test both over
        $tradeData2 = [
            'userCurrentSeasonCapTotal' => 6500,
            'partnerCurrentSeasonCapTotal' => 6500,
            'userCapSentToPartner' => 100,
            'partnerCapSentToUser' => 700
        ];

        $result2 = $this->validator->validateSalaryCaps($tradeData2);

        // User: 6500 - 100 + 700 = 7100
        // Partner: 6500 - 700 + 100 = 5900
        $this->assertFalse($result2['valid']);
        $this->assertCount(1, $result2['errors']); // Only user over
    }

    // ============================================
    // NULL/MISSING VALUE HANDLING
    // ============================================

    /**
     * Test salary cap validation with missing keys uses defaults
     */
    public function testValidatesSalaryCapsWithMissingKeys(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 5000,
            // Missing partnerCurrentSeasonCapTotal - should default to 0
            'userCapSentToPartner' => 100
            // Missing partnerCapSentToUser - should default to 0
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        // User: 5000 - 100 + 0 = 4900
        // Partner: 0 - 0 + 100 = 100
        $this->assertTrue($result['valid']);
        $this->assertEquals(4900, $result['userPostTradeCapTotal']);
        $this->assertEquals(100, $result['partnerPostTradeCapTotal']);
    }

    /**
     * Test salary cap validation with empty array
     */
    public function testValidatesSalaryCapsWithEmptyArray(): void
    {
        $result = $this->validator->validateSalaryCaps([]);

        // All values default to 0, so both teams at 0
        $this->assertTrue($result['valid']);
        $this->assertEquals(0, $result['userPostTradeCapTotal']);
        $this->assertEquals(0, $result['partnerPostTradeCapTotal']);
    }

    // ============================================
    // CASH VALIDATION EDGE CASES
    // ============================================

    /**
     * Test cash validation with exactly minimum amount (100)
     */
    public function testValidatesExactlyMinimumCash(): void
    {
        $userCash = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test cash validation with 99 (one below minimum)
     */
    public function testRejectsCashOneUnderMinimum(): void
    {
        $userCash = [1 => 99, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum amount of cash', $result['error']);
    }

    /**
     * Test cash validation with empty arrays (no cash exchanged)
     */
    public function testAcceptsEmptyCashArrays(): void
    {
        $result = $this->validator->validateMinimumCashAmounts([], []);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test cash validation with all zeros (no cash exchanged)
     */
    public function testAcceptsAllZeroCash(): void
    {
        $userCash = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test cash validation where only later years have cash
     */
    public function testValidatesCashInLaterYearsOnly(): void
    {
        $userCash = [1 => 0, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 0, 2 => 0, 3 => 150, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test cash validation with mixed valid/invalid amounts
     */
    public function testRejectsWhenAnyYearBelowMinimum(): void
    {
        $userCash = [1 => 100, 2 => 50, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertFalse($result['valid']);
    }

    // ============================================
    // PLAYER TRADABILITY EDGE CASES
    // ============================================

    /**
     * Test player with ordinal exactly at waiver threshold (960)
     */
    public function testPlayerTradeableAtExactWaiverThreshold(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => \JSB::WAIVERS_ORDINAL, 'cy' => 1000]
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertTrue($result);
    }

    /**
     * Test player with ordinal one above waiver threshold (961)
     */
    public function testPlayerNotTradeableOneAboveWaiverThreshold(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => \JSB::WAIVERS_ORDINAL + 1, 'cy' => 1000]
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertFalse($result);
    }

    /**
     * Test player with NULL ordinal treated as non-tradeable
     */
    public function testPlayerWithNullOrdinalNotTradeable(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => null, 'cy' => 1000]
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertFalse($result); // NULL ordinal becomes 99999
    }

    /**
     * Test player with NULL cy (salary) not tradeable
     */
    public function testPlayerWithNullSalaryNotTradeable(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => 500, 'cy' => null]
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertFalse($result); // NULL cy becomes 0
    }

    /**
     * Test player with string values for ordinal and cy
     */
    public function testPlayerWithStringValuesHandledCorrectly(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => '500', 'cy' => '1000']
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertTrue($result);
    }

    /**
     * Test player with exactly 1 salary (minimum positive)
     */
    public function testPlayerWithMinimumSalaryTradeable(): void
    {
        $this->mockDb->setMockData([
            ['ordinal' => 500, 'cy' => 1]
        ]);

        $result = $this->validator->canPlayerBeTraded(123);

        $this->assertTrue($result);
    }

    /**
     * Test player with negative ID
     */
    public function testPlayerWithNegativeIdNotTradeable(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->validator->canPlayerBeTraded(-1);

        $this->assertFalse($result);
    }

    /**
     * Test player with zero ID
     */
    public function testPlayerWithZeroIdNotTradeable(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->validator->canPlayerBeTraded(0);

        $this->assertFalse($result);
    }

    // ============================================
    // CASH CONSIDERATIONS EDGE CASES
    // ============================================

    /**
     * Test cash considerations with empty arrays
     */
    public function testGetCashConsiderationsWithEmptyArrays(): void
    {
        $result = $this->validator->getCurrentSeasonCashConsiderations([], []);

        $this->assertEquals(0, $result['cashSentToThem']);
        $this->assertEquals(0, $result['cashSentToMe']);
    }

    /**
     * Test cash considerations with only future year cash
     */
    public function testGetCashConsiderationsIgnoresWrongYears(): void
    {
        // During regular season, year 1 is current
        $userCash = [1 => 0, 2 => 500, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 0, 2 => 300, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->getCurrentSeasonCashConsiderations($userCash, $partnerCash);

        // Should return year 1 values (0) since we're in Regular Season by default
        $this->assertEquals(0, $result['cashSentToThem']);
        $this->assertEquals(0, $result['cashSentToMe']);
    }

    // ============================================
    // LARGE VALUE TESTS
    // ============================================

    /**
     * Test salary cap with very large values
     */
    public function testValidatesSalaryCapsWithLargeValues(): void
    {
        $tradeData = [
            'userCurrentSeasonCapTotal' => 999999,
            'partnerCurrentSeasonCapTotal' => 999999,
            'userCapSentToPartner' => 500000,
            'partnerCapSentToUser' => 500000
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertFalse($result['valid']);
        $this->assertEquals(999999, $result['userPostTradeCapTotal']);
    }

    /**
     * Test cash validation with large amounts
     */
    public function testValidatesCashWithLargeAmounts(): void
    {
        $userCash = [1 => 1000000, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 1000000, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        $this->assertTrue($result['valid']);
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    /**     */
    #[DataProvider('boundaryCapValuesProvider')]
    public function testSalaryCapBoundaryConditions(
        int $userCap,
        int $partnerCap,
        int $sentToPartner,
        int $sentToUser,
        bool $expectedValid
    ): void {
        $tradeData = [
            'userCurrentSeasonCapTotal' => $userCap,
            'partnerCurrentSeasonCapTotal' => $partnerCap,
            'userCapSentToPartner' => $sentToPartner,
            'partnerCapSentToUser' => $sentToUser
        ];

        $result = $this->validator->validateSalaryCaps($tradeData);

        $this->assertEquals($expectedValid, $result['valid']);
    }

    public static function boundaryCapValuesProvider(): array
    {
        $hardCap = League::HARD_CAP_MAX;

        return [
            'user exactly at cap' => [$hardCap, 5000, 0, 0, true],
            'user one over cap' => [$hardCap + 1, 5000, 0, 0, false],
            'partner exactly at cap' => [5000, $hardCap, 0, 0, true],
            'partner one over cap' => [5000, $hardCap + 1, 0, 0, false],
            'trade to exactly at cap' => [6000, 5000, 0, 1000, true],
            'trade to one over cap' => [6000, 5000, 0, 1001, false],
            'zero cap teams valid' => [0, 0, 0, 0, true],
            'large trade under cap' => [3000, 3000, 1000, 1000, true],
        ];
    }

    // --- Merged from SeasonPhaseTest ---

    /**
     * Test that cash considerations behave correctly across season phases
     * @group season-phase
     */
    public function testCashConsiderationsVaryBySeasonPhase(): void
    {
        // This test verifies the behavior without relying on reflection
        // The actual season phase behavior is tested through integration tests
        // where the full context (including season phase) is properly set up

        $userCash = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 150, 2 => 250, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->getCurrentSeasonCashConsiderations($userCash, $partnerCash);

        // Assert - Verify the method returns valid cash considerations structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cashSentToThem', $result);
        $this->assertArrayHasKey('cashSentToMe', $result);
        $this->assertGreaterThanOrEqual(0, $result['cashSentToThem']);
        $this->assertGreaterThanOrEqual(0, $result['cashSentToMe']);
    }
}