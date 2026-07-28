<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckDocsSourceCommentRefsCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-docs');
        self::assertNotFalse($resolved, 'bin/check-docs must exist');

        $this->tmpDir = sys_get_temp_dir() . '/check-docs-src-refs-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir . '/tools', 0755, true);
        mkdir($this->tmpDir . '/.git', 0755, true);
        mkdir($this->tmpDir . '/bin/lib', 0755, true);
        mkdir($this->tmpDir . '/ibl5/classes/Foo', 0755, true);
        mkdir($this->tmpDir . '/ibl5/tests/Cli', 0755, true);

        $this->scriptPath = $this->tmpDir . '/tools/check-docs';
        copy($resolved, $this->scriptPath);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    // -----------------------------------------------------------------------
    // Positive controls (requirement 2)
    // -----------------------------------------------------------------------

    public function testDeadRefInPhpLineCommentFails(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// See ibl5/gone/Missing.php for details.\nclass Bar {}\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('ibl5/gone/Missing.php', $r['output']);
        self::assertStringContainsString('dead references in source comments', $r['output']);
    }

    public function testDeadRefInShellCommentFails(): void
    {
        file_put_contents(
            $this->tmpDir . '/bin/some-script',
            "#!/usr/bin/env bash\n# Mirrors bin/gone-script.\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('bin/gone-script', $r['output']);
    }

    public function testDeadRefInDocblockFails(): void
    {
        mkdir($this->tmpDir . '/ibl5/phpstan-rules', 0755, true);
        file_put_contents(
            $this->tmpDir . '/ibl5/phpstan-rules/FooRule.php',
            "<?php\n/**\n * FooRule.\n * Documented in ibl5/docs/GONE.md.\n */\nclass FooRule {}\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('ibl5/docs/GONE.md', $r['output']);
    }

    // -----------------------------------------------------------------------
    // Suppression classes (requirement 3)
    // -----------------------------------------------------------------------

    public function testTrailingPunctuationSuppressed(): void
    {
        file_put_contents($this->tmpDir . '/bin/real-target', '#!/usr/bin/env bash');
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// Mirrors bin/real-target.\nclass Bar {}\n"
        );
        $this->assertSuppressed('bin/real-target.');
    }

    public function testDirectoryTokenSuppressed(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// Backlogs (ibl5/docs/backlog/) mark items.\nclass Bar {}\n"
        );
        $this->assertSuppressed('ibl5/docs/backlog/');
    }

    public function testAdrPlaceholderSuppressed(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// See ibl5/docs/decisions/00NN-slug.md and ibl5/docs/decisions/0099-x.md\nclass Bar {}\n"
        );
        $this->assertSuppressed('ibl5/docs/decisions/00NN-slug.md');
        $this->assertSuppressed('ibl5/docs/decisions/0099-x.md');
    }

    public function testRuntimeArtifactSuppressed(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// --gallery=ibl5/vr-gallery.json\nclass Bar {}\n"
        );
        $this->assertSuppressed('ibl5/vr-gallery.json');
    }

    public function testIbl5BinAmbiguitySuppressed(): void
    {
        mkdir($this->tmpDir . '/ibl5/bin', 0755, true);
        file_put_contents($this->tmpDir . '/ibl5/bin/check-thing', '#!/usr/bin/env bash');
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// See bin/check-thing for the runner.\nclass Bar {}\n"
        );
        $this->assertSuppressed('bin/check-thing');
    }

    // -----------------------------------------------------------------------
    // Boundary cases
    // -----------------------------------------------------------------------

    public function testBracketedDeadRefStillFlags(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// [--out=ibl5/gone-artifact.json]\nclass Bar {}\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('ibl5/gone-artifact.json', $r['output']);
    }

    public function testGlobTokenSuppressed(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// bin/test-bug-pipeline-* harnesses\nclass Bar {}\n"
        );
        $this->assertSuppressed('bin/test-bug-pipeline-*');
    }

    public function testExampleMarkerSuppressed(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/classes/Foo/Bar.php',
            "<?php\n// See bin/foo (example) for the shape.\nclass Bar {}\n"
        );
        $this->assertSuppressed('bin/foo');
    }

    // -----------------------------------------------------------------------
    // Exclusions (requirement 4)
    // -----------------------------------------------------------------------

    public function testDeadRefInBinTestHarnessIgnored(): void
    {
        file_put_contents(
            $this->tmpDir . '/bin/test-check-plan-fixture',
            "#!/usr/bin/env bash\n# See bin/gone-fixture for details.\n"
        );
        $this->assertSuppressed('bin/gone-fixture');
    }

    public function testDeadRefInIbl5TestsIgnored(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/tests/Cli/SomeTest.php',
            "<?php\n// See bin/gone-under-test for context.\nclass SomeTest {}\n"
        );
        $this->assertSuppressed('bin/gone-under-test');
    }

    public function testNonSourceFilesNotScanned(): void
    {
        file_put_contents(
            $this->tmpDir . '/bin/README.md',
            "# README\nSee bin/gone-from-readme for details.\n"
        );
        file_put_contents(
            $this->tmpDir . '/bin/notes.txt',
            "See bin/gone-from-notes for details.\n"
        );
        $this->assertSuppressed('bin/gone-from-readme');
    }

    // -----------------------------------------------------------------------
    // Markdown regression (requirement 5)
    // -----------------------------------------------------------------------

    public function testMarkdownFixtureBehaviorUnchanged(): void
    {
        mkdir($this->tmpDir . '/ibl5/docs', 0755, true);

        // Dead backtick ref → exit 1 with markdown wording (not source-comment wording)
        file_put_contents(
            $this->tmpDir . '/ibl5/docs/x.md',
            "---\ndescription: test doc\nlast_verified: 2026-07-01\n---\n\nSee `ibl5/nope.md` for details.\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('dead reference: `ibl5/nope.md`', $r['output']);

        // Resolving backtick path → exit 0 with '1 docs verified.'
        file_put_contents(
            $this->tmpDir . '/ibl5/docs/x.md',
            "---\ndescription: test doc\nlast_verified: 2026-07-01\n---\n\nSee `ibl5/classes/Foo` for details.\n"
        );
        $r = $this->runScript();
        self::assertSame(0, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('1 docs verified.', $r['output']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Anti-vacuous-pass helper: plants a known-dead canary so the scanner must
     * fire at all, then asserts the suppressed token is absent.
     */
    private function assertSuppressed(string $suppressedToken): void
    {
        file_put_contents(
            $this->tmpDir . '/bin/canary',
            "#!/usr/bin/env bash\n# See bin/zzz-canary-missing for details.\n"
        );
        $r = $this->runScript();
        self::assertSame(1, $r['exit'], "Output: {$r['output']}");
        self::assertStringContainsString('bin/zzz-canary-missing', $r['output']);
        self::assertStringNotContainsString($suppressedToken, $r['output']);
        unlink($this->tmpDir . '/bin/canary');
    }

    /** @return array{output: string, exit: int} */
    private function runScript(array $args = ['--no-staleness']): array
    {
        $cmd = 'php ' . escapeshellarg($this->scriptPath);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }
        $output = [];
        $exit = 0;
        exec($cmd . ' 2>&1', $output, $exit);
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
