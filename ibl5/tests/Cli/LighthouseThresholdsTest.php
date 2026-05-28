<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseThresholds;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseThresholdsTest extends TestCase
{
    public function testPerformanceThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertSame(0.6, $thresholds['performance']['minScore']);
        self::assertSame('warn', $thresholds['performance']['level']);
    }

    public function testAccessibilityThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertSame(0.85, $thresholds['accessibility']['minScore']);
        self::assertSame('error', $thresholds['accessibility']['level']);
    }

    public function testBestPracticesThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertSame(0.8, $thresholds['best-practices']['minScore']);
        self::assertSame('warn', $thresholds['best-practices']['level']);
    }

    public function testRegressionThreshold(): void
    {
        self::assertSame(0.03, LighthouseThresholds::REGRESSION_THRESHOLD);
    }

    public function testCategories(): void
    {
        self::assertSame(
            ['performance', 'accessibility', 'best-practices'],
            LighthouseThresholds::CATEGORIES
        );
    }
}
