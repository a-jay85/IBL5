<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

/**
 * Pins the exact set of web entry points that lift the statement-time cap.
 *
 * A root script cannot run inside PHPUnit (top-level require and exit), so the
 * call site is pinned structurally. The helper's runtime behavior is covered by
 * StatementTimeoutTest and StatementTimeoutIntegrationTest.
 */
class StatementTimeoutExemptionsTest extends TestCase
{
    private const DISABLE_CALL = '\Database\StatementTimeout::disable($mysqli_db);';

    /** Paths relative to ibl5/. */
    private const EXEMPT_SCRIPTS = [
        'scripts/updateAllTheThings.php',
        'uploadDraftClass.php',
        'import-demands.php',
        'faprep.php',
        'simSummaries.php',
    ];

    public function testLongRunningAdminEndpointsDisableTheCap(): void
    {
        foreach (self::EXEMPT_SCRIPTS as $relative) {
            $src = $this->read($relative);

            $this->assertSame(
                1,
                substr_count($src, self::DISABLE_CALL),
                $relative . ' must call ' . self::DISABLE_CALL . ' exactly once'
            );
        }
    }

    public function testDisableCallFollowsTheAdminGate(): void
    {
        foreach (self::EXEMPT_SCRIPTS as $relative) {
            $src = $this->read($relative);

            $gate = strpos($src, 'if (!is_admin()) {');
            $disable = strpos($src, 'StatementTimeout::disable(');

            $this->assertNotFalse($gate, $relative . ' must keep its is_admin() gate');
            $this->assertNotFalse($disable, $relative . ' must call StatementTimeout::disable()');
            $this->assertLessThan($disable, $gate, $relative . ' must disable the cap only after the admin gate');

            if ($relative === 'scripts/updateAllTheThings.php') {
                $limit = strpos($src, 'set_time_limit(300);');
                $this->assertNotFalse($limit, $relative . ' must keep set_time_limit(300)');
                $this->assertLessThan($disable, $limit, $relative . ' must disable the cap after set_time_limit(300)');
            }
        }
    }

    public function testNoOtherEntryPointDisablesTheCap(): void
    {
        // The definition (classes/Database/StatementTimeout.php) declares `function disable(`
        // and never contains the `StatementTimeout::disable(` call text, so it needs no entry.
        $expected = self::EXEMPT_SCRIPTS;
        sort($expected);

        $found = [];
        foreach ($this->candidateFiles() as $relative) {
            if (str_contains($this->read($relative), 'StatementTimeout::disable(')) {
                $found[] = $relative;
            }
        }
        sort($found);

        $this->assertSame($expected, $found, 'A new StatementTimeout::disable() caller must be added to EXEMPT_SCRIPTS on purpose');
    }

    /** @return list<string> */
    private function candidateFiles(): array
    {
        $base = $this->basePath();
        $files = [];

        foreach (['*.php', 'scripts/*.php'] as $pattern) {
            $matches = glob($base . '/' . $pattern);
            $this->assertIsArray($matches);
            foreach ($matches as $path) {
                $files[] = substr($path, strlen($base) + 1);
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base . '/classes', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($base) + 1);
            }
        }

        return $files;
    }

    private function basePath(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        // phpunit-hygiene-allow: root scripts exit at top level, so the call site can only be pinned as text
        $src = file_get_contents($this->basePath() . '/' . $relative);
        $this->assertIsString($src, 'Cannot read ' . $relative);

        return $src;
    }
}
