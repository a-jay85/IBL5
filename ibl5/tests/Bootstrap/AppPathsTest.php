<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\AppPaths;
use PHPUnit\Framework\TestCase;

final class AppPathsTest extends TestCase
{
    private string $originalCwd = '';
    private string $tempDir = '';

    protected function setUp(): void
    {
        $cwd = getcwd();
        self::assertNotFalse($cwd);
        $this->originalCwd = $cwd;

        $this->tempDir = sys_get_temp_dir() . '/app_paths_test_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->tempDir, 0777, true));
        self::assertTrue(chdir($this->tempDir));
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeTree($this->tempDir);
    }

    public function testReturnsRelativeConfigWhenCwdHasConfigDir(): void
    {
        $this->makeDir('config');

        self::assertSame('config', AppPaths::configDir());
    }

    public function testReturnsIbl5ConfigWhenOnlyProjectRootLayoutExists(): void
    {
        $this->makeDir('ibl5/config');

        self::assertSame('ibl5/config', AppPaths::configDir());
    }

    public function testPrefersRelativeConfigOverIbl5ConfigWhenBothExist(): void
    {
        $this->makeDir('config');
        $this->makeDir('ibl5/config');

        self::assertSame('config', AppPaths::configDir());
    }

    public function testFallsBackToRootAbsolutePathWhenNoRelativeConfigExists(): void
    {
        $result = AppPaths::configDir();

        self::assertSame(AppPaths::root() . '/config', $result);
        self::assertDirectoryExists($result);
    }

    public function testIgnoresRegularFileNamedConfig(): void
    {
        self::assertNotFalse(file_put_contents($this->tempDir . '/config', 'not a directory'));

        self::assertSame(AppPaths::root() . '/config', AppPaths::configDir());
    }

    private function makeDir(string $relative): void
    {
        self::assertTrue(mkdir($this->tempDir . '/' . $relative, 0777, true));
    }

    private function removeTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $this->removeTree($path . '/' . $entry);
            }
        }

        rmdir($path);
    }
}
