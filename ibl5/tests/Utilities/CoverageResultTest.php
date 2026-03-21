<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\CoverageResult;

/**
 * @covers \Utilities\CoverageResult
 */
class CoverageResultTest extends TestCase
{
    public function testSuccessFactorySetsPassedTrue(): void
    {
        $result = CoverageResult::success(55.50, 45.00);

        $this->assertTrue($result->passed());
    }

    public function testSuccessFactoryFormatsMessage(): void
    {
        $result = CoverageResult::success(55.50, 45.00);

        $this->assertSame('Coverage 55.50% meets threshold 45.00%', $result->getMessage());
    }

    public function testSuccessFactoryStoresPercentageAndThreshold(): void
    {
        $result = CoverageResult::success(55.50, 45.00);

        $this->assertSame(55.50, $result->getPercentage());
        $this->assertSame(45.00, $result->getThreshold());
    }

    public function testFailureFactorySetsPassedFalse(): void
    {
        $result = CoverageResult::failure(30.00, 45.00, 'Coverage too low');

        $this->assertFalse($result->passed());
    }

    public function testFailureFactoryStoresCallerMessage(): void
    {
        $result = CoverageResult::failure(30.00, 45.00, 'Coverage too low');

        $this->assertSame('Coverage too low', $result->getMessage());
    }

    public function testFailureFactoryStoresPercentageAndThreshold(): void
    {
        $result = CoverageResult::failure(30.00, 45.00, 'Coverage too low');

        $this->assertSame(30.00, $result->getPercentage());
        $this->assertSame(45.00, $result->getThreshold());
    }
}
