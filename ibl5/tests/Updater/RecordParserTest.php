<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\RecordParser;

/**
 * RecordParserTest - Tests for win-loss record parsing utility
 */
class RecordParserTest extends TestCase
{
    public function testExtractWinsFromSingleDigitRecord(): void
    {
        $result = RecordParser::extractWins('5-3');
        $this->assertSame(5, $result);
    }

    public function testExtractWinsFromDoubleDigitRecord(): void
    {
        $result = RecordParser::extractWins('45-37');
        $this->assertSame(45, $result);
    }

    public function testExtractWinsFromTripleDigitRecord(): void
    {
        $result = RecordParser::extractWins('100-82');
        $this->assertSame(100, $result);
    }

    public function testExtractLossesFromSingleDigitRecord(): void
    {
        $result = RecordParser::extractLosses('5-3');
        $this->assertSame(3, $result);
    }

    public function testExtractLossesFromDoubleDigitRecord(): void
    {
        $result = RecordParser::extractLosses('45-37');
        $this->assertSame(37, $result);
    }

    public function testExtractLossesFromTripleDigitRecord(): void
    {
        $result = RecordParser::extractLosses('82-100');
        $this->assertSame(100, $result);
    }

    public function testParseRecordReturnsArray(): void
    {
        $result = RecordParser::parseRecord('45-37');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wins', $result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertSame(45, $result['wins']);
        $this->assertSame(37, $result['losses']);
    }

    public function testExtractWinsHandlesMixedDigitRecords(): void
    {
        $this->assertSame(5, RecordParser::extractWins('5-37'));
        $this->assertSame(45, RecordParser::extractWins('45-3'));
    }

    public function testExtractLossesHandlesMixedDigitRecords(): void
    {
        $this->assertSame(37, RecordParser::extractLosses('5-37'));
        $this->assertSame(3, RecordParser::extractLosses('45-3'));
    }

    public function testExtractWinsWithWhitespace(): void
    {
        $result = RecordParser::extractWins(' 45-37 ');
        $this->assertSame(45, $result);
    }

    public function testExtractLossesWithMissingValue(): void
    {
        $result = RecordParser::extractLosses('45');
        $this->assertSame(0, $result);
    }

    public function testParseRecordWithZeroValues(): void
    {
        $result = RecordParser::parseRecord('0-0');
        $this->assertSame(0, $result['wins']);
        $this->assertSame(0, $result['losses']);
    }

    public function testExtractWinsFromEmptyStringReturnsZero(): void
    {
        $this->assertSame(0, RecordParser::extractWins(''));
    }

    public function testExtractLossesFromEmptyStringReturnsZero(): void
    {
        $this->assertSame(0, RecordParser::extractLosses(''));
    }

    public function testExtractWinsFromNonNumericReturnsZero(): void
    {
        $this->assertSame(0, RecordParser::extractWins('abc-def'));
    }

    public function testExtractWinsFromLeadingDashReturnsZero(): void
    {
        $this->assertSame(0, RecordParser::extractWins('-5'));
    }

    public function testParseRecordIgnoresExtraSeparators(): void
    {
        $result = RecordParser::parseRecord('45-37-12');
        $this->assertSame(45, $result['wins']);
        $this->assertSame(37, $result['losses']);
    }
}
