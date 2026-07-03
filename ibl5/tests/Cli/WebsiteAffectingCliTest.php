<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('cli')]
final class WebsiteAffectingCliTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/website-affecting');
        self::assertNotFalse($resolved, 'bin/website-affecting must exist');
        $this->scriptPath = $resolved;
    }

    // =========================================================================
    // SKIP cases (exit 1 — non-website)
    // =========================================================================

    public function testDocsOnlyDiffExitsOne(): void
    {
        // Row 1: docs-only diff → SKIP
        $result = $this->runPredicate("README.md\ndocs/x.md\nibl5/docs/y.md\n");
        self::assertSame(1, $result['exit'], $result['stderr']);
    }

    public function testClaudeOnlyDiffExitsOne(): void
    {
        // Row 2: .claude/ only → SKIP
        $result = $this->runPredicate(".claude/rules/foo.md\n.claude/commands/bar.md\n");
        self::assertSame(1, $result['exit'], $result['stderr']);
    }

    public function testHostBinOnlyDiffExitsOne(): void
    {
        // Row 3: host bin/ only → SKIP
        $result = $this->runPredicate("bin/wt-new\nbin/lib/db-helpers.sh\n");
        self::assertSame(1, $result['exit'], $result['stderr']);
    }

    public function testHostBinWithWebishExtensionExitsOne(): void
    {
        // Row 4: host bin/ with website-ish extension — the bug being fixed → SKIP
        $result = $this->runPredicate("bin/foo.js\nbin/tool.ts\n");
        self::assertSame(1, $result['exit'], $result['stderr']);
    }

    public function testEngineOnlyDiffExitsOne(): void
    {
        // Row 4b: Go sim engine source only → SKIP. E2E runs the PREBAKED jsbsim
        // binary + seed fixtures, never the PR's engine/ source, so an engine-only
        // change can't affect an E2E/VR outcome; engine.yml covers it. Note
        // ibl5/bin/jsbsim (the baked binary, ^ibl5/) is NOT matched by ^engine/.
        $result = $this->runPredicate("engine/cmd/jsbsim/main.go\nengine/internal/backup/assemble.go\n");
        self::assertSame(1, $result['exit'], $result['stderr']);
    }

    // =========================================================================
    // RUN cases (exit 0 — website-affecting)
    // =========================================================================

    public function testSinglePhpChangeExitsZero(): void
    {
        // Row 5: single PHP change → RUN
        $result = $this->runPredicate("ibl5/modules/Schedule/index.php\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testTsJsCssUnderWebsiteTreeExitsZero(): void
    {
        // Row 6: .ts/.js/.css under website tree → RUN
        $result = $this->runPredicate("ibl5/jslib/foo.ts\nibl5/themes/IBL/style/x.css\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testIbl5BinChangeExitsZero(): void
    {
        // Row 7: ibl5/bin/ app-bin (^bin/ must not match ibl5/bin/) → RUN
        $result = $this->runPredicate("ibl5/bin/validate-schema\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testE2eTestDirChangeExitsZero(): void
    {
        // Row 8: ibl5/tests/e2e/ → RUN (E2E test dir stays website-side)
        $result = $this->runPredicate("ibl5/tests/e2e/foo.spec.ts\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testMixedDiffExitsZero(): void
    {
        // Row 9: mixed (docs + one PHP file) → RUN (any website file forces build)
        $result = $this->runPredicate("docs/foo.md\nibl5/modules/Schedule/index.php\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testEmptyStdinExitsZero(): void
    {
        // Row 10: empty stdin → RUN (fail-safe default)
        $result = $this->runPredicate('');
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testGarbageInputExitsZero(): void
    {
        // Row 11: garbage / unexpected input → RUN (fail-safe on unexpected input)
        $result = $this->runPredicate("   leading whitespace\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testPredicateSelfChangeExitsZero(): void
    {
        // Row 12: bin/website-affecting itself → RUN (carve-out from ^bin/)
        $result = $this->runPredicate("bin/website-affecting\n");
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testWorkflowSelfChangeExitsZero(): void
    {
        // Row 13: workflow files → RUN (naturally website-side, not denied)
        $result = $this->runPredicate(
            ".github/workflows/e2e-tests.yml\n.github/workflows/lighthouse.yml\n"
        );
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testCiAdjacentPathsExitZero(): void
    {
        // Row 14: CI-adjacent paths → RUN (preserve existing e2e triggers)
        $result = $this->runPredicate(
            ".github/scripts/foo\n.github/actions/setup-docker-e2e/action.yml\n"
        );
        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    // =========================================================================
    // --help
    // =========================================================================

    public function testHelpExitsZeroAndPrintsDenySet(): void
    {
        // Row 15: --help prints deny-set/contract and exits 0
        $result = $this->runRaw('--help');
        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringContainsString('\.md$', $result['output']);
        self::assertStringContainsString('^bin/', $result['output']);
        self::assertStringContainsString('^docs/', $result['output']);
        self::assertStringContainsString('^\.claude/', $result['output']);
        self::assertStringContainsString('^engine/', $result['output']);
        self::assertStringContainsString('bin/website-affecting', $result['output']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Pipe $stdin to the predicate script and return exit code + stderr verdict.
     * @return array{exit: int, stderr: string}
     */
    private function runPredicate(string $stdin): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open(escapeshellcmd($this->scriptPath), $descriptors, $pipes);
        self::assertIsResource($proc, 'Failed to start website-affecting');

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['exit' => $exit, 'stderr' => (string) $stderr];
    }

    /**
     * Run the predicate with raw arguments (for --help).
     * @return array{exit: int, output: string}
     */
    private function runRaw(string $args): array
    {
        $cmd = escapeshellcmd($this->scriptPath) . ' ' . $args . ' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }
}
