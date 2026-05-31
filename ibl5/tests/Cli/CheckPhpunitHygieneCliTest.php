<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckPhpunitHygieneCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-phpunit-hygiene');
        self::assertNotFalse($resolved, 'bin/check-phpunit-hygiene must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/phpunit-hyg-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        $this->runInDir('git init -b main');
        $this->runInDir('git config user.email "test@test.com"');
        $this->runInDir('git config user.name "Test"');

        mkdir($this->tmpDir . '/ibl5/tests', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    // The fixture strings below build the banned call from a split token so this
    // test file's own source never matches the scanner's `markTestSkipped\(` pattern
    // (the scanner walks ibl5/tests/**/*.php, which includes this file). The temp
    // fixtures still receive the real call via string interpolation.
    private const SKIP_FN = 'markTestSkipped';

    public function testUnallowlistedMarkTestSkippedExitsOne(): void
    {
        $fn = self::SKIP_FN;
        $this->writeTest('SomeTest.php', "<?php\n\$this->{$fn}('disabled for now');\n");

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('mark-test-skipped', $result['output']);
        self::assertStringContainsString('SomeTest.php', $result['output']);
    }

    public function testAllowlistedMarkTestSkippedExitsZero(): void
    {
        $fn = self::SKIP_FN;
        $php = "<?php\n";
        $php .= "// phpunit-hygiene-allow: integration test skips when Mailpit SMTP is unreachable on localhost:1025\n";
        $php .= "\$this->{$fn}('Mailpit is not reachable at localhost:1025');\n";
        $this->writeTest('MailTest.php', $php);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS', $result['output']);
    }

    public function testShortReasonMarkerExitsOne(): void
    {
        $fn = self::SKIP_FN;
        $php = "<?php\n";
        $php .= "// phpunit-hygiene-allow: too short\n";
        $php .= "\$this->{$fn}('skip it');\n";
        $this->writeTest('ShortTest.php', $php);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('mark-test-skipped', $result['output']);
    }

    public function testCleanTestExitsZero(): void
    {
        $this->writeTest('CleanTest.php', "<?php\n\$this->assertTrue(true);\n");

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS', $result['output']);
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
        self::assertStringContainsString('Unknown argument', implode("\n", $output));
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runScript(): array
    {
        $output = [];
        $exit = 0;
        $cmd = 'cd ' . escapeshellarg($this->tmpDir) . ' && '
            . 'bash ' . escapeshellarg($this->scriptPath) . ' 2>&1';
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function writeTest(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/ibl5/tests/' . $filename, $content);
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
