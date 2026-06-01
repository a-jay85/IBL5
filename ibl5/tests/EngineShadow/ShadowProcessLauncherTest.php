<?php

declare(strict_types=1);

namespace Tests\EngineShadow;

use EngineShadow\ShadowProcessLauncher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the detached shadow-process launcher.
 *
 * Rows 4/5 use a spawn() recording seam (no real process) to lock the exact argv
 * and descriptors. The non-blocking test does a REAL spawn against a slow stub
 * script to prove `setsid --fork` detaches: launch() must return immediately while
 * the child keeps running (without --fork, proc_close would block until the child
 * exits — the exact bug this PR removes). The small dev/CI seed cannot surface that
 * regression via the live run rows, so this timed assertion is the discriminator.
 */
final class ShadowProcessLauncherTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    #[Test]
    public function buildsExactDetachedArgvAndFileDescriptors(): void
    {
        $script = $this->tempScript('<?php');
        $logPath = $this->tempPath('log');

        // Recording subclass as a LOCAL var so PHPStan keeps the anon type (sees
        // the public $recordedArgv / $recordedDescriptors properties).
        $launcher = new class ($script, $logPath, '/usr/local/bin/php', '/usr/bin/setsid') extends ShadowProcessLauncher {
            /** @var list<string> */
            public array $recordedArgv = [];
            /** @var array<int, array{0: string, 1: string, 2?: string}> */
            public array $recordedDescriptors = [];

            protected function spawn(array $argv, array $descriptors): void
            {
                $this->recordedArgv = $argv;
                $this->recordedDescriptors = $descriptors;
            }
        };

        $launcher->launch();

        self::assertSame(
            ['/usr/bin/setsid', '--fork', '/usr/local/bin/php', $script],
            $launcher->recordedArgv,
        );
        self::assertSame(['file', '/dev/null', 'r'], $launcher->recordedDescriptors[0]);
        self::assertSame(['file', $logPath, 'a'], $launcher->recordedDescriptors[1]);
        self::assertSame(['file', $logPath, 'a'], $launcher->recordedDescriptors[2]);
    }

    #[Test]
    public function argvIsAFixedArrayWithNoShellMetacharactersOrUserInput(): void
    {
        $script = $this->tempScript('<?php');
        $logPath = $this->tempPath('log');

        $launcher = new class ($script, $logPath) extends ShadowProcessLauncher {
            /** @var list<string> */
            public array $recordedArgv = [];

            protected function spawn(array $argv, array $descriptors): void
            {
                $this->recordedArgv = $argv;
            }
        };

        $launcher->launch();

        // No element carries a shell metacharacter, and none is user-derived — the
        // year is resolved inside the CLI script, never passed on the web path.
        foreach ($launcher->recordedArgv as $arg) {
            self::assertDoesNotMatchRegularExpression('/[;&|`$(){}<>\s]/', $arg, "argv element '$arg' must be shell-metachar-free");
        }
        // setsid --fork is present (the non-blocking guarantee depends on it).
        self::assertContains('--fork', $launcher->recordedArgv);
    }

    #[Test]
    public function missingScriptThrowsBeforeSpawn(): void
    {
        $launcher = new ShadowProcessLauncher('/nonexistent/runEngineShadow-' . bin2hex(random_bytes(4)) . '.php', $this->tempPath('log'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');
        $launcher->launch();
    }

    #[Test]
    public function launchReturnsImmediatelyWhileChildRunsDetached(): void
    {
        if (!is_file('/usr/bin/setsid') || !is_file('/usr/local/bin/php')) {
            // phpunit-hygiene-allow: integration-availability skip — real setsid+php-cli exist only in the Linux container/CI, not the macOS host; runs for real in CI
            self::markTestSkipped('Real-spawn detachment proof requires Linux setsid + php-cli (CI/container only).');
        }

        $sentinel = $this->tempPath('sentinel');
        // Slow child: sleeps, then writes the sentinel. If launch() blocked on it,
        // the elapsed time below would exceed the sleep.
        $script = $this->tempScript(
            "<?php\nusleep(2_000_000);\nfile_put_contents('" . $sentinel . "', 'done');\n"
        );
        $logPath = $this->tempPath('log');

        $launcher = new ShadowProcessLauncher($script, $logPath);

        $t0 = microtime(true);
        $launcher->launch();
        $elapsed = microtime(true) - $t0;

        // Detached: launch() returns in well under the child's 2s sleep.
        self::assertLessThan(1.0, $elapsed, 'launch() must not block on the child (setsid --fork detaches it)');
        // The sentinel does NOT exist yet (child still sleeping) — proves async.
        self::assertFileDoesNotExist($sentinel, 'child should still be running, not yet finished');

        // The detached child survives and completes on its own.
        $deadline = microtime(true) + 6.0;
        while (!is_file($sentinel) && microtime(true) < $deadline) {
            usleep(100_000);
        }
        self::assertFileExists($sentinel, 'detached child ran to completion after launch() returned');
    }

    private function tempScript(string $body): string
    {
        $path = $this->tempPath('script') . '.php';
        file_put_contents($path, $body);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function tempPath(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/shadow-launcher-' . $prefix . '-' . bin2hex(random_bytes(6));
        $this->tempFiles[] = $path;

        return $path;
    }
}
