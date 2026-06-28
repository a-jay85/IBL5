<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\SubmissionResult;

class SubmissionResultTest extends TestCase
{
    public function testSuccessHasNoErrors(): void
    {
        $result = SubmissionResult::success();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->errors);
        $this->assertFalse($result->hasErrors());
    }

    public function testWithErrorsCarriesAllAndReportsHasErrors(): void
    {
        $result = SubmissionResult::withErrors(['e1', 'e2']);

        $this->assertFalse($result->success);
        $this->assertSame(['e1', 'e2'], $result->errors);
        $this->assertTrue($result->hasErrors());

        $emptyErrors = SubmissionResult::withErrors([]);
        $this->assertFalse($emptyErrors->hasErrors());
    }
}
