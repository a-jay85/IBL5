<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bin/check-coverage as a child process against runtime clover fixtures.
 * CoverageChecker has its own unit tests; this pins argv handling and exit codes.
 */
final class CheckCoverageScriptTest extends TestCase
{
    private string $ibl5Dir;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->ibl5Dir = dirname(__DIR__, 2);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * @param list<string> $args
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(array $args): array
    {
        $cmd = 'cd ' . escapeshellarg($this->ibl5Dir) . ' && '
            . escapeshellarg(PHP_BINARY) . ' bin/check-coverage';
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function createCloverXml(int $statements, int $covered): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clover');
        self::assertNotFalse($path);
        $this->tempFiles[] = $path;
        file_put_contents(
            $path,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<coverage><project><metrics statements="' . $statements
            . '" coveredstatements="' . $covered . '"/></project></coverage>'
        );

        return $path;
    }

    #[Test]
    public function testExitsZeroWhenCoverageMeetsThreshold(): void
    {
        $result = $this->runScript([$this->createCloverXml(100, 85), '80']);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('Coverage: 85.00% | Threshold: 80.00%', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    /**
     * Boundary: equality passes because `CoverageChecker::check` compares with strict `<`.
     */
    #[Test]
    public function testExitsZeroWhenCoverageEqualsThresholdExactly(): void
    {
        $result = $this->runScript([$this->createCloverXml(100, 80), '80']);

        self::assertSame(0, $result['code']);
    }

    #[Test]
    public function testExitsOneWhenCoverageBelowThreshold(): void
    {
        $result = $this->runScript([$this->createCloverXml(100, 79), '80']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('Coverage 79.00% is below threshold 80.00%', $result['stdout']);
        self::assertStringContainsString('Coverage: 79.00% | Threshold: 80.00%', $result['stdout']);
    }

    #[Test]
    public function testExitsOneWhenCloverFileMissing(): void
    {
        $missing = sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4)) . '.xml';

        $result = $this->runScript([$missing, '80']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('Clover XML file not found:', $result['stdout']);
    }

    #[Test]
    public function testUsageErrorWhenArgumentsMissing(): void
    {
        $usage = 'Usage: php bin/check-coverage <clover-file> <threshold>';

        $noArgs = $this->runScript([]);
        self::assertSame(1, $noArgs['code']);
        self::assertStringContainsString($usage, $noArgs['stderr']);
        self::assertSame('', $noArgs['stdout']);

        $oneArg = $this->runScript([$this->createCloverXml(100, 85)]);
        self::assertSame(1, $oneArg['code']);
        self::assertStringContainsString($usage, $oneArg['stderr']);
        self::assertSame('', $oneArg['stdout']);
    }

    #[Test]
    public function testThresholdArgumentIsHonoured(): void
    {
        $result = $this->runScript([$this->createCloverXml(100, 85), '90']);

        self::assertSame(1, $result['code']);
    }
}
