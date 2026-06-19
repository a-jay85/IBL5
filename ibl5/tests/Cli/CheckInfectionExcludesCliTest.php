<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Drives bin/check-infection-excludes against fixture infection.json5 files in a
 * temp dir. The script reads infection.json5 from cwd and greps cwd-relative
 * classes/ and tests/, so each fixture provides all three; the script is run via
 * `cd <tmpDir> && bash <scriptPath>` (host realpath to repo-root bin/).
 */
#[Group('cli')]
final class CheckInfectionExcludesCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        // check-infection-excludes lives at ibl5/bin/ (app-pinned, run from the ibl5/
        // working dir in CI) — two levels up from tests/Cli/, NOT repo-root bin/.
        $resolved = realpath(__DIR__ . '/../../bin/check-infection-excludes');
        self::assertNotFalse($resolved, 'bin/check-infection-excludes must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/infection-excludes-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);
        mkdir($this->tmpDir . '/classes', 0755, true);
        mkdir($this->tmpDir . '/tests', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testReconciledConfigExitsZero(): void
    {
        $this->writeClass('Foo.php');
        $this->writeConfig(['"Foo.php" // low-level glue, never mutated here']);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    public function testMissingReasonExitsNonZero(): void
    {
        $this->writeClass('Foo.php');
        $this->writeConfig(['"Foo.php"']);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('reason-missing', $result['output']);
        self::assertStringContainsString('Foo.php', $result['output']);
    }

    public function testShortReasonExitsNonZero(): void
    {
        $this->writeClass('Foo.php');
        // 7 non-whitespace chars (< 12) — too short.
        $this->writeConfig(['"Foo.php" // tooshrt']);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('reason-missing', $result['output']);
    }

    public function testExcludedFileWithReferencingTestExitsNonZero(): void
    {
        $this->writeClass('Foo.php');
        $this->writeTest('FooTest.php', "<?php\n// exercises Foo\n");
        // Reason is honest deferral (no infra-exclude marker) → gate 2 applies.
        $this->writeConfig(['"Foo.php" // no output-assertion test yet — deferred']);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('stale exclude', $result['output']);
    }

    public function testInfraExcludeMarkerSkipsStaleGate(): void
    {
        $this->writeClass('Foo.php');
        $this->writeTest('FooTest.php', "<?php\n// exercises Foo\n");
        // infra-exclude marker → gate 2 skipped even though a test references Foo.
        $this->writeConfig(['"Foo.php" // infra-exclude: low-level glue never mutated']);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    public function testStalePathStillFires(): void
    {
        // Note: Foo.php intentionally NOT created under classes/.
        $this->writeConfig(['"Foo.php" // infra-exclude: low-level glue never mutated']);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('not found', $result['output']);
        self::assertStringContainsString('Foo.php', $result['output']);
    }

    public function testDirLevelExcludeNeedsNoReason(): void
    {
        // Dir-level entry has no .php token → parser-invisible, needs no reason.
        $this->writeConfig(['"OneOnOneGame"']);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    /**
     * @param list<string> $excludeLines raw lines for the excludes array
     */
    private function writeConfig(array $excludeLines): void
    {
        $body = implode(",\n            ", $excludeLines);
        $json5 = "{\n"
            . "    \"source\": {\n"
            . "        \"directories\": [\"classes\"],\n"
            . "        \"excludes\": [\n"
            . "            {$body}\n"
            . "        ]\n"
            . "    }\n"
            . "}\n";
        file_put_contents($this->tmpDir . '/infection.json5', $json5);
    }

    private function writeClass(string $relPath): void
    {
        $full = $this->tmpDir . '/classes/' . $relPath;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($full, "<?php\nclass Stub {}\n");
    }

    private function writeTest(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/tests/' . $filename, $content);
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
