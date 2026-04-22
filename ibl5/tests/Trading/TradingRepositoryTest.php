<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeOfferRepository;
use Trading\TradeAssetRepository;
use Trading\TradeFormRepository;

/**
 * TradingRepositoryTest - Tests for the 3 split Trading repositories
 *
 * Tests:
 * - Repository instantiation
 * - Interface compliance
 * - Query execution via mock
 */
class TradingRepositoryTest extends TestCase
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

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                // Don't call parent::__construct() to avoid real DB connection
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
    // TRADE OFFER REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeOfferRepositoryCanBeInstantiated(): void
    {
        $repository = new TradeOfferRepository($this->mockMysqliDb);

        $this->assertInstanceOf(TradeOfferRepository::class, $repository);
    }

    public function testTradeOfferRepositoryImplementsCorrectInterface(): void
    {
        $repository = new TradeOfferRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \Trading\Contracts\TradeOfferRepositoryInterface::class,
            $repository
        );
    }

    public function testTradeOfferRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new TradeOfferRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // TRADE ASSET REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeAssetRepositoryCanBeInstantiated(): void
    {
        $repository = new TradeAssetRepository($this->mockMysqliDb);

        $this->assertInstanceOf(TradeAssetRepository::class, $repository);
    }

    public function testTradeAssetRepositoryImplementsCorrectInterface(): void
    {
        $repository = new TradeAssetRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \Trading\Contracts\TradeAssetRepositoryInterface::class,
            $repository
        );
    }

    public function testTradeAssetRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new TradeAssetRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // TRADE FORM REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeFormRepositoryCanBeInstantiated(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);

        $this->assertInstanceOf(TradeFormRepository::class, $repository);
    }

    public function testTradeFormRepositoryImplementsCorrectInterface(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \Trading\Contracts\TradeFormRepositoryInterface::class,
            $repository
        );
    }

    public function testTradeFormRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // TRADE ASSET REPOSITORY - QUERY TESTS
    // ============================================

    public function testGetPlayerForTradeValidationReturnsNullWhenNoData(): void
    {
        $repository = new TradeAssetRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getPlayerForTradeValidation(1);

        $this->assertNull($result);
    }

    public function testGetPlayerForTradeValidationReturnsPlayerData(): void
    {
        $repository = new TradeAssetRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['ordinal' => 5, 'cy' => 2]
        ]);

        $result = $repository->getPlayerForTradeValidation(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ordinal', $result);
        $this->assertArrayHasKey('cy', $result);
    }

    // ============================================
    // TRADE FORM REPOSITORY - QUERY TESTS
    // ============================================

    public function testGetTeamPlayersForTradingReturnsPlayerRows(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['pos' => 'PG', 'name' => 'Guard One', 'pid' => 1, 'ordinal' => 10, 'cy' => 2, 'cy1' => 500, 'cy2' => 525, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
            ['pos' => 'C', 'name' => 'Center Two', 'pid' => 2, 'ordinal' => 20, 'cy' => 1, 'cy1' => 800, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ]);

        $result = $repository->getTeamPlayersForTrading(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Guard One', $result[0]['name']);
        $this->assertSame('PG', $result[0]['pos']);
    }

    public function testGetTeamPlayersForTradingReturnsEmptyArray(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamPlayersForTrading(99);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTeamDraftPicksForTradingReturnsDraftPicks(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['pickid' => 1, 'year' => 2026, 'round' => 1, 'pick' => 5, 'owner_teamid' => 1, 'teampick_teamid' => 3],
            ['pickid' => 2, 'year' => 2026, 'round' => 2, 'pick' => 10, 'owner_teamid' => 1, 'teampick_teamid' => 1],
        ]);

        $result = $repository->getTeamDraftPicksForTrading(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetTeamDraftPicksForTradingReturnsEmptyArray(): void
    {
        $repository = new TradeFormRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamDraftPicksForTrading(99);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new TradeOfferRepository($this->mockMysqliDb);
        $repo2 = new TradeAssetRepository($this->mockMysqliDb);
        $repo3 = new TradeFormRepository($this->mockMysqliDb);

        $this->assertInstanceOf(TradeOfferRepository::class, $repo1);
        $this->assertInstanceOf(TradeAssetRepository::class, $repo2);
        $this->assertInstanceOf(TradeFormRepository::class, $repo3);
        $this->assertNotSame($repo1, $repo2);
    }
}
