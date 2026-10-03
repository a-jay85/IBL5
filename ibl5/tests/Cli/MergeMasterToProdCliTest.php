<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class MergeMasterToProdCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;
    private string $originDir;
    private string $cloneDir;
    private string $shimDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/merge-master-to-prod');
        self::assertNotFalse($resolved, 'bin/merge-master-to-prod must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/merge-prod-test-' . bin2hex(random_bytes(8));
        $this->originDir = $this->tmpDir . '/origin.git';
        $this->cloneDir = $this->tmpDir . '/clone';
        $this->shimDir = $this->tmpDir . '/shims';

        mkdir($this->tmpDir, 0755, true);
        mkdir($this->shimDir, 0755, true);

        $this->createBareOrigin();
        $this->createWorkingClone();
        $this->createShims();
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testHappyPath(): void
    {
        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Expected exit 0, got: {$result['output']}");
        self::assertStringContainsString('Done. Pushed master and production.', $result['output']);

        $masterSha = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse master'));
        $prodSha = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse production'));
        self::assertSame($masterSha, $prodSha, 'production should match master after merge');
    }

    public function testInvokesCiGreenGate(): void
    {
        $sentinel = $this->tmpDir . '/ci-gate-called';
        $binDir = $this->cloneDir . '/bin';
        file_put_contents(
            $binDir . '/check-master-ci-green',
            "#!/bin/bash\ntouch " . escapeshellarg($sentinel) . "\nexit 0\n"
        );
        chmod($binDir . '/check-master-ci-green', 0755);

        $this->runScript();

        self::assertFileExists($sentinel, 'check-master-ci-green should have been called');
    }

    public function testSkipCiCheckEnvBypassesGate(): void
    {
        $binDir = $this->cloneDir . '/bin';
        file_put_contents($binDir . '/check-master-ci-green', "#!/bin/bash\nexit 1\n");
        chmod($binDir . '/check-master-ci-green', 0755);

        $result = $this->runScript(['SKIP_CI_CHECK' => '1']);

        self::assertSame(0, $result['exit'], "Expected exit 0 with SKIP_CI_CHECK, got: {$result['output']}");
        self::assertStringContainsString('Done. Pushed master and production.', $result['output']);
    }

    public function testRedCiAbortsBeforePush(): void
    {
        $binDir = $this->cloneDir . '/bin';
        file_put_contents($binDir . '/check-master-ci-green', "#!/bin/bash\nexit 1\n");
        chmod($binDir . '/check-master-ci-green', 0755);

        $prodShaBefore = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse production'));

        $result = $this->runScript();

        self::assertNotSame(0, $result['exit'], 'Should fail when CI is red');
        self::assertStringContainsString('not green on CI', $result['output']);

        $prodShaAfter = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse production'));
        self::assertSame($prodShaBefore, $prodShaAfter, 'production should not have changed');
    }

    public function testDivergedProductionAborts(): void
    {
        $prodClone = $this->tmpDir . '/prod-pusher';
        exec('git clone ' . escapeshellarg($this->originDir) . ' ' . escapeshellarg($prodClone) . ' 2>&1');
        exec('git -C ' . escapeshellarg($prodClone) . ' checkout production 2>&1');
        exec('git -C ' . escapeshellarg($prodClone) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($prodClone) . ' config user.name "Test"');
        file_put_contents($prodClone . '/diverged.txt', 'production-only commit');
        exec('git -C ' . escapeshellarg($prodClone) . ' add -A');
        exec('git -C ' . escapeshellarg($prodClone) . ' commit -m "diverge production" 2>&1');
        exec('git -C ' . escapeshellarg($prodClone) . ' push origin production 2>&1');

        $prodShaBefore = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse production'));

        $result = $this->runScript();

        self::assertNotSame(0, $result['exit'], 'Should fail when production has diverged');
        self::assertStringContainsString('fast-forward failed', $result['output']);

        $prodShaAfter = trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse production'));
        self::assertSame($prodShaBefore, $prodShaAfter, 'production should not have changed');
    }

    public function testHelpFlagPrintsUsageWithoutTouchingOrigin(): void
    {
        $masterBefore = $this->originSha('master');
        $prodBefore = $this->originSha('production');

        $result = $this->runScript([], ['--help']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Usage: bin/merge-master-to-prod', $result['output']);
        self::assertStringContainsString('SKIP_CI_CHECK=1', $result['output']);
        self::assertSame($masterBefore, $this->originSha('master'));
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testShortHelpFlagExitsZero(): void
    {
        $prodBefore = $this->originSha('production');

        $result = $this->runScript([], ['-h']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Usage: bin/merge-master-to-prod', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testNotAGitRepositoryFails(): void
    {
        $prodBefore = $this->originSha('production');

        $result = $this->runScript(['GIT_DIR' => $this->tmpDir . '/not-a-repo']);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAILED: Not a git repository', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testMissingOriginMasterFails(): void
    {
        $prodBefore = $this->originSha('production');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' update-ref -d refs/remotes/origin/master 2>&1');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAILED: master branch not found on origin', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testMissingOriginProductionFails(): void
    {
        $prodBefore = $this->originSha('production');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' update-ref -d refs/remotes/origin/production 2>&1');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAILED: production branch not found on origin', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testShallowCloneIsHealedBeforeMerge(): void
    {
        $this->recloneShallow();
        self::assertSame('true', $this->cloneShallowState(), 'fixture clone must start shallow');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Expected exit 0, got: {$result['output']}");
        self::assertStringContainsString('Shallow clone detected', $result['output']);
        self::assertStringContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame('false', $this->cloneShallowState());
        self::assertSame($this->originSha('master'), $this->originSha('production'));
    }

    public function testShallowCloneUnshallowFailureAborts(): void
    {
        $this->recloneShallow();
        self::assertSame('true', $this->cloneShallowState(), 'fixture clone must start shallow');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' remote set-url origin '
            . escapeshellarg($this->tmpDir . '/gone.git') . ' 2>&1');
        $prodBefore = $this->originSha('production');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAILED: unshallow failed', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame('true', $this->cloneShallowState());
        self::assertSame($prodBefore, $this->originSha('production'));
    }

    public function testNoCommonAncestorAbortsWithoutCherryPickAdvice(): void
    {
        $scratch = $this->tmpDir . '/orphan';
        mkdir($scratch, 0755, true);
        exec('git -C ' . escapeshellarg($scratch) . ' init 2>&1');
        exec('git -C ' . escapeshellarg($scratch) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($scratch) . ' config user.name "Test"');
        file_put_contents($scratch . '/orphan.txt', 'unrelated history');
        exec('git -C ' . escapeshellarg($scratch) . ' add -A');
        exec('git -C ' . escapeshellarg($scratch) . ' commit -m "orphan" 2>&1');
        $orphanSha = trim((string) shell_exec('git -C ' . escapeshellarg($scratch) . ' rev-parse HEAD'));
        self::assertNotSame('', $orphanSha);

        exec('git -C ' . escapeshellarg($this->originDir) . ' fetch ' . escapeshellarg($scratch) . ' HEAD 2>&1');
        exec('git -C ' . escapeshellarg($this->originDir) . ' update-ref refs/heads/production ' . escapeshellarg($orphanSha));
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' fetch origin 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' update-ref refs/heads/production ' . escapeshellarg($orphanSha));
        self::assertSame($orphanSha, $this->originSha('production'));

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('share no common ancestor', $result['output']);
        self::assertStringContainsString('git fetch --unshallow origin', $result['output']);
        self::assertStringNotContainsString(
            'cherry-pick the production-only commits onto master first',
            $result['output']
        );
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($orphanSha, $this->originSha('production'));
    }

    public function testPushMasterFailureAbortsBeforeProduction(): void
    {
        file_put_contents($this->cloneDir . '/local-only.txt', 'unpushed master commit');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' add local-only.txt 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' commit -m "local master commit" 2>&1');
        $this->rejectPushesTo('refs/heads/master');
        $sentinel = $this->installCleanupSentinel();
        $prodBefore = $this->originSha('production');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], $result['output']);
        self::assertStringContainsString('FAILED: push master', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
        self::assertFileDoesNotExist($sentinel);
    }

    public function testPushProductionFailureAbortsBeforeCleanup(): void
    {
        $this->rejectPushesTo('refs/heads/production');
        $sentinel = $this->installCleanupSentinel();
        $prodBefore = $this->originSha('production');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAILED: push production', $result['output']);
        self::assertStringNotContainsString('Done. Pushed master and production.', $result['output']);
        self::assertSame($prodBefore, $this->originSha('production'));
        self::assertFileDoesNotExist($sentinel);
    }

    public function testCleanupRunsWithAllFlagAndSkipWtCleanupExported(): void
    {
        $sentinel = $this->installCleanupSentinel();

        $result = $this->runScript(['SKIP_WT_CLEANUP' => '0']);

        self::assertSame(0, $result['exit'], "Expected exit 0, got: {$result['output']}");
        self::assertFileExists($sentinel);
        // The caller passed 0, so only the script's own export can yield 1.
        // Deleting that export would leave the sentinel reading '--all|0'.
        self::assertSame('--all|1', file_get_contents($sentinel));
    }

    public function testNonExecutableCleanupIsSkipped(): void
    {
        $sentinel = $this->installCleanupSentinel(false);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Expected exit 0, got: {$result['output']}");
        self::assertStringContainsString('Done. Pushed master and production.', $result['output']);
        self::assertFileDoesNotExist($sentinel);
    }

    private function originSha(string $branch): string
    {
        return trim((string) shell_exec('git -C ' . escapeshellarg($this->originDir) . ' rev-parse ' . escapeshellarg($branch)));
    }

    private function cloneShallowState(): string
    {
        return trim((string) shell_exec('git -C ' . escapeshellarg($this->cloneDir) . ' rev-parse --is-shallow-repository'));
    }

    private function rejectPushesTo(string $ref): void
    {
        $hook = $this->originDir . '/hooks/pre-receive';
        $body = "#!/bin/bash\n"
            . "while read -r _old _new ref; do\n"
            . '  [ "$ref" = ' . escapeshellarg($ref) . ' ] && { echo "rejected $ref" >&2; exit 1; }' . "\n"
            . "done\n"
            . "exit 0\n";
        if (!is_dir(dirname($hook))) {
            mkdir(dirname($hook), 0755, true);
        }
        file_put_contents($hook, $body);
        chmod($hook, 0755);
    }

    private function installCleanupSentinel(bool $executable = true): string
    {
        $sentinel = $this->tmpDir . '/cleanup-called';
        $cleanup = $this->cloneDir . '/bin/cleanup';
        file_put_contents(
            $cleanup,
            "#!/bin/bash\nprintf '%s|%s' \"\$*\" \"\${SKIP_WT_CLEANUP:-unset}\" > " . escapeshellarg($sentinel) . "\n"
        );
        chmod($cleanup, $executable ? 0755 : 0644);

        return $sentinel;
    }

    private function recloneShallow(): void
    {
        $this->recursiveRm($this->cloneDir);
        exec('git clone --depth 1 --no-single-branch ' . escapeshellarg('file://' . $this->originDir)
            . ' ' . escapeshellarg($this->cloneDir) . ' 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' config user.name "Test"');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' checkout master 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' branch production origin/production 2>&1');
        $this->createShims();
    }

    /**
     * @param array<string, string> $env
     * @param list<string> $args
     * @return array{output: string, exit: int}
     */
    private function runScript(array $env = [], array $args = []): array
    {
        $output = [];
        $exit = 0;

        $envPrefix = 'PATH=' . escapeshellarg($this->shimDir . ':' . getenv('PATH'));
        $envPrefix .= ' SKIP_WT_CLEANUP=1';
        foreach ($env as $key => $value) {
            $envPrefix .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $cmd = 'cd ' . escapeshellarg($this->cloneDir)
            . ' && ' . $envPrefix
            . ' bash ' . escapeshellarg($this->scriptPath)
            . ($args === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $args)))
            . ' 2>&1';

        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function createBareOrigin(): void
    {
        exec('git init --bare ' . escapeshellarg($this->originDir) . ' 2>&1');

        $setup = $this->tmpDir . '/setup';
        mkdir($setup, 0755, true);
        exec('git clone ' . escapeshellarg($this->originDir) . ' ' . escapeshellarg($setup) . ' 2>&1');
        exec('git -C ' . escapeshellarg($setup) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($setup) . ' config user.name "Test"');
        exec('git -C ' . escapeshellarg($setup) . ' checkout -b master 2>&1');
        file_put_contents($setup . '/README.md', 'init');
        exec('git -C ' . escapeshellarg($setup) . ' add -A');
        exec('git -C ' . escapeshellarg($setup) . ' commit -m "initial" 2>&1');
        exec('git -C ' . escapeshellarg($setup) . ' push origin master 2>&1');

        exec('git -C ' . escapeshellarg($setup) . ' checkout -b production 2>&1');
        exec('git -C ' . escapeshellarg($setup) . ' push origin production 2>&1');

        exec('git -C ' . escapeshellarg($setup) . ' checkout master 2>&1');
        file_put_contents($setup . '/feature.txt', 'new feature');
        exec('git -C ' . escapeshellarg($setup) . ' add -A');
        exec('git -C ' . escapeshellarg($setup) . ' commit -m "add feature" 2>&1');
        exec('git -C ' . escapeshellarg($setup) . ' push origin master 2>&1');

        $this->recursiveRm($setup);
    }

    private function createWorkingClone(): void
    {
        exec('git clone ' . escapeshellarg($this->originDir) . ' ' . escapeshellarg($this->cloneDir) . ' 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' config user.email "test@test.com"');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' config user.name "Test"');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' checkout master 2>&1');
        exec('git -C ' . escapeshellarg($this->cloneDir) . ' branch production origin/production 2>&1');
    }

    private function createShims(): void
    {
        file_put_contents($this->shimDir . '/gh', "#!/bin/bash\nexit 0\n");
        chmod($this->shimDir . '/gh', 0755);

        $binDir = $this->cloneDir . '/bin';
        mkdir($binDir, 0755, true);

        file_put_contents($binDir . '/cleanup', "#!/bin/bash\nexit 0\n");
        chmod($binDir . '/cleanup', 0755);

        file_put_contents($binDir . '/check-master-ci-green', "#!/bin/bash\nexit 0\n");
        chmod($binDir . '/check-master-ci-green', 0755);
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
