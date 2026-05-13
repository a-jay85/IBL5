<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class RefactorFlagCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/refactor-flag');
        self::assertNotFalse($resolved, 'bin/refactor-flag must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/refactor-flag-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        $this->runInDir('git init -b main');
        $this->runInDir('git config user.email "test@test.com"');
        $this->runInDir('git config user.name "Test"');

        mkdir($this->tmpDir . '/ibl5/classes/Example', 0755, true);
        mkdir($this->tmpDir . '/ibl5/tests/Example', 0755, true);
        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', "<?php\nclass Foo {}\n");
        file_put_contents($this->tmpDir . '/ibl5/tests/Example/FooTest.php', "<?php\nclass FooTest {}\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "initial"');
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    /** Case 1: No classes/ changes → exit 0 */
    public function testNoClassesChangesExitsZero(): void
    {
        file_put_contents($this->tmpDir . '/README.md', 'hello');
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('No refactor signals detected', $result['output']);
    }

    /** Case 2: Rename under classes/ + new test in PR → exit 0 */
    public function testRenameWithNewTestExitsZero(): void
    {
        $this->runInDir('git mv ibl5/classes/Example/Foo.php ibl5/classes/Example/Bar.php');
        file_put_contents($this->tmpDir . '/ibl5/tests/Example/BarTest.php', "<?php\nclass BarTest {}\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('PASS', $result['output']);
    }

    /** Case 3: Rename under classes/ + no test → exit 1 */
    public function testRenameWithNoTestExitsOne(): void
    {
        $this->runInDir('git mv ibl5/classes/Example/Foo.php ibl5/classes/Example/Bar.php');
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
        self::assertStringContainsString('rename', $result['output']);
    }

    /** Case 4: Rename + bypass marker (≥20 chars) → exit 0 */
    public function testRenameWithValidBypassExitsZero(): void
    {
        $this->runInDir('git mv ibl5/classes/Example/Foo.php ibl5/classes/Example/Bar.php');
        $this->runInDir('git add -A');

        $bypass = '<!-- no-refactor-tests: pure namespace move with existing coverage intact -->';
        $result = $this->runScript($bypass);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    /** Case 5: Rename + bypass marker too short (10 chars) → exit 1 */
    public function testRenameWithShortBypassExitsOne(): void
    {
        $this->runInDir('git mv ibl5/classes/Example/Foo.php ibl5/classes/Example/Bar.php');
        $this->runInDir('git add -A');

        $bypass = '<!-- no-refactor-tests: too short -->';
        $result = $this->runScript($bypass);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
    }

    /** Case 6: Method signature change + matching test modified → exit 0 */
    public function testSignatureChangeWithMatchingTestExitsZero(): void
    {
        $original = <<<'PHP'
<?php
class Foo {
    public function doStuff(int $a): string
    {
        return (string) $a;
    }
}
PHP;
        $changed = <<<'PHP'
<?php
class Foo {
    public function doStuff(int $a, string $b): string
    {
        return $b . $a;
    }
}
PHP;
        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $original);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add method"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $changed);
        file_put_contents($this->tmpDir . '/ibl5/tests/Example/FooTest.php', "<?php\nclass FooTest { /* updated */ }\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('PASS', $result['output']);
    }

    /** Case 7: Method signature change + unrelated test modified → exit 1 */
    public function testSignatureChangeWithUnrelatedTestExitsOne(): void
    {
        $original = <<<'PHP'
<?php
class Foo {
    public function doStuff(int $a): string
    {
        return (string) $a;
    }
}
PHP;
        $changed = <<<'PHP'
<?php
class Foo {
    public function doStuff(int $a, string $b): string
    {
        return $b . $a;
    }
}
PHP;
        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $original);
        mkdir($this->tmpDir . '/ibl5/tests/Other', 0755, true);
        file_put_contents($this->tmpDir . '/ibl5/tests/Other/UnrelatedTest.php', "<?php\nclass UnrelatedTest {}\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add method"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $changed);
        file_put_contents($this->tmpDir . '/ibl5/tests/Other/UnrelatedTest.php', "<?php\nclass UnrelatedTest { /* modified */ }\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
    }

    /** Case 8: Class declaration removed + new test added → exit 0 */
    public function testClassRemovedWithNewTestExitsZero(): void
    {
        $withClass = <<<'PHP'
<?php
class Foo {
    public function hello(): void {}
}
PHP;
        $withoutClass = "<?php\n// removed\n";

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $withClass);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add class"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $withoutClass);
        file_put_contents($this->tmpDir . '/ibl5/tests/Example/FooRemovalTest.php', "<?php\nclass FooRemovalTest {}\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('PASS', $result['output']);
    }

    /** Case 8b: final/abstract class declaration removed + no test → exit 1 */
    public function testFinalClassRemovedWithNoTestExitsOne(): void
    {
        $withClass = "<?php\nfinal class Foo {}\n";

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $withClass);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add final class"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', "<?php\n// removed\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
        self::assertStringContainsString('class-removed', $result['output']);
    }

    /** Case 9: Visibility change (public → private) + no test → exit 1 */
    public function testVisibilityChangeWithNoTestExitsOne(): void
    {
        $original = <<<'PHP'
<?php
class Foo {
    public function secret(): void {}
}
PHP;
        $changed = <<<'PHP'
<?php
class Foo {
    private function secret(): void {}
}
PHP;
        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $original);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add method"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $changed);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
        self::assertStringContainsString('visibility', $result['output']);
    }

    /** Case 10: Large deletion (>30 lines) + no test → exit 1 */
    public function testLargeDeletionWithNoTestExitsOne(): void
    {
        $lines = "<?php\nclass Foo {\n";
        for ($i = 0; $i < 40; $i++) {
            $lines .= "    public function method{$i}(): void {}\n";
        }
        $lines .= "}\n";

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', $lines);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "big class"');

        file_put_contents($this->tmpDir . '/ibl5/classes/Example/Foo.php', "<?php\nclass Foo {}\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL', $result['output']);
        self::assertStringContainsString('large-deletion', $result['output']);
    }

    /** Case 11: --help flag → exit 0 with "Usage:" */
    public function testHelpFlagExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }

    /** Case 12: --bogus flag → exit 2 with "unknown flag" */
    public function testBogusArgExitsTwo(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --bogus 2>&1', $output, $exit);

        self::assertSame(2, $exit);
        self::assertStringContainsString('unknown flag', implode("\n", $output));
    }

    /**
     * Run bin/refactor-flag --staged inside the temporary git repo.
     * @return array{output: string, exit: int}
     */
    private function runScript(?string $prBody = null): array
    {
        $output = [];
        $exit = 0;

        $cmd = 'cd ' . escapeshellarg($this->tmpDir) . ' && ';

        if ($prBody !== null) {
            $cmd .= 'echo ' . escapeshellarg($prBody) . ' | ';
            $cmd .= escapeshellcmd($this->scriptPath) . ' --staged --bypass-from-stdin';
        } else {
            $cmd .= escapeshellcmd($this->scriptPath) . ' --staged';
        }

        $cmd .= ' 2>&1';

        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function runInDir(string $cmd): void
    {
        exec('cd ' . escapeshellarg($this->tmpDir) . ' && ' . $cmd . ' 2>&1');
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
