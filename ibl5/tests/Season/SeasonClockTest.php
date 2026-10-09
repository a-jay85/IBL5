<?php

declare(strict_types=1);

namespace Tests\Season;

use PHPUnit\Framework\TestCase;

/**
 * Pins the injected clock for the ending-year fallback used when no season is configured.
 *
 * The PHPUnit bootstrap aliases Season\Season to a mock (TestAliasesBootstrap), so the
 * real class is exercised in a child PHP process that loads only the Composer autoloader.
 */
class SeasonClockTest extends TestCase
{
    private function endingYearAt(int $timestamp): int
    {
        $root = dirname(__DIR__, 2);
        $script = <<<'PHP'
            require $argv[1] . '/vendor/autoload.php';
            // Resolve classes from this checkout even when vendor/ is a worktree symlink.
            spl_autoload_register(static function (string $class) use ($argv): void {
                $base = str_starts_with($class, 'Tests\\')
                    ? $argv[1] . '/tests/' . substr($class, 6)
                    : $argv[1] . '/classes/' . $class;
                $file = str_replace('\\', '/', $base) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }, true, true);
            date_default_timezone_set('UTC');
            $db = new Tests\WideUnit\Mocks\MockDatabase();
            $db->onQuery('ibl_settings', []);
            $season = new Season\Season($db, null, new Tests\Clock\FixedClock((int) $argv[2]));
            echo $season->endingYear;
            PHP;

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script)
            . ' ' . escapeshellarg($root) . ' ' . escapeshellarg((string) $timestamp) . ' 2>&1';
        $output = [];
        exec($cmd, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        return (int) implode('', $output);
    }

    public function testEndingYearFallbackUsesInjectedClockAcrossYearBoundary(): void
    {
        $lastSecondOf2025 = (int) gmmktime(23, 59, 59, 12, 31, 2025);

        self::assertSame(2026, $this->endingYearAt($lastSecondOf2025));
        self::assertSame(2027, $this->endingYearAt($lastSecondOf2025 + 1));
    }
}
