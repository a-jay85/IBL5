<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use HeadToHeadRecords\LogoResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HeadToHeadRecords\LogoResolver
 */
class LogoResolverTest extends TestCase
{
    public function testFallsBackToDefaultPngWhenNoFilesExist(): void
    {
        $resolver = new LogoResolver('/nonexistent/path/');

        $result = $resolver->resolve(1, 'Warriors');

        self::assertSame('images/logo/new1.png', $result);
    }

    public function testResolvesEraSpecificPngWhenExists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/logo_test_' . uniqid() . '/';
        mkdir($tmpDir . 'images/logo', 0777, true);
        touch($tmpDir . 'images/logo/new16(Kings).png');

        $resolver = new LogoResolver($tmpDir);

        $result = $resolver->resolve(16, 'Kings');

        self::assertSame('images/logo/new16(Kings).png', $result);

        unlink($tmpDir . 'images/logo/new16(Kings).png');
        rmdir($tmpDir . 'images/logo');
        rmdir($tmpDir . 'images');
        rmdir($tmpDir);
    }

    public function testFallsBackToFranchisePngWhenNoEraFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/logo_test_' . uniqid() . '/';
        mkdir($tmpDir . 'images/logo', 0777, true);

        $resolver = new LogoResolver($tmpDir);

        $result = $resolver->resolve(16, 'Kings');

        self::assertSame('images/logo/new16.png', $result);

        rmdir($tmpDir . 'images/logo');
        rmdir($tmpDir . 'images');
        rmdir($tmpDir);
    }
}
