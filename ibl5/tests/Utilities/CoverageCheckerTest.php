<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utilities\CoverageChecker;

final class CoverageCheckerTest extends TestCase
{
    private CoverageChecker $checker;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->checker = new CoverageChecker();
        $this->tmpDir = sys_get_temp_dir() . '/coverage-checker-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function passesWhenCoverageMeetsThreshold(): void
    {
        $cloverFile = $this->createCloverXml(statements: 100, covered: 80);

        $result = $this->checker->check($cloverFile, 60.0);

        self::assertTrue($result->passed());
        self::assertEqualsWithDelta(80.0, $result->getPercentage(), 0.01);
        self::assertEqualsWithDelta(60.0, $result->getThreshold(), 0.01);
    }

    #[Test]
    public function passesWhenCoverageExactlyEqualsThreshold(): void
    {
        $cloverFile = $this->createCloverXml(statements: 100, covered: 60);

        $result = $this->checker->check($cloverFile, 60.0);

        self::assertTrue($result->passed());
    }

    #[Test]
    public function failsWhenCoverageIsBelowThreshold(): void
    {
        $cloverFile = $this->createCloverXml(statements: 100, covered: 50);

        $result = $this->checker->check($cloverFile, 60.0);

        self::assertFalse($result->passed());
        self::assertEqualsWithDelta(50.0, $result->getPercentage(), 0.01);
        self::assertStringContainsString('below threshold', $result->getMessage());
    }

    #[Test]
    public function failsWhenFileDoesNotExist(): void
    {
        $result = $this->checker->check('/nonexistent/clover.xml', 60.0);

        self::assertFalse($result->passed());
        self::assertStringContainsString('not found', $result->getMessage());
    }

    #[Test]
    public function failsWhenNoCoverableStatements(): void
    {
        $cloverFile = $this->createCloverXml(statements: 0, covered: 0);

        $result = $this->checker->check($cloverFile, 60.0);

        self::assertFalse($result->passed());
        self::assertStringContainsString('No coverable statements', $result->getMessage());
    }

    #[Test]
    public function failsWhenXmlIsInvalid(): void
    {
        $file = $this->tmpDir . '/invalid.xml';
        file_put_contents($file, 'not xml at all');

        $result = $this->checker->check($file, 60.0);

        self::assertFalse($result->passed());
        self::assertStringContainsString('Failed to parse', $result->getMessage());
    }

    #[Test]
    public function reportsCorrectPercentage(): void
    {
        $cloverFile = $this->createCloverXml(statements: 200, covered: 150);

        $result = $this->checker->check($cloverFile, 50.0);

        self::assertTrue($result->passed());
        self::assertEqualsWithDelta(75.0, $result->getPercentage(), 0.01);
    }

    private function createCloverXml(int $statements, int $covered): string
    {
        $file = $this->tmpDir . '/clover.xml';
        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <coverage generated="1234567890">
            <project timestamp="1234567890">
                <metrics files="10" loc="500" ncloc="400"
                         classes="20" methods="50" coveredmethods="40"
                         conditionals="0" coveredconditionals="0"
                         statements="{$statements}" coveredstatements="{$covered}"
                         elements="50" coveredelements="40"/>
            </project>
        </coverage>
        XML;
        file_put_contents($file, $xml);

        return $file;
    }
}
