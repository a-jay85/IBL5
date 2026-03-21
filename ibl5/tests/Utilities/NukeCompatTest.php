<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\NukeCompat;

/**
 * Tests for NukeCompat methods that have pure business logic.
 *
 * Only formatLocalTime() is tested here — the PHP-Nuke wrapper methods
 * (isUser, isAdmin, cookieDecode, etc.) delegate to global functions
 * that are not available in the PHPUnit bootstrap.
 *
 * @covers \Utilities\NukeCompat
 */
class NukeCompatTest extends TestCase
{
    private NukeCompat $compat;

    protected function setUp(): void
    {
        $this->compat = new NukeCompat();
    }

    // --- formatLocalTime() with numeric timestamps ---

    public function testFormatLocalTimeReturnsTimeElement(): void
    {
        $html = $this->compat->formatLocalTime(0);

        $this->assertStringContainsString('<time', $html);
        $this->assertStringContainsString('</time>', $html);
    }

    public function testFormatLocalTimeHasLocalTimeClass(): void
    {
        $html = $this->compat->formatLocalTime(0);

        $this->assertStringContainsString('class="local-time"', $html);
    }

    public function testFormatLocalTimeHasIso8601DatetimeAttribute(): void
    {
        // Unix epoch = 1970-01-01T00:00:00+00:00
        $html = $this->compat->formatLocalTime(0);

        $this->assertStringContainsString('datetime="1970-01-01T00:00:00+00:00"', $html);
    }

    public function testFormatLocalTimeWithKnownTimestamp(): void
    {
        // 2025-01-15 14:30:00 UTC = 1736951400
        $html = $this->compat->formatLocalTime(1736951400);

        $this->assertStringContainsString('datetime="2025-01-15T14:30:00+00:00"', $html);
        // Fallback text should contain UTC date
        $this->assertStringContainsString('January', $html);
        $this->assertStringContainsString('2025', $html);
    }

    public function testFormatLocalTimeWithNumericString(): void
    {
        // Numeric string should be treated as unix timestamp
        $html = $this->compat->formatLocalTime('0');

        $this->assertStringContainsString('datetime="1970-01-01T00:00:00+00:00"', $html);
    }

    // --- formatLocalTime() with datetime strings ---

    public function testFormatLocalTimeParsesDatetimeString(): void
    {
        $html = $this->compat->formatLocalTime('2026-03-15 10:00:00');

        $this->assertStringContainsString('datetime="2026-03-15T10:00:00+00:00"', $html);
        $this->assertStringContainsString('March', $html);
    }

    public function testFormatLocalTimeMalformedStringReturnsEpoch(): void
    {
        // Malformed string can't be parsed → falls back to timestamp 0
        $html = $this->compat->formatLocalTime('not-a-date');

        $this->assertStringContainsString('datetime="1970-01-01T00:00:00+00:00"', $html);
    }

    public function testFormatLocalTimeEmptyStringReturnsEpoch(): void
    {
        $html = $this->compat->formatLocalTime('');

        $this->assertStringContainsString('datetime="1970-01-01T00:00:00+00:00"', $html);
    }
}
