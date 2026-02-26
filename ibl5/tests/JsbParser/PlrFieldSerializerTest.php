<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlrFieldSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlrFieldSerializer
 */
class PlrFieldSerializerTest extends TestCase
{
    public function testFormatIntRightJustifies(): void
    {
        $this->assertSame(' 5', PlrFieldSerializer::formatInt(5, 2));
        $this->assertSame('  42', PlrFieldSerializer::formatInt(42, 4));
        $this->assertSame('123', PlrFieldSerializer::formatInt(123, 3));
    }

    public function testFormatIntHandlesZero(): void
    {
        $this->assertSame(' 0', PlrFieldSerializer::formatInt(0, 2));
        $this->assertSame('   0', PlrFieldSerializer::formatInt(0, 4));
    }

    public function testFormatIntHandlesNegativeValues(): void
    {
        $this->assertSame(' -1', PlrFieldSerializer::formatInt(-1, 3));
        $this->assertSame('  -5', PlrFieldSerializer::formatInt(-5, 4));
    }

    public function testFormatIntThrowsOnOverflow(): void
    {
        $this->expectException(\OverflowException::class);
        PlrFieldSerializer::formatInt(100, 2);
    }

    public function testFormatIntThrowsOnNegativeOverflow(): void
    {
        $this->expectException(\OverflowException::class);
        PlrFieldSerializer::formatInt(-10, 2);
    }

    public function testFormatIntExactFit(): void
    {
        $this->assertSame('99', PlrFieldSerializer::formatInt(99, 2));
        $this->assertSame('9999', PlrFieldSerializer::formatInt(9999, 4));
    }

    public function testFormatIntWidthOne(): void
    {
        $this->assertSame('0', PlrFieldSerializer::formatInt(0, 1));
        $this->assertSame('9', PlrFieldSerializer::formatInt(9, 1));
    }

    public function testFormatRightStringRightJustifies(): void
    {
        $this->assertSame('  AB', PlrFieldSerializer::formatRightString('AB', 4));
        $this->assertSame(' X', PlrFieldSerializer::formatRightString('X', 2));
    }

    public function testFormatRightStringExactFit(): void
    {
        $this->assertSame('ABCD', PlrFieldSerializer::formatRightString('ABCD', 4));
    }

    public function testFormatRightStringEmptyValue(): void
    {
        $this->assertSame('    ', PlrFieldSerializer::formatRightString('', 4));
    }

    public function testFormatRightStringThrowsOnOverflow(): void
    {
        $this->expectException(\OverflowException::class);
        PlrFieldSerializer::formatRightString('ABCDE', 4);
    }

    public function testToCP1252ConvertAccentedCharacters(): void
    {
        // José in UTF-8 → José in CP1252 (é = 0xe9 in CP1252)
        $utf8 = "Jos\xc3\xa9";
        $cp1252 = PlrFieldSerializer::toCP1252($utf8);
        $this->assertSame("Jos\xe9", $cp1252);
    }

    public function testToCP1252PreservesAscii(): void
    {
        $ascii = 'John Smith';
        $this->assertSame($ascii, PlrFieldSerializer::toCP1252($ascii));
    }

    public function testFormatIntProducesCorrectLength(): void
    {
        for ($width = 1; $width <= 6; $width++) {
            $result = PlrFieldSerializer::formatInt(0, $width);
            $this->assertSame($width, strlen($result), 'Width ' . $width . ' should produce ' . $width . ' chars');
        }
    }
}
