<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_TradeValidator class
 * 
 * Tests all trade validation logic including cash minimums,
 * salary cap validation, and player tradability.
 */
class TradeValidatorTest extends TestCase
{
    private $validator;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->validator = new Trading_TradeValidator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
        $this->mockDb = null;
    }

    /**
     * @test
     */
    public function validateMinimumCashAmounts_withValidAmounts_returnsValid()
    {
        // Arrange
        $userCash = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 150, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function validateMinimumCashAmounts_withUserCashBelowMinimum_returnsInvalid()
    {
        // Arrange
        $userCash = [1 => 50, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]; // Below minimum
        $partnerCash = [1 => 150, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum amount of cash that your team can send', $result['error']);
    }

    /**
     * @test
     */
    public function validateMinimumCashAmounts_withPartnerCashBelowMinimum_returnsInvalid()
    {
        // Arrange
        $userCash = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 50, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]; // Below minimum

        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum amount of cash that the other team can send', $result['error']);
    }

    /**
     * @test
     */
    public function validateMinimumCashAmounts_withEmptyArrays_returnsValid()
    {
        // Arrange
        $userCash = [];
        $partnerCash = [];

        // Act
        $result = $this->validator->validateMinimumCashAmounts($userCash, $partnerCash);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function validateSalaryCaps_withValidCaps_returnsValid()
    {
        // Arrange
        $tradeData = [
            'userCurrentSeasonCapTotal' => 50000,
            'partnerCurrentSeasonCapTotal' => 55000,
            'userCapSentToPartner' => 5000,
            'partnerCapSentToUser' => 4000
        ];

        // Act
        $result = $this->validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(49000, $result['userPostTradeCapTotal']); // 50000 - 5000 + 4000
        $this->assertEquals(56000, $result['partnerPostTradeCapTotal']); // 55000 - 4000 + 5000
    }

    /**
     * @test
     */
    public function validateSalaryCaps_withUserOverCap_returnsInvalid()
    {
        // Arrange
        $tradeData = [
            'userCurrentSeasonCapTotal' => 70000,
            'partnerCurrentSeasonCapTotal' => 50000,
            'userCapSentToPartner' => 1000,
            'partnerCapSentToUser' => 10000
        ];

        // Act
        $result = $this->validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertContains('This trade is illegal since it puts you over the hard cap.', $result['errors']);
        $this->assertEquals(79000, $result['userPostTradeCapTotal']); // Over 75000 cap
    }

    /**
     * @test
     */
    public function validateSalaryCaps_withPartnerOverCap_returnsInvalid()
    {
        // Arrange
        $tradeData = [
            'userCurrentSeasonCapTotal' => 50000,
            'partnerCurrentSeasonCapTotal' => 70000,
            'userCapSentToPartner' => 10000,
            'partnerCapSentToUser' => 1000
        ];

        // Act
        $result = $this->validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertContains('This trade is illegal since it puts other team over the hard cap.', $result['errors']);
        $this->assertEquals(79000, $result['partnerPostTradeCapTotal']); // Over 75000 cap
    }

    /**
     * @test
     */
    public function getCurrentSeasonCashConsiderations_inRegularSeason_returnsFirstYearValues()
    {
        // Arrange
        $userCash = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerCash = [1 => 150, 2 => 250, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->validator->getCurrentSeasonCashConsiderations($userCash, $partnerCash);

        // Assert
        $this->assertEquals(100, $result['cashSentToThem']);
        $this->assertEquals(150, $result['cashSentToMe']);
    }

    /**
     * @test
     */
    public function canPlayerBeTraded_withValidPlayer_returnsTrue()
    {
        // Arrange
        $playerId = 12345;
        $this->mockDb->setMockData([
            ['ordinal' => 1000, 'cy' => 5000] // Valid player: low ordinal, has contract
        ]);

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function canPlayerBeTraded_withWaivedPlayer_returnsFalse()
    {
        // Arrange
        $playerId = 12345;
        $this->mockDb->setMockData([
            ['ordinal' => 60000, 'cy' => 5000] // Waived player: high ordinal
        ]);

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canPlayerBeTraded_withZeroSalaryPlayer_returnsFalse()
    {
        // Arrange
        $playerId = 12345;
        $this->mockDb->setMockData([
            ['ordinal' => 1000, 'cy' => 0] // No contract
        ]);

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canPlayerBeTraded_withNonexistentPlayer_returnsFalse()
    {
        // Arrange
        $playerId = 99999;
        $this->mockDb->setMockData([]); // No player found

        // Act
        $result = $this->validator->canPlayerBeTraded($playerId);

        // Assert
        $this->assertFalse($result);
    }
}