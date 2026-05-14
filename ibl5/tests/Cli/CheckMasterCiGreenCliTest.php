<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckMasterCiGreenCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;
    private string $shimDir;
    private string $repoDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-master-ci-green');
        self::assertNotFalse($resolved, 'bin/check-master-ci-green must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/ci-green-test-' . bin2hex(random_bytes(8));
        $this->shimDir = $this->tmpDir . '/shims';
        $this->repoDir = $this->tmpDir . '/repo';

        mkdir($this->shimDir, 0755, true);
        mkdir($this->repoDir, 0755, true);

        exec('git init -b master ' . escapeshellarg($this->repoDir) . ' 2>&1');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' config user.name "Test"');
        file_put_contents($this->repoDir . '/README.md', 'init');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' add -A');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' commit -m "initial" 2>&1');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' remote add origin https://github.com/test/repo.git 2>&1');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' update-ref refs/remotes/origin/master HEAD 2>&1');

        $this->createGhShim(0);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testAllGreenExitsZero(): void
    {
        $fixture = $this->writeFixture([
            $this->checkRun('tests', 'completed', 'success'),
            $this->checkRun('lint', 'completed', 'success'),
            $this->checkRun('deploy', 'completed', 'success'),
        ]);

        $result = $this->runScript([], ['GH_API_CMD' => "cat $fixture #"]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS', $result['output']);
    }

    public function testFailedCheckExitsOne(): void
    {
        $fixture = $this->writeFixture([
            $this->checkRun('tests', 'completed', 'success'),
            $this->checkRun('deploy', 'completed', 'failure'),
        ]);

        $result = $this->runScript([], ['GH_API_CMD' => "cat $fixture #"]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('deploy', $result['output']);
    }

    public function testInProgressExitsOne(): void
    {
        $fixture = $this->writeFixture([
            $this->checkRun('tests', 'completed', 'success'),
            $this->checkRun('e2e', 'in_progress', null),
        ]);

        $result = $this->runScript([], ['GH_API_CMD' => "cat $fixture #"]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('still running', $result['output']);
    }

    public function testNoChecksFoundExitsOne(): void
    {
        $fixture = $this->writeFixture([]);

        $result = $this->runScript([], ['GH_API_CMD' => "cat $fixture #"]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('0 check-run', $result['output']);
    }

    public function testMinRequiredEnvOverride(): void
    {
        $fixture = $this->writeFixture([
            $this->checkRun('tests', 'completed', 'success'),
            $this->checkRun('lint', 'completed', 'success'),
        ]);

        $result = $this->runScript([], [
            'GH_API_CMD' => "cat $fixture #",
            'MIN_REQUIRED_CHECKS' => '3',
        ]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('only 2', $result['output']);
    }

    public function testSkipCiCheckBypassesFailure(): void
    {
        $result = $this->runScript(['--skip-ci-check']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('BYPASS', $result['output']);
    }

    public function testHelpFlagExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }

    public function testBogusArgExitsTwo(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --bogus 2>&1', $output, $exit);

        self::assertSame(2, $exit);
        self::assertStringContainsString('unknown flag', implode("\n", $output));
    }

    public function testGhUnauthenticatedExitsTwo(): void
    {
        $this->createGhShim(1);

        $result = $this->runScript([]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('gh auth login', $result['output']);
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runScript(array $args = [], array $env = []): array
    {
        $output = [];
        $exit = 0;

        $envPrefix = 'PATH=' . escapeshellarg($this->shimDir . ':' . getenv('PATH'));
        foreach ($env as $key => $value) {
            $envPrefix .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $argStr = '';
        foreach ($args as $arg) {
            $argStr .= ' ' . escapeshellarg($arg);
        }

        $cmd = 'cd ' . escapeshellarg($this->repoDir)
            . ' && ' . $envPrefix
            . ' bash ' . escapeshellarg($this->scriptPath) . $argStr . ' 2>&1';

        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function createGhShim(int $authExitCode): void
    {
        $script = "#!/bin/bash\n";
        $script .= "if [[ \"\$1\" == \"auth\" && \"\$2\" == \"status\" ]]; then\n";
        $script .= "    exit $authExitCode\n";
        $script .= "fi\n";
        $script .= "exit 1\n";
        file_put_contents($this->shimDir . '/gh', $script);
        chmod($this->shimDir . '/gh', 0755);
    }

    private function writeFixture(array $checkRuns): string
    {
        $path = $this->tmpDir . '/fixture-' . bin2hex(random_bytes(4)) . '.json';
        $data = json_encode(['check_runs' => $checkRuns], JSON_PRETTY_PRINT);
        file_put_contents($path, $data);
        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRun(string $name, string $status, ?string $conclusion): array
    {
        $run = [
            'name' => $name,
            'status' => $status,
        ];
        if ($conclusion !== null) {
            $run['conclusion'] = $conclusion;
        }
        return $run;
    }

    private function recursiveRm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRm($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
