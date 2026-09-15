<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;

/**
 * Structural pins for the FreeAgency admin/user split (backlog 2.13).
 *
 * These assertions make the privilege boundary enforceable in CI: the admin
 * mutators live in FreeAgency\Admin, are constructed only from the admin-gated
 * entry point (block.php), and the user-facing flow never reaches into them.
 */
final class AdminBoundaryTest extends TestCase
{
    private const ADMIN_CLASSES = [
        'FreeAgency\Admin\FreeAgencyAdminProcessor',
        'FreeAgency\Admin\FreeAgencyAdminRepository',
    ];

    private const ADMIN_INTERFACES = [
        'FreeAgency\Admin\Contracts\FreeAgencyAdminProcessorInterface',
        'FreeAgency\Admin\Contracts\FreeAgencyAdminRepositoryInterface',
    ];

    private const RETIRED_FQCNS = [
        'FreeAgency\FreeAgencyAdminProcessor',
        'FreeAgency\FreeAgencyAdminRepository',
        'FreeAgency\Contracts\FreeAgencyAdminProcessorInterface',
        'FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface',
        'FreeAgency\FreeAgencyFormComponents',
    ];

    /** repo root = ibl5/ */
    private static function ibl5Root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testAdminClassesAndContractsResolveInTheAdminNamespace(): void
    {
        foreach (self::ADMIN_CLASSES as $fqcn) {
            self::assertTrue(class_exists($fqcn), "Admin class should resolve: {$fqcn}");
        }

        foreach (self::ADMIN_INTERFACES as $fqcn) {
            self::assertTrue(interface_exists($fqcn), "Admin contract should resolve: {$fqcn}");
        }
    }

    public function testRetiredFqcnsNoLongerResolve(): void
    {
        // Assert on the source tree, not class_exists(): under a symlinked-vendor
        // worktree the composer autoloader's $baseDir points at the main checkout
        // (master), so a retired FQCN would still resolve from master's pre-move
        // copy even though this tree no longer contains it. The file-level check is
        // environment-independent and targets the real failure mode — a stray
        // duplicate left behind at the canonical PSR-4 path in this tree.
        foreach (self::RETIRED_FQCNS as $fqcn) {
            $path = self::ibl5Root() . '/classes/' . str_replace('\\', '/', $fqcn) . '.php';
            self::assertFileDoesNotExist(
                $path,
                "Retired FQCN should no longer resolve (stray duplicate left behind?): {$fqcn}"
            );
        }
    }

    public function testAdminClassesAreConstructedOnlyFromTheAdminEntryPoint(): void
    {
        $root = self::ibl5Root();
        $phpFiles = $this->collectProductionPhpFiles($root);
        self::assertNotEmpty($phpFiles, 'Production PHP file scan matched nothing — skip-list likely broke.');

        $constructionSites = [];
        foreach ($phpFiles as $file) {
            $contents = (string) file_get_contents($file);
            if (preg_match('/new\s+FreeAgencyAdmin(Processor|Repository)\s*\(/', $contents) === 1) {
                $constructionSites[] = ltrim(str_replace($root, '', $file), '/');
            }
        }
        sort($constructionSites);

        self::assertSame(
            ['block.php'],
            $constructionSites,
            'Admin mutators must be constructed only from the admin-gated entry point (block.php).'
        );
    }

    public function testUserFacingFreeAgencyClassesDoNotReferenceTheAdminNamespace(): void
    {
        $globResult = glob(self::ibl5Root() . '/classes/FreeAgency/*.php');
        $topLevelFreeAgencyFiles = $globResult !== false ? $globResult : [];
        self::assertNotEmpty($topLevelFreeAgencyFiles, 'Top-level FreeAgency class glob matched nothing.');

        foreach ($topLevelFreeAgencyFiles as $file) {
            $contents = (string) file_get_contents($file);
            self::assertStringNotContainsString(
                'FreeAgency\Admin',
                $contents,
                'User-facing FreeAgency class must not reference the admin namespace: ' . basename($file)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function collectProductionPhpFiles(string $root): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current): bool {
                    $name = $current->getFilename();
                    if ($current->isDir()) {
                        return !in_array($name, ['vendor', 'node_modules', 'coverage', 'tests'], true)
                            && !str_starts_with($name, '.');
                    }

                    return true;
                }
            )
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }
}
