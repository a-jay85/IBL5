<?php

declare(strict_types=1);

namespace Tests\Updater;

use Clock\ClockInterface;
use PHPUnit\Framework\TestCase;
use RecordHolders\CachedRecordHoldersService;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshRecordHoldersStep;

/**
 * @covers \Updater\Steps\RefreshRecordHoldersStep
 */
class RefreshRecordHoldersStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshRecordHoldersStep::class)
        );
    }

    public function testGetLabelReturnsExpectedString(): void
    {
        $step = $this->buildStep(
            self::createStub(CachedRecordHoldersService::class),
            1000.0,
            1000.0,
        );

        $this->assertSame('record holders cache refreshed', $step->getLabel());
    }

    public function testExecuteCallsRebuildCacheOnceWithinBudget(): void
    {
        $service = self::createMock(CachedRecordHoldersService::class);
        $service->expects($this->once())->method('rebuildCache')->willReturn([]);

        $result = $this->buildStep($service, 1000.0, 950.0)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
    }

    public function testExecuteRunsRebuildAtExactBudgetBoundary(): void
    {
        $service = self::createMock(CachedRecordHoldersService::class);
        $service->expects($this->once())->method('rebuildCache')->willReturn([]);

        $result = $this->buildStep($service, 1180.0, 1000.0)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
    }

    public function testExecuteSkipsWithoutRebuildWhenOverBudget(): void
    {
        $service = self::createMock(CachedRecordHoldersService::class);
        $service->expects($this->never())->method('rebuildCache');

        $factoryCalled = false;
        $clock = self::createStub(ClockInterface::class);
        $clock->method('microtime')->willReturn(1200.0);
        $step = new RefreshRecordHoldersStep(
            static function () use ($service, &$factoryCalled): CachedRecordHoldersService {
                $factoryCalled = true;
                return $service;
            },
            $clock,
            1000.0,
        );

        $result = $step->execute();

        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('200.0 s', $result->detail);
        $this->assertFalse($factoryCalled);
    }

    public function testExecuteReturnsFailureWhenRebuildThrows(): void
    {
        $service = self::createStub(CachedRecordHoldersService::class);
        $service->method('rebuildCache')->willThrowException(new \RuntimeException('db gone'));

        $result = $this->buildStep($service, 1000.0, 950.0)->execute();

        $this->assertFalse($result->success);
        $this->assertSame('db gone', $result->errorMessage);
    }

    public function testExecuteReturnsFailureWhenFactoryThrows(): void
    {
        $clock = self::createStub(ClockInterface::class);
        $clock->method('microtime')->willReturn(1000.0);
        $step = new RefreshRecordHoldersStep(
            static function (): CachedRecordHoldersService {
                throw new \RuntimeException('connect failed');
            },
            $clock,
            950.0,
        );

        $result = $step->execute();

        $this->assertFalse($result->success);
        $this->assertSame('connect failed', $result->errorMessage);
    }

    private function buildStep(CachedRecordHoldersService $service, float $now, float $startedAt): RefreshRecordHoldersStep
    {
        $clock = self::createStub(ClockInterface::class);
        $clock->method('microtime')->willReturn($now);

        return new RefreshRecordHoldersStep(
            static fn (): CachedRecordHoldersService => $service,
            $clock,
            $startedAt,
        );
    }
}
