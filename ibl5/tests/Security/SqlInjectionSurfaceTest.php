<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Locks the Axis-A SQL injection audit (docs/sql-injection-audit-2026-09-22.md).
 * Each assertion pins one shape the audit found closed; a new hit means a
 * new construction site that needs its own verdict.
 *
 * Known scope limit: the scan covers classes/ and modules/ only, matching the
 * enumeration commands the audit ran. Root-level legacy scripts such as
 * ibl5/uploadDraftClass.php sit outside both the audit's grep surface and this
 * lock; extending SCAN_ROOTS to reach them is a separate decision.
 */
class SqlInjectionSurfaceTest extends TestCase
{
    private const SCAN_ROOTS = ['classes', 'modules'];

    /** Migration-time validator; input is a migration identifier, not a request value. */
    private const ESCAPE_ALLOWLIST = ['classes/Migration/SchemaValidator.php'];

    /**
     * Scan the repository's production roots.
     *
     * @param list<string> $allowlist
     * @return list<string> "relative/path:line: text" for every matching line
     */
    private function scan(string $pattern, array $allowlist = []): array
    {
        $base = realpath(__DIR__ . '/../..');
        self::assertIsString($base);
        self::assertDirectoryExists($base . '/classes');

        return $this->scanIn($base, $pattern, $allowlist);
    }

    /**
     * The scan itself, rooted at an arbitrary base so the planted-hit test
     * exercises the same code path the real assertions use.
     *
     * @param list<string> $allowlist
     * @return list<string>
     */
    private function scanIn(string $base, string $pattern, array $allowlist = []): array
    {
        $hits = [];
        foreach (self::SCAN_ROOTS as $root) {
            $rootPath = $base . '/' . $root;
            if (!is_dir($rootPath)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($base) + 1);
                if (in_array($relative, $allowlist, true)) {
                    continue;
                }
                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
                self::assertIsArray($lines);
                foreach ($lines as $i => $line) {
                    if ($this->isCommentLine($line)) {
                        continue;
                    }
                    $code = preg_replace('~//.*$~', '', $line) ?? $line;
                    if (preg_match($pattern, $code) === 1) {
                        $hits[] = sprintf('%s:%d: %s', $relative, $i + 1, trim($line));
                    }
                }
            }
        }
        sort($hits);

        return $hits;
    }

    /**
     * Whole-line comments, including docblock continuation lines. Several
     * Contracts interfaces name escapeString() in prose only; those are
     * documentation of legacy behaviour, not call sites.
     */
    private function isCommentLine(string $line): bool
    {
        $trimmed = ltrim($line);

        return $trimmed !== '' && (
            str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '/*')
        );
    }

    public function testNoProductionSqlQueryCallSites(): void
    {
        $hits = $this->scan('/\bsql_query\s*\(/');
        self::assertSame([], $hits, "sql_query() re-entered production code:\n" . implode("\n", $hits));
    }

    public function testNoMysqliStringEscapingOutsideAllowlist(): void
    {
        $hits = $this->scan('/\b(real_escape_string|escapeString)\s*\(/', self::ESCAPE_ALLOWLIST);
        self::assertSame([], $hits, "mysqli string escaping found; bind the value instead:\n" . implode("\n", $hits));
    }

    public function testAllowlistEntriesStillExist(): void
    {
        $base = realpath(__DIR__ . '/../..');
        self::assertIsString($base);
        foreach (self::ESCAPE_ALLOWLIST as $relative) {
            self::assertFileExists($base . '/' . $relative, 'Stale allowlist entry: ' . $relative);
        }
    }

    public function testScannerDetectsPlantedHit(): void
    {
        $dir = sys_get_temp_dir() . '/sqli-surface-' . bin2hex(random_bytes(4));
        mkdir($dir . '/classes', 0777, true);
        mkdir($dir . '/modules', 0777, true);
        file_put_contents($dir . '/classes/Planted.php', "<?php\n\$db->real_escape_string(\$x);\n");
        try {
            $hits = $this->scanIn($dir, '/\b(real_escape_string|escapeString)\s*\(/');
            self::assertSame(['classes/Planted.php:2: $db->real_escape_string($x);'], $hits);
        } finally {
            unlink($dir . '/classes/Planted.php');
            rmdir($dir . '/classes');
            rmdir($dir . '/modules');
            rmdir($dir);
        }
    }
}
