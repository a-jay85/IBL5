<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\StepResult;

/**
 * @covers \Updater\StepResult
 */
class StepResultTest extends TestCase
{
    public function testSuccessFactorySetsSuccessTrue(): void
    {
        $result = StepResult::success('Import players');

        $this->assertTrue($result->success);
    }

    public function testSuccessFactoryStoresLabel(): void
    {
        $result = StepResult::success('Import players');

        $this->assertSame('Import players', $result->label);
    }

    public function testSuccessFactoryDefaultsAreEmpty(): void
    {
        $result = StepResult::success('Import players');

        $this->assertSame('', $result->detail);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame('', $result->errorMessage);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSuccessFactoryAcceptsOptionalParameters(): void
    {
        $result = StepResult::success(
            label: 'Import players',
            detail: '82 players imported',
            capturedLog: 'log output here',
            inlineHtml: '<p>Done</p>',
            messages: ['msg1', 'msg2'],
            messageErrorCount: 1,
        );

        $this->assertSame('82 players imported', $result->detail);
        $this->assertSame('log output here', $result->capturedLog);
        $this->assertSame('<p>Done</p>', $result->inlineHtml);
        $this->assertSame(['msg1', 'msg2'], $result->messages);
        $this->assertSame(1, $result->messageErrorCount);
    }

    public function testFailureFactorySetsSuccessFalse(): void
    {
        $result = StepResult::failure('Import players', 'File not found');

        $this->assertFalse($result->success);
    }

    public function testFailureFactoryStoresLabelAndErrorMessage(): void
    {
        $result = StepResult::failure('Import players', 'File not found');

        $this->assertSame('Import players', $result->label);
        $this->assertSame('File not found', $result->errorMessage);
    }

    public function testFailureFactoryDefaultsOtherFieldsEmpty(): void
    {
        $result = StepResult::failure('Import players', 'File not found');

        $this->assertSame('', $result->detail);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSkippedFactorySetsSuccessTrue(): void
    {
        $result = StepResult::skipped('Import players', 'Already imported');

        $this->assertTrue($result->success);
    }

    public function testSkippedFactoryStoresReasonAsDetail(): void
    {
        $result = StepResult::skipped('Import players', 'Already imported');

        $this->assertSame('Import players', $result->label);
        $this->assertSame('Already imported', $result->detail);
    }

    public function testSkippedFactoryDefaultsErrorMessageEmpty(): void
    {
        $result = StepResult::skipped('Import players', 'Already imported');

        $this->assertSame('', $result->errorMessage);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }
}
