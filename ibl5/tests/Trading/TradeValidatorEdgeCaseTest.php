<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * TradeValidatorEdgeCaseTest - Edge case and boundary tests for TradeValidator
 *
 * Tests boundary conditions, null/empty inputs, and edge cases not covered
 * by the main test file.
 *
 * @covers \Trading\TradeValidator
 */
class TradeValidatorEdgeCaseTest extends TestCase
{
    private \Trading\TradeValidator $validator;
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();

        $mockMysqli = new class ($this->mockDb) {
            private $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;
            public int $insert_id = 1;

            public function __construct($mockDb)
            {
                $this->mockDb = $mockDb;
            }

            public function prepare($query)
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }
        };

        $this->validator = new \Trading\TradeValidator($this->mockDb, $mockMysqli);
    }

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
        $this->assertEquals(\League::HARD_CAP_MAX, $result['userPostTradeCapTotal']);
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
        $this->assertEquals(\League::HARD_CAP_MAX + 1, $result['userPostTradeCapTotal']);
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
        $this->assertEquals(\League::HARD_CAP_MAX, $result['userPostTradeCapTotal']);
        $this->assertEquals(\League::HARD_CAP_MAX, $result['partnerPostTradeCapTotal']);
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
        $hardCap = \League::HARD_CAP_MAX;

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
}
