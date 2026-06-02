<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\TradeProcessor;
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
}
