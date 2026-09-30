<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bin/check-coverage-regression as a child process with runtime clover and
 * baseline fixtures. CoverageComparator has its own unit tests; this pins argv handling,
 * baseline JSON handling, and exit codes.
 */
final class CheckCoverageRegressionScriptTest extends TestCase
{
    private const BASELINE_80 = '{"percentage": 80.0, "tolerance": 0.5}';

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
            . escapeshellarg(PHP_BINARY) . ' bin/check-coverage-regression';
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

    private function writeBaseline(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'covbase');
        self::assertNotFalse($path);
        $this->tempFiles[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    #[Test]
    public function testExitsZeroWhenCoverageWithinTolerance(): void
    {
        $result = $this->runScript([
            $this->createCloverXml(100, 80),
            $this->writeBaseline(self::BASELINE_80),
        ]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('is within tolerance of previous 80.00%', $result['stdout']);
        self::assertStringContainsString(
            'Current: 80.00% | Previous: 80.00% | Minimum: 79.50%',
            $result['stdout']
        );
    }

    /**
     * Boundary: `CoverageComparator::compare` uses `>=` against previous minus tolerance,
     * so 79.50% against an 80.0 baseline with 0.5 tolerance passes.
     */
    #[Test]
    public function testExitsZeroAtExactToleranceBoundary(): void
    {
        $result = $this->runScript([
            $this->createCloverXml(200, 159),
            $this->writeBaseline(self::BASELINE_80),
        ]);

        self::assertSame(0, $result['code']);
    }

    #[Test]
    public function testExitsOneWhenCoverageRegressesBeyondTolerance(): void
    {
        $result = $this->runScript([
            $this->createCloverXml(100, 79),
            $this->writeBaseline(self::BASELINE_80),
        ]);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString(
            'Coverage regressed: 79.00% < minimum allowed 79.50%',
            $result['stdout']
        );
    }

    #[Test]
    public function testBaselineToleranceIsHonoured(): void
    {
        $result = $this->runScript([
            $this->createCloverXml(100, 79),
            $this->writeBaseline('{"percentage": 80.0, "tolerance": 2.0}'),
        ]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('Minimum: 78.00%', $result['stdout']);
    }

    #[Test]
    public function testToleranceDefaultsToHalfPointWhenKeyAbsent(): void
    {
        $baseline = $this->writeBaseline('{"percentage": 80.0}');

        $atBoundary = $this->runScript([$this->createCloverXml(200, 159), $baseline]);
        self::assertSame(0, $atBoundary['code']);
        self::assertStringContainsString('Minimum: 79.50%', $atBoundary['stdout']);

        $below = $this->runScript([$this->createCloverXml(1000, 794), $baseline]);
        self::assertSame(1, $below['code']);
    }

    #[Test]
    public function testFirstRunPassesWhenBaselineFileAbsent(): void
    {
        $missingBaseline = sys_get_temp_dir() . '/no-baseline-' . bin2hex(random_bytes(4)) . '.json';

        $result = $this->runScript([$this->createCloverXml(100, 50), $missingBaseline]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('first run, no previous baseline', $result['stdout']);
        self::assertStringContainsString('Previous: N/A', $result['stdout']);
    }

    #[Test]
    public function testExitsOneOnInvalidBaselineJson(): void
    {
        $clover = $this->createCloverXml(100, 80);

        foreach (['not json', '42'] as $contents) {
            $result = $this->runScript([$clover, $this->writeBaseline($contents)]);

            self::assertSame(1, $result['code'], "baseline contents: {$contents}");
            self::assertStringContainsString('Invalid JSON in baseline file:', $result['stderr']);
            self::assertSame('', $result['stdout']);
        }
    }

    #[Test]
    public function testExitsOneWhenCloverFileMissing(): void
    {
        $missing = sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4)) . '.xml';

        $result = $this->runScript([$missing, $this->writeBaseline('{"percentage": 80.0}')]);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString(
            'Error reading clover file: Clover XML file not found:',
            $result['stderr']
        );
    }

    #[Test]
    public function testUsageErrorWhenArgumentsMissing(): void
    {
        $usage = 'Usage: php bin/check-coverage-regression <clover-file> <baseline-json>';

        $noArgs = $this->runScript([]);
        self::assertSame(1, $noArgs['code']);
        self::assertStringContainsString($usage, $noArgs['stderr']);

        $oneArg = $this->runScript([$this->createCloverXml(100, 80)]);
        self::assertSame(1, $oneArg['code']);
        self::assertStringContainsString($usage, $oneArg['stderr']);
    }
}
