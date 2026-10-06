<?php

declare(strict_types=1);

namespace Tests\PageLayout;

use PHPUnit\Framework\TestCase;

/**
 * Guards the invariant that no production code reads the legacy `$cookie` global.
 *
 * PageLayout::header() no longer calls cookiedecode(), so a new reader of the
 * global would see null. Identity comes from `$authService` or the
 * `auth.username` container entry.
 *
 * The last dead declaration, modules/News/categories.php:49, was removed with
 * this guard. The only comment hit, AuthService.php:27, is skipped by the
 * comment filter in findViolations().
 */
final class CookieGlobalReadersCorpusTest extends TestCase
{
    private const SCAN_DIRECTORIES = ['classes', 'modules', 'blocks', 'themes', 'includes', 'admin'];

    private const FORBIDDEN_SHAPES = [
        'read-index' => '/\$cookie\s*\[/',
        'global-decl' => '/\bglobal\b[^;]*\$cookie\b/',
        'globals-array' => '/\$GLOBALS\s*\[\s*[\'"]cookie[\'"]\s*\]/',
        'bare-write' => '/\$cookie\s*=(?!=)/',
    ];

    /**
     * Path-scoped exemptions: path, label, trimmed line.
     */
    private const ALLOWLIST = [
        ['modules/YourAccount/index.php', 'bare-write', "\$cookie = '';"],
        ['classes/Bootstrap/LegacyFunctions.php', 'global-decl', 'global $cookie, $authService;'],
        ['classes/Bootstrap/LegacyFunctions.php', 'bare-write', '$cookie = $cookieArray;'],
    ];

    public function testNoProductionFileReadsOrDeclaresCookieGlobal(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];

        foreach (self::collectFiles($root) as $relativePath) {
            $source = file_get_contents($root . '/' . $relativePath);
            if ($source === false) {
                continue;
            }
            array_push($violations, ...self::findViolations($relativePath, $source));
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testAllowlistEntriesStillMatch(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::ALLOWLIST as [$path, $label, $line]) {
            $source = file_get_contents($root . '/' . $path);
            self::assertNotFalse($source, $path . ' is missing');

            $found = false;
            foreach (explode("\n", $source) as $sourceLine) {
                if (trim($sourceLine) === $line && preg_match(self::FORBIDDEN_SHAPES[$label], $sourceLine) === 1) {
                    $found = true;
                    break;
                }
            }

            self::assertTrue($found, 'Stale allowlist entry: ' . $path . ' ' . $label . ' ' . $line);
        }
    }

    public function testMatcherFlagsEachForbiddenShape(): void
    {
        $cases = [
            'read-index' => '$x = $cookie[1];',
            'global-decl' => 'global $user, $cookie, $db;',
            'globals-array' => '$u = $GLOBALS[\'cookie\'][1];',
            'bare-write' => '$cookie = cookiedecode($user);',
        ];

        foreach ($cases as $label => $source) {
            $violations = self::findViolations('modules/Fake/index.php', $source);

            self::assertCount(1, $violations, $source);
            self::assertStringContainsString(': ' . $label . ': ', $violations[0]);
        }
    }

    public function testMatcherIgnoresNearMissShapes(): void
    {
        $nearMisses = [
            '$cookies[0] = 1;',
            '$cookieArray = [];',
            'public function cookieDecode(mixed $cookie): array',
            'if ($cookie == null)',
            ' * legacy $cookie[] references.',
            '// $cookie[1] is gone',
        ];

        foreach ($nearMisses as $source) {
            self::assertSame([], self::findViolations('modules/Fake/index.php', $source), $source);
        }

        self::assertSame([], self::findViolations('modules/YourAccount/index.php', "\$cookie = '';"));

        $other = self::findViolations('modules/Other/index.php', "\$cookie = '';");
        self::assertCount(1, $other);
        self::assertStringContainsString(': bare-write: ', $other[0]);
    }

    /**
     * @return list<string> Paths relative to the ibl5 root
     */
    private static function collectFiles(string $root): array
    {
        $files = [];

        foreach (self::SCAN_DIRECTORIES as $directory) {
            $path = $root . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                if (!str_ends_with($filePath, '.php') || str_contains($filePath, '/vendor/')) {
                    continue;
                }
                $files[] = substr($filePath, strlen($root) + 1);
            }
        }

        $topLevelFiles = glob($root . '/*.php');
        foreach ($topLevelFiles !== false ? $topLevelFiles : [] as $topLevel) {
            $files[] = substr($topLevel, strlen($root) + 1);
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string> "<path>:<line>: <label>: <trimmed line>" for each non-allowlisted hit
     */
    private static function findViolations(string $relativePath, string $source): array
    {
        $violations = [];

        foreach (explode("\n", $source) as $index => $line) {
            $trimmed = trim($line);
            if (
                $trimmed === ''
                || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '/*')
                || str_starts_with($trimmed, '#')
            ) {
                continue;
            }

            foreach (self::FORBIDDEN_SHAPES as $label => $pattern) {
                if (preg_match($pattern, $line) !== 1) {
                    continue;
                }
                if (in_array([$relativePath, $label, $trimmed], self::ALLOWLIST, true)) {
                    continue;
                }
                $violations[] = $relativePath . ':' . ($index + 1) . ': ' . $label . ': ' . $trimmed;
            }
        }

        return $violations;
    }
}
