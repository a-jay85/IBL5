<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Static scan for DB tests that commit, open their own connection, or spawn a
 * process, and so must carry #[Group('mutation-unsafe')].
 */
final class MutationUnsafeScanner
{
    public const NEEDS_TAG_PATTERNS = [
        '/->commit\(/',
        '/->begin_transaction\(/',
        '/->autocommit\(/',
        '/->real_connect\(/',
        '/new\s+\\\\?mysqli\s*\(/',
        '/\bproc_open\s*\(/',
        '/[\'"]DB_NAME[\'"]/',
    ];
    public const TAG_PATTERN = '/#\[Group\(\'mutation-unsafe\'\)\]/';
    private const SELF_EXEMPT = ['MutationUnsafeScannerTest.php', 'DatabaseNameResolverTest.php', 'TransactionLeakGuardTest.php'];

    public static function needsTag(string $source): bool
    {
        foreach (self::NEEDS_TAG_PATTERNS as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                return true;
            }
        }
        return false;
    }

    public static function isTagged(string $source): bool
    {
        return preg_match(self::TAG_PATTERN, $source) === 1;
    }

    /** @return list<string> relative paths under $testsDir, sorted */
    public static function untaggedFiles(string $testsDir): array
    {
        $root = $testsDir . '/DatabaseIntegration';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $found = [];
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $name = $file->getFilename();
            if (!str_ends_with($name, 'Test.php') || str_contains($path, '/Fixtures/') || in_array($name, self::SELF_EXEMPT, true)) {
                continue;
            }
            $source = file_get_contents($path);
            if ($source === false) {
                continue;
            }
            if (self::needsTag($source) && !self::isTagged($source)) {
                $found[] = substr($path, strlen($testsDir) + 1);
            }
        }
        sort($found);
        return $found;
    }
}
