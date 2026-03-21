<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;
use Updater\UpdaterService;

/**
 * @covers \Updater\UpdaterService
 */
class UpdaterServiceTest extends TestCase
{
    private UpdaterService $service;

    protected function setUp(): void
    {
        $this->service = new UpdaterService();
    }

    public function testRunReturnsEmptyArrayWithNoSteps(): void
    {
        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame([], $results);
    }

    public function testRunReturnsOneResultForOneStep(): void
    {
        $step = $this->createSuccessStep('Step 1');
        $this->service->addStep($step);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(1, $results);
    }

    public function testRunCallsOnStepStartCallback(): void
    {
        $step = $this->createSuccessStep('Step 1');
        $this->service->addStep($step);

        $started = [];
        $this->service->run(
            static function (PipelineStepInterface $step) use (&$started): void {
                $started[] = $step->getLabel();
            },
            static function (StepResult $result): void {},
        );

        $this->assertSame(['Step 1'], $started);
    }

    public function testRunCallsOnStepCompleteCallback(): void
    {
        $step = $this->createSuccessStep('Step 1');
        $this->service->addStep($step);

        $completed = [];
        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result) use (&$completed): void {
                $completed[] = $result->label;
            },
        );

        $this->assertSame(['Step 1'], $completed);
    }

    public function testRunCallsCallbacksInCorrectOrder(): void
    {
        $step = $this->createSuccessStep('Step 1');
        $this->service->addStep($step);

        $events = [];
        $this->service->run(
            static function (PipelineStepInterface $step) use (&$events): void {
                $events[] = 'start:' . $step->getLabel();
            },
            static function (StepResult $result) use (&$events): void {
                $events[] = 'complete:' . $result->label;
            },
        );

        $this->assertSame(['start:Step 1', 'complete:Step 1'], $events);
    }

    public function testSuccessfulStepIncrementsSuccessCount(): void
    {
        $this->service->addStep($this->createSuccessStep('Step 1'));

        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(1, $this->service->getSuccessCount());
        $this->assertSame(0, $this->service->getErrorCount());
    }

    public function testFailedStepIncrementsErrorCount(): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Failing step');
        $step->method('execute')->willReturn(StepResult::failure('Failing step', 'Something broke'));
        $this->service->addStep($step);

        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(0, $this->service->getSuccessCount());
        $this->assertSame(1, $this->service->getErrorCount());
    }

    public function testMixedStepsCountedCorrectly(): void
    {
        $this->service->addStep($this->createSuccessStep('OK 1'));
        $this->service->addStep($this->createSuccessStep('OK 2'));

        $failStep = $this->createStub(PipelineStepInterface::class);
        $failStep->method('getLabel')->willReturn('Fail');
        $failStep->method('execute')->willReturn(StepResult::failure('Fail', 'error'));
        $this->service->addStep($failStep);

        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame(2, $this->service->getSuccessCount());
        $this->assertSame(1, $this->service->getErrorCount());
    }

    public function testThrowingStepProducesFailureResult(): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Boom');
        $step->method('execute')->willThrowException(new \RuntimeException('Unexpected error'));
        $this->service->addStep($step);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertSame('Unexpected error', $results[0]->errorMessage);
        $this->assertSame('Boom', $results[0]->label);
    }

    public function testThrowingStepStillCallsOnStepComplete(): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Boom');
        $step->method('execute')->willThrowException(new \RuntimeException('err'));
        $this->service->addStep($step);

        $completed = [];
        $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result) use (&$completed): void {
                $completed[] = $result->label;
            },
        );

        $this->assertSame(['Boom'], $completed);
    }

    public function testRunResetsCountsBetweenCalls(): void
    {
        $this->service->addStep($this->createSuccessStep('Step 1'));
        $noop = static function (PipelineStepInterface $step): void {};
        $noopResult = static function (StepResult $result): void {};

        $this->service->run($noop, $noopResult);
        $this->assertSame(1, $this->service->getSuccessCount());

        // Second run — steps are still registered, counts should reset
        $this->service->run($noop, $noopResult);
        $this->assertSame(1, $this->service->getSuccessCount());
        $this->assertSame(0, $this->service->getErrorCount());
    }

    public function testRunResultsMatchStepCount(): void
    {
        $this->service->addStep($this->createSuccessStep('A'));
        $this->service->addStep($this->createSuccessStep('B'));
        $this->service->addStep($this->createSuccessStep('C'));

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertCount(3, $results);
    }

    public function testStepLabelUsedInExceptionWrapping(): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn('Custom Label');
        $step->method('execute')->willThrowException(new \RuntimeException('msg'));
        $this->service->addStep($step);

        $results = $this->service->run(
            static function (PipelineStepInterface $step): void {},
            static function (StepResult $result): void {},
        );

        $this->assertSame('Custom Label', $results[0]->label);
    }

    private function createSuccessStep(string $label): PipelineStepInterface
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn($label);
        $step->method('execute')->willReturn(StepResult::success($label));
        return $step;
    }
}
