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

    public function testResolvesEraSpecificJpgWhenExists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/logo_test_' . uniqid() . '/';
        mkdir($tmpDir . 'images/logo', 0777, true);
        touch($tmpDir . 'images/logo/16(Kings).jpg');

        $resolver = new LogoResolver($tmpDir);

        $result = $resolver->resolve(16, 'Kings');

        self::assertSame('images/logo/16(Kings).jpg', $result);

        unlink($tmpDir . 'images/logo/16(Kings).jpg');
        rmdir($tmpDir . 'images/logo');
        rmdir($tmpDir . 'images');
        rmdir($tmpDir);
    }

    public function testResolvesFranchiseFallbackJpgWhenNoEraFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/logo_test_' . uniqid() . '/';
        mkdir($tmpDir . 'images/logo', 0777, true);
        touch($tmpDir . 'images/logo/16.jpg');

        $resolver = new LogoResolver($tmpDir);

        $result = $resolver->resolve(16, 'Kings');

        self::assertSame('images/logo/16.jpg', $result);

        unlink($tmpDir . 'images/logo/16.jpg');
        rmdir($tmpDir . 'images/logo');
        rmdir($tmpDir . 'images');
        rmdir($tmpDir);
    }
}
