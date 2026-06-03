<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeExecutionRepositoryInterface;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Trading\TradeProcessor;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TradeProcessorTest - Tests for TradeProcessor
 */
class TradeProcessorTest extends TestCase
{
    private MockDatabase $mockDb;
    private TeamIdentityRepositoryInterface $mockCommonRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
    }

    // ============================================
    // PROCESS TRADE TESTS
    // ============================================

    public function testProcessTradeReturnsErrorWhenNoTradeDataFound(): void
    {
        $processor = new TradeProcessor($this->mockDb, $this->mockCommonRepo);

        $result = $processor->processTrade(99999);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }

    // ============================================
    // CONSTRUCTOR INJECTION
    // ============================================

    /**
     * The real constructor accepts every collaborator as an injected double and
     * wires them in place of its internal `new`s — the injected offer repository
     * is the one consulted by processTrade().
     */
    public function testConstructableWithInjectedCollaborators(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $offerRepository->expects($this->once())
            ->method('getTradesByOfferIdForUpdate')
            ->with(99999)
            ->willReturn([]);

        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepository = self::createStub(TradeCashRepositoryInterface::class);
        $cashConsiderationRepository = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $executionRepository = self::createStub(TradeExecutionRepositoryInterface::class);
        $season = self::createStub(Season::class);

        $processor = new TradeProcessor(
            $this->mockDb,
            $this->mockCommonRepo,
            '',
            $offerRepository,
            $assetRepository,
            $cashRepository,
            $cashConsiderationRepository,
            $executionRepository,
            $season,
        );

        $result = $processor->processTrade(99999);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }
}
