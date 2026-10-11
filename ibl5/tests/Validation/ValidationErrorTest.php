<?php

declare(strict_types=1);

namespace Tests\Validation;

use PHPUnit\Framework\TestCase;
use Validation\ValidationError;

class ValidationErrorTest extends TestCase
{
    public function testExposesTypeMessageAndDetail(): void
    {
        $error = new ValidationError('cap_exceeded', 'Over the cap', 'Over by 500');

        $this->assertSame('cap_exceeded', $error->type);
        $this->assertSame('Over the cap', $error->message);
        $this->assertSame('Over by 500', $error->detail);
    }

    public function testDetailDefaultsToEmptyString(): void
    {
        $error = new ValidationError('cap_exceeded', 'Over the cap');

        $this->assertSame('', $error->detail);
    }

    public function testMessageIsStoredRawWithoutEscaping(): void
    {
        $error = new ValidationError('raw', '<b>x</b> & "y"');

        $this->assertSame('<b>x</b> & "y"', $error->message);
    }
}
