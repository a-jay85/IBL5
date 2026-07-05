<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Characterization of the shared live-hold predicate bin/lib/pr-armable.sh.
 *
 * These rows pin the EXACT decision each function makes — the same logic that
 * /post-plan Phase 6.5 conditions (1)/(5)/(6)/(8)/(10) now call, so a refactor of
 * either consumer that diverges from these decisions fails here.
 */
#[Group('cli')]
final class PrArmableLibCliTest extends TestCase
{
    private string $libPath;
    private string $tmpDir;
    private string $shimDir;
    private string $harness;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/lib/pr-armable.sh');
        self::assertNotFalse($resolved, 'bin/lib/pr-armable.sh must exist');
        $this->libPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/pr-armable-test-' . bin2hex(random_bytes(8));
        $this->shimDir = $this->tmpDir . '/shims';
        mkdir($this->shimDir, 0755, true);

        // Dispatcher harness: source the lib, then invoke the named function.
        // Shell functions are callable via "$@" exactly like an executable.
        $this->harness = $this->tmpDir . '/harness.sh';
        file_put_contents(
            $this->harness,
            "#!/bin/bash\nset -uo pipefail\nsource " . escapeshellarg($this->libPath) . "\n\"\$@\"\n"
        );
        chmod($this->harness, 0755);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    // --- (1) Manual-Testing clearance: the fail-closed positive-clearance axis ---

    public function testClearanceSentinelUnitE2eSuffixIsCleared(): void
    {
        $body = "# PR\n\n## Manual Testing\n\nNo manual testing needed — all changes are covered by unit and E2E tests.\n";
        self::assertSame('CLEARED', $this->runFn('pr_manual_testing_clearance', [$body])['output']);
    }

    public function testClearanceSentinelAutomatedSuffixIsCleared(): void
    {
        // The OTHER sentinel suffix the source writes (Phase 6 vs Phase 1).
        $body = "## Manual Testing\nNo manual testing needed — all changes are covered by automated tests.";
        self::assertSame('CLEARED', $this->runFn('pr_manual_testing_clearance', [$body])['output']);
    }

    public function testClearanceRealRowsIsHeld(): void
    {
        $body = "## Manual Testing\n\n- Review that the new <h1> renders correctly\n\n## Notes\nx";
        self::assertSame('HELD', $this->runFn('pr_manual_testing_clearance', [$body])['output']);
    }

    public function testClearanceNoSectionIsUnknownFailClosed(): void
    {
        // No `## Manual Testing` section at all -> UNKNOWN -> never auto-armable.
        $body = "# Some hand-made PR\n\nJust a description, no manual testing heading.";
        self::assertSame('UNKNOWN', $this->runFn('pr_manual_testing_clearance', [$body])['output']);
    }

    // --- (5) golden-snapshot touch ---

    public function testGoldenHoldFiresWhenGoldenTouched(): void
    {
        $files = '[{"path":"ibl5/x.php"},{"path":"engine/internal/sim/testdata/golden.json"}]';
        self::assertSame('golden-changed', $this->runFn('pr_golden_hold', [$files])['output']);
    }

    public function testGoldenHoldEmptyWhenGoldenUntouched(): void
    {
        $files = '[{"path":"ibl5/x.php"}]';
        self::assertSame('', $this->runFn('pr_golden_hold', [$files])['output']);
    }

    // --- (8) feat: floor — label-aware ---

    public function testFeatHoldFiresOnFeatTitleWithoutLabel(): void
    {
        self::assertSame(
            'feat-awaiting-signoff',
            $this->runFn('pr_feat_hold', ['feat: shiny new page', '[]'])['output']
        );
    }

    public function testFeatHoldClearedByHumanApprovedLabel(): void
    {
        self::assertSame(
            '',
            $this->runFn('pr_feat_hold', ['feat(x): y', '[{"name":"human-approved"}]'])['output']
        );
    }

    public function testFeatHoldEmptyForNonFeatTitle(): void
    {
        self::assertSame('', $this->runFn('pr_feat_hold', ['fix(a11y): z', '[]'])['output']);
    }

    public function testFeatHoldFiresOnBangVariant(): void
    {
        self::assertSame(
            'feat-awaiting-signoff',
            $this->runFn('pr_feat_hold', ['feat!: breaking', '[]'])['output']
        );
    }

    // --- (10) pipeline-authored floor — UNCONDITIONAL (no override label) ---

    public function testPipelineAuthoredHoldFiresWhenLabelPresent(): void
    {
        self::assertSame(
            'pipeline-authored',
            $this->runFn('pr_pipeline_authored_hold', ['[{"name":"pipeline-authored"}]'])['output']
        );
    }

    public function testPipelineAuthoredHoldEmptyWhenLabelAbsent(): void
    {
        self::assertSame(
            '',
            $this->runFn('pr_pipeline_authored_hold', ['[{"name":"chore"}]'])['output']
        );
    }

    // --- (6) Depends-on merge-order (needs a gh shim for predecessor state) ---

    public function testDepHoldsFiresOnUnmergedPredecessor(): void
    {
        $this->writeGhShim(['1100' => 'OPEN']);
        $body = "body line\nDepends-on: #1100\nmore";
        $result = $this->runFn('pr_dep_holds', [$body], ['GH_CMD' => $this->shimDir . '/gh']);
        self::assertSame('depends-on:#1100', $result['output']);
    }

    public function testDepHoldsEmptyWhenPredecessorMerged(): void
    {
        $this->writeGhShim(['1071' => 'MERGED']);
        $result = $this->runFn('pr_dep_holds', ["Depends-on: #1071"], ['GH_CMD' => $this->shimDir . '/gh']);
        self::assertSame('', $result['output']);
    }

    public function testDepHoldsIgnoresInlineProseMention(): void
    {
        // An inline (not start-of-line) mention of the marker must NOT be parsed.
        $this->writeGhShim(['1100' => 'OPEN']);
        $body = "see the `Depends-on: #1100` gate documented inline";
        $result = $this->runFn('pr_dep_holds', [$body], ['GH_CMD' => $this->shimDir . '/gh']);
        self::assertSame('', $result['output']);
    }

    /**
     * @param list<string> $args
     * @param array<string,string> $env
     * @return array{output: string, exit: int}
     */
    private function runFn(string $fn, array $args, array $env = []): array
    {
        $envPrefix = '';
        foreach ($env as $k => $v) {
            $envPrefix .= $k . '=' . escapeshellarg($v) . ' ';
        }
        $cmd = $envPrefix . 'bash ' . escapeshellarg($this->harness) . ' ' . escapeshellarg($fn);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $cmd .= ' 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        return ['output' => trim(implode("\n", $output)), 'exit' => $exit];
    }

    /**
     * @param array<int,string> $states  map of PR number => state
     *        (PHP coerces the numeric-string keys to int)
     */
    private function writeGhShim(array $states): void
    {
        $cases = '';
        foreach ($states as $n => $state) {
            $cases .= "    $n) echo '{\"state\":\"$state\"}' ;;\n";
        }
        $script = "#!/bin/bash\n"
            . "# Shim: `gh pr view <n> --json state` -> {\"state\":...}\n"
            . "if [ \"\$1\" = \"pr\" ] && [ \"\$2\" = \"view\" ]; then\n"
            . "  case \"\$3\" in\n"
            . $cases
            . "    *) echo '{\"state\":\"OPEN\"}' ;;\n"
            . "  esac\n"
            . "  exit 0\n"
            . "fi\n"
            . "exit 1\n";
        file_put_contents($this->shimDir . '/gh', $script);
        chmod($this->shimDir . '/gh', 0755);
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
