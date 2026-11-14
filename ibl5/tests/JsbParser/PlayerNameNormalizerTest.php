<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlayerNameNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlayerNameNormalizer
 */
final class PlayerNameNormalizerTest extends TestCase
{
    public function testMapsDoubleQuoteToApostrophe(): void
    {
        self::assertSame("Shaquille O'Neal", PlayerNameNormalizer::normalize('Shaquille O"Neal'));
        self::assertSame("A'Ja Wilson", PlayerNameNormalizer::normalize('A"Ja Wilson'));
    }

    public function testLeavesApostropheAndPlainNamesUnchanged(): void
    {
        self::assertSame("D'Angelo Russell", PlayerNameNormalizer::normalize("D'Angelo Russell"));
        self::assertSame("Hamady N'Diaye", PlayerNameNormalizer::normalize("Hamady N'Diaye"));
        self::assertSame('Karl-Anthony Towns', PlayerNameNormalizer::normalize('Karl-Anthony Towns'));
    }

    public function testLeavesHtmlEntityUndecoded(): void
    {
        self::assertSame('O&#039;Neal', PlayerNameNormalizer::normalize('O&#039;Neal'));
        self::assertSame('O&quot;Neal', PlayerNameNormalizer::normalize('O&quot;Neal'));
    }

    public function testEmptyStringAndMultipleQuotes(): void
    {
        self::assertSame('', PlayerNameNormalizer::normalize(''));
        self::assertSame("a'b'c", PlayerNameNormalizer::normalize('a"b"c'));
    }
}
