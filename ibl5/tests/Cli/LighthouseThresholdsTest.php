<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseThresholds;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseThresholdsTest extends TestCase
{
    /**
     * Returns its argument typed as mixed so PHPStan does not constant-fold an
     * assertion that compares class constants.
     */
    private static function unfoldedConstant(mixed $value): mixed
    {
        return $value;
    }

    public function testPerformanceThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertArrayHasKey('performance', $thresholds);
        self::assertGreaterThan(0.0, self::unfoldedConstant($thresholds['performance']['minScore']));
        self::assertContains($thresholds['performance']['level'], ['error', 'warn']);
    }

    public function testAccessibilityThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertArrayHasKey('accessibility', $thresholds);
        self::assertGreaterThan(0.0, self::unfoldedConstant($thresholds['accessibility']['minScore']));
        self::assertContains($thresholds['accessibility']['level'], ['error', 'warn']);
    }

    public function testBestPracticesThreshold(): void
    {
        $thresholds = LighthouseThresholds::THRESHOLDS;

        self::assertArrayHasKey('best-practices', $thresholds);
        self::assertGreaterThan(0.0, self::unfoldedConstant($thresholds['best-practices']['minScore']));
        self::assertContains($thresholds['best-practices']['level'], ['error', 'warn']);
    }

    public function testRegressionThreshold(): void
    {
        self::assertGreaterThan(0.0, self::unfoldedConstant(LighthouseThresholds::REGRESSION_THRESHOLD));
        self::assertLessThan(1.0, self::unfoldedConstant(LighthouseThresholds::REGRESSION_THRESHOLD));
    }

    public function testCategories(): void
    {
        self::assertContains('performance', LighthouseThresholds::CATEGORIES);
        self::assertContains('accessibility', LighthouseThresholds::CATEGORIES);
        self::assertContains('best-practices', LighthouseThresholds::CATEGORIES);
    }
}
