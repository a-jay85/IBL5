<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Services\CommonMysqliRepository;
use Trading\Contracts\TradeExecutionRepositoryInterface;
use Trading\TradeQueueProcessor;

class TradeQueueProcessorTest extends TestCase
{
    private CommonMysqliRepository $stubCommonRepo;

    protected function setUp(): void
    {
        $mockDb = new class extends \mysqli {
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct()
            {
            }
        };
        $this->stubCommonRepo = new CommonMysqliRepository($mockDb);
    }

    // ============================================
    // EMPTY QUEUE
    // ============================================

    public function testEmptyQueueReturnsZeroCounts(): void
    {
        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn([]);
        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);

        $result = $processor->processQueue();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['messages']);
    }

    // ============================================
    // PLAYER TRANSFER
    // ============================================

    public function testPlayerTransferSuccessIncrementsProcessed(): void
    {
        $trade = $this->makeQueuedTrade(1, 'player_transfer', ['player_id' => 42, 'team_id' => 5], 'Player X to Team Y');

        $mock = $this->createMock(TradeExecutionRepositoryInterface::class);
        $mock->method('getQueuedTrades')->willReturn([$trade]);
        $mock->expects($this->once())
            ->method('executeQueuedPlayerTransfer')
            ->with(42, 5)
            ->willReturn(1);
        $mock->expects($this->once())
            ->method('deleteQueuedTrade')
            ->with(1);

        $processor = new TradeQueueProcessor($mock, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(['Player X to Team Y'], $result['messages']);
    }

    public function testPlayerTransferFailureIncrementsFailedAndKeepsTrade(): void
    {
        $trade = $this->makeQueuedTrade(1, 'player_transfer', ['player_id' => 42, 'team_id' => 5], 'Player X to Team Y');

        $mock = $this->createMock(TradeExecutionRepositoryInterface::class);
        $mock->method('getQueuedTrades')->willReturn([$trade]);
        $mock->method('executeQueuedPlayerTransfer')->willReturn(0);
        $mock->expects($this->never())->method('deleteQueuedTrade');

        $processor = new TradeQueueProcessor($mock, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('FAILED:', $result['messages'][0]);
    }

    public function testPlayerTransferMissingParamsFails(): void
    {
        $trade = $this->makeQueuedTrade(1, 'player_transfer', ['player_id' => 42], 'Incomplete trade');

        $mock = $this->createMock(TradeExecutionRepositoryInterface::class);
        $mock->method('getQueuedTrades')->willReturn([$trade]);
        $mock->expects($this->never())->method('deleteQueuedTrade');

        $processor = new TradeQueueProcessor($mock, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Missing required', $result['messages'][0]);
    }

    // ============================================
    // PICK TRANSFER
    // ============================================

    public function testPickTransferSuccessWithOwnerId(): void
    {
        $trade = $this->makeQueuedTrade(2, 'pick_transfer', ['pick_id' => 10, 'new_owner' => 'Miami', 'new_owner_id' => 7], 'Pick to Miami');

        $mock = $this->createMock(TradeExecutionRepositoryInterface::class);
        $mock->method('getQueuedTrades')->willReturn([$trade]);
        $mock->expects($this->once())
            ->method('executeQueuedPickTransfer')
            ->with(10, 'Miami', 7)
            ->willReturn(1);
        $mock->expects($this->once())->method('deleteQueuedTrade')->with(2);

        $processor = new TradeQueueProcessor($mock, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['processed']);
    }

    public function testPickTransferFailureIncrementsFailedCount(): void
    {
        $trade = $this->makeQueuedTrade(2, 'pick_transfer', ['pick_id' => 10, 'new_owner' => 'Miami', 'new_owner_id' => 7], 'Pick to Miami');

        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn([$trade]);
        $stub->method('executeQueuedPickTransfer')->willReturn(0);

        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('FAILED:', $result['messages'][0]);
    }

    public function testPickTransferMissingParamsFails(): void
    {
        $trade = $this->makeQueuedTrade(3, 'pick_transfer', ['pick_id' => 10], 'Missing owner');

        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn([$trade]);

        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Missing required', $result['messages'][0]);
    }

    // ============================================
    // INVALID JSON / UNKNOWN OPERATION
    // ============================================

    public function testInvalidJsonParamsFails(): void
    {
        $trade = [
            'id' => 1,
            'operation_type' => 'player_transfer',
            'params' => '{invalid json}',
            'tradeline' => 'Bad trade',
        ];

        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn([$trade]);

        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Invalid JSON', $result['messages'][0]);
    }

    public function testUnknownOperationTypeFails(): void
    {
        $trade = $this->makeQueuedTrade(1, 'salary_adjustment', ['amount' => 100], 'Unknown op');

        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn([$trade]);

        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Unknown operation type', $result['messages'][0]);
    }

    // ============================================
    // MULTIPLE TRADES
    // ============================================

    public function testMultipleTradesMixOfSuccessAndFailure(): void
    {
        $trades = [
            $this->makeQueuedTrade(1, 'player_transfer', ['player_id' => 1, 'team_id' => 2], 'Trade 1'),
            $this->makeQueuedTrade(2, 'player_transfer', ['player_id' => 3, 'team_id' => 4], 'Trade 2'),
            $this->makeQueuedTrade(3, 'pick_transfer', ['pick_id' => 5, 'new_owner' => 'LA', 'new_owner_id' => 6], 'Trade 3'),
        ];

        $stub = $this->createStub(TradeExecutionRepositoryInterface::class);
        $stub->method('getQueuedTrades')->willReturn($trades);
        $stub->method('executeQueuedPlayerTransfer')
            ->willReturnMap([
                [1, 2, 1], // success
                [3, 4, 0], // failure
            ]);
        $stub->method('executeQueuedPickTransfer')->willReturn(1);

        $processor = new TradeQueueProcessor($stub, $this->stubCommonRepo);
        $result = $processor->processQueue();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(3, $result['messages']);
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * @param array<string, int|string> $params
     * @return array{id: int, operation_type: string, params: string, tradeline: string}
     */
    private function makeQueuedTrade(int $id, string $operationType, array $params, string $tradeline): array
    {
        return [
            'id' => $id,
            'operation_type' => $operationType,
            'params' => json_encode($params, JSON_THROW_ON_ERROR),
            'tradeline' => $tradeline,
        ];
    }
}
