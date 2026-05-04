<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use PHPUnit\Framework\TestCase;

class IntegrationDirectoryRenameTest extends TestCase
{
    private string $testsDir;

    protected function setUp(): void
    {
        $this->testsDir = dirname(__DIR__);
    }

    public function testIntegrationDirectoryDoesNotExist(): void
    {
        $this->assertDirectoryDoesNotExist($this->testsDir . '/Integration');
    }

    public function testWideUnitDirectoryExists(): void
    {
        $this->assertDirectoryExists($this->testsDir . '/WideUnit');
    }

    public function testWideUnitTestCaseExists(): void
    {
        $this->assertFileExists($this->testsDir . '/WideUnit/WideUnitTestCase.php');
    }

    public function testWideUnitTestCaseHasCorrectNamespace(): void
    {
        $content = file_get_contents($this->testsDir . '/WideUnit/WideUnitTestCase.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('namespace Tests\\WideUnit;', $content);
    }
}
