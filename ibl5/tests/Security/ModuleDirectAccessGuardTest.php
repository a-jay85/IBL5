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
            $refused = str_contains($out, self::DIE_MESSAGE)
                || str_contains($out, 'stripos(): Argument #1 ($haystack) must be of type string');
            self::assertTrue($refused, "guard fell through for $relPath with setup: $setup\n$out");
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
}
