<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class PhpunitConfigTest extends TestCase
{
    private string $testsDir;
    private SimpleXMLElement $xml;

    protected function setUp(): void
    {
        $this->testsDir = dirname(__DIR__);
        $configPath = dirname($this->testsDir) . '/phpunit.xml';
        $content = file_get_contents($configPath);
        self::assertNotFalse($content, 'phpunit.xml not found');
        $this->xml = new SimpleXMLElement($content);
    }

    public function testEveryTestDirectoryIsRegistered(): void
    {
        $registeredDirs = $this->getRegisteredDirectories();
        $registeredFiles = $this->getRegisteredFiles();

        $testDirs = $this->getDirectoriesContainingTests();

        $unregistered = [];
        foreach ($testDirs as $dir) {
            if ($this->isDirectoryCovered($dir, $registeredDirs, $registeredFiles)) {
                continue;
            }
            $unregistered[] = $dir;
        }

        self::assertSame(
            [],
            $unregistered,
            "Test directories not registered in phpunit.xml:\n  - " . implode("\n  - ", $unregistered),
        );
    }

    /**
     * @return list<string> Relative directory paths registered as testsuites
     */
    private function getRegisteredDirectories(): array
    {
        $dirs = [];
        foreach ($this->xml->testsuites->testsuite as $suite) {
            foreach ($suite->directory as $dir) {
                $dirs[] = (string) $dir;
            }
        }
        return $dirs;
    }

    /**
     * @return list<string> Relative file paths registered as testsuite entries
     */
    private function getRegisteredFiles(): array
    {
        $files = [];
        foreach ($this->xml->testsuites->testsuite as $suite) {
            foreach ($suite->file as $file) {
                $files[] = (string) $file;
            }
        }
        return $files;
    }

    /**
     * @return list<string> Relative paths like "tests/Foo" for every dir with *Test.php files
     */
    private function getDirectoriesContainingTests(): array
    {
        $dirs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testsDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            if (!str_ends_with($file->getBasename(), 'Test.php')) {
                continue;
            }
            $relDir = rtrim(
                'tests/' . ltrim(str_replace($this->testsDir, '', $file->getPath()), DIRECTORY_SEPARATOR),
                '/',
            );
            if (!in_array($relDir, $dirs, true)) {
                $dirs[] = $relDir;
            }
        }

        sort($dirs);
        return $dirs;
    }

    /**
     * @param list<string> $registeredDirs
     * @param list<string> $registeredFiles
     */
    private function isDirectoryCovered(string $dir, array $registeredDirs, array $registeredFiles): bool
    {
        foreach ($registeredDirs as $registered) {
            if ($dir === $registered || str_starts_with($dir, $registered . '/')) {
                return true;
            }
        }

        foreach ($registeredFiles as $file) {
            $fileDir = dirname($file);
            if ($dir === $fileDir) {
                return true;
            }
        }

        return false;
    }
}
