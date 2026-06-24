<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end behavior of bin/pr-triage: one assertion per bucket, plus the
 * fail-closed negative-path rows that are the whole safety story (an --arm run
 * must arm ONLY the ARMABLE bucket, never a HELD / BLOCKED / FEAT / UNCLEARED PR).
 *
 * A single `gh` shim on PATH dispatches every call pr-triage makes (auth / pr
 * list / api protection / api check-runs / pr view state / pr merge) from
 * fixtures whose paths are passed via env. `pr merge` appends the PR# to a
 * merge-attempts log — the sentinel proving exactly which PRs were armed.
 */
#[Group('cli')]
final class PrTriageCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;
    private string $shimDir;
    private string $repoDir;
    private string $crDir;
    private string $mergeLog;
    private string $contextsFixture;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/pr-triage');
        self::assertNotFalse($resolved, 'bin/pr-triage must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/pr-triage-test-' . bin2hex(random_bytes(8));
        $this->shimDir = $this->tmpDir . '/shims';
        $this->repoDir = $this->tmpDir . '/repo';
        $this->crDir = $this->tmpDir . '/checkruns';
        $this->mergeLog = $this->tmpDir . '/merge-attempts.log';
        mkdir($this->shimDir, 0755, true);
        mkdir($this->repoDir, 0755, true);
        mkdir($this->crDir, 0755, true);

        exec('git init -b master ' . escapeshellarg($this->repoDir) . ' 2>&1');
        exec('git -C ' . escapeshellarg($this->repoDir) . ' remote add origin https://github.com/test/repo.git 2>&1');

        // Required-contexts fixture (the protection API response).
        $this->contextsFixture = $this->tmpDir . '/contexts.json';
        file_put_contents(
            $this->contextsFixture,
            json_encode(['required_status_checks' => ['contexts' => ['Tests and Analysis', 'E2E Tests', 'human-signoff']]])
        );

        $this->writeGhShim(0);

        // Standard check-run fixtures shared across tests.
        $this->writeCheckRuns('shaGREEN', [
            $this->checkRun('Tests and Analysis', 'completed', 'success'),
            $this->checkRun('E2E Tests', 'completed', 'success'),
            $this->checkRun('human-signoff', 'completed', 'success'),
        ]);
        $this->writeCheckRuns('shaFAIL', [
            $this->checkRun('Tests and Analysis', 'completed', 'success'),
            $this->checkRun('E2E Tests', 'completed', 'failure'),
            $this->checkRun('human-signoff', 'completed', 'success'),
        ]);
        $this->writeCheckRuns('shaCANCEL', [
            $this->checkRun('Tests and Analysis', 'completed', 'cancelled', 'https://github.com/test/repo/actions/runs/55555/job/9'),
            $this->checkRun('E2E Tests', 'completed', 'success'),
            $this->checkRun('human-signoff', 'completed', 'success'),
        ]);
        // shaABSENT: E2E Tests check-run is missing entirely (missing != green).
        $this->writeCheckRuns('shaABSENT', [
            $this->checkRun('Tests and Analysis', 'completed', 'success'),
            $this->checkRun('human-signoff', 'completed', 'success'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    private const CLEARED = "## Manual Testing\nNo manual testing needed — all changes are covered by automated tests.";

    /**
     * The comprehensive fixture exercising every bucket exactly once, at the
     * head of each test that needs the full matrix.
     *
     * @return list<array<string,mixed>>
     */
    private function allBucketsFixture(): array
    {
        return [
            $this->pr(1,    'fix: dirty',          self::CLEARED,                              'DIRTY', 'shaGREEN'),
            $this->pr(2,    'fix: failing check',  self::CLEARED,                              'CLEAN', 'shaFAIL'),
            $this->pr(1163, 'fix(a11y): cancel',   self::CLEARED,                              'CLEAN', 'shaCANCEL'),
            $this->pr(4,    'fix: absent check',   self::CLEARED,                              'CLEAN', 'shaABSENT'),
            $this->pr(1188, 'fix(a11y): new h1',   "## Manual Testing\n\n- Review the new <h1> renders", 'CLEAN', 'shaGREEN'),
            $this->pr(5,    'fix: golden',         self::CLEARED,                              'CLEAN', 'shaGREEN', [['path' => 'engine/internal/sim/testdata/golden.json']]),
            $this->pr(6,    'fix: dnm frontmatter', self::CLEARED . "\n\nauto_merge: false",    'CLEAN', 'shaGREEN'),
            $this->pr(7,    'fix: prose hold',     self::CLEARED . "\n\nNeeds a human reviewer first.", 'CLEAN', 'shaGREEN'),
            $this->pr(8,    'fix: has dep',        self::CLEARED . "\n\nDepends-on: #1100",     'CLEAN', 'shaGREEN'),
            $this->pr(9,    'feat: shiny',         self::CLEARED,                              'CLEAN', 'shaGREEN'),
            $this->pr(10,   'fix: handmade',       "Just a body, no manual testing heading.",  'CLEAN', 'shaGREEN'),
            $this->pr(11,   'fix: clean armable',  self::CLEARED,                              'CLEAN', 'shaGREEN'),
            $this->pr(12,   'feat: approved',      self::CLEARED,                              'CLEAN', 'shaGREEN', [], [['name' => 'human-approved']]),
        ];
    }

    public function testEachBucketClassifiedCorrectly(): void
    {
        $this->writePrs($this->allBucketsFixture());
        $r = $this->runScript([]);
        self::assertSame(0, $r['exit'], $r['output']);

        $this->assertBucket($r['output'], 1, 'DIRTY');
        $this->assertBucket($r['output'], 2, 'BLOCKED-CHECK');
        $this->assertBucket($r['output'], 1163, 'BLOCKED-CHECK');   // stale-CANCELLED
        $this->assertBucket($r['output'], 4, 'BLOCKED-CHECK');      // absent required check
        $this->assertBucket($r['output'], 1188, 'HELD');           // real Manual-Testing row
        $this->assertBucket($r['output'], 5, 'HELD');              // golden touched
        $this->assertBucket($r['output'], 6, 'HELD');              // auto_merge: false
        $this->assertBucket($r['output'], 7, 'HELD');              // prose human reviewer
        $this->assertBucket($r['output'], 8, 'BLOCKED-DEP');
        $this->assertBucket($r['output'], 9, 'FEAT-AWAITING-SIGNOFF');
        $this->assertBucket($r['output'], 10, 'UNCLEARED');        // fail-closed: no section
        $this->assertBucket($r['output'], 11, 'ARMABLE');
        $this->assertBucket($r['output'], 12, 'ARMABLE');          // feat + human-approved label
    }

    public function testArmArmsOnlyArmableBucket(): void
    {
        $this->writePrs($this->allBucketsFixture());
        $r = $this->runScript(['--arm']);
        self::assertSame(0, $r['exit'], $r['output']);

        $armed = $this->mergedPrs();
        // ONLY the two ARMABLE PRs are armed.
        self::assertEqualsCanonicalizing([11, 12], $armed, 'merge log: ' . implode(',', $armed));

        // The fail-closed guarantee: none of the deliberate-hold buckets armed.
        foreach ([1, 2, 1163, 4, 1188, 5, 6, 7, 8, 9, 10] as $heldPr) {
            self::assertNotContains($heldPr, $armed, "PR #$heldPr must NOT be armed");
        }
    }

    public function testUnclearedHandMadePrIsNeverArmed(): void
    {
        // The advisor's core correction: a green, non-feat, no-hold PR with NO
        // Manual-Testing section must NOT auto-arm (it is UNCLEARED, not ARMABLE).
        $this->writePrs([
            $this->pr(10, 'fix: handmade green', 'A body with no manual testing heading at all.', 'CLEAN', 'shaGREEN'),
        ]);
        $r = $this->runScript(['--arm']);
        self::assertSame(0, $r['exit'], $r['output']);
        $this->assertBucket($r['output'], 10, 'UNCLEARED');
        self::assertSame([], $this->mergedPrs(), 'an UNCLEARED PR must never be armed');
    }

    public function testArmStaleCancelledEmitsRerunHintAndDoesNotArm(): void
    {
        $this->writePrs([
            $this->pr(1163, 'fix(a11y): cancel', self::CLEARED, 'CLEAN', 'shaCANCEL'),
        ]);
        $r = $this->runScript(['--arm']);
        self::assertSame(0, $r['exit'], $r['output']);
        self::assertSame([], $this->mergedPrs(), 'a CANCELLED-check PR must not arm');
        self::assertStringContainsString('gh run rerun 55555', $r['output'], 'rerun id parsed from details_url');
    }

    public function testArmDoesArmArmablePr(): void
    {
        $this->writePrs([
            $this->pr(11, 'fix: clean armable', self::CLEARED, 'CLEAN', 'shaGREEN'),
        ]);
        $r = $this->runScript(['--arm']);
        self::assertSame(0, $r['exit'], $r['output']);
        self::assertSame([11], $this->mergedPrs());
    }

    public function testFeatWithHumanApprovedLabelIsArmable(): void
    {
        $this->writePrs([
            $this->pr(12, 'feat: approved', self::CLEARED, 'CLEAN', 'shaGREEN', [], [['name' => 'human-approved']]),
        ]);
        $r = $this->runScript(['--arm']);
        $this->assertBucket($r['output'], 12, 'ARMABLE');
        self::assertSame([12], $this->mergedPrs());
    }

    public function testPrecedenceDirtyBeatsFeat(): void
    {
        // A PR that is both DIRTY and feat: -> DIRTY wins (first match).
        $this->writePrs([
            $this->pr(20, 'feat: conflicted', self::CLEARED, 'DIRTY', 'shaGREEN'),
        ]);
        $r = $this->runScript([]);
        $this->assertBucket($r['output'], 20, 'DIRTY');
    }

    public function testNoFlagRunIsReadOnly(): void
    {
        $this->writePrs([
            $this->pr(11, 'fix: clean armable', self::CLEARED, 'CLEAN', 'shaGREEN'),
        ]);
        $r = $this->runScript([]);
        $this->assertBucket($r['output'], 11, 'ARMABLE');
        self::assertSame([], $this->mergedPrs(), 'a no-flag report must never arm');
    }

    public function testArmFailureExitsOne(): void
    {
        // gh pr merge shim that fails -> exit 1 + a failure line.
        $this->writeGhShim(0, /* mergeFails */ true);
        $this->writePrs([
            $this->pr(11, 'fix: clean armable', self::CLEARED, 'CLEAN', 'shaGREEN'),
        ]);
        $r = $this->runScript(['--arm']);
        self::assertSame(1, $r['exit'], $r['output']);
        self::assertStringContainsString('ARM FAILED', $r['output']);
    }

    public function testBogusFlagExitsTwo(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --bogus 2>&1', $output, $exit);
        self::assertSame(2, $exit);
        self::assertStringContainsString('unknown flag', implode("\n", $output));
    }

    public function testHelpExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);
        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }

    public function testUnauthenticatedExitsTwo(): void
    {
        $this->writeGhShim(1);
        $this->writePrs([]);
        $r = $this->runScript([]);
        self::assertSame(2, $r['exit']);
        self::assertStringContainsString('gh auth login', $r['output']);
    }

    // ------------------------------------------------------------------ helpers

    private function assertBucket(string $output, int $pr, string $bucket): void
    {
        $found = false;
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^#' . $pr . '\s/', $line) === 1) {
                self::assertStringContainsString($bucket, $line, "PR #$pr expected bucket $bucket; got: $line");
                $found = true;
                break;
            }
        }
        self::assertTrue($found, "no report row for PR #$pr in:\n$output");
    }

    /**
     * @return list<int>
     */
    private function mergedPrs(): array
    {
        if (!is_file($this->mergeLog)) {
            return [];
        }
        $raw = trim((string) file_get_contents($this->mergeLog));
        if ($raw === '') {
            return [];
        }
        return array_map('intval', explode("\n", $raw));
    }

    /**
     * @param list<array<string,mixed>> $files
     * @param list<array<string,string>> $labels
     * @return array<string,mixed>
     */
    private function pr(int $number, string $title, string $body, string $mss, string $sha, array $files = [], array $labels = []): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
            'mergeStateStatus' => $mss,
            'headRefOid' => $sha,
            'files' => $files,
        ];
    }

    /**
     * @param list<array<string,mixed>> $prs
     */
    private function writePrs(array $prs): void
    {
        file_put_contents($this->tmpDir . '/prs.json', (string) json_encode($prs));
    }

    /**
     * @param list<array<string,mixed>> $runs
     */
    private function writeCheckRuns(string $sha, array $runs): void
    {
        file_put_contents($this->crDir . '/' . $sha . '.json', (string) json_encode(['check_runs' => $runs]));
    }

    /**
     * @return array<string,mixed>
     */
    private function checkRun(string $name, string $status, ?string $conclusion, ?string $detailsUrl = null): array
    {
        $run = ['name' => $name, 'status' => $status, 'completed_at' => '2026-06-24T00:00:00Z'];
        if ($conclusion !== null) {
            $run['conclusion'] = $conclusion;
        }
        if ($detailsUrl !== null) {
            $run['details_url'] = $detailsUrl;
        }
        return $run;
    }

    /**
     * @param list<string> $args
     * @return array{output: string, exit: int}
     */
    private function runScript(array $args): array
    {
        $env = 'PATH=' . escapeshellarg($this->shimDir . ':' . getenv('PATH'))
            . ' PRS=' . escapeshellarg($this->tmpDir . '/prs.json')
            . ' CRDIR=' . escapeshellarg($this->crDir)
            . ' CONTEXTS=' . escapeshellarg($this->contextsFixture)
            . ' MERGELOG=' . escapeshellarg($this->mergeLog);

        $argStr = '';
        foreach ($args as $arg) {
            $argStr .= ' ' . escapeshellarg($arg);
        }
        $cmd = 'cd ' . escapeshellarg($this->repoDir)
            . ' && ' . $env
            . ' bash ' . escapeshellarg($this->scriptPath) . $argStr . ' 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function writeGhShim(int $authExit, bool $mergeFails = false): void
    {
        $mergeExit = $mergeFails ? 1 : 0;
        $script = <<<SHIM
#!/bin/bash
# Multi-call gh shim for pr-triage. Fixtures via env: PRS, CRDIR, CONTEXTS, MERGELOG.
if [ "\$1" = "auth" ] && [ "\$2" = "status" ]; then exit $authExit; fi
if [ "\$1" = "api" ]; then
  url="\$2"
  case "\$url" in
    *"/branches/master/protection") cat "\$CONTEXTS"; exit 0 ;;
    *"/commits/"*"/check-runs"*)
      sha=\$(echo "\$url" | sed -E 's#.*/commits/([^/]+)/check-runs.*#\\1#')
      cat "\$CRDIR/\$sha.json" 2>/dev/null || echo '{"check_runs":[]}'
      exit 0 ;;
  esac
  echo '{}'; exit 0
fi
if [ "\$1" = "pr" ] && [ "\$2" = "list" ]; then cat "\$PRS"; exit 0; fi
if [ "\$1" = "pr" ] && [ "\$2" = "view" ]; then
  case "\$3" in
    1100) echo '{"state":"OPEN"}' ;;
    1071) echo '{"state":"MERGED"}' ;;
    *) echo '{"state":"OPEN"}' ;;
  esac
  exit 0
fi
if [ "\$1" = "pr" ] && [ "\$2" = "merge" ]; then echo "\$3" >> "\$MERGELOG"; exit $mergeExit; fi
exit 1
SHIM;
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
