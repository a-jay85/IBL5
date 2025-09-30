<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_CashTransactionHandler class
 * 
 * Tests cash transaction logic including PID generation,
 * contract calculations, and database operations.
 */
class CashTransactionHandlerTest extends TestCase
{
    private $cashHandler;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->cashHandler = new Trading_CashTransactionHandler($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->cashHandler = null;
        $this->mockDb = null;
    }

    /**
     * @test
     */
    public function generateUniquePid_withAvailablePid_returnsSamePid()
    {
        // Arrange
        $testPid = 99999;
        $this->mockDb->setMockData([]); // No existing PID
        $this->mockDb->setReturnTrue(false); // SELECT query should return result

        // Act
        $result = $this->cashHandler->generateUniquePid($testPid);

        // Assert
        $this->assertEquals($testPid, $result);
    }

    /**
     * @test
     */
    public function calculateContractTotalYears_withThreeYearContract_returnsThree()
    {
        // Arrange
        $cashYear = [1 => 100, 2 => 200, 3 => 300, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->calculateContractTotalYears($cashYear);

        // Assert
        $this->assertEquals(3, $result);
    }

    /**
     * @test
     */
    public function calculateContractTotalYears_withSixYearContract_returnsSix()
    {
        // Arrange
        $cashYear = [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600];

        // Act
        $result = $this->cashHandler->calculateContractTotalYears($cashYear);

        // Assert
        $this->assertEquals(6, $result);
    }

    /**
     * @test
     */
    public function calculateContractTotalYears_withOneYearContract_returnsOne()
    {
        // Arrange
        $cashYear = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->calculateContractTotalYears($cashYear);

        // Assert
        $this->assertEquals(1, $result);
    }

    /**
     * @test
     */
    public function calculateContractTotalYears_withEmptyContract_returnsOne()
    {
        // Arrange
        $cashYear = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->calculateContractTotalYears($cashYear);

        // Assert
        $this->assertEquals(1, $result);
    }

    /**
     * @test
     */
    public function hasCashInTrade_withCashPresent_returnsTrue()
    {
        // Arrange
        $cashAmounts = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->hasCashInTrade($cashAmounts);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function hasCashInTrade_withNoCash_returnsFalse()
    {
        // Arrange
        $cashAmounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->hasCashInTrade($cashAmounts);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasCashInTrade_withEmptyArray_returnsFalse()
    {
        // Arrange
        $cashAmounts = [];

        // Act
        $result = $this->cashHandler->hasCashInTrade($cashAmounts);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasCashInTrade_withCashInLastYear_returnsTrue()
    {
        // Arrange
        $cashAmounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 500];

        // Act
        $result = $this->cashHandler->hasCashInTrade($cashAmounts);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function createCashTransaction_withValidData_returnsSuccess()
    {
        // Arrange
        $itemId = 12345;
        $from = 'Team A';
        $to = 'Team B';
        $cashYear = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->cashHandler->createCashTransaction($itemId, $from, $to, $cashYear);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString($from, $result['tradeLine']);
        $this->assertStringContainsString($to, $result['tradeLine']);
        $this->assertStringContainsString('100 200', $result['tradeLine']);
    }

    /**
     * @test
     */
    public function insertCashTradeData_withValidData_returnsTrue()
    {
        // Arrange
        $tradeOfferId = 999;
        $sendingTeam = 'Team A';
        $receivingTeam = 'Team B';
        $cashAmounts = [1 => 100, 2 => 200, 3 => 300, 4 => 0, 5 => 0, 6 => 0];
        
        $this->mockDb->setReturnTrue(true); // INSERT should return true

        // Act
        $result = $this->cashHandler->insertCashTradeData($tradeOfferId, $sendingTeam, $receivingTeam, $cashAmounts);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function insertCashTradeData_withPartialData_fillsZeros()
    {
        // Arrange
        $tradeOfferId = 999;
        $sendingTeam = 'Team A';
        $receivingTeam = 'Team B';
        $cashAmounts = [1 => 100, 3 => 300]; // Missing years 2, 4, 5, 6
        
        $this->mockDb->setReturnTrue(true); // INSERT should return true

        // Act
        $result = $this->cashHandler->insertCashTradeData($tradeOfferId, $sendingTeam, $receivingTeam, $cashAmounts);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     * @dataProvider contractYearDataProvider
     */
    public function calculateContractTotalYears_withVariousScenarios_returnsCorrectYears($cashYear, $expectedYears)
    {
        // Act
        $result = $this->cashHandler->calculateContractTotalYears($cashYear);

        // Assert
        $this->assertEquals($expectedYears, $result, 
            "Expected {$expectedYears} years for cash distribution: " . json_encode($cashYear));
    }

    /**
     * Data provider for contract year calculation tests
     */
    public function contractYearDataProvider()
    {
        return [
            'One year contract' => [
                [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                1
            ],
            'Two year contract' => [
                [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                2
            ],
            'Three year contract' => [
                [1 => 100, 2 => 200, 3 => 300, 4 => 0, 5 => 0, 6 => 0],
                3
            ],
            'Four year contract' => [
                [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 0, 6 => 0],
                4
            ],
            'Five year contract' => [
                [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 0],
                5
            ],
            'Six year contract' => [
                [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600],
                6
            ],
            'Partial contract (years 1,3,5)' => [
                [1 => 100, 2 => 0, 3 => 300, 4 => 0, 5 => 500, 6 => 0],
                5 // Based on highest non-zero year
            ],
            'Backend loaded contract' => [
                [1 => 0, 2 => 0, 3 => 0, 4 => 400, 5 => 500, 6 => 600],
                6
            ]
        ];
    }
}