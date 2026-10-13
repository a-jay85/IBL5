<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleDirectAccessGuardTest extends TestCase
{
    private const DIE_MESSAGE = "You can't access this file directly";

    /** Files whose direct-access guard reads $_SERVER['PHP_SELF']. */
    private const GUARDED_FILES = [
        'modules/ApiKeys/index.php',
        'modules/ActivityTracker/index.php',
        'modules/DebugMenu/index.php',
        'modules/index.php',
    ];

    /** Files where $_SERVER['SERVER_NAME'] was replaced by HttpRequest::server() (Phase 3). */
    private const SERVER_NAME_FILES = [
        'modules/Trading/maketradeoffer.php',
        'modules/Trading/accepttradeoffer.php',
        'modules/Trading/rejecttradeoffer.php',
        'modules/Player/extension.php',
    ];

    /** Standalone POST handlers that pass the request body to the controller. */
    private const POST_HANDLER_FILES = [
        'modules/Trading/maketradeoffer.php',
        'modules/Trading/accepttradeoffer.php',
        'modules/Trading/rejecttradeoffer.php',
    ];

    /**
     * Runs the module file in a fresh php process with $_SERVER['PHP_SELF']
     * prepared by $setup, and returns combined stdout+stderr.
     */
    private function runModuleFile(string $relPath, string $setup): string
    {
        $abs = dirname(__DIR__, 2) . '/' . $relPath;
        self::assertFileExists($abs);
        $code = $setup . ' require ' . var_export($abs, true) . ';';
        $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -r '
            . escapeshellarg($code) . ' 2>&1';
        $lines = [];
        $exit = 0;
        exec($cmd, $lines, $exit);
        return implode("\n", $lines);
    }

    /** @return iterable<string, array{string}> */
    public static function guardedFiles(): iterable
    {
        foreach (self::GUARDED_FILES as $f) {
            yield $f => [$f];
        }
    }

    #[DataProvider('guardedFiles')]
    public function testGuardDiesWhenPhpSelfLacksModulesPhp(string $relPath): void
    {
        $out = $this->runModuleFile($relPath, '$_SERVER["PHP_SELF"] = "/ibl5/index.php";');
        self::assertStringContainsString(self::DIE_MESSAGE, $out);
    }

    #[DataProvider('guardedFiles')]
    public function testGuardRefusesNonStringPhpSelf(string $relPath): void
    {
        foreach (['unset($_SERVER["PHP_SELF"]);', '$_SERVER["PHP_SELF"] = ["x"];'] as $setup) {
            $out = $this->runModuleFile($relPath, $setup);
            self::assertStringContainsString(self::DIE_MESSAGE, $out, "setup: $setup for $relPath");
            self::assertStringNotContainsString('must be of type string', $out, "setup: $setup for $relPath");
        }
    }

    public function testGuardPassesWhenPhpSelfNamesModulesPhp(): void
    {
        // modules/index.php is the PHP-Nuke dispatcher; past its guard it includes
        // other files that print the same message, so only the three module pages
        // are asserted here. Each of them fatals on an unloaded class right after
        // the guard, which is fine: the assertion is only that the guard did not fire.
        foreach (array_slice(self::GUARDED_FILES, 0, 3) as $relPath) {
            $out = $this->runModuleFile($relPath, '$_SERVER["PHP_SELF"] = "/ibl5/modules.php";');
            self::assertStringNotContainsString(self::DIE_MESSAGE, $out, $relPath);
        }
    }

    public function testServerNameIsReadThroughHttpRequestWithStringFallback(): void
    {
        foreach (self::SERVER_NAME_FILES as $relPath) {
            // phpunit-hygiene-allow: page script sits behind mainfile.php and cannot be executed in-process
            $src = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relPath);
            self::assertStringNotContainsString("\$_SERVER['SERVER_NAME']", $src, $relPath);
            self::assertStringContainsString("->server('SERVER_NAME')", $src, $relPath);
            self::assertStringContainsString(
                "is_string(\$serverNameRaw) ? \$serverNameRaw : ''",
                $src,
                $relPath
            );
        }
    }

    public function testTradingPostHandlersPassAllPostFromHttpRequest(): void
    {
        foreach (self::POST_HANDLER_FILES as $relPath) {
            // phpunit-hygiene-allow: page script sits behind mainfile.php and cannot be executed in-process
            $src = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relPath);
            self::assertStringNotContainsString('$_POST', $src, $relPath);
            self::assertStringContainsString('$httpRequest->allPost()', $src, $relPath);
        }
    }
}
