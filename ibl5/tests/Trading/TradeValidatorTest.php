<?php

use PHPUnit\Framework\TestCase;

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
        $this->mockDb = new MockDatabase();
        
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
                return new MockPreparedStatement($this->mockDb, $query);
            }
        };
        
        $this->validator = new Trading\TradeValidator($this->mockDb, $this->mockMysqli);
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
     * @group cash
     * @dataProvider invalidCashAmountProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCashAmountProvider')]
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
     * @group salary-cap
     * @dataProvider salaryCapViolationProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('salaryCapViolationProvider')]
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
     * @group player-validation
     * @dataProvider nonTradeablePlayerProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonTradeablePlayerProvider')]
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

        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 1, 2);

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

        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 0, 2);

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

        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 2, 0);

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
        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 0, 0);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('your team', $result['errors'][0]);
        $this->assertStringContainsString('other team', $result['errors'][1]);
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

        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 2, 2);

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

        $result = $this->validator->validateRosterLimits('Team A', 'Team B', 0, 1);

        $this->assertTrue($result['valid'], 'Exactly at 15-player limit should be valid');
        $this->assertSame([], $result['errors']);
    }
}