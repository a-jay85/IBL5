<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class RollbackPhantomRepairCliTest extends TestCase
{
    private const USAGE_LINE = 'Usage: bin/rollback-phantom-repair [--help]';

    private string $tmpDir;
    private string $fixtureRoot;
    private string $shimDir;
    private string $dockerLog;

    protected function setUp(): void
    {
        $src = realpath(__DIR__ . '/../../../bin/rollback-phantom-repair');
        self::assertNotFalse($src, 'bin/rollback-phantom-repair must exist');

        $tmpBase = realpath(sys_get_temp_dir());
        self::assertNotFalse($tmpBase);
        $this->tmpDir = $tmpBase . '/rollback-phantom-test-' . bin2hex(random_bytes(8));
        $this->fixtureRoot = $this->tmpDir . '/repo';
        $this->shimDir = $this->tmpDir . '/shims';
        $this->dockerLog = $this->tmpDir . '/docker.log';

        mkdir($this->fixtureRoot . '/bin/lib', 0755, true);
        mkdir($this->shimDir, 0755, true);

        copy($src, $this->fixtureRoot . '/bin/rollback-phantom-repair');
        chmod($this->fixtureRoot . '/bin/rollback-phantom-repair', 0755);
        copy(dirname($src) . '/lib/db-helpers.sh', $this->fixtureRoot . '/bin/lib/db-helpers.sh');
        copy(dirname($src) . '/lib/git-helpers.sh', $this->fixtureRoot . '/bin/lib/git-helpers.sh');

        $this->createDockerShim();
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testHelpFlagPrintsUsageAndExitsZeroWithoutDocker(): void
    {
        $result = $this->runScript([], ['--help']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(self::USAGE_LINE, $result['stdout']);
        self::assertSame([], $this->dockerCalls());
    }

    public function testShortHelpFlagExitsZero(): void
    {
        $result = $this->runScript([], ['-h']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(self::USAGE_LINE, $result['stdout']);
        self::assertSame([], $this->dockerCalls());
    }

    public function testUnknownFlagExitsTwoWithUsageOnStderr(): void
    {
        $result = $this->runScript([], ['--force']);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Unknown flag: --force', $result['stderr']);
        self::assertStringContainsString(self::USAGE_LINE, $result['stderr']);
        self::assertSame('', $result['stdout']);
        self::assertSame([], $this->dockerCalls());
    }

    public function testEnvOverrideSetsContainerName(): void
    {
        file_put_contents($this->fixtureRoot . '/.wt-slug', 'feat-x');

        $result = $this->runScript(['PHANTOM_ROLLBACK_PHP_CONTAINER' => 'custom-php']);

        self::assertSame(0, $result['exit']);
        $calls = $this->dockerCalls();
        self::assertNotEmpty($calls);
        self::assertStringEndsWith(' custom-php', $this->callStartingWith($calls, 'inspect'));
        self::assertStringContainsString(' custom-php php ', $this->callStartingWith($calls, 'exec'));
    }

    public function testSlugFileDerivesWorktreeContainerName(): void
    {
        file_put_contents($this->fixtureRoot . '/.wt-slug', 'feat-x');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(' ibl5-php-feat-x php ', $this->callStartingWith($this->dockerCalls(), 'exec'));
    }

    public function testMainCheckoutDerivesBareContainerName(): void
    {
        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(' ibl5-php php ', $this->callStartingWith($this->dockerCalls(), 'exec'));
    }

    public function testLinkedWorktreeWithoutSlugUsesBasename(): void
    {
        $gitdir = $this->tmpDir . '/canon/.git/worktrees/wt1';
        mkdir($gitdir, 0755, true);
        file_put_contents($this->fixtureRoot . '/.git', 'gitdir: ' . $gitdir . "\n");

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(' ibl5-php-repo php ', $this->callStartingWith($this->dockerCalls(), 'exec'));
    }

    public function testStoppedContainerExitsThreeWithWtUpHint(): void
    {
        file_put_contents($this->fixtureRoot . '/.wt-slug', 'feat-x');

        $result = $this->runScript(['DOCKER_SHIM_RUNNING' => 'false']);

        self::assertSame(3, $result['exit']);
        self::assertStringContainsString("PHP container 'ibl5-php-feat-x' is not running", $result['stderr']);
        self::assertStringContainsString('bin/wt-up feat-x', $result['stderr']);
        self::assertSame('', $this->execCall($this->dockerCalls()));
    }

    public function testStoppedContainerWithoutSlugExitsThreeWithDevUpHint(): void
    {
        $result = $this->runScript(['DOCKER_SHIM_RUNNING' => 'false']);

        self::assertSame(3, $result['exit']);
        self::assertStringContainsString('bin/dev-up', $result['stderr']);
        self::assertStringNotContainsString('bin/wt-up', $result['stderr']);
        self::assertSame('', $this->execCall($this->dockerCalls()));
    }

    public function testEmptySlugFileFallsBackToDevUpHint(): void
    {
        file_put_contents($this->fixtureRoot . '/.wt-slug', '');

        $result = $this->runScript(['DOCKER_SHIM_RUNNING' => 'false']);

        self::assertSame(3, $result['exit']);
        self::assertStringContainsString('bin/dev-up', $result['stderr']);
        self::assertStringNotContainsString('bin/wt-up', $result['stderr']);
    }

    public function testInspectFailureExitsThree(): void
    {
        $result = $this->runScript(['DOCKER_SHIM_INSPECT_RC' => '1']);

        self::assertSame(3, $result['exit']);
        self::assertSame('', $this->execCall($this->dockerCalls()));
    }

    public function testExecRunsRollbackEntryPointInsideContainer(): void
    {
        file_put_contents($this->fixtureRoot . '/.wt-slug', 'feat-x');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertContains(
            'exec -w /var/www/html/ibl5 ibl5-php-feat-x php bin/rollback-phantom-repair-run',
            $this->dockerCalls()
        );
    }

    public function testNonZeroExecExitCodePropagates(): void
    {
        $one = $this->runScript(['DOCKER_SHIM_EXEC_RC' => '1']);
        $seven = $this->runScript(['DOCKER_SHIM_EXEC_RC' => '7']);

        self::assertSame(1, $one['exit']);
        self::assertSame(7, $seven['exit']);
    }

    /**
     * @param array<string, string> $env
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runScript(array $env = [], array $args = []): array
    {
        $stderrFile = $this->tmpDir . '/stderr';
        $envPrefix = 'DOCKER_SHIM_LOG=' . escapeshellarg($this->dockerLog)
            . ' PATH=' . escapeshellarg($this->shimDir . ':' . getenv('PATH'));
        foreach ($env as $key => $value) {
            $envPrefix .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $unset = array_key_exists('PHANTOM_ROLLBACK_PHP_CONTAINER', $env)
            ? ''
            : ' -u PHANTOM_ROLLBACK_PHP_CONTAINER';

        $cmd = 'env' . $unset . ' ' . $envPrefix
            . ' bash ' . escapeshellarg($this->fixtureRoot . '/bin/rollback-phantom-repair')
            . ($args === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $args)))
            . ' 2>' . escapeshellarg($stderrFile);

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        $stderr = is_file($stderrFile) ? (string) file_get_contents($stderrFile) : '';

        return ['exit' => $exit, 'stdout' => implode("\n", $output), 'stderr' => $stderr];
    }

    /**
     * @return list<string>
     */
    private function dockerCalls(): array
    {
        if (!is_file($this->dockerLog)) {
            return [];
        }
        $lines = file($this->dockerLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? [] : array_values($lines);
    }

    /**
     * @param list<string> $calls
     */
    private function callStartingWith(array $calls, string $verb): string
    {
        foreach ($calls as $call) {
            if (str_starts_with($call, $verb . ' ')) {
                return $call;
            }
        }
        self::fail("No docker call starting with '{$verb}'; calls: " . implode(' | ', $calls));
    }

    /**
     * @param list<string> $calls
     */
    private function execCall(array $calls): string
    {
        foreach ($calls as $call) {
            if (str_starts_with($call, 'exec ')) {
                return $call;
            }
        }

        return '';
    }

    private function createDockerShim(): void
    {
        $shim = <<<'SH'
#!/bin/bash
printf '%s\n' "$*" >> "$DOCKER_SHIM_LOG"
case "$1" in
  inspect)
    [ "${DOCKER_SHIM_INSPECT_RC:-0}" = "0" ] || exit "$DOCKER_SHIM_INSPECT_RC"
    echo "${DOCKER_SHIM_RUNNING:-true}"; exit 0 ;;
  exec)
    exit "${DOCKER_SHIM_EXEC_RC:-0}" ;;
  *) exit 99 ;;
esac

SH;
        file_put_contents($this->shimDir . '/docker', $shim);
        chmod($this->shimDir . '/docker', 0755);
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
