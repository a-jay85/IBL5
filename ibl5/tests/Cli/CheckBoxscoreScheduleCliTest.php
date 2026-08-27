<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Host-side tests for the bin/check-boxscore-schedule bash wrapper.
 *
 * These tests exercise the wrapper directly and must not require a database
 * or a running Docker container.
 */
#[Group('cli')]
final class CheckBoxscoreScheduleCliTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-boxscore-schedule');
        self::assertNotFalse($resolved, 'bin/check-boxscore-schedule must exist');
        $this->scriptPath = $resolved;
    }

    // ── Existence and executability ──────────────────────────────────────────

    /**
     * The script must exist and be executable — the most common miss on a new bin/ entry.
     */
    public function testScriptExistsAndIsExecutable(): void
    {
        self::assertNotFalse(realpath($this->scriptPath), 'bin/check-boxscore-schedule must exist');
        self::assertTrue(is_executable($this->scriptPath), 'bin/check-boxscore-schedule must be executable (chmod +x)');
    }

    // ── Help and usage ───────────────────────────────────────────────────────

    /**
     * --help must exit 0 and emit the usage block naming all key options and exit codes.
     */
    public function testHelpExitsZeroAndPrintsUsage(): void
    {
        [$stdout, $stderr, $exit] = $this->exec('--help');

        self::assertSame(0, $exit, "Expected exit 0 for --help, got {$exit}. stderr: {$stderr}");
        self::assertStringContainsString('--replay', $stdout);
        self::assertStringContainsString('--season', $stdout);
        self::assertStringContainsString('exit code', strtolower($stdout));
        // All four exit codes must be named
        self::assertStringContainsString('0', $stdout);
        self::assertStringContainsString('1', $stdout);
        self::assertStringContainsString('2', $stdout);
        self::assertStringContainsString('3', $stdout);
    }

    // ── Unknown flag handling ────────────────────────────────────────────────

    /**
     * An unknown flag must exit 2 with usage on stderr, not stdout.
     */
    public function testUnknownFlagExitsTwo(): void
    {
        [$stdout, $stderr, $exit] = $this->exec('--bogus');

        self::assertSame(2, $exit, "Expected exit 2 for --bogus, got {$exit}");
        self::assertStringContainsString('Usage:', $stderr, 'Usage block must appear on stderr');
        self::assertSame('', $stdout, 'Nothing should be written to stdout for an unknown flag');
    }

    // ── --replay path constraint ─────────────────────────────────────────────

    /**
     * A --replay path outside the checkout (e.g. /tmp) must exit 2 with the
     * bind-mount constraint message — not "file not found" from inside the container.
     */
    public function testReplayPathOutsideCheckoutExitsTwo(): void
    {
        [$stdout, $stderr, $exit] = $this->exec('--replay=/tmp/nope.sco');

        self::assertSame(2, $exit, "Expected exit 2 for out-of-checkout --replay, got {$exit}. stderr: {$stderr}");
        self::assertStringContainsString('bind-mount', strtolower($stderr . $stdout), 'Must explain the bind-mount constraint');
    }

    // ── Container liveness ───────────────────────────────────────────────────

    /**
     * When the PHP container is unavailable (pointed at a non-running slug),
     * the wrapper must exit 3 and name a remedy command (bin/dev-up or bin/wt-up).
     *
     * Uses BOXSCORE_SCHEDULE_PHP_CONTAINER to bypass the slug-derived container name
     * and force the liveness check to fail.
     */
    public function testMissingPhpContainerExitsThree(): void
    {
        // A container name that is guaranteed not to be running.
        $fakeContainer = 'ibl5-php-boxscore-schedule-guard-no-such-container-' . bin2hex(random_bytes(4));

        [$stdout, $stderr, $exit] = $this->exec('', env: ['BOXSCORE_SCHEDULE_PHP_CONTAINER' => $fakeContainer]);

        self::assertSame(3, $exit, "Expected exit 3 when container is missing, got {$exit}. stderr: {$stderr}");
        $combined = $stdout . $stderr;
        self::assertTrue(
            str_contains($combined, 'bin/dev-up') || str_contains($combined, 'bin/wt-up'),
            "Remedy command (bin/dev-up or bin/wt-up) must appear in output. Got: {$combined}"
        );
    }

    // ── Flag order independence ──────────────────────────────────────────────

    /**
     * --json --help must still exit 0, proving flags are collected before dispatch.
     */
    public function testJsonFlagIsAcceptedByArgParser(): void
    {
        [$stdout, $stderr, $exit] = $this->exec('--json --help');

        self::assertSame(0, $exit, "Expected exit 0 for --json --help, got {$exit}. stderr: {$stderr}");
        self::assertStringContainsString('Usage:', $stdout);
    }

    /**
     * --help output must name --rejects so operators know the flag exists.
     */
    public function testRejectsFlagAppearsInHelp(): void
    {
        [$stdout, $stderr, $exit] = $this->exec('--help');

        self::assertSame(0, $exit, "Expected exit 0 for --help, got {$exit}. stderr: {$stderr}");
        self::assertStringContainsString('--rejects', $stdout);
    }

    /**
     * --rejects=abc (non-numeric) must exit 2 before reaching the container check.
     *
     * The bash wrapper validates the value so the exit happens on the host,
     * without requiring a running Docker container.
     */
    public function testRejectsFlagWithNonNumericValueExitsTwo(): void
    {
        [, $stderr, $exit] = $this->exec('--rejects=abc');

        self::assertSame(2, $exit, "Expected exit 2 for --rejects=abc, got {$exit}. stderr: {$stderr}");
    }

    // ── Shellcheck ───────────────────────────────────────────────────────────

    /**
     * The wrapper must be shellcheck-clean if shellcheck is available on the host.
     */
    public function testShellcheckClean(): void
    {
        $shellcheck = trim((string) shell_exec('command -v shellcheck 2>/dev/null'));
        if ($shellcheck === '') {
            self::markTestSkipped('shellcheck not installed — install it to enable this check'); // phpunit-hygiene-allow: shellcheck unavailability is an environment constraint, not a test defect
        }

        // Run shellcheck with -x (follow source directives) from the repo root so
        // it can resolve "bin/lib/db-helpers.sh" and "bin/lib/git-helpers.sh".
        $repoRoot = realpath(__DIR__ . '/../../../');
        self::assertIsString($repoRoot);
        $scriptRelative = str_replace($repoRoot . '/', '', $this->scriptPath);

        $output = [];
        $exit   = 0;
        exec(
            'cd ' . escapeshellarg($repoRoot) . ' && '
            . escapeshellarg($shellcheck) . ' -x ' . escapeshellarg($scriptRelative) . ' 2>&1',
            $output,
            $exit
        );

        self::assertSame(0, $exit, 'shellcheck found issues: ' . implode("\n", $output));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Execute the wrapper with optional args and env overrides.
     *
     * Returns [stdout, stderr, exit_code].
     *
     * @param array<string, string> $env
     * @return array{0: string, 1: string, 2: int}
     */
    private function exec(string $args, array $env = []): array
    {
        $stdoutFile = tempnam(sys_get_temp_dir(), 'cbs_out_');
        $stderrFile = tempnam(sys_get_temp_dir(), 'cbs_err_');
        self::assertIsString($stdoutFile);
        self::assertIsString($stderrFile);

        // Build the env prefix using `env KEY=VALUE` to avoid shell quoting around
        // the variable name that would make the shell treat it as a command.
        $envParts = [];
        foreach ($env as $k => $v) {
            $envParts[] = $k . '=' . escapeshellarg($v);
        }
        $envPrefix = $envParts !== [] ? 'env ' . implode(' ', $envParts) . ' ' : '';

        $cmd = $envPrefix . escapeshellarg($this->scriptPath)
            . ($args !== '' ? ' ' . $args : '')
            . ' >' . escapeshellarg($stdoutFile)
            . ' 2>' . escapeshellarg($stderrFile);

        $exit = 0;
        exec($cmd, $_, $exit);

        $stdout = (string) file_get_contents($stdoutFile);
        $stderr = (string) file_get_contents($stderrFile);

        unlink($stdoutFile);
        unlink($stderrFile);

        return [$stdout, $stderr, $exit];
    }
}
