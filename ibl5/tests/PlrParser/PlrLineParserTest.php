<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrLineParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PlrParser\PlrLineParser
 */
class PlrLineParserTest extends TestCase
{
    /**
     * Build a 607-byte .plr record line carrying ordinal, name, age, and pid.
     *
     * Only the fields exercised by these tests are populated; the rest of the
     * fixed-width record is space-padded. Offsets mirror PlrLineParser::parse():
     * ordinal(0,4) | name(4,32) | age(36,2) | pid(38,6) | teamid(44,2).
     */
    private function buildPlrLine(int $ordinal, string $name, int $pid, int $teamid = 1): string
    {
        $line = str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT); // 0-3
        $line .= str_pad($name, 32);                              // 4-35
        $line .= str_pad('25', 2, ' ', STR_PAD_LEFT);            // 36-37 (age)
        $line .= str_pad((string) $pid, 6, ' ', STR_PAD_LEFT);   // 38-43
        $line .= str_pad((string) $teamid, 2, ' ', STR_PAD_LEFT); // 44-45

        return str_pad($line, 607); // full record width
    }

    public function testParsesAsciiName(): void
    {
        $result = PlrLineParser::parse($this->buildPlrLine(1, 'John Smith', 12345));

        $this->assertNotNull($result);
        $this->assertSame('John Smith', $result['name']);
        $this->assertSame(1, $result['ordinal']);
        $this->assertSame(12345, $result['pid']);
    }

    public function testParsesAccentedNameFromCp1252(): void
    {
        // "José Garcia" in CP1252 (é = 0xe9) decodes to UTF-8 (é = 0xc3 0xa9).
        $result = PlrLineParser::parse($this->buildPlrLine(2, "Jos\xe9 Garcia", 67890));

        $this->assertNotNull($result);
        $this->assertSame('José Garcia', $result['name']);
    }

    public function testMapsUndefinedCp1252ByteToUnicode(): void
    {
        // 0x81 is undefined in CP1252. This locks the consolidated decode (PR 3):
        // the shared PlrFieldSerializer::toUtf8 maps it to U+0081 (0xc2 0x81),
        // whereas the previous iconv('CP1252','UTF-8//IGNORE') variant in this
        // parser silently dropped the byte ("AB"). Standardizing on the mb
        // behavior is the latent-bug fix this PR makes.
        $result = PlrLineParser::parse($this->buildPlrLine(3, "A\x81B", 11111));

        $this->assertNotNull($result);
        $this->assertSame("A\xc2\x81B", $result['name']);
    }

    public function testReturnsNullForZeroPid(): void
    {
        $this->assertNull(PlrLineParser::parse($this->buildPlrLine(1, 'Empty Slot', 0)));
    }

    public function testReturnsNullForOrdinalAbove1440(): void
    {
        $this->assertNull(PlrLineParser::parse($this->buildPlrLine(1441, 'Over Limit', 99999)));
    }
}
