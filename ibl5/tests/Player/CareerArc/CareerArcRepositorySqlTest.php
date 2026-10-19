<?php

declare(strict_types=1);

namespace Tests\Player\CareerArc;

use PHPUnit\Framework\TestCase;
use Player\CareerArc\CareerArcRepository;

/**
 * Static guards on CareerArcRepository SQL: every query binds with `?`
 * placeholders and every fetch call passes a named SQL constant.
 */
class CareerArcRepositorySqlTest extends TestCase
{
    public function testEverySqlConstantUsesPlaceholders(): void
    {
        $reflection = new \ReflectionClass(CareerArcRepository::class);

        $checked = 0;
        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!str_ends_with($constant->getName(), '_SQL')) {
                continue;
            }
            $sql = $constant->getValue();
            self::assertIsString($sql);
            self::assertStringContainsString('?', $sql, $constant->getName() . ' must bind with ?');
            self::assertStringNotContainsString('$', $sql, $constant->getName() . ' must not interpolate');
            self::assertStringNotContainsString('{', $sql, $constant->getName() . ' must not interpolate');
            $checked++;
        }

        self::assertGreaterThan(0, $checked);
    }

    public function testEveryFetchCallPassesSqlConstant(): void
    {
        $reflection = new \ReflectionClass(CareerArcRepository::class);
        $file = $reflection->getFileName();
        self::assertIsString($file);
        // phpunit-hygiene-allow: the property is the file's shape, no seam exists to observe SQL-constant use
        $source = file_get_contents($file);
        self::assertIsString($source);

        $total = preg_match_all('/fetch(All|One)\(/', $source);
        $viaConstant = preg_match_all('/fetch(All|One)\(\s*self::[A-Z_]+_SQL\s*,/', $source);

        self::assertGreaterThan(0, $total);
        self::assertSame($total, $viaConstant, 'every fetchAll/fetchOne call must pass a self::*_SQL constant');
    }
}
