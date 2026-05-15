<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scripts\CoverageComparator;

final class CoverageComparatorTest extends TestCase
{
    private CoverageComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new CoverageComparator();
    }

    #[Test]
    public function passesWithinTolerance(): void
    {
        $result = $this->comparator->compare(current: 72.0, previous: 72.5, tolerance: 0.5);

        self::assertTrue($result->passed());
        self::assertStringContainsString('within tolerance', $result->getMessage());
    }

    #[Test]
    public function passesWhenCurrentExceedsPrevious(): void
    {
        $result = $this->comparator->compare(current: 75.0, previous: 72.5, tolerance: 0.5);

        self::assertTrue($result->passed());
    }

    #[Test]
    public function passesAtExactToleranceBoundary(): void
    {
        $result = $this->comparator->compare(current: 72.0, previous: 72.5, tolerance: 0.5);

        self::assertTrue($result->passed());
    }

    #[Test]
    public function failsBeyondTolerance(): void
    {
        $result = $this->comparator->compare(current: 71.0, previous: 72.5, tolerance: 0.5);

        self::assertFalse($result->passed());
        self::assertStringContainsString('regressed', $result->getMessage());
        self::assertEqualsWithDelta(72.0, $result->getMinimumAllowed(), 0.01);
    }

    #[Test]
    public function passesWithNullPrevious(): void
    {
        $result = $this->comparator->compare(current: 50.0, previous: null, tolerance: 0.5);

        self::assertTrue($result->passed());
        self::assertStringContainsString('first run', $result->getMessage());
    }

    #[Test]
    public function reportsCorrectValues(): void
    {
        $result = $this->comparator->compare(current: 73.5, previous: 72.0, tolerance: 1.0);

        self::assertEqualsWithDelta(73.5, $result->getCurrent(), 0.01);
        self::assertEqualsWithDelta(72.0, $result->getPrevious(), 0.01);
        self::assertEqualsWithDelta(71.0, $result->getMinimumAllowed(), 0.01);
    }

    #[Test]
    public function zeroToleranceMeansExactMatch(): void
    {
        $result = $this->comparator->compare(current: 72.49, previous: 72.5, tolerance: 0.0);

        self::assertFalse($result->passed());
    }
}
