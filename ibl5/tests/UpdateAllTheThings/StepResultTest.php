<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use PHPUnit\Framework\TestCase;
use Updater\StepResult;

class StepResultTest extends TestCase
{
    public function testSuccessFactoryCreatesSuccessfulResult(): void
    {
        $result = StepResult::success('Schedule updated');

        $this->assertSame('Schedule updated', $result->label);
        $this->assertTrue($result->success);
        $this->assertSame('', $result->detail);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame('', $result->errorMessage);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSuccessFactoryWithAllOptionalParameters(): void
    {
        $result = StepResult::success(
            label: 'JSB files parsed',
            detail: '5 files processed',
            capturedLog: '<p>Processing...</p>',
            inlineHtml: '<div>Results</div>',
            messages: ['File 1 OK', 'ERROR: File 2 failed'],
            messageErrorCount: 1,
        );

        $this->assertSame('JSB files parsed', $result->label);
        $this->assertTrue($result->success);
        $this->assertSame('5 files processed', $result->detail);
        $this->assertSame('<p>Processing...</p>', $result->capturedLog);
        $this->assertSame('<div>Results</div>', $result->inlineHtml);
        $this->assertSame('', $result->errorMessage);
        $this->assertSame(['File 1 OK', 'ERROR: File 2 failed'], $result->messages);
        $this->assertSame(1, $result->messageErrorCount);
    }

    public function testFailureFactoryCreatesFailedResult(): void
    {
        $result = StepResult::failure('League config', 'Connection refused');

        $this->assertSame('League config', $result->label);
        $this->assertFalse($result->success);
        $this->assertSame('Connection refused', $result->errorMessage);
        $this->assertSame('', $result->detail);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSkippedFactoryCreatesSuccessWithReason(): void
    {
        $result = StepResult::skipped('Player file', 'No IBL5.plr file found');

        $this->assertSame('Player file', $result->label);
        $this->assertTrue($result->success);
        $this->assertSame('No IBL5.plr file found', $result->detail);
        $this->assertSame('', $result->errorMessage);
        $this->assertSame('', $result->capturedLog);
        $this->assertSame('', $result->inlineHtml);
        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSuccessWithDetailOnly(): void
    {
        $result = StepResult::success('Depth charts updated', '3 active DCs extended');

        $this->assertTrue($result->success);
        $this->assertSame('3 active DCs extended', $result->detail);
    }

    public function testSuccessWithCapturedLogOnly(): void
    {
        $result = StepResult::success('Schedule updated', capturedLog: '<p>Log output</p>');

        $this->assertTrue($result->success);
        $this->assertSame('<p>Log output</p>', $result->capturedLog);
        $this->assertSame('', $result->detail);
    }

    public function testFailureErrorMessagePreservedExactly(): void
    {
        $errorMessage = "SQLSTATE[HY000]: General error: can't open file";
        $result = StepResult::failure('Standings', $errorMessage);

        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testSuccessWithEmptyMessages(): void
    {
        $result = StepResult::success('Test', messages: [], messageErrorCount: 0);

        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }
}
