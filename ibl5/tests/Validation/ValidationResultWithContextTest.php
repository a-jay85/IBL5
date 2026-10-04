<?php

declare(strict_types=1);

namespace Tests\Validation;

use PHPUnit\Framework\TestCase;
use Validation\ValidationError;
use Validation\ValidationResultWithContext;

class ValidationResultWithContextTest extends TestCase
{
    public function testSuccessIsValidWithNoErrorsAndNullContextByDefault(): void
    {
        $result = ValidationResultWithContext::success();

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
        $this->assertSame([], $result->getErrorMessages());
        $this->assertNull($result->getFirstError());
        $this->assertNull($result->getContext());
    }

    public function testSuccessCarriesContext(): void
    {
        $result = ValidationResultWithContext::success(['a' => 1]);

        $this->assertSame(['a' => 1], $result->getContext());
    }

    public function testFromErrorsWithErrorsIsInvalid(): void
    {
        $first = new ValidationError('t1', 'm1');
        $second = new ValidationError('t2', 'm2', 'd2');

        $result = ValidationResultWithContext::fromErrors([$first, $second]);

        $this->assertFalse($result->isValid());
        $this->assertSame([$first, $second], $result->getErrors());
        $this->assertSame(['m1', 'm2'], $result->getErrorMessages());
        $this->assertSame($first, $result->getFirstError());
    }

    public function testFromErrorsWithEmptyListIsValid(): void
    {
        $result = ValidationResultWithContext::fromErrors([], 7);

        $this->assertTrue($result->isValid());
        $this->assertSame(7, $result->getContext());
    }

    public function testContextSurvivesFailure(): void
    {
        $error = new ValidationError('cap_exceeded', 'Over the cap');

        $result = ValidationResultWithContext::fromErrors([$error], ['total' => 150000]);

        $this->assertSame(150000, $result->getContext()['total']);
    }
}
