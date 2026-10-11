<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * The prepare()/get_result() guards in patch_2007_asg_sco.php replace an
 * uncaught-by-nothing Error with a RuntimeException. Both fire inside the script's
 * try/catch, so the exit code stays 1. They must throw and never exit or die.
 */
final class PatchAsgNameLookupGuardTest extends TestCase
{
    public function testPreparedLookupFailuresThrowInsideTryAndNeverExit(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/patch_2007_asg_sco.php');

        self::assertSame(
            1,
            preg_match('/prepare\(\'SELECT name FROM ibl_plr WHERE pid = \? LIMIT 1\'\);\s*if \(\$stmt === false\) \{(.*?)\n    \}/s', $src, $prepareGuard),
            'prepare() result must be guarded immediately after the call'
        );
        self::assertSame(
            1,
            preg_match('/\$result = \$stmt->get_result\(\);\s*if \(\$result === false\) \{(.*?)\n    \}/s', $src, $resultGuard),
            'get_result() result must be guarded immediately after the call'
        );

        foreach ([$prepareGuard[1], $resultGuard[1]] as $body) {
            self::assertStringContainsString('throw new RuntimeException', $body);
            self::assertSame(0, preg_match('/\b(exit|die)\b/', $body), 'guard must throw, never exit/die');
        }

        $tryPos = strpos($src, "\ntry {");
        $callPos = strpos($src, '$buildGameArray($games');
        $catchPos = strpos($src, '} catch (Throwable $e) {');
        self::assertNotFalse($tryPos);
        self::assertNotFalse($callPos);
        self::assertNotFalse($catchPos);
        self::assertGreaterThan($tryPos, $callPos, '$buildGameArray must only be called inside the try block');
        self::assertLessThan($catchPos, $callPos);
        self::assertStringContainsString('exit(1);', substr($src, $catchPos));
    }
}
