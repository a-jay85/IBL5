<?php

declare(strict_types=1);

namespace Tests\Module;

use Module\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    public function testKnownModuleIsValid(): void
    {
        self::assertTrue(ModuleRegistry::isValid('Standings'));
        self::assertTrue(ModuleRegistry::isValid('News'));
        self::assertTrue(ModuleRegistry::isValid('YourAccount'));
    }

    public function testUnknownModuleIsInvalid(): void
    {
        self::assertFalse(ModuleRegistry::isValid('NotAModule'));
        self::assertFalse(ModuleRegistry::isValid(''));
    }

    public function testCaseSensitive(): void
    {
        self::assertFalse(ModuleRegistry::isValid('standings'));
        self::assertFalse(ModuleRegistry::isValid('NEWS'));
    }

    public function testGetAllModulesReturnsNonEmptyList(): void
    {
        $modules = ModuleRegistry::getAllModules();

        self::assertIsArray($modules);
        self::assertGreaterThanOrEqual(46, count($modules));
        self::assertContains('Standings', $modules);
        self::assertContains('Player', $modules);
        self::assertContains('Team', $modules);
    }

    public function testGetAllModulesMatchesIsValid(): void
    {
        foreach (ModuleRegistry::getAllModules() as $module) {
            self::assertTrue(
                ModuleRegistry::isValid($module),
                "getAllModules() entry '$module' should pass isValid()"
            );
        }
    }

    public function testEveryModuleDirectoryIsRegistered(): void
    {
        $modulesDir = __DIR__ . '/../../modules';
        $entries = scandir($modulesDir);
        $dirs = array_filter(
            $entries ? $entries : [],
            static fn(string $d): bool => $d !== '.' && $d !== '..' && is_dir($modulesDir . '/' . $d)
        );

        foreach ($dirs as $dir) {
            self::assertTrue(
                ModuleRegistry::isValid($dir),
                "Module directory '$dir' exists on disk but is not in ModuleRegistry::VALID_MODULES"
            );
        }
    }
}
