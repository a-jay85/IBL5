<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\ScheduleParser;

/**
 * ScheduleParserTest - Tests for schedule HTML parsing utility
 */
class ScheduleParserTest extends TestCase
{
    public function testExtractBoxIDFromValidLink(): void
    {
        $result = ScheduleParser::extractBoxID('box12345.htm');
        $this->assertEquals('12345', $result);
    }

    public function testExtractBoxIDFromLongID(): void
    {
        $result = ScheduleParser::extractBoxID('box123456789.htm');
        $this->assertEquals('123456789', $result);
    }

    public function testExtractBoxIDFromShortID(): void
    {
        $result = ScheduleParser::extractBoxID('box1.htm');
        $this->assertEquals('1', $result);
    }

    public function testExtractBoxIDWithoutExtension(): void
    {
        $result = ScheduleParser::extractBoxID('box12345');
        $this->assertEquals('12345', $result);
    }

    public function testExtractBoxIDWithPathPrefix(): void
    {
        // Should still work since ltrim removes 'box' from start
        $result = ScheduleParser::extractBoxID('box99999.htm');
        $this->assertEquals('99999', $result);
    }
}
