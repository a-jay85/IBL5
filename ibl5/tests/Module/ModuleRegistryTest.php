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

    public function testEveryModuleDirectoryIsRegistered(): void
    {
        $modulesDir = __DIR__ . '/../../modules';
        $dirs = array_filter(
            scandir($modulesDir) ?: [],
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
