<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeOfferRepository;
use Trading\TradeAssetRepository;
use Trading\TradeFormRepository;
use Tests\WideUnit\Mocks\MockDatabase;

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
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // TRADE OFFER REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeOfferRepositoryImplementsCorrectInterface(): void
    {
        self::assertContains(
            \Trading\Contracts\TradeOfferRepositoryInterface::class,
            (array) class_implements(TradeOfferRepository::class)
        );
    }

    public function testTradeOfferRepositoryExtendsBaseMysqliRepository(): void
    {
        self::assertContains(
            \BaseMysqliRepository::class,
            (array) class_parents(TradeOfferRepository::class)
        );
    }

    // ============================================
    // TRADE ASSET REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeAssetRepositoryImplementsCorrectInterface(): void
    {
        self::assertContains(
            \Trading\Contracts\TradeAssetRepositoryInterface::class,
            (array) class_implements(TradeAssetRepository::class)
        );
    }

    public function testTradeAssetRepositoryExtendsBaseMysqliRepository(): void
    {
        self::assertContains(
            \BaseMysqliRepository::class,
            (array) class_parents(TradeAssetRepository::class)
        );
    }

    // ============================================
    // TRADE FORM REPOSITORY - CONSTRUCTOR TESTS
    // ============================================

    public function testTradeFormRepositoryImplementsCorrectInterface(): void
    {
        self::assertContains(
            \Trading\Contracts\TradeFormRepositoryInterface::class,
            (array) class_implements(TradeFormRepository::class)
        );
    }

    public function testTradeFormRepositoryExtendsBaseMysqliRepository(): void
    {
        self::assertContains(
            \BaseMysqliRepository::class,
            (array) class_parents(TradeFormRepository::class)
        );
    }

    // ============================================
    // TRADE ASSET REPOSITORY - QUERY TESTS
    // ============================================

    public function testGetPlayerForTradeValidationReturnsNullWhenNoData(): void
    {
        $repository = new TradeAssetRepository($this->mockDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getPlayerForTradeValidation(1);

        $this->assertNull($result);
    }

    public function testGetPlayerForTradeValidationReturnsPlayerData(): void
    {
        $repository = new TradeAssetRepository($this->mockDb);
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
        $repository = new TradeFormRepository($this->mockDb);
        $this->mockDb->setMockData([
            ['pos' => 'PG', 'name' => 'Guard One', 'pid' => 1, 'ordinal' => 10, 'cy' => 2, 'salary_yr1' => 500, 'salary_yr2' => 525, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ['pos' => 'C', 'name' => 'Center Two', 'pid' => 2, 'ordinal' => 20, 'cy' => 1, 'salary_yr1' => 800, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ]);

        $result = $repository->getTeamPlayersForTrading(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Guard One', $result[0]['name']);
        $this->assertSame('PG', $result[0]['pos']);
    }

    public function testGetTeamPlayersForTradingReturnsEmptyArray(): void
    {
        $repository = new TradeFormRepository($this->mockDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamPlayersForTrading(99);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTeamDraftPicksForTradingReturnsDraftPicks(): void
    {
        $repository = new TradeFormRepository($this->mockDb);
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
        $repository = new TradeFormRepository($this->mockDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamDraftPicksForTrading(99);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
