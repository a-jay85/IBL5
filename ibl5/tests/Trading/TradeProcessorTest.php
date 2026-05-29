<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\TradeProcessor;
use Trading\Contracts\TradeProcessorInterface;
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
        $this->mockCommonRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $processor = new TradeProcessor($this->mockDb, $this->mockCommonRepo);

        $this->assertInstanceOf(TradeProcessor::class, $processor);
    }

    public function testImplementsInterface(): void
    {
        $processor = new TradeProcessor($this->mockDb, $this->mockCommonRepo);

        $this->assertInstanceOf(TradeProcessorInterface::class, $processor);
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
}
