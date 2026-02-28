<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Contracts\UpdaterServiceInterface;
use Updater\StepResult;
use Updater\UpdaterService;

class UpdaterServiceTest extends TestCase
{
    private UpdaterService $service;

    protected function setUp(): void
    {
        $this->service = new UpdaterService();
    }

    public function testImplementsUpdaterServiceInterface(): void
    {
        $this->assertInstanceOf(UpdaterServiceInterface::class, $this->service);
    }

    public function testRunWithNoStepsReturnsEmptyResults(): void
    {
        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame([], $results);
        $this->assertSame(0, $this->service->getSuccessCount());
        $this->assertSame(0, $this->service->getErrorCount());
    }

    public function testRunExecutesStepsInOrder(): void
    {
        $executionOrder = [];

        $step1 = $this->createStub(PipelineStepInterface::class);
        $step1->method('getLabel')->willReturn('Step 1');
        $step1->method('execute')->willReturnCallback(static function () use (&$executionOrder): StepResult {
            $executionOrder[] = 'Step 1';
            return StepResult::success('Step 1');
        });

        $step2 = $this->createStub(PipelineStepInterface::class);
        $step2->method('getLabel')->willReturn('Step 2');
        $step2->method('execute')->willReturnCallback(static function () use (&$executionOrder): StepResult {
            $executionOrder[] = 'Step 2';
            return StepResult::success('Step 2');
        });

        $this->service->addStep($step1);
        $this->service->addStep($step2);

        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(['Step 1', 'Step 2'], $executionOrder);
    }

    public function testRunCallsOnStepStartBeforeExecution(): void
    {
        $events = [];

        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Test Step');
        $step->method('execute')->willReturnCallback(static function () use (&$events): StepResult {
            $events[] = 'execute';
            return StepResult::success('Test Step');
        });

        $this->service->addStep($step);

        $this->service->run(
            static function (PipelineStepInterface $step) use (&$events): void {
                $events[] = 'onStart:' . $step->getLabel();
            },
            static function (StepResult $result) use (&$events): void {
                $events[] = 'onComplete:' . $result->label;
            },
        );

        $this->assertSame(['onStart:Test Step', 'execute', 'onComplete:Test Step'], $events);
    }

    public function testRunCountsSuccessesAndErrors(): void
    {
        $successStep = $this->createStub(PipelineStepInterface::class);
        $successStep->method('getLabel')->willReturn('Success');
        $successStep->method('execute')->willReturn(StepResult::success('Success'));

        $failStep = $this->createStub(PipelineStepInterface::class);
        $failStep->method('getLabel')->willReturn('Fail');
        $failStep->method('execute')->willReturn(StepResult::failure('Fail', 'Error'));

        $skipStep = $this->createStub(PipelineStepInterface::class);
        $skipStep->method('getLabel')->willReturn('Skip');
        $skipStep->method('execute')->willReturn(StepResult::skipped('Skip', 'No file'));

        $this->service->addStep($successStep);
        $this->service->addStep($failStep);
        $this->service->addStep($skipStep);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(3, $results);
        $this->assertSame(2, $this->service->getSuccessCount());
        $this->assertSame(1, $this->service->getErrorCount());
    }

    public function testRunContinuesAfterStepFailure(): void
    {
        $failStep = $this->createStub(PipelineStepInterface::class);
        $failStep->method('getLabel')->willReturn('Failing');
        $failStep->method('execute')->willReturn(StepResult::failure('Failing', 'Boom'));

        $afterStep = $this->createStub(PipelineStepInterface::class);
        $afterStep->method('getLabel')->willReturn('After');
        $afterStep->method('execute')->willReturn(StepResult::success('After'));

        $this->service->addStep($failStep);
        $this->service->addStep($afterStep);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]->success);
        $this->assertTrue($results[1]->success);
    }

    public function testRunCatchesExceptionsAndContinues(): void
    {
        $throwingStep = $this->createStub(PipelineStepInterface::class);
        $throwingStep->method('getLabel')->willReturn('Thrower');
        $throwingStep->method('execute')->willThrowException(new \RuntimeException('Database gone'));

        $afterStep = $this->createStub(PipelineStepInterface::class);
        $afterStep->method('getLabel')->willReturn('After');
        $afterStep->method('execute')->willReturn(StepResult::success('After'));

        $this->service->addStep($throwingStep);
        $this->service->addStep($afterStep);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]->success);
        $this->assertSame('Database gone', $results[0]->errorMessage);
        $this->assertSame('Thrower', $results[0]->label);
        $this->assertTrue($results[1]->success);
    }

    public function testRunResetsCountsBetweenRuns(): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Test');
        $step->method('execute')->willReturn(StepResult::success('Test'));

        $this->service->addStep($step);

        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(1, $this->service->getSuccessCount());

        // Run again — counts should reset, not accumulate
        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(1, $this->service->getSuccessCount());
        $this->assertSame(0, $this->service->getErrorCount());
    }

    public function testRunReturnsResultsInStepOrder(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $step = $this->createStub(PipelineStepInterface::class);
            $step->method('getLabel')->willReturn('Step ' . $i);
            $step->method('execute')->willReturn(StepResult::success('Step ' . $i));
            $this->service->addStep($step);
        }

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame('Step 1', $results[0]->label);
        $this->assertSame('Step 2', $results[1]->label);
        $this->assertSame('Step 3', $results[2]->label);
    }
}
