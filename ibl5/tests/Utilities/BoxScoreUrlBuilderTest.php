<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\BoxScoreUrlBuilder;

class BoxScoreUrlBuilderTest extends TestCase
{
    public function testBuildUrlReturnsCorrectFormat(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-01-15', 3);

        $this->assertStringContainsString('2025-01-15-game-3/boxscore', $url);
    }

    public function testBuildUrlFallsBackToLegacyBoxIdUrl(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-01-15', 0, 545);

        $this->assertSame('./ibl/IBL/box545.htm', $url);
    }

    public function testBuildUrlPrefersIbl6OverLegacy(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-01-15', 3, 545);

        $this->assertStringContainsString('2025-01-15-game-3/boxscore', $url);
        $this->assertStringNotContainsString('box545', $url);
    }

    public function testBuildUrlReturnsEmptyWhenNeitherAvailable(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-01-15', 0, 0);

        $this->assertSame('', $url);
    }

    public function testBuildUrlReturnsEmptyForNegativeGameOfThatDayAndNoBoxId(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-01-15', -1);

        $this->assertSame('', $url);
    }

    public function testBuildUrlReturnsLegacyForEmptyDateWithBoxId(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('', 3, 545);

        $this->assertSame('./ibl/IBL/box545.htm', $url);
    }

    public function testBuildUrlReturnsLegacyForZeroDateWithBoxId(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('0000-00-00', 3, 545);

        $this->assertSame('./ibl/IBL/box545.htm', $url);
    }

    public function testBuildUrlContainsBaseUrl(): void
    {
        $url = BoxScoreUrlBuilder::buildUrl('2025-03-10', 1);

        $this->assertStringStartsWith('https://', $url);
    }

    public function testBuildUrlWithDifferentGameNumbers(): void
    {
        $url1 = BoxScoreUrlBuilder::buildUrl('2025-01-15', 1);
        $url5 = BoxScoreUrlBuilder::buildUrl('2025-01-15', 5);

        $this->assertStringContainsString('-game-1/', $url1);
        $this->assertStringContainsString('-game-5/', $url5);
    }
}
