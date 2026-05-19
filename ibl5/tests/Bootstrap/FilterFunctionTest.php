<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use PHPUnit\Framework\TestCase;

final class FilterFunctionTest extends TestCase
{
    protected function setUp(): void
    {
        /** @phpstan-ignore ibl.requireOnce (loading legacy functions for characterization tests) */
        require_once __DIR__ . '/../../classes/Bootstrap/LegacyFunctions.php';
    }

    public function testFilterNoHtmlStripsTagsAndReturnsPlainText(): void
    {
        $result = filter('<script>alert(1)</script>Hello', 'nohtml');
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    public function testFilterNoHtmlHandlesPlainString(): void
    {
        $result = filter('plain text', 'nohtml');
        self::assertSame('plain text', $result);
    }

    public function testFilterSaveModeAddsSlashes(): void
    {
        $result = filter("it's a test", '', '1');
        self::assertStringContainsString("it\\'s a test", $result);
    }

    public function testFilterDefaultModeStripsSlashes(): void
    {
        $result = filter("it\\'s a test");
        self::assertStringContainsString("it's a test", $result);
    }

    public function testFilterHandlesEmptyString(): void
    {
        $result = filter('');
        self::assertSame('', $result);
    }

    public function testCheckHtmlStripsDisallowedTags(): void
    {
        $result = check_html('<script>alert(1)</script>text');
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('text', $result);
    }

    public function testCheckHtmlNoHtmlStripsAllTags(): void
    {
        $result = check_html('<p>Hello</p>', 'nohtml');
        self::assertStringNotContainsString('<p>', $result);
    }

    public function testFilterNohtmlEscapesAndDecodesEntities(): void
    {
        $result = filter('Hello & "World"', 'nohtml');
        self::assertSame('Hello & "World"', $result);
    }

    public function testFilterEmptyStringWithNohtmlReturnsEmpty(): void
    {
        $result = filter('', 'nohtml');
        self::assertSame('', $result);
    }

    public function testCheckWordsReturnsOriginalWhenNoCensoring(): void
    {
        $GLOBALS['CensorMode'] = 0;
        $result = check_words('Hello World');
        self::assertSame('Hello World', $result);
    }
}
