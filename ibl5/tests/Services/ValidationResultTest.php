<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Services\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function testSuccessIsValid(): void
    {
        $result = ValidationResult::success();
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
        $this->assertSame([], $result->getErrors());
    }

    public function testFailureIsNotValid(): void
    {
        $result = ValidationResult::failure('Something went wrong');
        $this->assertFalse($result->isValid());
        $this->assertSame('Something went wrong', $result->getError());
    }

    public function testFailureGetErrors(): void
    {
        $result = ValidationResult::failure('Error message');
        $this->assertSame(['Error message'], $result->getErrors());
    }

    public function testMultipleFailures(): void
    {
        $result = ValidationResult::failures(['Error 1', 'Error 2', 'Error 3']);
        $this->assertFalse($result->isValid());
        $this->assertSame('Error 1', $result->getError());
        $this->assertCount(3, $result->getErrors());
    }

    public function testEmptyFailuresIsValid(): void
    {
        $result = ValidationResult::failures([]);
        $this->assertTrue($result->isValid());
    }
}
