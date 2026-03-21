<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeCashRepository;

/**
 * TradeCashRepositoryTest - Tests for TradeCashRepository database operations
 *
 * Tests cash transaction operations: cash player records,
 * cash trade offers, team cash salary records, and bulk clear.
 */
class TradeCashRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class ($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $this->assertInstanceOf(TradeCashRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \Trading\Contracts\TradeCashRepositoryInterface::class,
            $repository
        );
    }

    // ============================================
    // GET CASH TRANSACTION BY OFFER TESTS
    // ============================================

    public function testGetCashTransactionByOfferReturnsDataWhenFound(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['tradeOfferID' => 5, 'sendingTeam' => 'Boston', 'receivingTeam' => 'Denver', 'cy1' => 50, 'cy2' => 75, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ]);

        $result = $repository->getCashTransactionByOffer(5, 'Boston');

        $this->assertIsArray($result);
        $this->assertSame(5, $result['tradeOfferID']);
    }

    public function testGetCashTransactionByOfferReturnsNullWhenNotFound(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getCashTransactionByOffer(999, 'Nobody');

        $this->assertNull($result);
    }

    // ============================================
    // INSERT CASH PLAYER RECORD TESTS
    // ============================================

    public function testInsertCashPlayerRecordExecutesInsert(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $data = [
            'ordinal' => 999,
            'pid' => 9999,
            'name' => '|Cash',
            'tid' => 1,
            'teamname' => 'Miami',
            'exp' => 0,
            'cy' => 0,
            'cyt' => 'cash',
            'cy1' => 100,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'retired' => 0,
        ];

        $result = $repository->insertCashPlayerRecord($data);

        $this->assertIsInt($result);
    }

    // ============================================
    // GET TEAM CASH RECORDS FOR SALARY TESTS
    // ============================================

    public function testGetTeamCashRecordsForSalaryReturnsResults(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['pos' => '', 'name' => '|Cash From Trade', 'pid' => 9999, 'ordinal' => 999, 'cy' => 0, 'cy1' => 100, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ]);

        $result = $repository->getTeamCashRecordsForSalary(1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('|Cash From Trade', $result[0]['name']);
    }

    public function testGetTeamCashRecordsForSalaryReturnsEmptyArray(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamCashRecordsForSalary(1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // CLEAR TRADE CASH TESTS
    // ============================================

    public function testClearTradeCashExecutesDelete(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $result = $repository->clearTradeCash();

        $this->assertIsInt($result);
    }

    // ============================================
    // INSERT CASH TRADE OFFER TESTS
    // ============================================

    public function testInsertCashTradeOfferExecutesInsert(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $result = $repository->insertCashTradeOffer(1, 'Miami', 'Chicago', 100, 200, 0, 0, 0, 0);

        $this->assertIsInt($result);
    }

    // ============================================
    // DELETE TRADE CASH BY OFFER ID TESTS
    // ============================================

    public function testDeleteTradeCashByOfferIdExecutesDelete(): void
    {
        $repository = new TradeCashRepository($this->mockMysqliDb);

        $result = $repository->deleteTradeCashByOfferId(1);

        $this->assertIsInt($result);
    }
}
