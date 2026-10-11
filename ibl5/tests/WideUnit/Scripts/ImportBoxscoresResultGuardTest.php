<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * The get_result() guard in importBoxscoresFromHtml.php replaces an uncaught Error
 * (exit 255) with an uncaught RuntimeException (exit 255). It must throw and never
 * exit or die, and the script must stay free of any catch.
 */
final class ImportBoxscoresResultGuardTest extends TestCase
{
    public function testScheduleResultFailureThrowsUncaughtAndNeverExits(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/archive/importBoxscoresFromHtml.php');

        self::assertSame(
            1,
            preg_match('/\$result = \$stmt->get_result\(\);\s*if \(\$result === false\) \{(.*?)\n\}/s', $src, $m),
            'get_result() result must be guarded immediately after the call'
        );
        self::assertStringContainsString('throw new RuntimeException', $m[1]);
        self::assertSame(0, preg_match('/\b(exit|die)\b/', $m[1]), 'guard must throw, never exit/die');
        self::assertStringNotContainsString('catch (', $src, 'an uncaught throw keeps exit 255 only while the script has no catch');
        self::assertStringContainsString("global \$mysqli_db;\n/** @var \\mysqli \$mysqli_db */", $src);
    }
}
