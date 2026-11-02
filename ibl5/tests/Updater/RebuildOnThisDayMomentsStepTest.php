<?php

declare(strict_types=1);

namespace Tests\Updater;

use Clock\ClockInterface;
use OnThisDay\Contracts\OnThisDayMomentBuilderInterface;
use PHPUnit\Framework\TestCase;
use Updater\Steps\RebuildOnThisDayMomentsStep;

/**
 * @covers \Updater\Steps\RebuildOnThisDayMomentsStep
 */
final class RebuildOnThisDayMomentsStepTest extends TestCase
{
    public function testRebuildsAndReportsRowCountWithinBudget(): void
    {
        $builder = self::createMock(OnThisDayMomentBuilderInterface::class);
        $builder->expects($this->once())->method('rebuild')->willReturn(42);

        $result = $this->buildStep($builder, 1010.0, 1000.0)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        $this->assertSame('on this day moments rebuilt', $result->label);
        $this->assertStringContainsString('42 moments', $result->detail);
    }

    public function testSkipsWhenOverBudget(): void
    {
        $builder = self::createMock(OnThisDayMomentBuilderInterface::class);
        $builder->expects($this->never())->method('rebuild');

        $result = $this->buildStep($builder, 1121.0, 1000.0)->execute();

        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('121.0 s', $result->detail);
        $this->assertStringContainsString('120 s budget', $result->detail);
    }

    public function testBudgetBoundaryIsInclusive(): void
    {
        $builder = self::createMock(OnThisDayMomentBuilderInterface::class);
        $builder->expects($this->once())->method('rebuild')->willReturn(0);

        $result = $this->buildStep($builder, 1120.0, 1000.0)->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
    }

    public function testBuilderExceptionBecomesFailure(): void
    {
        $builder = self::createStub(OnThisDayMomentBuilderInterface::class);
        $builder->method('rebuild')->willThrowException(new \RuntimeException('boom'));

        $result = $this->buildStep($builder, 1010.0, 1000.0)->execute();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('boom', $result->errorMessage);
    }

    private function buildStep(
        OnThisDayMomentBuilderInterface $builder,
        float $now,
        float $startedAt,
    ): RebuildOnThisDayMomentsStep {
        $clock = self::createStub(ClockInterface::class);
        $clock->method('microtime')->willReturn($now);

        return new RebuildOnThisDayMomentsStep(
            static fn (): OnThisDayMomentBuilderInterface => $builder,
            $clock,
            $startedAt,
        );
    }
}
