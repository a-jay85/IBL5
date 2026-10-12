<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * The fopen() guard in engParser.php replaces an uncaught TypeError (exit 255) with an
 * uncaught RuntimeException (exit 255). It must throw and never exit or die.
 */
final class EngParserFopenGuardTest extends TestCase
{
    public function testFopenFailureThrowsAndNeverExits(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/engParser.php');

        self::assertSame(
            1,
            preg_match('/\$engFile = fopen\(\$engFilePath, "rb"\);\s*if \(\$engFile === false\) \{(.*?)\n\}/s', $src, $m),
            'fopen() result must be guarded immediately after the call'
        );
        self::assertStringContainsString('throw new', $m[1]);
        self::assertSame(0, preg_match('/\b(exit|die)\b/', $m[1]), 'guard must throw, never exit/die');
    }
}
