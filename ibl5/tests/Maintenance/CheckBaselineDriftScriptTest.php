<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use Maintenance\PhpstanBaselineCounter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bin/check-baseline-drift. The counts JSON is the committed tracked file;
 * setUp saves its bytes and tearDown restores them.
 */
final class CheckBaselineDriftScriptTest extends TestCase
{
    private const UNRESOLVABLE_BASE = '--base=0000000000000000000000000000000000000000';

    private string $ibl5Dir;
    private string $snapshotFile;
    private string $snapshotBytes;

    protected function setUp(): void
    {
        $this->ibl5Dir = dirname(__DIR__, 2);
        $this->snapshotFile = $this->ibl5Dir . '/phpstan-baseline-counts.json';
        $bytes = file_get_contents($this->snapshotFile);
        self::assertNotFalse($bytes);
        $this->snapshotBytes = $bytes;
    }

    protected function tearDown(): void
    {
        file_put_contents($this->snapshotFile, $this->snapshotBytes);
    }

    /**
     * @param list<string> $args
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(array $args, bool $ci = false): array
    {
        $cmd = 'cd ' . escapeshellarg($this->ibl5Dir) . ' && '
            . ($ci ? 'GITHUB_ACTIONS=true ' : 'env -u GITHUB_ACTIONS ')
            . 'php bin/check-baseline-drift';
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

    /**
     * @return array<string, array<string, int>>
     */
    private function readSnapshot(): array
    {
        $decoded = json_decode((string) file_get_contents($this->snapshotFile), true);
        self::assertIsArray($decoded);
        /** @var array<string, array<string, int>> $decoded */

        return $decoded;
    }

    private function writeStaleHighSnapshot(): int
    {
        $snapshot = $this->readSnapshot();
        $snapshot['phpstan-baseline.neon']['argument.type'] = ($snapshot['phpstan-baseline.neon']['argument.type'] ?? 0) + 10;
        file_put_contents($this->snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        return $snapshot['phpstan-baseline.neon']['argument.type'];
    }

    #[Test]
    public function testUnresolvableBaseFailsLoudInCi(): void
    {
        $result = $this->runScript([self::UNRESOLVABLE_BASE], true);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('BASE-REF UNAVAILABLE', $result['stderr']);
    }

    #[Test]
    public function testUnresolvableBaseWarnsLocallyAndRunsSnapshotRule(): void
    {
        $result = $this->runScript([self::UNRESOLVABLE_BASE]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('merge-base comparison SKIPPED', $result['stderr']);
    }

    #[Test]
    public function testRejectsUnknownAndSpaceFormFlags(): void
    {
        foreach ([['--bogus'], ['--base', '0000000'], ['--base=']] as $args) {
            $result = $this->runScript($args);

            self::assertSame(2, $result['code'], 'args: ' . implode(' ', $args));
            self::assertStringContainsString('Usage:', $result['stderr']);
        }
    }

    #[Test]
    public function testUpdateLeavesSnapshotByteIdenticalWhenNothingGrew(): void
    {
        $before = md5_file($this->snapshotFile);

        $result = $this->runScript(['--update', '--base=HEAD']);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('No raise needed', $result['stdout']);
        self::assertSame($before, md5_file($this->snapshotFile));
    }

    #[Test]
    public function testUpdateDoesNotLowerStaleHighSnapshot(): void
    {
        $stale = $this->writeStaleHighSnapshot();

        $result = $this->runScript(['--update', '--base=HEAD']);

        self::assertSame(0, $result['code']);
        self::assertSame($stale, $this->readSnapshot()['phpstan-baseline.neon']['argument.type']);
    }

    #[Test]
    public function testSyncLowersStaleHighSnapshot(): void
    {
        $this->writeStaleHighSnapshot();

        $result = $this->runScript(['--sync']);

        self::assertSame(0, $result['code']);
        $live = (new PhpstanBaselineCounter())->countByIdentifier($this->ibl5Dir . '/phpstan-baseline.neon');
        self::assertSame($live['argument.type'], $this->readSnapshot()['phpstan-baseline.neon']['argument.type']);
    }

    #[Test]
    public function testUpdateAndSyncTogetherRejected(): void
    {
        $result = $this->runScript(['--update', '--sync']);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('Usage:', $result['stderr']);
    }
}
